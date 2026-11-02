<?php

namespace App\Filament\Widgets;

use App\Model\User;
use App\Model\UserMessage;
use BezhanSalleh\FilamentShield\Traits\HasWidgetShield;
use Illuminate\Support\Carbon;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Flowframe\Trend\Trend;
use Flowframe\Trend\TrendValue;

class UsersChart extends ChartWidget
{
    use InteractsWithPageFilters;

    use HasWidgetShield;

    protected static ?int $sort = 1;

    protected ?string $pollingInterval = '10s';

    public ?string $filter = '12m';

    protected ?string $maxHeight = '260px';

    public function getHeading(): string|null
    {
        return __('admin.widgets.users_chart.title');
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
        // Default date range and interval: YTD up to now (avoid future-month zeros)
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

        // Apply predefined filter (overrides custom range)
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

        // Safety clamp if custom endDate drifts into the future
        if ($endDate->gt(now())) {
            $endDate = now();
        }

        // Build the trend queries
        $users = Trend::model(User::class)
            ->between($startDate, $endDate)
            ->{$intervalMethod}()
            ->count();

        $userMessages = Trend::model(UserMessage::class)
            ->between($startDate, $endDate)
            ->{$intervalMethod}()
            ->count();

        return [
            'datasets' => [
                [
                    'label' => __('admin.widgets.users_chart.datasets.users'),
                    'data' => $users->map(fn (TrendValue $value) => $value->aggregate),
                    'fill' => false,
                    'borderWidth' => 2,
                    'order' => 1,
                ],
                [
                    'label' => __('admin.widgets.users_chart.datasets.user_messages'),
                    'data' => $userMessages->map(fn (TrendValue $value) => $value->aggregate),
                    'fill' => false,
                    'borderColor' => '#10b981', // green
                    'borderWidth' => 2,
                    'order' => 2,
                ],
            ],
            'labels' => $users->map(fn (TrendValue $value) => $value->date),
        ];
    }
}
