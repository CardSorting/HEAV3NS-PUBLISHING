<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\LaravelSettings\Migrations\SettingsMigration;

class V1080 extends SettingsMigration
{
    public function up()
    {
        if (! $this->migrator->exists('compliance.id_verify_custom_message_box')) {
            $this->migrator->add('compliance.id_verify_custom_message_box', null);
        }

        if (! Schema::hasTable('message_templates')) {
            Schema::create('message_templates', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('user_id');
                $table->string('trigger_type', 64);
                $table->boolean('enabled')->default(false);
                $table->longText('message')->nullable();
                $table->decimal('price', 12, 2)->default(0)->nullable();
                $table->timestamps();

                $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
                $table->unique(['user_id', 'trigger_type']);
                $table->index(['trigger_type', 'enabled']);
            });
        }

        if (Schema::hasTable('attachments') && ! $this->columnExists('attachments', 'message_template_id')) {
            Schema::table('attachments', function (Blueprint $table) {
                $table->unsignedBigInteger('message_template_id')->nullable()->after('message_id');
                $table->foreign('message_template_id')->references('id')->on('message_templates')->onDelete('cascade');
                $table->index('message_template_id');
            });
        }

        if (Schema::hasTable('user_messages') && ! $this->columnExists('user_messages', 'message_template_id')) {
            Schema::table('user_messages', function (Blueprint $table) {
                $table->unsignedBigInteger('message_template_id')->nullable();
                $table->foreign('message_template_id')->references('id')->on('message_templates')->nullOnDelete();
                $table->index('message_template_id');
            });
        }

        if (! $this->migrator->exists('security.email_domain_policy')) {
            $this->migrator->add('security.email_domain_policy', 'allow_all');
        }

        if (! $this->migrator->exists('security.email_allowedlist_domains')) {
            $this->migrator->add('security.email_allowedlist_domains', []);
        }

        if (! $this->migrator->exists('security.email_blocklist_domains')) {
            $this->migrator->add('security.email_blocklist_domains', []);
        }

        foreach ($this->getRateLimitSettings() as $key => $value) {
            if (! $this->migrator->exists('security.'.$key)) {
                $this->migrator->add('security.'.$key, $value);
            }
        }

        foreach ($this->getRuntimeSettings() as $key => $value) {
            if (! $this->migrator->exists('runtime.'.$key)) {
                $this->migrator->add('runtime.'.$key, $value);
            }
        }

        if (! Schema::hasTable('cache')) {
            Schema::create('cache', function (Blueprint $table) {
                $table->string('key')->primary();
                $table->mediumText('value');
                $table->integer('expiration');
            });
        }

        if (! Schema::hasTable('cache_locks')) {
            Schema::create('cache_locks', function (Blueprint $table) {
                $table->string('key')->primary();
                $table->string('owner');
                $table->integer('expiration');
            });
        }

        if (! Schema::hasTable('sessions')) {
            Schema::create('sessions', function (Blueprint $table) {
                $table->string('id')->primary();
                $table->foreignId('user_id')->nullable()->index();
                $table->string('ip_address', 45)->nullable();
                $table->text('user_agent')->nullable();
                $table->longText('payload');
                $table->integer('last_activity')->index();
            });
        }

        if (! Schema::hasTable('user_payout_accounts')) {
            Schema::create('user_payout_accounts', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('user_id');
                $table->string('method_key', 64)->index();
                $table->string('label')->nullable();
                $table->boolean('is_default')->default(false);
                $table->boolean('is_active')->default(true);
                $table->string('account_holder_name');
                $table->string('iban', 64);
                $table->string('swift_bic', 64)->nullable();
                $table->string('bank_name');
                $table->text('bank_address')->nullable();
                $table->unsignedBigInteger('country_id')->nullable();
                $table->longText('extra_data')->nullable();
                $table->timestamp('last_used_at')->nullable();
                $table->timestamps();

                $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
                $table->foreign('country_id')->references('id')->on('countries')->nullOnDelete();
                $table->index(['user_id', 'method_key', 'is_default']);
            });
        }

        if (Schema::hasTable('withdrawals')) {
            Schema::table('withdrawals', function (Blueprint $table) {
                if (! $this->columnExists('withdrawals', 'payout_account_id')) {
                    $table->unsignedBigInteger('payout_account_id')->nullable()->after('payment_identifier');
                    $table->foreign('payout_account_id')->references('id')->on('user_payout_accounts')->nullOnDelete();
                }

                if (! $this->columnExists('withdrawals', 'payout_method_key')) {
                    $table->string('payout_method_key', 64)->nullable()->after('payment_method');
                    $table->index('payout_method_key');
                }

                if (! $this->columnExists('withdrawals', 'payout_snapshot')) {
                    $table->longText('payout_snapshot')->nullable()->after('payout_method_key');
                }
            });
        }

        $this->grantRuntimePermissionToExistingSettingsRoles();

        Artisan::call('permission:cache-reset');
        Artisan::call('view:clear');

    }

    public function down()
    {
        if (Schema::hasTable('user_messages') && $this->columnExists('user_messages', 'message_template_id')) {
            Schema::table('user_messages', function (Blueprint $table) {
                $table->dropForeign(['message_template_id']);
                $table->dropColumn('message_template_id');
            });
        }

        if (Schema::hasTable('attachments') && $this->columnExists('attachments', 'message_template_id')) {
            Schema::table('attachments', function (Blueprint $table) {
                $table->dropForeign(['message_template_id']);
                $table->dropColumn('message_template_id');
            });
        }

        Schema::dropIfExists('message_templates');

        if ($this->migrator->exists('compliance.id_verify_custom_message_box')) {
            $this->migrator->delete('compliance.id_verify_custom_message_box');
        }

        if ($this->migrator->exists('security.email_domain_policy')) {
            $this->migrator->delete('security.email_domain_policy');
        }

        if ($this->migrator->exists('security.email_allowedlist_domains')) {
            $this->migrator->delete('security.email_allowedlist_domains');
        }

        if ($this->migrator->exists('security.email_blocklist_domains')) {
            $this->migrator->delete('security.email_blocklist_domains');
        }

        foreach (array_keys($this->getRateLimitSettings()) as $key) {
            if ($this->migrator->exists('security.'.$key)) {
                $this->migrator->delete('security.'.$key);
            }
        }

        foreach (array_keys($this->getRuntimeSettings()) as $key) {
            if ($this->migrator->exists('runtime.'.$key)) {
                $this->migrator->delete('runtime.'.$key);
            }
        }

        if (Schema::hasTable('withdrawals')) {
            Schema::table('withdrawals', function (Blueprint $table) {
                if ($this->columnExists('withdrawals', 'payout_account_id')) {
                    $table->dropForeign(['payout_account_id']);
                    $table->dropColumn('payout_account_id');
                }

                if ($this->columnExists('withdrawals', 'payout_snapshot')) {
                    $table->dropColumn('payout_snapshot');
                }

                if ($this->columnExists('withdrawals', 'payout_method_key')) {
                    $table->dropIndex(['payout_method_key']);
                    $table->dropColumn('payout_method_key');
                }
            });
        }

        Schema::dropIfExists('user_payout_accounts');

    }

    protected function getRateLimitSettings(): array
    {
        return [
            'enable_feature_rate_limits' => false,
            'rate_limit_posts_save_enabled' => false,
            'rate_limit_posts_save_max_attempts' => 10,
            'rate_limit_posts_save_decay_seconds' => 60,
            'rate_limit_posts_comments_add_enabled' => false,
            'rate_limit_posts_comments_add_max_attempts' => 20,
            'rate_limit_posts_comments_add_decay_seconds' => 60,
            'rate_limit_stories_store_enabled' => false,
            'rate_limit_stories_store_max_attempts' => 10,
            'rate_limit_stories_store_decay_seconds' => 60,
            'rate_limit_streams_init_enabled' => false,
            'rate_limit_streams_init_max_attempts' => 5,
            'rate_limit_streams_init_decay_seconds' => 60,
            'rate_limit_stream_comments_add_enabled' => false,
            'rate_limit_stream_comments_add_max_attempts' => 20,
            'rate_limit_stream_comments_add_decay_seconds' => 30,
            'rate_limit_suggestions_generate_enabled' => false,
            'rate_limit_suggestions_generate_max_attempts' => 5,
            'rate_limit_suggestions_generate_decay_seconds' => 60,
            'rate_limit_profile_asset_generate_enabled' => false,
            'rate_limit_profile_asset_generate_max_attempts' => 3,
            'rate_limit_profile_asset_generate_decay_seconds' => 60,
            'rate_limit_messenger_send_enabled' => false,
            'rate_limit_messenger_send_max_attempts' => 20,
            'rate_limit_messenger_send_decay_seconds' => 60,
        ];
    }

    protected function getRuntimeSettings(): array
    {
        return [
            'cache_driver' => config('cache.default', 'file'),
            'cache_prefix' => config('cache.prefix'),
            'cache_redis_host' => config('database.redis.cache.host', '127.0.0.1'),
            'cache_redis_port' => (string) config('database.redis.cache.port', '6379'),
            'cache_redis_password' => config('database.redis.cache.password'),
            'session_driver' => config('session.driver', 'file'),
            'session_lifetime' => (int) config('session.lifetime', 43200),
            'session_expire_on_close' => (bool) config('session.expire_on_close', false),
            'session_encrypt' => (bool) config('session.encrypt', false),
            'session_redis_host' => config('database.redis.default.host', '127.0.0.1'),
            'session_redis_port' => (string) config('database.redis.default.port', '6379'),
            'session_redis_password' => config('database.redis.default.password'),
        ];
    }

    protected function columnExists(string $table, string $column): bool
    {
        $connection = DB::connection();

        return ! empty($connection->select(
            'select column_name from information_schema.columns where table_schema = database() and table_name = ? and column_name = ? limit 1',
            [$connection->getTablePrefix().$table, $column]
        ));
    }

    protected function grantRuntimePermissionToExistingSettingsRoles(): void
    {
        if (! Schema::hasTable('permissions') || ! Schema::hasTable('roles') || ! Schema::hasTable('role_has_permissions')) {
            return;
        }

        $permissionId = DB::table('permissions')
            ->where('name', 'View:ManageRuntimeSettings')
            ->where('guard_name', 'web')
            ->value('id');

        if (! $permissionId) {
            $permissionId = DB::table('permissions')->insertGetId([
                'name' => 'View:ManageRuntimeSettings',
                'guard_name' => 'web',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $settingsPermissionIds = DB::table('permissions')
            ->where('guard_name', 'web')
            ->whereIn('name', [
                'View:ManageAdminSettings',
                'View:ManageGeneralSettings',
                'View:ManageStorageSettings',
            ])
            ->pluck('id');

        $roleIds = DB::table('role_has_permissions')
            ->whereIn('permission_id', $settingsPermissionIds)
            ->pluck('role_id')
            ->merge(
                DB::table('roles')
                    ->where('guard_name', 'web')
                    ->where('name', 'admin')
                    ->pluck('id')
            )
            ->unique()
            ->values();

        foreach ($roleIds as $roleId) {
            DB::table('role_has_permissions')->updateOrInsert([
                'permission_id' => $permissionId,
                'role_id' => $roleId,
            ]);
        }
    }
}
