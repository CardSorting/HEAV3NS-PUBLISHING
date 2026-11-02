<?php

use App\Services\ConversionTracking\ConversionTrackingPermissions;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\LaravelSettings\Migrations\SettingsMigration;
use Spatie\LaravelSettings\Support\SettingsCacheFactory;

class V1140 extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->deleteIfExists('site.allow_direction_switch');
        $this->upgradeConversionTracking();
        ConversionTrackingPermissions::grant();
        Artisan::call('minify:clear', ['--type' => 'all']);
    }

    /** Also supports upgrading development databases where 11.4.0 was already marked as applied. */
    public function upgradeConversionTracking(): void
    {
        $existingTracking = $this->migrator->exists('payments.conversion_tracking_enabled');
        $this->createConversionTrackingTables();
        foreach ($this->getConversionTrackingSettings() as $name => $value) {
            $key = 'payments.conversion_tracking_'.$name;
            if (! $this->migrator->exists($key)) {
                // Preserve subscription-only scope on installations that tested the earlier integration.
                if ($name === 'purchase_types' && $existingTracking) {
                    $value = ['subscription'];
                }
                $this->migrator->add($key, $value);
            }
        }
        $this->clearSettingsCache();
    }

    public function down(): void
    {
        ConversionTrackingPermissions::remove();
        Schema::dropIfExists('conversion_deliveries');
        if ($this->hasColumn('transactions', 'conversion_attribution_id')) {
            Schema::table('transactions', fn (Blueprint $table) => $table->dropConstrainedForeignId('conversion_attribution_id'));
        }
        foreach (['conversion_checkout', 'purchase_completed_at', 'subscription_completed_at'] as $column) {
            if ($this->hasColumn('transactions', $column)) {
                Schema::table('transactions', fn (Blueprint $table) => $table->dropColumn($column));
            }
        }
        Schema::dropIfExists('conversion_attributions');
        foreach (array_keys($this->getConversionTrackingSettings()) as $name) {
            $this->migrator->deleteIfExists('payments.conversion_tracking_'.$name);
        }

        if (! $this->migrator->exists('site.allow_direction_switch')) {
            $this->migrator->add('site.allow_direction_switch', false);
        }

        $this->clearSettingsCache();

        // Generated minifier builds are recreated automatically when requested.
    }

    protected function createConversionTrackingTables(): void
    {
        if (! $this->hasTable('conversion_attributions')) {
            Schema::create('conversion_attributions', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('token_hash', 64)->index();
                $table->foreignId('user_id')->nullable()->constrained('users')->cascadeOnDelete();
                $table->string('config_hash', 64);
                $table->text('click_id')->nullable();
                // Use DATETIME for application-supplied lifecycle values. Older
                // MySQL/MariaDB versions assign an implicit default to the first
                // non-null TIMESTAMP and reject subsequent ones without defaults.
                $table->dateTime('consented_at');
                $table->dateTime('expires_at')->index();
                $table->timestamp('revoked_at')->nullable();
                $table->timestamps();
            });
        }

        if (! $this->hasColumn('transactions', 'conversion_checkout')) {
            Schema::table('transactions', fn (Blueprint $table) => $table->boolean('conversion_checkout')->default(false));
        }
        if (! $this->hasColumn('transactions', 'conversion_attribution_id')) {
            Schema::table('transactions', fn (Blueprint $table) => $table->foreignId('conversion_attribution_id')->nullable()
                ->constrained('conversion_attributions')->nullOnDelete());
        }
        if (! $this->hasColumn('transactions', 'purchase_completed_at')) {
            if ($this->hasColumn('transactions', 'subscription_completed_at')) {
                Schema::table('transactions', fn (Blueprint $table) => $table->renameColumn('subscription_completed_at', 'purchase_completed_at'));
            } else {
                Schema::table('transactions', fn (Blueprint $table) => $table->timestamp('purchase_completed_at')->nullable());
            }
        }

        if (! $this->hasTable('conversion_deliveries')) {
            Schema::create('conversion_deliveries', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->foreignId('transaction_id')->unique()->constrained('transactions')->cascadeOnDelete();
                $table->foreignId('attribution_id')->nullable()->constrained('conversion_attributions')->nullOnDelete();
                $table->uuid('event_id')->unique();
                $table->string('provider_event_key', 64)->nullable()->unique();
                $table->string('method', 16);
                $table->string('config_hash', 64);
                $table->string('counting_mode', 32);
                $table->string('purchase_type', 32)->default('subscription');
                $table->boolean('is_recurring')->default(false);
                $table->decimal('value', 18, 2);
                $table->char('currency', 3);
                $table->boolean('test_mode')->default(true);
                $table->string('status', 32)->default('pending');
                $table->unsignedInteger('attempts')->default(0);
                $table->timestamp('available_at')->nullable();
                $table->timestamp('leased_until')->nullable();
                $table->string('browser_claim_token', 64)->nullable();
                $table->string('last_error', 255)->nullable();
                $table->timestamp('sent_at')->nullable();
                $table->timestamps();
                $table->index(['method', 'status', 'available_at'], 'conversion_delivery_pending');
            });
        } else {
            // Upgrade tables created by the earlier development migrations without losing their records.
            if (! $this->hasColumn('conversion_deliveries', 'purchase_type')) {
                Schema::table('conversion_deliveries', fn (Blueprint $table) => $table->string('purchase_type', 32)->default('subscription'));
            }
            if (! $this->hasColumn('conversion_deliveries', 'is_recurring')) {
                Schema::table('conversion_deliveries', fn (Blueprint $table) => $table->boolean('is_recurring')->default(false));
                DB::table('conversion_deliveries')->whereIn('transaction_id',
                    DB::table('transactions')->select('id')->where('type', 'subscription-renewal'),
                )->update(['is_recurring' => true]);
            }
        }
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
            throw new RuntimeException('The settings cache could not be cleared.', 0, $exception);
        }
    }

    protected function getConversionTrackingSettings(): array
    {
        // Keep release defaults here so later application defaults cannot change this migration.
        return [
            'enabled' => false,
            'method' => 'browser',
            'counting_mode' => 'first_site',
            'purchase_types' => ['subscription', 'post_unlock', 'message_unlock', 'stream_access'],
            'creator_scope' => 'all',
            'creator_ids' => [],
            'include_renewals' => false,
            'browser_code' => '',
            'postback_url' => '',
            'click_id_parameter' => 'click_id',
            'attribution_days' => 30,
            'test_mode' => true,
            'history_cutoff' => now()->toDateTimeString(),
            // This migration-owned value signals readiness only after all required columns exist.
            'purchase_history_cutoff' => now()->toDateTimeString(),
        ];
    }

    private function hasTable(string $table): bool
    {
        if (DB::connection()->getDriverName() === 'sqlite') {
            return Schema::getFacadeRoot()->hasTable($table);
        }

        return DB::table('information_schema.tables')
            ->whereRaw('table_schema = DATABASE()')
            ->where('table_name', $table)
            ->exists();
    }

    private function hasColumn(string $table, string $column): bool
    {
        if (DB::connection()->getDriverName() === 'sqlite') {
            return Schema::getFacadeRoot()->hasColumn($table, $column);
        }

        return DB::table('information_schema.columns')
            ->whereRaw('table_schema = DATABASE()')
            ->where('table_name', $table)
            ->where('column_name', $column)
            ->exists();
    }
}
