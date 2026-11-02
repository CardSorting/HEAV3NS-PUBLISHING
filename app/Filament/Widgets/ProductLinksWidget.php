<?php

namespace App\Filament\Widgets;

use BezhanSalleh\FilamentShield\Traits\HasWidgetShield;
use Filament\Widgets\Widget;

class ProductLinksWidget extends Widget
{
    use HasWidgetShield;

    protected static ?int $sort = 10;

    protected string $view = 'filament.widgets.product-info';

    protected int|string|array $columnSpan = 'full'; // Take full width

    public static function canView(): bool
    {
        // unconditional
        return true;

        // or: only for users who can access the panel
        // return auth()->check();
    }
}
