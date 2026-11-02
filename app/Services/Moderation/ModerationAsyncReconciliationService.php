<?php

namespace App\Services\Moderation;

use App\Model\MediaModerationItem;
use App\Services\Moderation\Exceptions\ModerationProviderException;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class ModerationAsyncReconciliationService
{
    public const MAX_POLL_DELAY_MINUTES = 60;

    public function __construct(
        protected ModerationScanService $scanner,
        protected ModerationConfiguration $configuration,
        protected ModerationProviderCapacityService $capacity,
    ) {
    }

    /**
     * @return array{checked: int, completed: int, pending: int, failed: int, errors: int}
     */
    public function reconcile(int $limit = 5): array
    {
        $stats = [
            'checked' => 0,
            'completed' => 0,
            'pending' => 0,
            'failed' => 0,
            'errors' => 0,
        ];
        $itemIds = MediaModerationItem::query()
            ->where('scan_status', MediaModerationItem::STATUS_AWAITING_PROVIDER)
            ->where('provider_processing_mode', 'async')
            ->whereNotNull('provider_media_id')
            ->where(function ($query) {
                $query->whereNull('provider_next_poll_at')
                    ->orWhere('provider_next_poll_at', '<=', now());
            })
            ->orderByRaw('COALESCE(provider_next_poll_at, provider_submitted_at)')
            ->limit(max(1, min(50, $limit)))
            ->pluck('id');

        foreach ($itemIds as $itemId) {
            if (!$this->claim((int) $itemId)) {
                continue;
            }

            $stats['checked']++;
            $item = MediaModerationItem::query()->find($itemId);

            if (!$item) {
                continue;
            }

            if ($this->expired($item)) {
                $this->scanner->expireAsyncResult($item);
                $stats['failed']++;

                continue;
            }

            try {
                $item = $this->scanner->refreshAsyncResult($item);
                $this->countResult($item, $stats);
            } catch (ModerationProviderException $exception) {
                if (!$exception->retryable) {
                    $item = $this->scanner->failAsyncResult($item, $exception);
                    $this->countResult($item, $stats);

                    continue;
                }

                $this->defer($item, $exception->getMessage());
                $stats['pending']++;
                $stats['errors']++;
                Log::warning('An asynchronous media moderation status check will be retried.', [
                    'item_id' => $item->id,
                    'provider' => $item->provider,
                    'error_type' => $exception->errorType,
                    'provider_code' => $exception->providerCode,
                ]);
            } catch (DomainException $exception) {
                $fresh = $item->fresh();

                if ($fresh?->scan_status === MediaModerationItem::STATUS_AWAITING_PROVIDER) {
                    $this->defer($fresh, $exception->getMessage());
                    $stats['pending']++;
                    $stats['errors']++;

                    continue;
                }

                if ($fresh) {
                    $this->countResult($fresh, $stats);
                }
            } catch (Throwable $exception) {
                $this->defer($item, 'The automatic provider status check could not be completed.');
                $stats['pending']++;
                $stats['errors']++;
                report($exception);
            }
        }

        $this->capacity->releaseAvailable();

        return $stats;
    }

    public function nextDelayMinutes(int $attempts): int
    {
        $multiplier = 2 ** max(0, min(10, $attempts - 1));

        return min(
            self::MAX_POLL_DELAY_MINUTES,
            ModerationScanService::INITIAL_PROVIDER_POLL_DELAY_MINUTES * $multiplier,
        );
    }

    protected function claim(int $itemId): bool
    {
        return MediaModerationItem::query()
            ->whereKey($itemId)
            ->where('scan_status', MediaModerationItem::STATUS_AWAITING_PROVIDER)
            ->where(function ($query) {
                $query->whereNull('provider_next_poll_at')
                    ->orWhere('provider_next_poll_at', '<=', now());
            })
            ->update([
                'provider_poll_attempts' => DB::raw('provider_poll_attempts + 1'),
                'provider_next_poll_at' => now()->addMinutes(self::MAX_POLL_DELAY_MINUTES),
            ]) === 1;
    }

    protected function expired(MediaModerationItem $item): bool
    {
        $submittedAt = $item->provider_submitted_at ?? $item->started_at;

        return $submittedAt !== null
            && $submittedAt->lte(now()->subMinutes($this->configuration->asyncProviderTimeoutMinutes()));
    }

    protected function defer(MediaModerationItem $item, ?string $error = null): void
    {
        $item->refresh();

        if ($item->scan_status !== MediaModerationItem::STATUS_AWAITING_PROVIDER) {
            return;
        }

        $item->update([
            'provider_next_poll_at' => now()->addMinutes(
                $this->nextDelayMinutes((int) $item->provider_poll_attempts),
            ),
            'provider_poll_error' => filled($error) ? mb_substr($error, 0, 1000) : null,
        ]);
    }

    /**
     * @param  array{checked: int, completed: int, pending: int, failed: int, errors: int}  $stats
     */
    protected function countResult(MediaModerationItem $item, array &$stats): void
    {
        if ($item->scan_status === MediaModerationItem::STATUS_COMPLETED) {
            $stats['completed']++;

            return;
        }

        if ($item->scan_status === MediaModerationItem::STATUS_FAILED) {
            $stats['failed']++;

            return;
        }

        if ($item->scan_status === MediaModerationItem::STATUS_AWAITING_PROVIDER) {
            $this->defer($item);
            $stats['pending']++;
        }
    }
}
