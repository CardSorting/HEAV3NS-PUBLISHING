<?php

namespace App\Filament\Widgets;

use Filament\Widgets\Widget;
use App\Providers\InstallerServiceProvider;

class PendingMigrationsWidget extends Widget
{
    protected static ?int $sort = -10;

    protected string $view = 'filament.widgets.pending-migrations-widget';

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        try {
            app()->instance('pretend_migration', true);
            return InstallerServiceProvider::hasAvailableMigrations() === true;
        } catch (\Throwable $e) {
            return false;
        } finally {
            // Optional cleanup (helps avoid any weird cross-request state in long-running contexts)
            if (app()->bound('pretend_migration')) {
                app()->forgetInstance('pretend_migration');
            }
        }
    }

    public function getViewData(): array
    {
        $canMigrate = false;

        try {
            app()->instance('pretend_migration', true);
            $canMigrate = InstallerServiceProvider::hasAvailableMigrations() === true;
        } catch (\Throwable $e) {
            $canMigrate = false;
        } finally {
            if (app()->bound('pretend_migration')) {
                app()->forgetInstance('pretend_migration');
            }
        }

        return [
            'canMigrate' => $canMigrate,
            'updateUrl' => route('installer.update'),
        ];
    }
}
