<?php

namespace App\Services\ConversionTracking;

use App\Model\ConversionAttribution;
use App\Model\ConversionDelivery;
use App\Model\Subscription;
use App\Model\Transaction;
use App\Model\User;
use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class PurchaseConversionService
{
    public function __construct(
        protected ConversionConfiguration $configuration,
        protected ConversionAttributionService $attributions,
    ) {}

    /** Only checkout, never a back-office transaction edit, sets this provenance. */
    public function capture(Transaction $transaction, Request $request): void
    {
        try {
            if (!$this->ready() || !in_array($transaction->type, ConversionEligibility::PURCHASE_TYPES, true)
                || !$request->user() || (int) $request->user()->id !== (int) $transaction->sender_user_id) {
                return;
            }
            $transaction->conversion_checkout = true;
            if ($this->configuration->enabled()) {
                $transaction->conversion_attribution_id = $this->attributions->current($request)?->id;
            }
        } catch (Throwable $exception) {
            $this->reportFailure($transaction->getKey());
        }
    }

    public function inheritAttribution(Transaction $transaction, Subscription $subscription): void
    {
        try {
            if (!$this->ready()) {
                return;
            }
            $transaction->conversion_checkout = true;
            if (!$this->configuration->enabled() || !$this->configuration->includesRenewals()) {
                return;
            }
            $attributionId = $this->renewalAttributionQuery($transaction, $subscription)->value('conversion_attribution_id');
            $attribution = $attributionId ? ConversionAttribution::query()->find($attributionId) : null;
            if ($this->validAttribution($attribution, $transaction)) {
                $transaction->conversion_attribution_id = $attribution->id;
            }
        } catch (Throwable $exception) {
            $this->reportFailure($transaction->getKey());
        }
    }

    /** Called explicitly after the gateway/wallet workflow has completed all required writes. */
    public function complete(Transaction $transaction, ?string $providerEventId = null): void
    {
        try {
            if (!$transaction->getKey() || !$this->ready()) {
                return;
            }
            if (!$this->configuration->enabled()) {
                if (!ConversionEligibility::isPaidPurchase($transaction) || $transaction->purchase_completed_at !== null) {
                    return;
                }
                // Snapshot the completed checkout; do not resolve attribution or reload/lock the buyer.
                $purchase = clone $transaction;
                $this->afterCommit(function () use ($purchase) {
                    try {
                        $this->recordHistoryOnly($purchase);
                    } catch (Throwable $exception) {
                        $this->reportFailure($purchase->getKey());
                    }
                });
                return;
            }
            $transactionId = (int) $transaction->getKey();
            $this->afterCommit(function () use ($transactionId, $providerEventId) {
                try {
                    $this->recordCompletedPurchase($transactionId, $providerEventId);
                } catch (Throwable $exception) {
                    $this->reportFailure($transactionId);
                }
            });
        } catch (Throwable $exception) {
            $this->reportFailure($transaction->getKey());
        }
    }

    protected function afterCommit(callable $callback): void
    {
        if (DB::connection()->transactionLevel() > 0) {
            DB::connection()->afterCommit($callback);
        } else {
            $callback();
        }
    }

    protected function recordCompletedPurchase(int $transactionId, ?string $providerEventId): void
    {
        // Serialize first-purchase decisions for a buyer, including simultaneous purchases of different creators.
        $buyerId = Transaction::query()->whereKey($transactionId)->value('sender_user_id');
        if (!$buyerId) {
            return;
        }
        DB::transaction(function () use ($transactionId, $providerEventId, $buyerId) {
            if (!User::query()->whereKey($buyerId)->lockForUpdate()->first()) {
                return;
            }
            $transaction = Transaction::query()->lockForUpdate()->find($transactionId);
            if (!$transaction || !ConversionEligibility::isPaidPurchase($transaction)
                || $transaction->purchase_completed_at !== null) {
                return;
            }
            if (!$this->hasMatchingPurchaseTarget($transaction)) {
                return;
            }

            // Keep purchase history even when reporting is off. Enabling tracking never backfills old sales.
            $transaction->purchase_completed_at = now();
            $transaction->saveQuietly();
            if (!$this->configuration->enabled()) {
                return;
            }
            if (!ConversionEligibility::permits($transaction, $this->configuration, $this->hasPriorPurchase($transaction))) {
                return;
            }

            $attribution = $transaction->conversion_attribution_id
                ? ConversionAttribution::query()->find($transaction->conversion_attribution_id) : null;
            if (!$this->validAttribution($attribution, $transaction)) {
                return;
            }
            $providerKey = ConversionEligibility::providerEventKey($transaction, $providerEventId);
            if ($providerKey === null) {
                return;
            }

            $status = $this->configuration->testMode() ? 'test' : 'pending';
            $error = null;
            if ($this->configuration->method() === 'postback' && !$attribution->click_id) {
                $status = 'skipped';
                $error = 'No campaign click ID was captured for this purchase.';
            }
            if ($this->configuration->method() === 'postback'
                && PostbackUrl::validateTemplate($this->configuration->postbackUrl()) !== null) {
                $status = 'failed';
                $error = 'The configured postback URL failed safety validation.';
            }

            // Both constraints matter: different local rows may represent the same provider callback.
            ConversionDelivery::query()->insertOrIgnore([
                'transaction_id' => $transaction->id,
                'attribution_id' => $attribution->id,
                'event_id' => (string) Str::uuid(),
                'provider_event_key' => $providerKey,
                'method' => $this->configuration->method(),
                'config_hash' => $this->configuration->fingerprint(),
                'counting_mode' => $this->configuration->countingMode(),
                'purchase_type' => ConversionEligibility::purchaseType($transaction->type),
                'is_recurring' => $transaction->type === Transaction::SUBSCRIPTION_RENEWAL,
                'value' => number_format((float) $transaction->amount, 2, '.', ''),
                'currency' => strtoupper($transaction->currency),
                'test_mode' => $this->configuration->testMode(),
                'status' => $status,
                'last_error' => $error,
                'available_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }, 3);
    }

    /** One conditional UPDATE, with no model hydration, observers, or explicit buyer locks. */
    protected function recordHistoryOnly(Transaction $transaction): void
    {
        $query = Transaction::query()->whereKey($transaction->getKey())
            ->where('status', Transaction::APPROVED_STATUS)->where('conversion_checkout', true)
            ->whereNull('purchase_completed_at')->where('amount', '>', 0)
            ->where('type', $transaction->type)->where('payment_provider', $transaction->payment_provider)
            ->where('sender_user_id', $transaction->sender_user_id)
            ->where('recipient_user_id', $transaction->recipient_user_id);

        // Validate ownership inside the UPDATE instead of loading the related purchase separately.
        match (ConversionEligibility::purchaseType($transaction->type)) {
            'subscription' => $query->whereHas('subscription', fn ($target) => $target
                ->whereColumn('subscriptions.sender_user_id', 'transactions.sender_user_id')
                ->whereColumn('subscriptions.recipient_user_id', 'transactions.recipient_user_id')),
            'post_unlock' => $query->whereHas('post', fn ($target) => $target
                ->whereColumn('posts.user_id', 'transactions.recipient_user_id')),
            'message_unlock' => $query->whereHas('userMessage', fn ($target) => $target
                ->whereColumn('user_messages.sender_id', 'transactions.recipient_user_id')
                ->whereColumn('user_messages.receiver_id', 'transactions.sender_user_id')),
            'stream_access' => $query->whereHas('stream', fn ($target) => $target
                ->whereColumn('streams.user_id', 'transactions.recipient_user_id')),
            'tip' => null,
            default => $query->whereRaw('1 = 0'),
        };

        // Do not change payment timestamps or trigger billing side effects for bookkeeping.
        $query->toBase()->update(['purchase_completed_at' => now()]);
    }

    protected function hasPriorPurchase(Transaction $transaction): bool
    {
        if ($this->configuration->countingMode() === 'every_purchase') {
            return false;
        }
        return $this->historyQuery($transaction)->exists();
    }

    protected function historyQuery(Transaction $transaction): Builder
    {
        return Transaction::query()->where('sender_user_id', $transaction->sender_user_id)
            ->where('id', '!=', $transaction->id)
            ->whereIn('type', $this->configuration->eligibleTransactionTypes())
            ->whereIn('payment_provider', ConversionEligibility::PROVIDERS)
            ->whereIn('status', [Transaction::APPROVED_STATUS, Transaction::REFUNDED_STATUS])
            ->where('amount', '>', 0)->whereColumn('sender_user_id', '!=', 'recipient_user_id')
            ->when(
                $this->configuration->countingMode() === 'first_creator',
                fn ($query) => $query->where('recipient_user_id', $transaction->recipient_user_id)
            )
            ->where(function ($query) use ($transaction) {
                $query->where('purchase_completed_at', '<=', $transaction->purchase_completed_at)
                    ->orWhere(function ($legacy) use ($transaction) {
                        $legacy->where('conversion_checkout', false)->whereNull('purchase_completed_at')
                            ->where('created_at', '<=', $transaction->purchase_completed_at)
                            ->where(function ($categories) {
                                $categories->where(function ($subscriptions) {
                                    $subscriptions->whereIn('type', [...ConversionEligibility::SUBSCRIPTION_TYPES, Transaction::SUBSCRIPTION_RENEWAL])
                                        ->whereIn('payment_provider', ConversionEligibility::SUBSCRIPTION_PROVIDERS)
                                        ->when($this->configuration->get('history_cutoff'), fn ($query, $cutoff) => $query->where('created_at', '<', $cutoff))
                                        ->whereHas('subscription', fn ($subscription) => $subscription
                                            ->whereColumn('subscriptions.sender_user_id', 'transactions.sender_user_id')
                                            ->whereColumn('subscriptions.recipient_user_id', 'transactions.recipient_user_id'));
                                })->orWhere(function ($purchases) {
                                    $purchases->whereIn('type', ConversionEligibility::NON_SUBSCRIPTION_TYPES)
                                        ->when($this->configuration->get('purchase_history_cutoff'), fn ($query, $cutoff) => $query->where('created_at', '<', $cutoff))
                                        ->where(function ($targets) {
                                            $targets->whereIn('type', [Transaction::TIP_TYPE, Transaction::CHAT_TIP_TYPE])
                                                ->orWhere(fn ($post) => $post->where('type', Transaction::POST_UNLOCK)
                                                    ->whereHas('post', fn ($target) => $target->whereColumn('posts.user_id', 'transactions.recipient_user_id')))
                                                ->orWhere(fn ($message) => $message->where('type', Transaction::MESSAGE_UNLOCK)
                                                    ->whereHas('userMessage', fn ($target) => $target
                                                        ->whereColumn('user_messages.sender_id', 'transactions.recipient_user_id')
                                                        ->whereColumn('user_messages.receiver_id', 'transactions.sender_user_id')))
                                                ->orWhere(fn ($stream) => $stream->where('type', Transaction::STREAM_ACCESS)
                                                    ->whereHas('stream', fn ($target) => $target->whereColumn('streams.user_id', 'transactions.recipient_user_id')));
                                        });
                                });
                            });
                    });
            });
    }

    protected function hasMatchingPurchaseTarget(Transaction $transaction): bool
    {
        return match (ConversionEligibility::purchaseType($transaction->type)) {
            'subscription' => $transaction->subscription !== null
                && (int) $transaction->subscription->sender_user_id === (int) $transaction->sender_user_id
                && (int) $transaction->subscription->recipient_user_id === (int) $transaction->recipient_user_id,
            'post_unlock' => $transaction->post !== null
                && (int) $transaction->post->user_id === (int) $transaction->recipient_user_id,
            'message_unlock' => $transaction->userMessage !== null
                && (int) $transaction->userMessage->sender_id === (int) $transaction->recipient_user_id
                && (int) $transaction->userMessage->receiver_id === (int) $transaction->sender_user_id,
            'stream_access' => $transaction->stream !== null
                && (int) $transaction->stream->user_id === (int) $transaction->recipient_user_id,
            'tip' => true,
            default => false,
        };
    }

    protected function validAttribution(?ConversionAttribution $attribution, Transaction $transaction): bool
    {
        return $attribution !== null && $attribution->permitsReporting()
            && (int) $attribution->user_id === (int) $transaction->sender_user_id
            && hash_equals($this->configuration->fingerprint(), (string) $attribution->config_hash);
    }

    protected function renewalAttributionQuery(Transaction $transaction, Subscription $subscription): Builder
    {
        // Subscription rows can be reused. Never fall back past a newer, unattributed checkout.
        return Transaction::query()->where('subscription_id', $subscription->id)
            ->where('sender_user_id', $transaction->sender_user_id)
            ->where('recipient_user_id', $transaction->recipient_user_id)
            ->whereIn('type', ConversionEligibility::SUBSCRIPTION_TYPES)
            ->where('conversion_checkout', true)->whereNotNull('purchase_completed_at')
            ->orderByDesc('id');
    }

    protected function ready(): bool
    {
        // Migration-owned, cached with payment settings. Avoid schema queries on every checkout.
        return (string) $this->configuration->get('purchase_history_cutoff') !== '';
    }

    protected function reportFailure(mixed $transactionId): void
    {
        // Payment completion must never fail because marketing storage is unavailable.
        try {
            Log::warning('Conversion tracking could not record a purchase.', ['transaction_id' => $transactionId]);
        } catch (Throwable $exception) {
            // Even an unavailable logger must not affect the payment response.
        }
    }
}
