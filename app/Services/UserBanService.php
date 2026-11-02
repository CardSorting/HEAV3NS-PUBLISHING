<?php

namespace App\Services;

use App\Helpers\PaymentHelper;
use App\Model\Subscription;
use App\Model\SubscriptionCancellationRequest;
use App\Model\Transaction;
use App\Model\User;
use App\Model\UserBan;
use App\Model\Withdrawal;
use App\Providers\EmailsServiceProvider;
use Carbon\CarbonInterface;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class UserBanService
{
    public function activeBan(User|int $user): ?UserBan
    {
        $user = $user instanceof User ? $user : User::find($user);
        if (!$user || !$user->getAttribute('last_ban_id')) {
            return null;
        }
        if ($user->relationLoaded('activeBan')) {
            $ban = $user->getRelation('activeBan');

            return $ban && $ban->status === 'active' ? $ban : null;
        }

        return UserBan::query()->where('user_id', $user->id)->active()->latest('id')->first();
    }

    public function isSuspended(User|int $user): bool
    {
        return $this->activeBan($user) !== null;
    }

    public function protects(User $user): bool
    {
        // Match the panel role rule before applying its suspension gate, including suspended accounts promoted to staff.
        $hasPanelRole = config('settings.admin_version') === 'v2' ? (int) $user->role_id !== 2 : (int) $user->role_id === 1;

        return $hasPanelRole || ($user->relationLoaded('roles')
            ? $user->getRelation('roles')->contains(fn ($role) => $role->name !== 'user')
            : $user->roles()->where('name', '!=', 'user')->exists());
    }

    public function canBan(User $target, User $actor): bool
    {
        return $actor->can('Ban:User') && (int) $target->id !== (int) $actor->id && !$this->protects($target);
    }

    public function ban(User $target, User $actor, string $reason, ?string $policyReference = null, ?CarbonInterface $expiresAt = null): UserBan
    {
        $reason = trim($reason);
        if ($reason === '' || mb_strlen($reason) > 5000 || mb_strlen($policyReference ?? '') > 500 || ($expiresAt && $expiresAt->lessThanOrEqualTo(now()))) {
            throw ValidationException::withMessages(['reason' => __('Enter a reason and a future expiry date, if applicable.')]);
        }

        return DB::transaction(function () use ($target, $actor, $reason, $policyReference, $expiresAt): UserBan {
            $locked = User::query()->whereKey($target->id)->lockForUpdate()->firstOrFail();
            if (!$this->canBan($locked, $actor)) {
                throw new AuthorizationException;
            }

            if ($existing = $this->activeBan($locked)) {
                return $existing;
            }

            $ban = UserBan::create([
                'user_id' => $locked->id, 'banned_by' => $actor->id, 'reason' => $reason,
                'policy_reference' => trim($policyReference ?? '') ?: null, 'expires_at' => $expiresAt,
            ]);
            $locked->forceFill(['last_ban_id' => $ban->id])->save();
            $target->forceFill(['last_ban_id' => $ban->id])->setRelation('activeBan', $ban);
            app(SuspendedActivityService::class)->pause($locked);

            $timestamp = now()->toDateTimeString();
            $subscriptions = $this->recurringSubscriptions($locked)->selectRaw(
                '? AS user_ban_id, id AS subscription_id, CASE WHEN provider = ? THEN ? '
                .'WHEN (provider = ? AND (stripe_subscription_id IS NULL OR stripe_subscription_id = \'\')) '
                .'OR (provider = ? AND (paypal_agreement_id IS NULL OR paypal_agreement_id = \'\')) '
                .'OR (provider = ? AND (ccbill_subscription_id IS NULL OR ccbill_subscription_id = \'\')) '
                .'OR (provider = ? AND (verotel_sale_id IS NULL OR verotel_sale_id = \'\')) '
                .'THEN ? ELSE ? END AS status, CASE WHEN provider = ? THEN ? ELSE NULL END AS completed_at, '
                .'? AS created_at, ? AS updated_at',
                [$ban->id, Transaction::CREDIT_PROVIDER, SubscriptionCancellationRequest::COMPLETED,
                    Transaction::STRIPE_PROVIDER, Transaction::PAYPAL_PROVIDER, Transaction::CCBILL_PROVIDER, Transaction::VEROTEL_PROVIDER,
                    SubscriptionCancellationRequest::AWAITING_SUBSCRIPTION, SubscriptionCancellationRequest::PENDING,
                    Transaction::CREDIT_PROVIDER, $timestamp, $timestamp, $timestamp]
            );
            DB::table('subscription_cancellation_requests')->insertOrIgnoreUsing(
                ['user_ban_id', 'subscription_id', 'status', 'completed_at', 'created_at', 'updated_at'],
                $subscriptions->toBase()
            );
            // These set-based writes only stop future local renewals; no entitlement expiry or money changes.
            $this->recurringSubscriptions($locked)->where('provider', Transaction::CREDIT_PROVIDER)->whereNull('canceled_at')->update(['canceled_at' => now()]);
            $this->recurringSubscriptions($locked)->where('provider', Transaction::CREDIT_PROVIDER)->update(['status' => Subscription::CANCELED_STATUS]);

            $checkouts = Transaction::query()->where(function (Builder $query) use ($locked): void {
                $query->where('sender_user_id', $locked->id)->orWhere('recipient_user_id', $locked->id);
            })->whereIn('type', $this->subscriptionTypes())
                ->whereIn('status', [Transaction::INITIATED_STATUS, Transaction::PENDING_STATUS, Transaction::PARTIALLY_PAID_STATUS])
                ->selectRaw(
                    '? AS user_ban_id, id AS transaction_id, ? AS status, ? AS created_at, ? AS updated_at',
                    [$ban->id, SubscriptionCancellationRequest::AWAITING_SUBSCRIPTION, $timestamp, $timestamp]
                );
            DB::table('subscription_cancellation_requests')->insertOrIgnoreUsing(
                ['user_ban_id', 'transaction_id', 'status', 'created_at', 'updated_at'],
                $checkouts->toBase()
            );

            DB::afterCommit(fn () => $this->sendNotice($locked, $ban));

            return $ban;
        }, 3);
    }

    public function unban(User $target, User $actor): void
    {
        DB::transaction(function () use ($target, $actor): void {
            $locked = User::query()->whereKey($target->id)->lockForUpdate()->firstOrFail();
            if (!$this->canBan($locked, $actor)) {
                throw new AuthorizationException;
            }
            $ban = $this->activeBan($locked);
            if ($ban) {
                $ban->update(['lifted_at' => now(), 'lifted_by' => $actor->id]);
                $target->setRelation('activeBan', null);
                DB::afterCommit(fn () => $this->sendNotice($locked, $ban, true));
            }
        }, 3);
    }

    /** @return array{outgoing_subscriptions:int,incoming_subscriptions:int,pending_withdrawals:int} */
    public function impact(User $user): array
    {
        return [
            'outgoing_subscriptions' => $this->recurringSubscriptions($user)->where('sender_user_id', $user->id)->count(),
            'incoming_subscriptions' => $this->recurringSubscriptions($user)->where('recipient_user_id', $user->id)->count(),
            'pending_withdrawals' => Withdrawal::query()->where('user_id', $user->id)->where('status', Withdrawal::REQUESTED_STATUS)
                ->count(),
        ];
    }

    /** @return array{pending:int,completed:int,review:int} */
    public function cancellationProgress(User $user): array
    {
        if (!$user->getAttribute('last_ban_id')) {
            return ['pending' => 0, 'completed' => 0, 'review' => 0];
        }
        $counts = SubscriptionCancellationRequest::query()->whereHas('ban', fn (Builder $query) => $query->where('user_id', $user->id))
            ->selectRaw('status, COUNT(*) AS aggregate')->groupBy('status')->pluck('aggregate', 'status');

        return [
            'pending' => (int) ($counts[SubscriptionCancellationRequest::PENDING] ?? 0)
                + (int) ($counts[SubscriptionCancellationRequest::PROCESSING] ?? 0)
                + (int) ($counts[SubscriptionCancellationRequest::AWAITING_SUBSCRIPTION] ?? 0),
            'completed' => (int) ($counts[SubscriptionCancellationRequest::COMPLETED] ?? 0),
            'review' => (int) ($counts[SubscriptionCancellationRequest::REVIEW] ?? 0),
        ];
    }

    public function requiresRenewalStop(Subscription $subscription): bool
    {
        $parties = User::query()->whereIn('id', [$subscription->sender_user_id, $subscription->recipient_user_id])->get();
        if (!$parties->contains(fn (User $user) => (bool) $user->getAttribute('last_ban_id'))) {
            return false;
        }

        return SubscriptionCancellationRequest::query()->where('subscription_id', $subscription->id)->exists()
            || $parties->contains(fn (User $user) => $this->isSuspended($user));
    }

    public function hasPendingRecovery(User $user): bool
    {
        return SubscriptionCancellationRequest::query()->where('status', '!=', SubscriptionCancellationRequest::COMPLETED)
            ->where(function (Builder $query) use ($user): void {
                $query->whereHas('ban', fn (Builder $ban) => $ban->where('user_id', $user->id))
                    ->orWhereHas('subscription', fn (Builder $subscription) => $subscription
                        ->where(fn (Builder $parties) => $parties->where('sender_user_id', $user->id)->orWhere('recipient_user_id', $user->id)))
                    ->orWhereHas('transaction', fn (Builder $transaction) => $transaction
                        ->where(fn (Builder $parties) => $parties->where('sender_user_id', $user->id)->orWhere('recipient_user_id', $user->id)));
            })->exists();
    }

    /** Inherit a checkout's persisted stop instruction even after its originating ban has ended. */
    public function inheritRenewalStop(Subscription $subscription, ?Transaction $transaction = null): void
    {
        $providerIdentityChanged = $subscription->wasChanged(['stripe_subscription_id', 'paypal_agreement_id', 'ccbill_subscription_id', 'verotel_sale_id']);
        $parties = User::query()->whereIn('id', [$subscription->sender_user_id, $subscription->recipient_user_id])->get();
        if (!$parties->contains(fn (User $user) => (bool) $user->getAttribute('last_ban_id'))) {
            return;
        }

        $intent = $transaction ? SubscriptionCancellationRequest::query()->where('transaction_id', $transaction->id)->first() : null;
        $existing = SubscriptionCancellationRequest::query()->where('subscription_id', $subscription->id)->first();
        $ban = $intent?->ban ?: $existing?->ban;
        if (!$ban) {
            $ban = UserBan::query()->whereIn('user_id', $parties->modelKeys())->where(function (Builder $query) use ($transaction): void {
                $query->active();
                if ($transaction?->created_at) {
                    // Include checkouts begun before or during the restriction, including a concurrent initiation.
                    $query->orWhere(function (Builder $historical) use ($transaction): void {
                        $historical->where(fn (Builder $end) => $end->whereNull('lifted_at')->orWhere('lifted_at', '>', $transaction->created_at))
                            ->where(fn (Builder $end) => $end->whereNull('expires_at')->orWhere('expires_at', '>', $transaction->created_at));
                    });
                }
            })->latest('id')->first();
        }
        if (!$ban) {
            return;
        }

        $request = $this->requestCancellation($subscription, $ban);
        if ($request->status === SubscriptionCancellationRequest::COMPLETED) {
            // Late success/renewal callbacks must not restore the canceled subscription's local renewal state.
            $this->markLocallyCanceled($subscription);
            if ($providerIdentityChanged && $subscription->provider !== Transaction::CREDIT_PROVIDER) {
                $request->update(['status' => SubscriptionCancellationRequest::REVIEW, 'failure_code' => 'provider_identity_changed']);
            }
            if ($transaction?->type === Transaction::SUBSCRIPTION_RENEWAL && $transaction->status === Transaction::APPROVED_STATUS
                && $transaction->created_at && $request->completed_at && $transaction->created_at->greaterThanOrEqualTo($request->completed_at)) {
                $request->update(['status' => SubscriptionCancellationRequest::REVIEW, 'failure_code' => 'renewal_captured_after_cancellation']);
            }
        }
        if ($intent && $intent->id !== $request->id) {
            $intent->update(['status' => SubscriptionCancellationRequest::COMPLETED, 'completed_at' => now()]);
        }
    }

    /** @return array{completed:int,review:int} */
    public function processPendingCancellations(int $limit = 10): array
    {
        $result = ['completed' => 0, 'review' => 0];
        $deadline = microtime(true) + 45;
        // A declined/abandoned checkout has no subscription to cancel. Keep its original stop instruction
        // so a later verified success still attaches cancellation work to any subscription it creates.
        $result['completed'] += SubscriptionCancellationRequest::query()
            ->where('status', SubscriptionCancellationRequest::AWAITING_SUBSCRIPTION)->whereNull('subscription_id')->whereNotNull('transaction_id')
            ->whereHas('transaction', fn (Builder $query) => $query->whereIn('status', [Transaction::DECLINED_STATUS, Transaction::CANCELED_STATUS]))
            ->update(['status' => SubscriptionCancellationRequest::COMPLETED, 'completed_at' => now(), 'failure_code' => null]);
        // A crashed process may have reached the provider: do not repeat that operation blindly.
        $result['review'] += SubscriptionCancellationRequest::query()->where('status', SubscriptionCancellationRequest::PROCESSING)
            ->where('attempted_at', '<', now()->subMinutes(5))->update([
                'status' => SubscriptionCancellationRequest::REVIEW, 'failure_code' => 'interrupted_provider_operation',
            ]);
        $ids = SubscriptionCancellationRequest::query()->where('status', SubscriptionCancellationRequest::PENDING)
            ->where(fn (Builder $query) => $query->whereNull('next_attempt_at')->orWhere('next_attempt_at', '<=', now()))
            ->orderBy('id')->limit(max(1, min($limit, 50)))->pluck('id');

        foreach ($ids as $id) {
            if ($deadline - microtime(true) < 30) {
                break;
            }
            $request = DB::transaction(function () use ($id): ?SubscriptionCancellationRequest {
                $request = SubscriptionCancellationRequest::query()->whereKey($id)->lockForUpdate()->first();
                if (!$request || $request->status !== SubscriptionCancellationRequest::PENDING) {
                    return null;
                }
                $request->update(['status' => SubscriptionCancellationRequest::PROCESSING, 'attempts' => $request->attempts + 1, 'attempted_at' => now()]);

                return $request;
            }, 3);
            if (!$request) {
                continue;
            }

            try {
                $subscription = $request->subscription;
                if (!$subscription) {
                    $request->update(['status' => SubscriptionCancellationRequest::REVIEW, 'failure_code' => 'subscription_unavailable']);
                    $result['review']++;
                    continue;
                }
                if (!$this->providerReady($subscription)) {
                    $request->update(['status' => SubscriptionCancellationRequest::AWAITING_SUBSCRIPTION]);
                    continue;
                }
                $success = ($subscription->provider === Transaction::CREDIT_PROVIDER && $subscription->status === Subscription::CANCELED_STATUS)
                    || app(PaymentHelper::class)->cancelSubscription($subscription) === true;
                $request->update([
                    'status' => $success ? SubscriptionCancellationRequest::COMPLETED : SubscriptionCancellationRequest::REVIEW,
                    'completed_at' => $success ? now() : null,
                    'failure_code' => $success ? null : 'provider_confirmation_required',
                ]);
                $result[$success ? 'completed' : 'review']++;
            } catch (\Throwable $exception) {
                $request->update(['status' => SubscriptionCancellationRequest::REVIEW, 'failure_code' => 'provider_confirmation_required']);
                $result['review']++;
                Log::warning('Subscription cancellation requires provider review.', ['request_id' => $request->id]);
            }
        }

        return $result;
    }

    /** Call only after an administrator has checked the provider for an uncertain prior attempt. */
    public function retryCancellation(SubscriptionCancellationRequest $request, User $actor): void
    {
        DB::transaction(function () use ($request, $actor): void {
            $request = $this->lockCancellationForActor($request, $actor);
            if ($request->status === SubscriptionCancellationRequest::REVIEW) {
                $request->update(['status' => SubscriptionCancellationRequest::PENDING, 'next_attempt_at' => now(), 'failure_code' => null]);
            }
        }, 3);
    }

    /** Record an administrator's explicit confirmation that the provider has no active billing agreement. */
    public function confirmProviderCancellation(SubscriptionCancellationRequest $request, User $actor): void
    {
        DB::transaction(function () use ($request, $actor): void {
            $request = $this->lockCancellationForActor($request, $actor);
            if (!in_array($request->status, [SubscriptionCancellationRequest::REVIEW, SubscriptionCancellationRequest::AWAITING_SUBSCRIPTION], true)) {
                throw ValidationException::withMessages(['cancellation' => __('Only cancellations awaiting information or provider review can be confirmed.')]);
            }
            if ($request->subscription_id) {
                $subscription = Subscription::query()->whereKey($request->subscription_id)->lockForUpdate()->first();
                if ($subscription) {
                    $this->markLocallyCanceled($subscription);
                }
            }
            $request->update(['status' => SubscriptionCancellationRequest::COMPLETED, 'completed_at' => now(), 'confirmed_by' => $actor->id, 'failure_code' => null]);
        }, 3);
    }

    /** Call inside a transaction; derive authorization from the stored request, never the caller's attributes. */
    protected function lockCancellationForActor(SubscriptionCancellationRequest $request, User $actor): SubscriptionCancellationRequest
    {
        $stored = SubscriptionCancellationRequest::query()->whereKey($request->id)->firstOrFail();
        $ban = $stored->ban;
        $target = $ban ? User::query()->whereKey($ban->user_id)->lockForUpdate()->first() : null;
        if (!$target || !$this->canBan($target, $actor)) {
            throw new AuthorizationException;
        }

        return SubscriptionCancellationRequest::query()->whereKey($stored->id)->where('user_ban_id', $stored->user_ban_id)->lockForUpdate()->firstOrFail();
    }

    protected function requestCancellation(Subscription $subscription, UserBan $ban): SubscriptionCancellationRequest
    {
        $request = SubscriptionCancellationRequest::firstOrCreate(['subscription_id' => $subscription->id], [
            'user_ban_id' => $ban->id, 'status' => $this->providerReady($subscription)
                ? SubscriptionCancellationRequest::PENDING : SubscriptionCancellationRequest::AWAITING_SUBSCRIPTION,
        ]);
        if ($request->status === SubscriptionCancellationRequest::AWAITING_SUBSCRIPTION && $this->providerReady($subscription)) {
            $request->update(['status' => SubscriptionCancellationRequest::PENDING]);
        }
        if ($subscription->provider === Transaction::CREDIT_PROVIDER) {
            $this->markLocallyCanceled($subscription);
            $request->update(['status' => SubscriptionCancellationRequest::COMPLETED, 'completed_at' => now(), 'failure_code' => null]);
        }

        return $request;
    }

    protected function markLocallyCanceled(Subscription $subscription): void
    {
        if ($subscription->status === Subscription::CANCELED_STATUS && $subscription->canceled_at) {
            return;
        }
        // saved observers run before syncOriginal: the just-persisted status can match the previous
        // canceled original, so dirty detection must start with the status actually written by the callback.
        $subscription->syncOriginalAttribute('status');
        $subscription->forceFill(['status' => Subscription::CANCELED_STATUS, 'canceled_at' => $subscription->canceled_at ?: now()])->saveQuietly();
    }

    protected function providerReady(Subscription $subscription): bool
    {
        return match ($subscription->provider) {
            Transaction::STRIPE_PROVIDER => (bool) $subscription->stripe_subscription_id,
            Transaction::PAYPAL_PROVIDER => (bool) $subscription->paypal_agreement_id,
            Transaction::CCBILL_PROVIDER => (bool) $subscription->ccbill_subscription_id,
            Transaction::VEROTEL_PROVIDER => (bool) $subscription->verotel_sale_id,
            default => true,
        };
    }

    /** @return Builder<Subscription> */
    protected function recurringSubscriptions(User $user): Builder
    {
        return Subscription::query()->where(function (Builder $query) use ($user): void {
            $query->where('sender_user_id', $user->id)->orWhere('recipient_user_id', $user->id);
        })->whereIn('status', [Subscription::ACTIVE_STATUS, Subscription::PENDING_STATUS, Subscription::SUSPENDED_STATUS]);
    }

    /** @return list<string> */
    protected function subscriptionTypes(): array
    {
        return [Transaction::ONE_MONTH_SUBSCRIPTION, Transaction::THREE_MONTHS_SUBSCRIPTION, Transaction::SIX_MONTHS_SUBSCRIPTION,
            Transaction::YEARLY_SUBSCRIPTION, Transaction::SUBSCRIPTION_RENEWAL];
    }

    protected function sendNotice(User $user, UserBan $ban, bool $lifted = false): void
    {
        try {
            $content = $lifted
                ? __('Your account suspension has been lifted. Canceled subscriptions will not restart automatically.')
                : __('Your account has been suspended. Reason: :reason', ['reason' => $ban->reason]);
            if (!$lifted && $ban->expires_at) {
                $content .= ' '.__('The suspension expires on :date.', ['date' => $ban->expires_at->toDayDateTimeString()]);
            }
            if (!$lifted && $ban->policy_reference) {
                $content .= ' '.__('Policy reference: :reference', ['reference' => $ban->policy_reference]);
            }
            EmailsServiceProvider::sendGenericEmail([
                'email' => $user->email,
                'subject' => $lifted ? __('Account suspension lifted') : __('Account suspended'),
                'title' => __('Hello, :name,', ['name' => $user->name]),
                'content' => $content,
                'button' => ['text' => __('Review account status'), 'url' => route('account.suspension')],
            ]);
        } catch (\Throwable $exception) {
            Log::warning('Account suspension notice could not be sent.', ['ban_id' => $ban->id]);
        }
    }
}
