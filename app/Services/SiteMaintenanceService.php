<?php

namespace App\Services;

use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Artisan;
use RuntimeException;

class SiteMaintenanceService
{
    public const PERMISSION = 'Manage:Maintenance';
    public const SOURCE = 'system-health';

    public function __construct(private readonly Application $app)
    {
    }

    public function state(): array
    {
        $mode = $this->app->maintenanceMode();
        $active = $mode->active();

        return [
            'active' => $active,
            'manual' => $active && ($mode->data()['justfans_source'] ?? null) === self::SOURCE,
        ];
    }

    public function enable(): void
    {
        $this->locked(function (): void {
            $state = $this->state();
            if ($state['active']) {
                if ($state['manual']) {
                    return;
                }
                throw new RuntimeException(__('An update or server maintenance is in progress. Complete it before changing maintenance here.'));
            }

            $this->app->maintenanceMode()->activate([
                'status' => 503,
                'retry' => 60,
                'justfans_source' => self::SOURCE,
            ]);
            if (!$this->state()['manual']) {
                throw new RuntimeException(__('Maintenance mode could not be enabled. Check storage permissions.'));
            }
        });
    }

    public function disable(): void
    {
        $this->locked(function (): void {
            $state = $this->state();
            if (!$state['active']) {
                return;
            }
            if (!$state['manual']) {
                throw new RuntimeException(__('An update or server maintenance is in progress. Complete it before changing maintenance here.'));
            }
            $this->app->maintenanceMode()->deactivate();
            if ($this->app->maintenanceMode()->active()) {
                throw new RuntimeException(__('Maintenance mode could not be disabled. Check storage permissions.'));
            }
        });
    }

    /** Keep manual controls and the browser updater from changing state concurrently. */
    public function upgrade(): void
    {
        $this->locked(function (): void {
            $mode = $this->app->maintenanceMode();
            $data = $mode->active() ? $mode->data() : [];
            // A failed attempt stays strict, but must remember an intentional manual closure on retry.
            $previous = $this->state()['manual'] ? $data : ($data['justfans_previous_manual'] ?? null);
            try {
                if (Artisan::call('down') !== 0) {
                    throw new RuntimeException(__('The site could not enter maintenance mode. No migration was started.'));
                }
            } finally {
                // The down command can activate strict mode before a later operation fails.
                if ($previous !== null && $mode->active() && !$this->state()['manual']) {
                    $mode->activate(array_merge($mode->data(), ['justfans_previous_manual' => $previous]));
                }
            }
            if (Artisan::call('migrate', ['--force' => true]) !== 0) {
                throw new RuntimeException(__('The database update failed. The site remains in maintenance mode; inspect the migration output before retrying.'));
            }
            foreach (['config:clear' => [], 'route:clear' => [], 'view:clear' => [], 'minify:clear' => ['--type' => 'all']] as $command => $arguments) {
                if (Artisan::call($command, $arguments) !== 0 || $this->frameworkCacheRemains($command)) {
                    throw new RuntimeException(__('The update could not refresh :command. The site remains in maintenance mode; correct the failure and retry.', ['command' => $command]));
                }
            }
            if ($previous !== null) {
                // Restore an intentional closure without briefly reopening the public site.
                $this->app->maintenanceMode()->activate($previous);
                if (!$this->state()['manual']) {
                    throw new RuntimeException(__('Maintenance mode could not be restored after the update.'));
                }
            } elseif (Artisan::call('up') !== 0) {
                throw new RuntimeException(__('The update completed, but maintenance mode could not be cleared.'));
            }
        });
    }

    private function frameworkCacheRemains(string $command): bool
    {
        $paths = match ($command) {
            'config:clear' => [$this->app->getCachedConfigPath()],
            'route:clear' => [$this->app->getCachedRoutesPath()],
            'view:clear' => glob(rtrim((string) config('view.compiled'), '/\\').'/*') ?: [],
            default => [],
        };

        foreach ($paths as $path) {
            clearstatcache(true, $path);
            if (file_exists($path)) {
                return true;
            }
        }

        return false;
    }

    private function locked(callable $operation): void
    {
        $handle = @fopen($this->app->storagePath('framework/site-maintenance.lock'), 'c');
        if ($handle === false) {
            throw new RuntimeException(__('Maintenance controls require writable application storage.'));
        }
        try {
            if (!flock($handle, LOCK_EX | LOCK_NB)) {
                throw new RuntimeException(__('Maintenance controls are busy. Please try again shortly.'));
            }
            $operation();
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }
}
