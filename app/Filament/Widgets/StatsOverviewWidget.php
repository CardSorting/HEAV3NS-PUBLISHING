<?php

namespace App\Filament\Widgets;

use App\Model\Transaction;
use App\Model\User;
use App\Providers\AdminHelperProvider;
use BezhanSalleh\FilamentShield\Traits\HasWidgetShield;
use Carbon\CarbonPeriod;
use Illuminate\Support\Carbon;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Number;

class StatsOverviewWidget extends BaseWidget
{
    use InteractsWithPageFilters;

    // TODO: Investigate why this is not working here
//    use HasWidgetShield;

    protected static ?int $sort = 0;

//    protected function getHeading(): ?string
//    {
//        return __('admin.widgets.stats_overview.title');
//    }

    protected function getStats(): array
    {
        $startInput = $this->filters['startDate'] ?? null;
        $endInput = $this->filters['endDate'] ?? null;

        $startDate = filled($startInput)
            ? Carbon::parse($startInput)->startOfDay()
            : now()->subDays(30)->startOfDay();

        $endDate = filled($endInput)
            ? Carbon::parse($endInput)->endOfDay()
            : now();

        $period = CarbonPeriod::create(
            $startDate->copy()->startOfDay(),
            $endDate->copy()->startOfDay()
        );

        // Helper to map grouped data to full period
        $fillMissingDates = function ($grouped, $field) use ($period) {
            return collect($period)->map(function ($date) use ($grouped, $field) {
                $key = $date->toDateString();
                return isset($grouped[$key]) ? (int) $grouped[$key]->$field : 0;
            })->toArray();
        };

        // Revenue by day
        $revenueRaw = Transaction::selectRaw('DATE(created_at) as date, SUM(amount) as total')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->where('status', Transaction::APPROVED_STATUS)
            ->where('type', '!=', Transaction::WITHDRAWAL_TYPE)
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->keyBy('date');
        $revenueChart = $fillMissingDates($revenueRaw, 'total');

        // New users by day
        $userRaw = User::selectRaw('DATE(created_at) as date, COUNT(*) as count')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->keyBy('date');

        $usersChart = $fillMissingDates($userRaw, 'count');

        // Payments by day (can be count of transactions)
        $paymentsRaw = Transaction::selectRaw('DATE(created_at) as date, COUNT(*) as count')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->where('status', Transaction::APPROVED_STATUS)
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->keyBy('date');

        $paymentsChart = $fillMissingDates($paymentsRaw, 'count');

        $revenue = array_sum($revenueChart);
        $newUsers = array_sum($usersChart);
        $newPayments = array_sum($paymentsChart);

        $formatNumber = fn (int|float $number): string => Number::format($number, 0);

        return [
            Stat::make(
                __('admin.widgets.stats_overview.revenue.label'),
                AdminHelperProvider::formatMoney($revenue)
            )
                ->description(__('admin.widgets.stats_overview.revenue.description'))
                ->descriptionIcon('heroicon-m-banknotes')
                ->chart($revenueChart)
                ->color('success')
                ->extraAttributes([
                    'aria-label' => __("admin.filters.last_month"),
                    'class' => 'has-tooltip',
                ]),

            Stat::make(__('admin.widgets.stats_overview.new_users.label'), $newUsers)
                ->description(__('admin.widgets.stats_overview.new_users.description'))
                ->descriptionIcon('heroicon-m-user-plus')
                ->chart($usersChart)
                ->color('primary')
                ->extraAttributes([
                    'aria-label' => __("admin.filters.last_month"),
                    'class' => 'has-tooltip',
                ]),

            Stat::make(__('admin.widgets.stats_overview.new_payments.label'), $newPayments)
                ->description(__('admin.widgets.stats_overview.new_payments.description'))
                ->descriptionIcon('heroicon-m-credit-card')
                ->chart($paymentsChart)
                ->color('info')
                ->extraAttributes([
                    'aria-label' => __("admin.filters.last_month"),
                    'class' => 'has-tooltip',
                ]),
        ];
    }
}
