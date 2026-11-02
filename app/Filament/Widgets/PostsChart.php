<?php

namespace App\Filament\Widgets;

use App\Model\Post;
use App\Model\PostComment;
use App\Model\Reaction;
use BezhanSalleh\FilamentShield\Traits\HasWidgetShield;
use Illuminate\Support\Carbon;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Flowframe\Trend\Trend;
use Flowframe\Trend\TrendValue;

class PostsChart extends ChartWidget
{
    use HasWidgetShield;

    use InteractsWithPageFilters;

    protected static ?int $sort = 2;

    protected ?string $pollingInterval = '10s';

    public ?string $filter = '12m';

    protected ?string $maxHeight = '260px';

    public function getHeading(): string|null
    {
        return __('admin.widgets.posts_chart.title');
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

        // Safety clamp if any custom endDate drifts into the future
        if ($endDate->gt(now())) {
            $endDate = now();
        }

        // Build the trend queries without dynamic method syntax (clearer + robust)
        $postsQuery = Trend::model(Post::class)->between($startDate, $endDate);
        $commentsQuery = Trend::model(PostComment::class)->between($startDate, $endDate);
        $reactionsQuery = Trend::model(Reaction::class)->between($startDate, $endDate);

        if ($intervalMethod === 'perHour') {
            $posts = $postsQuery->perHour()->count();
            $comments = $commentsQuery->perHour()->count();
            $reactions = $reactionsQuery->perHour()->count();
        } elseif ($intervalMethod === 'perDay') {
            $posts = $postsQuery->perDay()->count();
            $comments = $commentsQuery->perDay()->count();
            $reactions = $reactionsQuery->perDay()->count();
        } else { // perMonth
            $posts = $postsQuery->perMonth()->count();
            $comments = $commentsQuery->perMonth()->count();
            $reactions = $reactionsQuery->perMonth()->count();
        }

        return [
            'datasets' => [
                [
                    'label' => __('admin.widgets.posts_chart.datasets.posts'),
                    'data' => $posts->map(function (TrendValue $v) { return $v->aggregate; }),
                    'fill' => false,                 // don't cover other lines
                    'borderWidth' => 2,
                    'order' => 1,                    // draw first
                ],
                [
                    'label' => __('admin.widgets.posts_chart.datasets.comments'),
                    'data' => $comments->map(function (TrendValue $v) { return $v->aggregate; }),
                    'fill' => false,
                    'borderColor' => '#10b981', // green
                    'borderWidth' => 2,
                    'order' => 2,                   // draw after posts
                ],
                [
                    'label' => __('admin.widgets.posts_chart.datasets.reactions'),
                    'data' => $reactions->map(function (TrendValue $v) { return $v->aggregate; }),
                    'fill' => false,
                    'borderColor' => '#f59e0b', // amber
                    'borderWidth' => 2,
                    'order' => 3,                   // draw last, on top
                ],
            ],
            'labels' => $posts->map(function (TrendValue $v) { return $v->date; }),
        ];
    }
}
