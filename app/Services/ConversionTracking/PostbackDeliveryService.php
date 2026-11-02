<?php

namespace App\Services\ConversionTracking;

use App\Model\ConversionDelivery;
use App\Model\ConversionAttribution;
use App\Model\Transaction;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Database\Query\Expression;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

class PostbackDeliveryService
{
    public const MAX_ATTEMPTS = 5;

    public function __construct(
        protected ConversionConfiguration $configuration,
        protected PostbackUrl $postbackUrl,
    ) {
    }

    public function pruneExpiredAttribution(): int
    {
        // Remove encrypted identifiers even while tracking is disabled; keep the non-sensitive audit link.
        return ConversionAttribution::query()->whereNotNull('click_id')
            ->where(function (Builder $query) {
                $query->whereNotNull('revoked_at')->orWhere('expires_at', '<=', Carbon::now());
            })->update(['click_id' => null, 'updated_at' => Carbon::now()]);
    }

    public function invalidatePendingBrowser(): int
    {
        // Cleanup cannot depend on the buyer returning. Never reclaim or replay a browser dispatch.
        return $this->invalidPendingBrowserQuery()->update([
            'status' => 'skipped',
            'last_error' => 'Browser delivery became ineligible because tracking settings, consent, or payment state changed.',
            'browser_claim_token' => null,
            'available_at' => null,
            'updated_at' => Carbon::now(),
        ]);
    }

    protected function invalidPendingBrowserQuery(): Builder
    {
        $this->configuration->refresh();
        $query = ConversionDelivery::query()->where('method', 'browser')->where('status', 'pending');

        if (!$this->configuration->enabled() || $this->configuration->method() !== 'browser'
            || $this->configuration->testMode()) {
            return $query;
        }

        $fingerprint = $this->configuration->fingerprint();

        return $query->where(function (Builder $invalid) use ($fingerprint) {
            $invalid->where('test_mode', true)->orWhere('config_hash', '!=', $fingerprint)
                ->orWhereDoesntHave('transaction', function (Builder $transaction) {
                    $transaction->where('status', Transaction::APPROVED_STATUS)->where('amount', '>', 0)
                        ->where('conversion_checkout', true)->whereNotNull('purchase_completed_at');
                })->orWhereDoesntHave('attribution', function (Builder $attribution) use ($fingerprint) {
                    $attribution->whereNotNull('consented_at')->whereNull('revoked_at')
                        ->where('expires_at', '>', Carbon::now())->where('config_hash', $fingerprint)
                        ->where('user_id', '>', 0)
                        ->whereExists(function (QueryBuilder $owner) {
                            // Correlate the original attribution owner with this delivery's buyer.
                            $owner->selectRaw('1')->from('transactions as conversion_owner')
                                ->whereColumn('conversion_owner.id', 'conversion_deliveries.transaction_id')
                                ->whereColumn('conversion_owner.sender_user_id', 'conversion_attributions.user_id');
                        });
                });
        });
    }

    /**
     * Called by the scheduler, never from checkout. Network failures cannot affect purchases.
     *
     * @return array{processed: int, sent: int, pending: int, failed: int, skipped: int}
     */
    public function deliverPending(int $limit = 50): array
    {
        $counts = ['processed' => 0, 'sent' => 0, 'pending' => 0, 'failed' => 0, 'skipped' => 0];
        $this->configuration->refresh();
        if (!$this->configuration->enabled()) {
            // Retire the backlog in one query, without claiming/loading individual purchases or click IDs.
            $counts['skipped'] = ConversionDelivery::query()->where('method', 'postback')
                ->where(function (Builder $query) {
                    $query->where('status', 'pending')->orWhere(fn (Builder $expired) => $expired
                        ->where('status', 'delivering')->where('leased_until', '<=', Carbon::now()));
                })->update([
                    'status' => 'skipped',
                    'last_error' => 'Tracking was disabled before delivery.',
                    'leased_until' => null,
                    'available_at' => null,
                    'updated_at' => Carbon::now(),
                ]);
            $counts['processed'] = $counts['skipped'];
            return $counts;
        }
        $this->failExhaustedLeases();

        foreach ($this->candidateIds(max(1, min(500, $limit))) as $id) {
            try {
                $delivery = $this->claim((int) $id);
                if ($delivery === null) {
                    continue;
                }

                $status = $this->deliver($delivery);
                $counts['processed']++;
                $counts[$status]++;
            } catch (Throwable $exception) {
                // A persistence failure leaves the lease recoverable; never log request URLs or click IDs.
                $this->reportPersistenceFailure((int) $id);
            }
        }

        return $counts;
    }

    protected function deliver(ConversionDelivery $delivery): string
    {
        try {
            $reason = $this->skipReason($delivery);
            if ($reason !== null) {
                return $this->finish($delivery, 'skipped', $reason);
            }

            $target = $this->postbackUrl->resolveTarget($this->configuration->postbackUrl(), array_merge(
                $delivery->payload(),
                ['click_id' => $delivery->attribution->click_id],
            ));
            $statusCode = $this->sendRequest($target);

            if ($statusCode >= 200 && $statusCode < 300) {
                return $this->finish($delivery, 'sent');
            }

            if ($statusCode === 408 || $statusCode === 429 || $statusCode >= 500) {
                return $this->retry($delivery, 'The tracking provider temporarily rejected the postback.');
            }

            return $this->finish($delivery, 'failed', 'The tracking provider rejected the postback. Redirects are not followed.');
        } catch (InvalidArgumentException $exception) {
            return $this->finish($delivery, 'failed', 'The postback target or payload failed safety validation.');
        } catch (Throwable $exception) {
            return $this->retry($delivery, 'The postback could not be delivered.');
        }
    }

    protected function skipReason(ConversionDelivery $delivery): ?string
    {
        $this->configuration->refresh();

        if (!$this->configuration->enabled() || $this->configuration->method() !== 'postback'
            || $this->configuration->testMode() || $delivery->test_mode
            || !hash_equals($this->configuration->fingerprint(), (string) $delivery->config_hash)) {
            return 'Tracking was disabled, changed, or switched to test mode before delivery.';
        }

        $transaction = $delivery->transaction;
        if (!$transaction || !ConversionEligibility::isPaidPurchase($transaction) || !$transaction->purchase_completed_at) {
            return 'The purchase is no longer eligible.';
        }

        $attribution = $delivery->attribution;
        if (!$attribution || !$attribution->permitsReporting()
            || (int) $attribution->user_id !== (int) $transaction->sender_user_id
            || !hash_equals($this->configuration->fingerprint(), (string) $attribution->config_hash)
            || !is_string($attribution->click_id) || $attribution->click_id === '') {
            return 'Marketing consent or valid click attribution is no longer available.';
        }

        return null;
    }

    /** @return array<int, int> */
    protected function candidateIds(int $limit): array
    {
        return $this->claimable()->orderBy('id')->limit($limit)->pluck('id')->all();
    }

    protected function claimable(): Builder
    {
        $now = Carbon::now();

        return ConversionDelivery::query()
            ->where('method', 'postback')
            ->where('test_mode', false)
            ->where('attempts', '<', self::MAX_ATTEMPTS)
            ->where(function (Builder $query) use ($now) {
                $query->where(function (Builder $pending) use ($now) {
                    $pending->where('status', 'pending')->where(function (Builder $due) use ($now) {
                        $due->whereNull('available_at')->orWhere('available_at', '<=', $now);
                    });
                })->orWhere(function (Builder $expired) use ($now) {
                    $expired->where('status', 'delivering')->where('leased_until', '<=', $now);
                });
            });
    }

    protected function claim(int $id): ?ConversionDelivery
    {
        $claimed = $this->claimable()->whereKey($id)->update([
            'status' => 'delivering',
            'attempts' => new Expression('attempts + 1'),
            'leased_until' => Carbon::now()->addMinutes(2),
            'updated_at' => Carbon::now(),
        ]);

        return $claimed === 1 ? ConversionDelivery::with(['transaction', 'attribution'])->find($id) : null;
    }

    protected function failExhaustedLeases(): void
    {
        ConversionDelivery::query()->where('method', 'postback')->where('status', 'delivering')
            ->where('attempts', '>=', self::MAX_ATTEMPTS)->where('leased_until', '<=', Carbon::now())
            ->update([
                'status' => 'failed',
                'leased_until' => null,
                'last_error' => 'Postback delivery attempts were exhausted.',
                'updated_at' => Carbon::now(),
            ]);
    }

    protected function retry(ConversionDelivery $delivery, string $error): string
    {
        if ($delivery->attempts >= self::MAX_ATTEMPTS) {
            return $this->finish($delivery, 'failed', $error);
        }

        return $this->finish($delivery, 'pending', $error, Carbon::now()->addSeconds(
            60 * (2 ** max(0, (int) $delivery->attempts - 1)),
        ));
    }

    protected function finish(ConversionDelivery $delivery, string $status, ?string $error = null, ?Carbon $availableAt = null): string
    {
        // A stale worker cannot overwrite a later retry's result after losing its lease.
        ConversionDelivery::query()->whereKey($delivery->getKey())->where('status', 'delivering')
            ->where('attempts', $delivery->attempts)->update([
                'status' => $status,
                'last_error' => $error,
                'leased_until' => null,
                'available_at' => $availableAt,
                'sent_at' => $status === 'sent' ? Carbon::now() : null,
                'updated_at' => Carbon::now(),
            ]);

        return $status;
    }

    /**
     * cURL's resolver override prevents DNS rebinding between validation and connection.
     * No environment proxies, redirects, response logging, or custom CA bypasses are allowed.
     */
    protected function sendRequest(array $target): int
    {
        if (!function_exists('curl_init')) {
            throw new RuntimeException('The cURL extension is required for postbacks.');
        }

        $handle = curl_init();
        if ($handle === false) {
            throw new RuntimeException('The postback transport could not start.');
        }

        try {
            if (!curl_setopt_array($handle, $this->curlOptions($target))) {
                throw new RuntimeException('The postback transport could not be configured safely.');
            }
            if (curl_exec($handle) === false) {
                throw new RuntimeException('The postback transport failed.');
            }

            return (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
        } finally {
            curl_close($handle);
        }
    }

    protected function curlOptions(array $target): array
    {
        $receivedBytes = 0;

        return [
            CURLOPT_URL => $target['url'],
            CURLOPT_RESOLVE => $target['resolve'],
            CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
            CURLOPT_REDIR_PROTOCOLS => CURLPROTO_HTTPS,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_MAXREDIRS => 0,
            CURLOPT_PROXY => '',
            CURLOPT_NOPROXY => '*',
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_NOSIGNAL => true,
            CURLOPT_USERAGENT => 'Purchase-Conversion-Postback/1.0',
            CURLOPT_HTTPGET => true,
            CURLOPT_RETURNTRANSFER => false,
            CURLOPT_HEADER => false,
            CURLOPT_WRITEFUNCTION => static function ($handle, string $body) use (&$receivedBytes): int {
                $receivedBytes += strlen($body);

                // Discard the body; it may echo private click IDs or configuration credentials.
                return $receivedBytes <= 65536 ? strlen($body) : 0;
            },
        ];
    }

    protected function reportPersistenceFailure(int $id): void
    {
        try {
            Log::warning('A conversion postback could not be processed; its lease can be retried.', ['delivery_id' => $id]);
        } catch (Throwable $exception) {
            // An unavailable logger must not stop unrelated due deliveries.
        }
    }
}
