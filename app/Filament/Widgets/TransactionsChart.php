<?php

namespace App\Filament\Widgets;

use App\Model\Subscription;
use App\Model\Transaction;
use BezhanSalleh\FilamentShield\Traits\HasWidgetShield;
use Illuminate\Support\Carbon;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Flowframe\Trend\Trend;
use Flowframe\Trend\TrendValue;

class TransactionsChart extends ChartWidget
{
    use InteractsWithPageFilters;

    use HasWidgetShield;

    protected static ?int $sort = 3;

    protected ?string $pollingInterval = '10s';

    public ?string $filter = '12m';

    protected ?string $maxHeight = '260px';

    public function getHeading(): string|null
    {
        return __('admin.widgets.transactions_chart.title');
    }

    protected function getFilters(): ?array
    {
        return [
            'today' => __('admin.filters.today'),
            'week'  => __('admin.filters.week'),
            'month' => __('admin.filters.month'),
            'year'  => __('admin.filters.year'), // YTD
            '12m'  => __('admin.filters.last_year'), // Rolling 12m
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }

    // Keep a single y-axis so smaller lines stay proportional to the largest
    protected function getOptions(): array
    {
        return [
            'maintainAspectRatio' => true,
            'interaction' => ['mode' => 'index', 'intersect' => false],
            'plugins' => [
                'legend' => ['position' => 'bottom'],
            ],
            'elements' => [
                'line' => ['tension' => 0.4],
                'point' => ['radius' => 0, 'hoverRadius' => 3],
            ],
        ];
    }

    protected function getData(): array
    {
        // Default to YTD, ending "now" (avoid padding future months with zeros)
        $startDate = now()->startOfYear();
        $endDate = now();
        $intervalMethod = 'perMonth';

        // Override with custom date range if set
        if (!empty($this->filters['startDate'])) {
            $startDate = Carbon::createFromFormat('Y-m-d', $this->filters['startDate'])->startOfDay();
        }

        if (!empty($this->filters['endDate'])) {
            $endDate = Carbon::createFromFormat('Y-m-d', $this->filters['endDate'])->endOfDay();
        }

        // Apply predefined quick filter if selected (overrides custom range)
        if (!empty($this->filter)) {
            switch ($this->filter) {
                case 'today':
                    $startDate = now()->startOfDay();
                    $endDate = now();
                    $intervalMethod = 'perHour';
                    break;

                case 'week':
                    $startDate = now()->startOfWeek();
                    $endDate = now();
                    $intervalMethod = 'perDay';
                    break;

                case 'month':
                    $startDate = now()->startOfMonth();
                    $endDate = now();
                    $intervalMethod = 'perDay';
                    break;

                case 'year': // YTD
                    $startDate = now()->startOfYear();
                    $endDate = now();
                    $intervalMethod = 'perMonth';
                    break;

                case '12m':
                    $startDate = now()->subMonthsNoOverflow(11)->startOfMonth();
                    $endDate = now();
                    $intervalMethod = 'perMonth';
                    break;
            }
        }

        // Safety clamp if endDate drifts into the future (e.g., custom ranges)
        if ($endDate->gt(now())) {
            $endDate = now();
        }

        // Build trend queries without dynamic method braces for clarity
        $txQuery = Trend::model(Transaction::class)->between($startDate, $endDate);
        $subQuery = Trend::model(Subscription::class)->between($startDate, $endDate);

        if ($intervalMethod === 'perHour') {
            $transactions = $txQuery->perHour()->count();
            $subscriptions = $subQuery->perHour()->count();
        } elseif ($intervalMethod === 'perDay') {
            $transactions = $txQuery->perDay()->count();
            $subscriptions = $subQuery->perDay()->count();
        } else { // perMonth
            $transactions = $txQuery->perMonth()->count();
            $subscriptions = $subQuery->perMonth()->count();
        }

        return [
            'datasets' => [
                [
                    'label' => __('admin.widgets.transactions_chart.datasets.transactions'),
                    'data' => $transactions->map(function (TrendValue $v) { return $v->aggregate; }),
                    'fill' => false,
                    'borderWidth' => 2,
                    'order' => 1,
                ],
                [
                    'label' => __('admin.widgets.transactions_chart.datasets.subscriptions'),
                    'data' => $subscriptions->map(function (TrendValue $v) { return $v->aggregate; }),
                    'borderColor' => '#10b981', // green
                    'fill' => false,
                    'borderWidth' => 2,
                    'order' => 2,
                ],
            ],
            'labels' => $transactions->map(function (TrendValue $v) { return $v->date; }),
        ];
    }
}
