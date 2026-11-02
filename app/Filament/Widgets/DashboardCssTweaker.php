<?php

namespace App\Filament\Widgets;

use Filament\Widgets\Widget;

class DashboardCssTweaker extends Widget
{
    protected static ?int $sort = -1000; // Load early

    protected static bool $isLazy = false; // Force render on initial load

    protected int | string | array $columnSpan = 'full'; // Prevent layout shift

    protected string $view = 'filament.widgets.dashboard-css-tweaker';
}
