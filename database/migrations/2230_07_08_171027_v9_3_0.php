<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Spatie\LaravelSettings\Migrations\SettingsBlueprint;
use Spatie\LaravelSettings\Migrations\SettingsMigration;

class V930 extends SettingsMigration
{
    /**
     * Post-admin panel migrations.
     */
    public function up(): void
    {
        // Resetting permissions per latest changes
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        Artisan::call('db:seed',['--force'=>true,'--class'=>'Database\Seeders\PermissionsTableSeeder']);
        Artisan::call('db:seed',['--force'=>true,'--class'=>'Database\Seeders\RolesTableSeeder']);
        Artisan::call('db:seed',['--force'=>true,'--class'=>'Database\Seeders\ModelHasPermissionsTableSeeder']);
        Artisan::call('db:seed',['--force'=>true,'--class'=>'Database\Seeders\RoleHasPermissionsTableSeeder']);
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');

        // Clearing filament shield permissions cache
        Artisan::call('permission:cache-reset');
        // Clearing views cache (otherwise filament dashboard goes crazy on this update)
        Artisan::call('view:clear');

        $this->migrator->inGroup('payments', function (SettingsBlueprint $blueprint): void {
            $blueprint->add('paypal_webhook_id', '');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $this->migrator->inGroup('payments', function (SettingsBlueprint $blueprint): void {
            $blueprint->delete('paypal_webhook_id');
        });
    }
}
