<?php

use Spatie\LaravelSettings\Migrations\SettingsBlueprint;
use Spatie\LaravelSettings\Migrations\SettingsMigration;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;

class V940 extends SettingsMigration
{
    /**
     * Post-admin panel migrations.
     */
    public function up(): void
    {
        $this->migrator->inGroup('storage', function (SettingsBlueprint $blueprint): void {
            $blueprint->add('r2_access_key', '');
            $blueprint->add('r2_secret_key', '');
            $blueprint->add('r2_bucket_name', '');
            $blueprint->add('r2_endpoint', '');
            $blueprint->add('r2_region', 'auto');
            $blueprint->add('r2_custom_url', '');
        });

        Schema::table('global_announcements', function (Blueprint $table) {
            $table->boolean('id_verified_only')
                ->default(false)
                ->after('is_global');
//            $table->index('id_verified_only');
        });

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $this->migrator->inGroup('storage', function (SettingsBlueprint $blueprint): void {
            $blueprint->delete('r2_access_key');
            $blueprint->delete('r2_secret_key');
            $blueprint->delete('r2_bucket_name');
            $blueprint->delete('r2_endpoint');
            $blueprint->delete('r2_region');
            $blueprint->delete('r2_custom_url');
        });

        Schema::table('global_announcements', function (Blueprint $table) {
            $table->dropIndex(['id_verified_only']);
            $table->dropColumn('id_verified_only');
        });

    }
}
