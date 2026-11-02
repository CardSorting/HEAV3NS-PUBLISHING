<?php

namespace App\Services;

use App\Model\PendingUserEmailChange;
use App\Model\User;
use App\Model\UserEmailChangeRequest;
use App\Providers\EmailsServiceProvider;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class UserEmailChangeService
{
    public const TOKEN_TTL_HOURS = 24;

    public function enabled(): bool
    {
        return (bool) getSetting('compliance.allow_user_email_changes', true);
    }

    public function pendingChangeForUser(User $user): ?PendingUserEmailChange
    {
        if (!$this->enabled()) {
            return null;
        }

        if (!$this->tablesReady()) {
            return null;
        }

        $this->expireStalePendingChanges();

        return PendingUserEmailChange::query()
            ->where('user_id', $user->id)
            ->latest('id')
            ->first();
    }

    public function requestChange(User $user, string $newEmail): PendingUserEmailChange
    {
        if (!$this->enabled()) {
            throw ValidationException::withMessages([
                'email_change' => __('Email changes are currently disabled.'),
            ]);
        }

        if (!$this->tablesReady()) {
            throw ValidationException::withMessages([
                'new_email' => __('Email changes are not available yet. Please try again later.'),
            ]);
        }

        $this->expireStalePendingChanges();

        $newEmail = $this->normalizeEmail($newEmail);
        $token = Str::random(64);
        $expiresAt = now()->addHours(self::TOKEN_TTL_HOURS);

        try {
            $pendingChange = DB::transaction(function () use ($user, $newEmail, $token, $expiresAt) {
                $lockedUser = User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();

                $this->cancelPendingChangeForUser($lockedUser);

                $auditRequest = UserEmailChangeRequest::query()->create([
                    'user_id' => $lockedUser->id,
                    'current_email' => $this->normalizeEmail($lockedUser->email),
                    'new_email' => $newEmail,
                    'status' => UserEmailChangeRequest::STATUS_PENDING,
                    'requested_at' => now(),
                ]);

                return PendingUserEmailChange::query()->create([
                    'user_id' => $lockedUser->id,
                    'audit_id' => $auditRequest->id,
                    'current_email' => $this->normalizeEmail($lockedUser->email),
                    'new_email' => $newEmail,
                    'token_hash' => $this->hashToken($token),
                    'expires_at' => $expiresAt,
                ]);
            });
        } catch (QueryException $exception) {
            throw ValidationException::withMessages([
                'new_email' => __('This email address is already pending or unavailable.'),
            ]);
        }

        try {
            $this->sendVerificationEmail($pendingChange, $token);
        } catch (Throwable $exception) {
            Log::error('Failed sending email change verification.', [
                'user_id' => $user->id,
                'pending_email_change_id' => $pendingChange->id,
                'message' => $exception->getMessage(),
            ]);

            $this->failPendingChange($pendingChange);

            throw ValidationException::withMessages([
                'new_email' => __('We could not send the verification email. Please try again later.'),
            ]);
        }

        try {
            $this->sendRequestNoticeEmail($pendingChange);
        } catch (Throwable $exception) {
            Log::warning('Failed sending email change request notice.', [
                'user_id' => $user->id,
                'pending_email_change_id' => $pendingChange->id,
                'message' => $exception->getMessage(),
            ]);
        }

        return $pendingChange->refresh();
    }

    public function resend(User $user): PendingUserEmailChange
    {
        if (!$this->enabled()) {
            throw ValidationException::withMessages([
                'email_change' => __('Email changes are currently disabled.'),
            ]);
        }

        if (!$this->tablesReady()) {
            throw ValidationException::withMessages([
                'email_change' => __('Email changes are not available yet. Please try again later.'),
            ]);
        }

        $this->expireStalePendingChanges();

        $token = Str::random(64);
        $expiresAt = now()->addHours(self::TOKEN_TTL_HOURS);

        $pendingChange = PendingUserEmailChange::query()
            ->where('user_id', $user->id)
            ->first();

        if (!$pendingChange) {
            throw ValidationException::withMessages([
                'email_change' => __('There is no pending email change to resend.'),
            ]);
        }

        $oldHash = $pendingChange->token_hash;
        $oldExpiresAt = $pendingChange->expires_at;

        $pendingChange->forceFill([
            'token_hash' => $this->hashToken($token),
            'expires_at' => $expiresAt,
        ])->save();

        try {
            $this->sendVerificationEmail($pendingChange, $token);
        } catch (Throwable $exception) {
            Log::error('Failed resending email change verification.', [
                'user_id' => $user->id,
                'pending_email_change_id' => $pendingChange->id,
                'message' => $exception->getMessage(),
            ]);

            $pendingChange->forceFill([
                'token_hash' => $oldHash,
                'expires_at' => $oldExpiresAt,
            ])->save();

            throw ValidationException::withMessages([
                'email_change' => __('We could not resend the verification email. Please try again later.'),
            ]);
        }

        return $pendingChange->refresh();
    }

    public function cancel(User $user): bool
    {
        if (!$this->tablesReady()) {
            return false;
        }

        $this->expireStalePendingChanges();

        return DB::transaction(function () use ($user) {
            $pendingChange = PendingUserEmailChange::query()
                ->where('user_id', $user->id)
                ->lockForUpdate()
                ->first();

            if (!$pendingChange) {
                return false;
            }

            $this->markAuditRequest($pendingChange, UserEmailChangeRequest::STATUS_CANCELED, 'canceled_at');
            $pendingChange->delete();

            return true;
        });
    }

    public function verify(User $user, PendingUserEmailChange $pendingChange, string $token): void
    {
        if (!$this->enabled()) {
            throw ValidationException::withMessages([
                'email_change' => __('Email changes are currently disabled.'),
            ]);
        }

        try {
            [$oldEmail, $newEmail] = DB::transaction(function () use ($user, $pendingChange, $token) {
                $pendingChange = PendingUserEmailChange::query()
                    ->whereKey($pendingChange->id)
                    ->lockForUpdate()
                    ->first();

                if (!$pendingChange || (int) $pendingChange->user_id !== (int) $user->id) {
                    throw ValidationException::withMessages([
                        'email_change' => __('This email change link is no longer valid.'),
                    ]);
                }

                if ($pendingChange->expires_at && $pendingChange->expires_at->isPast()) {
                    $this->markAuditRequest($pendingChange, UserEmailChangeRequest::STATUS_EXPIRED, 'expired_at');
                    $pendingChange->delete();

                    throw ValidationException::withMessages([
                        'email_change' => __('This email change link has expired.'),
                    ]);
                }

                if (!hash_equals($pendingChange->token_hash, $this->hashToken($token))) {
                    throw ValidationException::withMessages([
                        'email_change' => __('This email change link is no longer valid.'),
                    ]);
                }

                $lockedUser = User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();
                $currentEmail = $this->normalizeEmail($lockedUser->email);

                if ($currentEmail !== $pendingChange->current_email) {
                    $this->markAuditRequest($pendingChange, UserEmailChangeRequest::STATUS_FAILED, 'failed_at');
                    $pendingChange->delete();

                    throw ValidationException::withMessages([
                        'email_change' => __('This email change can no longer be completed.'),
                    ]);
                }

                $emailTaken = User::query()
                    ->whereRaw('LOWER(email) = ?', [$pendingChange->new_email])
                    ->where('id', '!=', $lockedUser->id)
                    ->exists();

                if ($emailTaken) {
                    $this->markAuditRequest($pendingChange, UserEmailChangeRequest::STATUS_FAILED, 'failed_at');
                    $pendingChange->delete();

                    throw ValidationException::withMessages([
                        'email_change' => __('This email address is no longer available.'),
                    ]);
                }

                $oldEmail = $pendingChange->current_email;
                $newEmail = $pendingChange->new_email;

                $lockedUser->forceFill([
                    'email' => $newEmail,
                    'email_verified_at' => now(),
                ])->save();

                $this->markAuditRequest($pendingChange, UserEmailChangeRequest::STATUS_VERIFIED, 'verified_at');
                $pendingChange->delete();

                return [$oldEmail, $newEmail];
            });
        } catch (QueryException $exception) {
            throw ValidationException::withMessages([
                'email_change' => __('This email address is no longer available.'),
            ]);
        }

        try {
            $this->sendCompletedNoticeEmail($newEmail, $oldEmail);
        } catch (Throwable $exception) {
            Log::warning('Failed sending email change completed notice.', [
                'user_id' => $user->id,
                'message' => $exception->getMessage(),
            ]);
        }
    }

    public function expireStalePendingChanges(): void
    {
        if (!$this->tablesReady()) {
            return;
        }

        PendingUserEmailChange::query()
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now())
            ->orderBy('id')
            ->chunkById(100, function ($pendingChanges) {
                foreach ($pendingChanges as $pendingChange) {
                    DB::transaction(function () use ($pendingChange) {
                        $pendingChange = PendingUserEmailChange::query()
                            ->whereKey($pendingChange->id)
                            ->lockForUpdate()
                            ->first();

                        if (!$pendingChange || !$pendingChange->expires_at || $pendingChange->expires_at->isFuture()) {
                            return;
                        }

                        $this->markAuditRequest($pendingChange, UserEmailChangeRequest::STATUS_EXPIRED, 'expired_at');
                        $pendingChange->delete();
                    });
                }
            });
    }

    public function normalizeEmail(string $email): string
    {
        return mb_strtolower(trim($email));
    }

    protected function cancelPendingChangeForUser(User $user): void
    {
        $pendingChange = PendingUserEmailChange::query()
            ->where('user_id', $user->id)
            ->lockForUpdate()
            ->first();

        if (!$pendingChange) {
            return;
        }

        $this->markAuditRequest($pendingChange, UserEmailChangeRequest::STATUS_CANCELED, 'canceled_at');
        $pendingChange->delete();
    }

    protected function failPendingChange(PendingUserEmailChange $pendingChange): void
    {
        DB::transaction(function () use ($pendingChange) {
            $pendingChange = PendingUserEmailChange::query()
                ->whereKey($pendingChange->id)
                ->lockForUpdate()
                ->first();

            if (!$pendingChange) {
                return;
            }

            $this->markAuditRequest($pendingChange, UserEmailChangeRequest::STATUS_FAILED, 'failed_at');
            $pendingChange->delete();
        });
    }

    protected function markAuditRequest(PendingUserEmailChange $pendingChange, string $status, string $timestampColumn): void
    {
        UserEmailChangeRequest::query()
            ->whereKey($pendingChange->audit_id)
            ->update([
                'status' => $status,
                $timestampColumn => now(),
                'updated_at' => now(),
            ]);
    }

    protected function sendVerificationEmail(PendingUserEmailChange $pendingChange, string $token): void
    {
        EmailsServiceProvider::sendGenericEmail([
            'email' => $pendingChange->new_email,
            'subject' => __('Verify your new email address'),
            'title' => __('Verify your new email'),
            'content' => __('Confirm this address to finish changing the email for your :siteName account. This link expires on :date.', [
                'siteName' => getSetting('site.name'),
                'date' => $pendingChange->expires_at->toDayDateTimeString(),
            ]),
            'button' => [
                'text' => __('Verify email'),
                'url' => $this->verificationUrl($pendingChange, $token),
            ],
        ]);
    }

    protected function sendRequestNoticeEmail(PendingUserEmailChange $pendingChange): void
    {
        EmailsServiceProvider::sendGenericEmail([
            'email' => $pendingChange->current_email,
            'subject' => __('Email change requested'),
            'title' => __('Email change requested'),
            'content' => __('A request was made to change your :siteName account email from :currentEmail to :newEmail. If this was not you, please secure your account.', [
                'siteName' => getSetting('site.name'),
                'currentEmail' => $pendingChange->current_email,
                'newEmail' => $pendingChange->new_email,
            ]),
            'button' => [
                'text' => __('Review account'),
                'url' => route('my.settings', ['type' => 'account']),
            ],
        ]);
    }

    protected function sendCompletedNoticeEmail(string $newEmail, string $oldEmail): void
    {
        EmailsServiceProvider::sendGenericEmail([
            'email' => $newEmail,
            'subject' => __('Email address changed'),
            'title' => __('Email address changed'),
            'content' => __('Your account email was changed from :oldEmail to :newEmail.', [
                'oldEmail' => $oldEmail,
                'newEmail' => $newEmail,
            ]),
            'button' => [
                'text' => __('Account settings'),
                'url' => route('my.settings', ['type' => 'account']),
            ],
        ]);
    }

    protected function verificationUrl(PendingUserEmailChange $pendingChange, string $token): string
    {
        return URL::temporarySignedRoute(
            'my.settings.account.email.verify',
            $pendingChange->expires_at,
            [
                'emailChange' => $pendingChange->id,
                'token' => $token,
            ]
        );
    }

    protected function hashToken(string $token): string
    {
        return hash('sha256', $token);
    }

    protected function tablesReady(): bool
    {
        return Schema::hasTable('pending_user_email_changes')
            && Schema::hasTable('user_email_change_requests');
    }
}
