<?php

namespace App\Filament\Plugins;

use Filament\Contracts\Plugin;
use Filament\Panel;
use Illuminate\Support\Facades\View;

class CustomHeaderLabelPlugin implements Plugin
{
    public function getId(): string
    {
        return 'custom-header-label';
    }

    public function register(Panel $panel): void
    {
        $panel->renderHook('panels::global-search.before', function () {
            $version = trim(file_get_contents(public_path('version')));
            $license = getLicenseType();
            $year = date('Y');

            $bgColor = $license === 'Unlicensed'
                ? 'rgb(251 191 36)' // yellow-400
                : 'rgb(var(--primary-600))';

            $label = 'WhfgSnaf';

            return View::make('filament.custom-header-label', compact(
                'version',
                'license',
                'bgColor',
                'year',
                'label'
            ));
        });
    }

    public function boot(Panel $panel): void
    {
        // No-op
    }
}
