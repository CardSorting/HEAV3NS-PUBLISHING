<?php

namespace App\Services\Moderation;

use App\Model\MediaModerationRun;
use App\Model\MediaModerationItem;
use App\Model\MediaModerationUsage;
use App\Model\TextModerationItem;
use App\Services\Moderation\Data\QuotaReservation;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class ModerationQuotaService
{
    public function __construct(protected ModerationConfiguration $configuration)
    {
    }

    public function reserve(
        MediaModerationRun $run,
        MediaModerationItem|TextModerationItem $item,
        int $operations,
    ): ?QuotaReservation
    {
        $operations = max(1, $operations);
        $usageDate = CarbonImmutable::today();
        $itemClass = $item::class;

        return DB::transaction(function () use ($run, $item, $itemClass, $operations, $usageDate) {
            /** @var MediaModerationRun|null $lockedRun */
            $lockedRun = MediaModerationRun::query()->lockForUpdate()->find($run->id);

            if (!$lockedRun || $lockedRun->isTerminal() || $lockedRun->cancel_requested_at) {
                return null;
            }

            /** @var MediaModerationItem|TextModerationItem|null $lockedItem */
            $lockedItem = $itemClass::query()->lockForUpdate()->find($item->id);

            if (!$lockedItem || $lockedItem->run_id !== $lockedRun->id || $lockedItem->operations_reserved > 0) {
                return null;
            }

            MediaModerationUsage::query()->insertOrIgnore([
                'provider' => $lockedRun->provider,
                'usage_date' => $usageDate->toDateString(),
                'operations_used' => 0,
                'operations_reserved' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            /** @var MediaModerationUsage $dailyUsage */
            $dailyUsage = MediaModerationUsage::query()
                ->where('provider', $lockedRun->provider)
                ->whereDate('usage_date', $usageDate->toDateString())
                ->lockForUpdate()
                ->firstOrFail();

            $monthlyUsage = (int) MediaModerationUsage::query()
                ->where('provider', $lockedRun->provider)
                ->whereBetween('usage_date', [
                    $usageDate->startOfMonth()->toDateString(),
                    $usageDate->endOfMonth()->toDateString(),
                ])
                ->sum(DB::raw('operations_used + operations_reserved'));

            $dailyTotal = $dailyUsage->operations_used + $dailyUsage->operations_reserved;
            $runTotal = $lockedRun->operations_used + $lockedRun->operations_reserved;

            if ($dailyTotal + $operations > $this->configuration->dailyOperationLimit()
                || $monthlyUsage + $operations > $this->configuration->monthlyOperationLimit()
                || $runTotal + $operations > $lockedRun->max_operations) {
                return null;
            }

            $dailyUsage->increment('operations_reserved', $operations);
            $lockedRun->increment('operations_reserved', $operations);
            $lockedItem->update([
                'operations_reserved' => $operations,
                'operation_reservation_date' => $usageDate->toDateString(),
            ]);

            return new QuotaReservation(
                runId: $lockedRun->id,
                itemId: $lockedItem->id,
                provider: $lockedRun->provider,
                usageDate: $usageDate,
                operations: $operations,
                itemType: $item instanceof TextModerationItem ? 'text' : 'media',
            );
        }, 3);
    }

    public function settle(QuotaReservation $reservation, int $actualOperations): void
    {
        $actualOperations = max(0, $actualOperations);

        DB::transaction(function () use ($reservation, $actualOperations) {
            /** @var MediaModerationRun|null $run */
            $run = MediaModerationRun::query()->lockForUpdate()->find($reservation->runId);
            $itemClass = $reservation->itemType === 'text'
                ? TextModerationItem::class
                : MediaModerationItem::class;
            /** @var MediaModerationItem|TextModerationItem|null $item */
            $item = $itemClass::query()->lockForUpdate()->find($reservation->itemId);
            /** @var MediaModerationUsage|null $usage */
            $usage = MediaModerationUsage::query()
                ->where('provider', $reservation->provider)
                ->whereDate('usage_date', $reservation->usageDate->toDateString())
                ->lockForUpdate()
                ->first();

            if (!$item
                || (int) $item->operations_reserved !== $reservation->operations
                || $item->operation_reservation_date?->toDateString() !== $reservation->usageDate->toDateString()) {
                return;
            }

            if ($usage) {
                $usage->update([
                    'operations_reserved' => max(0, $usage->operations_reserved - $reservation->operations),
                    'operations_used' => $usage->operations_used + $actualOperations,
                ]);
            }

            if ($run) {
                $run->update([
                    'operations_reserved' => max(0, $run->operations_reserved - $reservation->operations),
                    'operations_used' => $run->operations_used + $actualOperations,
                    'last_activity_at' => now(),
                ]);
            }

            $item->update([
                'operations_reserved' => 0,
                'operation_reservation_date' => null,
            ]);
        }, 3);
    }

    public function reservationFor(MediaModerationItem|TextModerationItem $item): ?QuotaReservation
    {
        $item = $item->refresh();

        if ($item->operations_reserved < 1 || !$item->operation_reservation_date) {
            return null;
        }

        return new QuotaReservation(
            runId: $item->run_id,
            itemId: $item->id,
            provider: $item->provider,
            usageDate: CarbonImmutable::parse($item->operation_reservation_date),
            operations: (int) $item->operations_reserved,
            itemType: $item instanceof TextModerationItem ? 'text' : 'media',
        );
    }

    public function consumeInterruptedReservation(MediaModerationItem|TextModerationItem $item): int
    {
        $itemClass = $item::class;

        return DB::transaction(function () use ($item, $itemClass): int {
            /** @var MediaModerationRun|null $run */
            $run = MediaModerationRun::query()->lockForUpdate()->find($item->run_id);
            /** @var MediaModerationItem|TextModerationItem|null $lockedItem */
            $lockedItem = $itemClass::query()->lockForUpdate()->find($item->id);

            if (!$lockedItem || $lockedItem->operations_reserved < 1 || !$lockedItem->operation_reservation_date) {
                return 0;
            }

            $operations = (int) $lockedItem->operations_reserved;
            $reservationDate = $lockedItem->operation_reservation_date->toDateString();
            /** @var MediaModerationUsage|null $usage */
            $usage = MediaModerationUsage::query()
                ->where('provider', $lockedItem->provider)
                ->whereDate('usage_date', $reservationDate)
                ->lockForUpdate()
                ->first();

            if ($usage) {
                $usage->update([
                    'operations_reserved' => max(0, $usage->operations_reserved - $operations),
                    'operations_used' => $usage->operations_used + $operations,
                ]);
            }

            if ($run) {
                $run->update([
                    'operations_reserved' => max(0, $run->operations_reserved - $operations),
                    'operations_used' => $run->operations_used + $operations,
                    'last_activity_at' => now(),
                ]);
            }

            $lockedItem->update([
                'operations' => max((int) $lockedItem->operations, $operations),
                'operations_reserved' => 0,
                'operation_reservation_date' => null,
            ]);

            return $operations;
        }, 3);
    }

    public function usage(string $provider): array
    {
        $today = CarbonImmutable::today();
        $daily = MediaModerationUsage::query()
            ->where('provider', $provider)
            ->whereDate('usage_date', $today->toDateString())
            ->first();
        $monthlyUsed = (int) MediaModerationUsage::query()
            ->where('provider', $provider)
            ->whereBetween('usage_date', [
                $today->startOfMonth()->toDateString(),
                $today->endOfMonth()->toDateString(),
            ])
            ->sum('operations_used');
        $monthlyReserved = (int) MediaModerationUsage::query()
            ->where('provider', $provider)
            ->whereBetween('usage_date', [
                $today->startOfMonth()->toDateString(),
                $today->endOfMonth()->toDateString(),
            ])
            ->sum('operations_reserved');

        return [
            'daily_used' => (int) ($daily->operations_used ?? 0),
            'daily_reserved' => (int) ($daily->operations_reserved ?? 0),
            'daily_limit' => $this->configuration->dailyOperationLimit(),
            'monthly_used' => $monthlyUsed,
            'monthly_reserved' => $monthlyReserved,
            'monthly_limit' => $this->configuration->monthlyOperationLimit(),
        ];
    }
}
