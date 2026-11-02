<?php

namespace App\Filament\Pages;

use App\Filament\Pages\Settings\ManageMediaSettings;
use App\Filament\Pages\Settings\ManageRuntimeSettings;
use App\Filament\Pages\Settings\ManageStorageSettings;
use App\Services\SystemHealthService;
use App\Services\SiteMaintenanceService;
use App\Services\Queue\QueueRuntimeService;
use BackedEnum;
use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Pages\Page;
use Filament\Notifications\Notification;
use RuntimeException;
use Throwable;

class SystemHealth extends Page
{
    use HasPageShield;

    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-heart';

    protected static ?string $slug = 'system-health';

    protected string $view = 'filament.pages.system-health';

    public static function canAccess(): bool
    {
        return Filament::auth()->user()?->can('View:SystemHealth') ?? false;
    }

    public function getTitle(): string
    {
        return __('System Health');
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('refresh')->label(__('Refresh'))->icon('heroicon-o-arrow-path')->color('gray')->action(fn () => null),
            Action::make('enableMaintenance')
                ->label(__('Enable maintenance'))
                ->icon('heroicon-o-wrench-screwdriver')
                ->color('warning')
                ->visible(fn (): bool => self::canManageMaintenance() && !app(SiteMaintenanceService::class)->state()['active'])
                ->requiresConfirmation()
                ->modalDescription(__('Visitors will see the maintenance page.'))
                ->modalContent(fn () => view('filament.modals.confirmation-details', ['items' => [
                    __('Admin access and existing payment provider notifications remain available.'),
                    __('New checkouts are blocked.'),
                    __('Scheduled tasks and queued workers pause automatically; work already running may finish.'),
                ]]))
                ->action(fn () => $this->changeMaintenance(true)),
            Action::make('disableMaintenance')
                ->label(__('Disable maintenance'))
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->visible(fn (): bool => self::canManageMaintenance() && app(SiteMaintenanceService::class)->state()['manual'])
                ->requiresConfirmation()
                ->modalDescription(__('The public site will reopen and queued work will resume. Scheduled tasks continue at their next scheduled time.'))
                ->action(fn () => $this->changeMaintenance(false)),
        ];
    }

    public static function canManageMaintenance(): bool
    {
        return self::canAccess() && (Filament::auth()->user()?->can(SiteMaintenanceService::PERMISSION) ?? false);
    }

    protected function changeMaintenance(bool $enabled): void
    {
        abort_unless(self::canManageMaintenance(), 403);
        try {
            $service = app(SiteMaintenanceService::class);
            $enabled ? $service->enable() : $service->disable();
            Notification::make()->success()->title($enabled ? __('Maintenance enabled') : __('Maintenance disabled'))->send();
        } catch (Throwable $exception) {
            report($exception);
            Notification::make()->danger()->title(__('Could not change maintenance mode'))
                ->body($exception instanceof RuntimeException ? $exception->getMessage() : __('Check application storage permissions and try again.'))->send();
        }
    }

    protected function getViewData(): array
    {
        return [
            'snapshot' => app(SystemHealthService::class)->snapshot(),
            'maintenance' => app(SiteMaintenanceService::class)->state(),
            'commands' => [
                'scheduler' => QueueRuntimeService::schedulerCommandFor(base_path()),
                'queue' => app(QueueRuntimeService::class)->workerCommand(),
            ],
            'actionUrls' => [
                'queue' => ManageRuntimeSettings::canAccess() ? ManageRuntimeSettings::getUrl(['tab' => 'queue']) : null,
                'cache' => ManageRuntimeSettings::canAccess() ? ManageRuntimeSettings::getUrl(['tab' => 'cache']) : null,
                'storage' => ManageStorageSettings::canAccess() ? ManageStorageSettings::getUrl() : null,
                'media' => ManageMediaSettings::canAccess() ? ManageMediaSettings::getUrl() : null,
            ],
        ];
    }
}
