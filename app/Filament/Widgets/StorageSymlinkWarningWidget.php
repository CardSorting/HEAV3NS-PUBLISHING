<?php

namespace App\Filament\Widgets;

use Filament\Widgets\Widget;
use Illuminate\Support\Facades\Artisan;
use Filament\Notifications\Notification;
use RuntimeException;
use Throwable;

class StorageSymlinkWarningWidget extends Widget
{
    public bool $symlinkFixed = false;

    protected string $view = 'filament.widgets.storage-symlink-warning-widget';

    public function getColumnSpan(): int|string|array
    {
        return 2; // Or 2 if dashboard uses 2-column layout
    }

    public function createSymlink(): void
    {
        $this->symlinkFixed = false;

        try {
            $link = public_path('storage');
            clearstatcache(true);

            if (file_exists($link) && !is_link($link)) {
                throw new RuntimeException(__('public/storage is a real file or folder. Move it aside manually before retrying; its contents will not be deleted.'));
            }

            if (!is_dir(storage_path('app/public'))) {
                throw new RuntimeException(__('The storage/app/public folder is missing. Restore or create it before retrying.'));
            }

            $exitCode = Artisan::call('storage:link', ['--force' => true]);
            clearstatcache(true);

            if ($exitCode !== 0 || self::canView()) {
                throw new RuntimeException(__('The storage link could not be verified. Check folder permissions and server symlink support before retrying.'));
            }
        } catch (Throwable $exception) {
            report($exception);

            Notification::make()
                ->title(__('Unable to create the storage link.'))
                ->body($exception->getMessage())
                ->danger()
                ->send();

            return;
        }

        Notification::make()
            ->title(__('Symlink created successfully.'))
            ->success()
            ->send();

        $this->symlinkFixed = true;
    }

    public static function canView(): bool
    {
        $link = public_path('storage');
        $target = storage_path('app/public');

        return !is_link($link) || !is_dir($link) || realpath($link) !== realpath($target);
    }

    public function getViewData(): array
    {
        return []; // no variables needed if logic is in canView()
    }
}
