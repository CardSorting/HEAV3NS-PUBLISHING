<?php

use App\Services\Media\AttachmentPlacementMigration;
use Illuminate\Support\Facades\Artisan;
use Spatie\LaravelSettings\Migrations\SettingsMigration;
use Spatie\LaravelSettings\Support\SettingsCacheFactory;

return new class extends SettingsMigration
{
    // MySQL schema changes are not atomic. Every step is restartable and verifies before dropping ownership.
    public $withinTransaction = false;

    public function up(): void
    {
        // Preserve an administrator's choice when retrying a partially completed upgrade.
        if (! $this->migrator->exists('media.vault_enabled')) {
            $this->migrator->add('media.vault_enabled', true);
        }

        $this->clearSettingsCache();
        app(AttachmentPlacementMigration::class)->up();
        if (Artisan::call('minify:clear', ['--type' => 'all']) !== 0) {
            throw new RuntimeException('Media migration completed, but clearing stale frontend assets failed. Keep the site in maintenance and rerun the upgrade.');
        }
    }

    public function down(): void
    {
        $this->migrator->deleteIfExists('media.vault_enabled');
        $this->clearSettingsCache();

        throw new RuntimeException('Shared media placements cannot be rolled back safely to single-owner attachments. Use a forward repair or restore matching code and a reviewed pre-upgrade database snapshot.');
    }

    private function clearSettingsCache(): void
    {
        try {
            // The installer runs migrations from an HTTP request. Spatie only
            // registers settings:clear-cache in console mode, so clear the
            // configured caches through the package service instead.
            if (app()->bound(SettingsCacheFactory::class)) {
                foreach (app(SettingsCacheFactory::class)->all() as $cache) {
                    $cache->clear();
                }

                return;
            }

            // Keep isolated migration tests and older bootstraps compatible.
            if (Artisan::call('settings:clear-cache') !== 0) {
                throw new RuntimeException('The settings cache could not be cleared.');
            }
        } catch (\Throwable $exception) {
            throw new RuntimeException('The Media Vault setting was updated, but clearing the settings cache failed. Rerun the upgrade after resolving the cache error.', 0, $exception);
        }
    }
};
