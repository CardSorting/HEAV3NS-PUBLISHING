<?php

namespace App\Filament\Resources\Subscriptions\Widgets;

use App\Filament\Resources\Subscriptions\Pages\ListSubscriptions;
use App\Model\Subscription;
use App\Model\Transaction;
use Filament\Widgets\Concerns\InteractsWithPageTable;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Flowframe\Trend\Trend;
use Flowframe\Trend\TrendValue;
use Illuminate\Support\Number;

class SubscriptionStats extends BaseWidget
{
    use InteractsWithPageTable;

    protected ?string $pollingInterval = null;

    public array $tableColumnSearches = [];

    protected function getTablePage(): string
    {
        return ListSubscriptions::class;
    }

//    protected function getHeading(): ?string
//    {
//        return __('admin.widgets.subscription_stats.heading');
//    }

    protected function getStats(): array
    {
        $orderData = Trend::model(Subscription::class)
            ->between(
                start: now()->subYear(),
                end: now(),
            )
            ->perMonth()
            ->count();
        $currency = getSetting('payments.currency_code') ?? 'USD';
        return [
            Stat::make(__('admin.widgets.subscription_stats.total'), Subscription::query()->count())
                ->chart(
                    collect($orderData)
                        ->map(fn (TrendValue $value) => (float) $value->aggregate)
                        ->toArray()
                )
                ->extraAttributes([
                    'aria-label' => __("admin.filters.last_year"),
                    'class' => 'has-tooltip',
                ]),
            Stat::make(
                __('admin.widgets.subscription_stats.active'),
                Subscription::query()
                    ->where('status', Subscription::ACTIVE_STATUS)
                    ->where('expires_at', '>', now())
                    ->count()
            )
                ->extraAttributes([
                    'aria-label' => __("admin.filters.last_year"),
                    'class' => 'has-tooltip',
                ]),
            Stat::make(
                __('admin.widgets.subscription_stats.average_price'),
                Number::currency(Subscription::query()->avg('amount') ?? 0, in: $currency)
            )
                ->extraAttributes([
                    'aria-label' => __("admin.filters.last_year"),
                    'class' => 'has-tooltip',
                ]),
        ];
    }
}
