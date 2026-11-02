<?php

namespace App\Services;

use App\Model\PaymentRequest;
use App\Model\Stream;
use App\Model\Subscription;
use App\Model\Transaction;
use App\Model\User;
use App\Model\UserDeletionRequest;
use App\Model\UserReport;
use App\Model\Withdrawal;
use App\Providers\EmailsServiceProvider;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class UserDeletionRequestService
{
    public function enabled(): bool
    {
        return (bool) getSetting('compliance.account_deletion_enabled', false);
    }

    public function mode(): string
    {
        $mode = (string) getSetting('compliance.account_deletion_mode', UserDeletionRequest::MODE_MANUAL_REVIEW_WITH_COOLDOWN);

        return in_array($mode, UserDeletionRequest::MODES, true)
            ? $mode
            : UserDeletionRequest::MODE_MANUAL_REVIEW_WITH_COOLDOWN;
    }

    public function cooldownDays(): int
    {
        return max(0, (int) getSetting('compliance.account_deletion_cooldown_days', 14));
    }

    public function usersMayCancel(): bool
    {
        return (bool) getSetting('compliance.account_deletion_allow_user_cancel', true);
    }

    public function activeRequestForUser(User $user): ?UserDeletionRequest
    {
        return UserDeletionRequest::query()
            ->where('user_id', $user->id)
            ->whereIn('status', UserDeletionRequest::ACTIVE_STATUSES)
            ->latest('id')
            ->first();
    }

    public function createRequest(User $user, ?string $reason = null): array
    {
        if (!$this->enabled()) {
            return [
                'success' => false,
                'message' => __('Account deletion requests are not enabled.'),
            ];
        }

        if ($this->activeRequestForUser($user)) {
            return [
                'success' => false,
                'message' => __('You already have an active account deletion request.'),
            ];
        }

        $blockingReasons = $this->blockingReasons($user);

        if ($blockingReasons !== []) {
            return [
                'success' => false,
                'message' => __('Your account cannot be deleted yet: :reasons', ['reasons' => implode(' ', $blockingReasons)]),
                'blockingReasons' => $blockingReasons,
            ];
        }

        $mode = $this->mode();
        $cooldownDays = $this->cooldownDays();
        $eligibleForDeletionAt = $this->modeUsesCooldown($mode) && $cooldownDays > 0
            ? now()->addDays($cooldownDays)
            : ($mode === UserDeletionRequest::MODE_COOLDOWN_AUTO ? now() : null);

        $deletionRequest = UserDeletionRequest::create([
            'user_id' => $user->id,
            'requested_name' => $user->name,
            'requested_email' => $user->email,
            'requested_username' => $user->username,
            'status' => UserDeletionRequest::STATUS_PENDING,
            'mode' => $mode,
            'reason' => $reason,
            'requested_at' => now(),
            'eligible_for_deletion_at' => $eligibleForDeletionAt,
        ]);

        $this->safelyNotify(function () use ($deletionRequest, $user) {
            $this->sendRequestedEmail($deletionRequest, $user);
            $this->sendAdminNotification($deletionRequest);
        }, 'deletion request notification for request '.$deletionRequest->id);

        return [
            'success' => true,
            'message' => __('Your account deletion request has been submitted.'),
            'request' => $deletionRequest,
        ];
    }

    public function cancel(UserDeletionRequest $deletionRequest, ?User $actor = null, ?string $notes = null): array
    {
        if (!$deletionRequest->isActive()) {
            return [
                'success' => false,
                'message' => __('This deletion request has already been processed.'),
            ];
        }

        $deletionRequest->update([
            'status' => UserDeletionRequest::STATUS_CANCELED,
            'admin_notes' => $notes ?: $deletionRequest->admin_notes,
            'reviewed_by' => $actor?->id,
            'reviewed_at' => now(),
            'canceled_at' => now(),
        ]);

        $user = $deletionRequest->user;

        if ($user) {
            $this->safelyNotify(
                fn () => $this->sendCanceledEmail($deletionRequest, $user),
                'canceled deletion request email for request '.$deletionRequest->id
            );
        }

        return [
            'success' => true,
            'message' => __('The account deletion request has been canceled.'),
        ];
    }

    public function reject(UserDeletionRequest $deletionRequest, User $reviewer, string $reason): array
    {
        if (!$deletionRequest->isActive()) {
            return [
                'success' => false,
                'message' => __('This deletion request has already been processed.'),
            ];
        }

        $deletionRequest->update([
            'status' => UserDeletionRequest::STATUS_REJECTED,
            'rejection_reason' => $reason,
            'reviewed_by' => $reviewer->id,
            'reviewed_at' => now(),
        ]);

        $user = $deletionRequest->user;

        if ($user) {
            $this->safelyNotify(
                fn () => $this->sendRejectedEmail($deletionRequest, $user, $reason),
                'rejected deletion request email for request '.$deletionRequest->id
            );
        }

        return [
            'success' => true,
            'message' => __('The account deletion request has been rejected.'),
        ];
    }

    public function approve(UserDeletionRequest $deletionRequest, User $reviewer, ?string $notes = null): array
    {
        if (!$deletionRequest->isActive()) {
            return [
                'success' => false,
                'message' => __('This deletion request has already been processed.'),
            ];
        }

        $user = $deletionRequest->user;

        if (!$user) {
            return [
                'success' => false,
                'message' => __('The user attached to this request no longer exists.'),
            ];
        }

        $blockingReasons = $this->blockingReasons($user);

        if ($blockingReasons !== []) {
            $this->markBlocked($deletionRequest, $reviewer, $blockingReasons);

            return [
                'success' => false,
                'message' => __('The request is blocked: :reasons', ['reasons' => implode(' ', $blockingReasons)]),
                'blockingReasons' => $blockingReasons,
            ];
        }

        if ($this->hasFutureCooldown($deletionRequest)) {
            $deletionRequest->update([
                'status' => UserDeletionRequest::STATUS_APPROVED,
                'admin_notes' => $notes ?: $deletionRequest->admin_notes,
                'reviewed_by' => $reviewer->id,
                'reviewed_at' => now(),
            ]);

            $this->safelyNotify(
                fn () => $this->sendApprovedEmail($deletionRequest, $user),
                'approved deletion request email for request '.$deletionRequest->id
            );

            return [
                'success' => true,
                'message' => __('The deletion request has been approved and will be processed after the cooldown period.'),
            ];
        }

        return $this->finalizeDeletion($deletionRequest, $reviewer);
    }

    public function processEligibleRequests(int $limit = 50): int
    {
        $requests = UserDeletionRequest::query()
            ->whereNotNull('eligible_for_deletion_at')
            ->where('eligible_for_deletion_at', '<=', now())
            ->where(function ($query) {
                $query->where(function ($manualQuery) {
                    $manualQuery
                        ->where('status', UserDeletionRequest::STATUS_APPROVED)
                        ->where('mode', UserDeletionRequest::MODE_MANUAL_REVIEW_WITH_COOLDOWN);
                })->orWhere(function ($autoQuery) {
                    $autoQuery
                        ->where('status', UserDeletionRequest::STATUS_PENDING)
                        ->where('mode', UserDeletionRequest::MODE_COOLDOWN_AUTO);
                });
            })
            ->orderBy('eligible_for_deletion_at')
            ->limit($limit)
            ->get();

        $processed = 0;

        foreach ($requests as $deletionRequest) {
            $result = $this->finalizeDeletion($deletionRequest);

            if ($result['success']) {
                $processed++;
            }
        }

        return $processed;
    }

    public function blockingReasons(User $user): array
    {
        $reasons = [];

        if (app(UserBanService::class)->hasPendingRecovery($user)) {
            $reasons[] = __('Your subscription billing cancellation still needs confirmation. Please contact support.');
        }

        if ((int) $user->role_id === 1) {
            $reasons[] = __('Admin accounts cannot request deletion from user settings.');
        }

        if ((bool) getSetting('compliance.account_deletion_enforce_wallet_blocker', true)) {
            $walletTotal = $user->wallet ? (float) $user->wallet->total : 0.0;

            if ($walletTotal > 0) {
                $reasons[] = __('Your wallet still has a positive balance.');
            }

            if (Withdrawal::query()->where('user_id', $user->id)->where('status', Withdrawal::REQUESTED_STATUS)->exists()) {
                $reasons[] = __('You still have pending withdrawal requests.');
            }

            if (PaymentRequest::query()->where('user_id', $user->id)->where('status', PaymentRequest::PENDING_STATUS)->exists()) {
                $reasons[] = __('You still have pending deposit requests.');
            }

            if (Transaction::query()
                ->where(function ($query) use ($user) {
                    $query->where('sender_user_id', $user->id)
                        ->orWhere('recipient_user_id', $user->id);
                })
                ->whereIn('status', [Transaction::INITIATED_STATUS, Transaction::PENDING_STATUS, Transaction::PARTIALLY_PAID_STATUS])
                ->exists()) {
                $reasons[] = __('You still have pending payment transactions.');
            }
        }

        if ((bool) getSetting('compliance.account_deletion_enforce_subscription_blocker', true)) {
            if (Subscription::query()
                ->where(function ($query) use ($user) {
                    $query->where('sender_user_id', $user->id)
                        ->orWhere('recipient_user_id', $user->id);
                })
                ->whereIn('status', [Subscription::ACTIVE_STATUS, Subscription::PENDING_STATUS, Subscription::SUSPENDED_STATUS])
                ->exists()) {
                $reasons[] = __('You still have active or pending subscriptions.');
            }
        }

        if (Stream::query()->where('user_id', $user->id)->where('status', Stream::IN_PROGRESS_STATUS)->exists()) {
            $reasons[] = __('You still have an active livestream.');
        }

        if ((bool) getSetting('compliance.account_deletion_enforce_moderation_blocker', true)) {
            if (UserReport::query()
                ->where('user_id', $user->id)
                ->whereIn('status', [UserReport::RECEIVED_STATUS, UserReport::SEEN_STATUS])
                ->exists()) {
                $reasons[] = __('Your account still has unresolved moderation reports.');
            }
        }

        return $reasons;
    }

    public function modeUsesCooldown(string $mode): bool
    {
        return in_array($mode, [
            UserDeletionRequest::MODE_COOLDOWN_AUTO,
            UserDeletionRequest::MODE_MANUAL_REVIEW_WITH_COOLDOWN,
        ], true);
    }

    protected function finalizeDeletion(UserDeletionRequest $deletionRequest, ?User $reviewer = null): array
    {
        $deletionRequest->refresh();
        $user = $deletionRequest->user;

        if (!$user) {
            return [
                'success' => false,
                'message' => __('The user attached to this request no longer exists.'),
            ];
        }

        $blockingReasons = $this->blockingReasons($user);

        if ($blockingReasons !== []) {
            $this->markBlocked($deletionRequest, $reviewer, $blockingReasons);

            return [
                'success' => false,
                'message' => __('The request is blocked: :reasons', ['reasons' => implode(' ', $blockingReasons)]),
                'blockingReasons' => $blockingReasons,
            ];
        }

        try {
            $user->delete();

            $deletionRequest->update([
                'status' => UserDeletionRequest::STATUS_COMPLETED,
                'reviewed_by' => $reviewer?->id ?: $deletionRequest->reviewed_by,
                'reviewed_at' => $deletionRequest->reviewed_at ?: now(),
                'completed_at' => now(),
            ]);

            $this->safelyNotify(
                fn () => $this->sendCompletedEmail($deletionRequest, $user),
                'completed deletion request email for request '.$deletionRequest->id
            );

            return [
                'success' => true,
                'message' => __('The account has been deleted.'),
            ];
        } catch (\Throwable $exception) {
            Log::error('Failed processing user deletion request '.$deletionRequest->id.': '.$exception->getMessage());

            return [
                'success' => false,
                'message' => __('The account could not be deleted. Please check the logs and try again.'),
            ];
        }
    }

    protected function markBlocked(UserDeletionRequest $deletionRequest, ?User $reviewer, array $blockingReasons): void
    {
        $deletionRequest->update([
            'status' => UserDeletionRequest::STATUS_BLOCKED,
            'admin_notes' => implode(PHP_EOL, $blockingReasons),
            'reviewed_by' => $reviewer?->id ?: $deletionRequest->reviewed_by,
            'reviewed_at' => now(),
        ]);

        $user = $deletionRequest->user;

        if ($user) {
            $this->safelyNotify(
                fn () => $this->sendBlockedEmail($deletionRequest, $user, $blockingReasons),
                'blocked deletion request email for request '.$deletionRequest->id
            );
        }
    }

    protected function hasFutureCooldown(UserDeletionRequest $deletionRequest): bool
    {
        return $deletionRequest->eligible_for_deletion_at instanceof Carbon
            && $deletionRequest->eligible_for_deletion_at->isFuture();
    }

    protected function sendAdminNotification(UserDeletionRequest $deletionRequest): void
    {
        $adminUsers = User::query()->where('role_id', 1)->select(['email', 'name'])->get();

        foreach ($adminUsers as $adminUser) {
            EmailsServiceProvider::sendGenericEmail([
                'email' => $adminUser->email,
                'subject' => __('Action required | New account deletion request'),
                'title' => __('Hello, :name,', ['name' => $adminUser->name]),
                'content' => __('There is a new account deletion request on :siteName that requires your attention.', ['siteName' => getSetting('site.name')]),
                'button' => [
                    'text' => __('Go to admin'),
                    'url' => url()->route('filament.admin.resources.user-deletion-requests.index'),
                ],
            ]);
        }
    }

    protected function sendRequestedEmail(UserDeletionRequest $deletionRequest, User $user): void
    {
        $content = __('Your account deletion request on :siteName has been received.', ['siteName' => getSetting('site.name')]);

        if ($deletionRequest->eligible_for_deletion_at) {
            $content .= ' '.__('The request becomes eligible for deletion on :date.', [
                'date' => $deletionRequest->eligible_for_deletion_at->toDayDateTimeString(),
            ]);
        }

        $this->sendUserEmail($user, __('Account deletion request received'), $content);
    }

    protected function sendApprovedEmail(UserDeletionRequest $deletionRequest, User $user): void
    {
        $content = __('Your account deletion request on :siteName has been approved.', ['siteName' => getSetting('site.name')]);

        if ($deletionRequest->eligible_for_deletion_at) {
            $content .= ' '.__('The account will be deleted after :date if the request is not canceled.', [
                'date' => $deletionRequest->eligible_for_deletion_at->toDayDateTimeString(),
            ]);
        }

        $this->sendUserEmail($user, __('Account deletion request approved'), $content);
    }

    protected function sendRejectedEmail(UserDeletionRequest $deletionRequest, User $user, string $reason): void
    {
        $this->sendUserEmail(
            $user,
            __('Account deletion request rejected'),
            __('Your account deletion request on :siteName was rejected. Reason: :reason', [
                'siteName' => getSetting('site.name'),
                'reason' => $reason,
            ])
        );
    }

    protected function sendCanceledEmail(UserDeletionRequest $deletionRequest, User $user): void
    {
        $this->sendUserEmail(
            $user,
            __('Account deletion request canceled'),
            __('Your account deletion request on :siteName has been canceled.', ['siteName' => getSetting('site.name')])
        );
    }

    protected function sendBlockedEmail(UserDeletionRequest $deletionRequest, User $user, array $blockingReasons): void
    {
        $this->sendUserEmail(
            $user,
            __('Account deletion request needs attention'),
            __('Your account deletion request on :siteName could not be completed yet: :reasons', [
                'siteName' => getSetting('site.name'),
                'reasons' => implode(' ', $blockingReasons),
            ])
        );
    }

    protected function sendCompletedEmail(UserDeletionRequest $deletionRequest, User $user): void
    {
        EmailsServiceProvider::sendGenericEmail([
            'email' => $user->email,
            'subject' => __('Account deleted'),
            'title' => __('Hello, :name,', ['name' => $user->name]),
            'content' => __('Your account on :siteName has been deleted.', ['siteName' => getSetting('site.name')]),
            'button' => [
                'text' => __('Home'),
                'url' => route('home'),
            ],
        ]);
    }

    protected function sendUserEmail(User $user, string $subject, string $content): void
    {
        EmailsServiceProvider::sendGenericEmail([
            'email' => $user->email,
            'subject' => $subject,
            'title' => __('Hello, :name,', ['name' => $user->name]),
            'content' => $content,
            'button' => [
                'text' => __('Go to settings'),
                'url' => route('my.settings', ['type' => 'account']),
            ],
        ]);
    }

    protected function safelyNotify(callable $callback, string $context): void
    {
        try {
            $callback();
        } catch (\Throwable $exception) {
            Log::error('Failed sending '.$context.': '.$exception->getMessage());
        }
    }
}
