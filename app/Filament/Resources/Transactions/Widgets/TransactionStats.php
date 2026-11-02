<?php

namespace App\Filament\Resources\Transactions\Widgets;

use App\Filament\Resources\Transactions\Pages\ListTransactions;
use App\Model\Transaction;
use Filament\Widgets\Concerns\InteractsWithPageTable;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Flowframe\Trend\Trend;
use Flowframe\Trend\TrendValue;
use Illuminate\Support\Number;

class TransactionStats extends BaseWidget
{
    use InteractsWithPageTable;

    protected ?string $pollingInterval = null;

    public array $tableColumnSearches = [];

    protected function getTablePage(): string
    {
        return ListTransactions::class;
    }

//    protected function getHeading(): ?string
//    {
//        return __('admin.widgets.transaction_stats.heading');
//    }

    protected function getStats(): array
    {
        $orderData = Trend::model(Transaction::class)
            ->between(
                start: now()->subYear(),
                end: now(),
            )
            ->perMonth()
            ->count();
        $currency = getSetting('payments.currency_code') ?? 'USD';
        return [
            Stat::make(__('admin.widgets.transaction_stats.total'), Transaction::query()->count())
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
                __('admin.widgets.transaction_stats.completed'),
                Transaction::query()->where('status', Transaction::APPROVED_STATUS)->where('type', '!=', Transaction::WITHDRAWAL_TYPE)->count()
            )
                ->extraAttributes([
                    'aria-label' => __("admin.filters.last_year"),
                    'class' => 'has-tooltip',
                ]),
            Stat::make(
                __('admin.widgets.transaction_stats.average'),
                Number::currency(
                    Transaction::query()
                        ->where('type', '!=', Transaction::WITHDRAWAL_TYPE)
                        ->avg('amount')
                        ?? 0,
                    in: $currency
                )
            )->extraAttributes([
                'aria-label' => __("admin.filters.last_year"),
                'class' => 'has-tooltip',
            ]),
        ];
    }
}
