<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\LaravelSettings\Migrations\SettingsMigration;

class V1130 extends SettingsMigration
{
    public function up(): void
    {
        if (!$this->tableExists('queue_health_checks')) {
            Schema::create('queue_health_checks', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('execution_mode', 32);
                $table->string('connection', 32);
                $table->string('queue')->default('default');
                $table->string('status', 32)->default('dispatched');
                $table->timestamp('dispatched_at')->nullable();
                $table->timestamp('started_at')->nullable();
                $table->timestamp('completed_at')->nullable();
                $table->timestamp('failed_at')->nullable();
                $table->text('error')->nullable();
                $table->timestamps();
            });
        }

        $this->createAttachmentTranscodingsTable();
        $this->backfillPendingCoconutTranscodings();
        $this->dropLegacyAttachmentCoconutId();
        $this->createMediaModerationTables();
        $this->addSettingsIfMissing('runtime', $this->getQueueSettings());
        $this->addSettingsIfMissing('moderation', $this->getModerationSettings());
        $this->ensureMediaModerationPermissions();
        Artisan::call('permission:cache-reset');
    }

    public function down(): void
    {
        $this->deleteSettings('moderation', array_keys($this->getModerationSettings()));
        $this->deleteSettings('runtime', array_keys($this->getQueueSettings()));

        $this->deleteMediaModerationPermissions();
        Artisan::call('permission:cache-reset');

        Schema::dropIfExists('text_moderation_items');
        Schema::dropIfExists('media_moderation_usage');
        Schema::dropIfExists('media_moderation_items');
        Schema::dropIfExists('media_moderation_runs');
        $this->restoreLegacyAttachmentCoconutId();
        Schema::dropIfExists('attachment_transcodings');
        Schema::dropIfExists('queue_health_checks');
    }

    protected function createAttachmentTranscodingsTable(): void
    {
        if ($this->tableExists('attachment_transcodings')) {
            return;
        }

        Schema::create('attachment_transcodings', function (Blueprint $table) {
            $table->uuid('attachment_id')->primary();
            $table->string('driver', 32);
            $table->string('status', 32)->default('queued');
            $table->text('source_path');
            $table->text('output_path');
            $table->string('provider_job_id', 191)->nullable();
            $table->string('execution_mode', 32)->nullable();
            $table->string('connection', 32)->nullable();
            $table->string('queue', 191)->nullable();
            $table->longText('options')->nullable();
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->string('error_code', 64)->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('queued_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();

            $table->foreign('attachment_id', 'at_attachment_fk')
                ->references('id')
                ->on('attachments')
                ->cascadeOnDelete();

            $table->index(['status', 'updated_at'], 'at_status_updated_idx');
            $table->index(['driver', 'status'], 'at_driver_status_idx');
            $table->index('provider_job_id', 'at_provider_job_idx');
        });
    }

    protected function backfillPendingCoconutTranscodings(): void
    {
        if (
            !$this->tableExists('attachment_transcodings')
            || !$this->tableExists('attachments')
            || !$this->hasColumn('attachments', 'coconut_id')
        ) {
            return;
        }

        DB::table('attachments')
            ->whereNotNull('coconut_id')
            ->where('filename', 'like', '%/tmp/%')
            ->orderBy('id')
            ->chunk(100, function ($attachments): void {
                foreach ($attachments as $attachment) {
                    $sourcePath = trim((string) $attachment->filename, '/');
                    $baseDirectory = trim(dirname(dirname($sourcePath)), '/');

                    DB::table('attachment_transcodings')->insertOrIgnore([
                        'attachment_id' => $attachment->id,
                        'driver' => 'coconut',
                        'status' => 'processing',
                        'source_path' => $sourcePath,
                        'output_path' => $baseDirectory.'/'.$attachment->id.'.mp4',
                        'provider_job_id' => $attachment->coconut_id,
                        'execution_mode' => 'provider',
                        'attempts' => 1,
                        'queued_at' => $attachment->created_at,
                        'started_at' => $attachment->created_at,
                        'created_at' => $attachment->created_at,
                        'updated_at' => $attachment->updated_at,
                    ]);
                }
            });
    }

    protected function dropLegacyAttachmentCoconutId(): void
    {
        if (!$this->tableExists('attachments') || !$this->hasColumn('attachments', 'coconut_id')) {
            return;
        }

        if (!$this->tableExists('attachment_transcodings')) {
            throw new RuntimeException('The attachment_transcodings table must exist before removing attachments.coconut_id.');
        }

        $hasUnmigratedJobs = DB::table('attachments')
            ->leftJoin('attachment_transcodings', 'attachment_transcodings.attachment_id', '=', 'attachments.id')
            ->whereNotNull('attachments.coconut_id')
            ->where('attachments.filename', 'like', '%/tmp/%')
            ->where(function ($query): void {
                $query->whereNull('attachment_transcodings.attachment_id')
                    ->orWhereNull('attachment_transcodings.provider_job_id')
                    ->orWhereColumn('attachment_transcodings.provider_job_id', '!=', 'attachments.coconut_id');
            })
            ->exists();

        if ($hasUnmigratedJobs) {
            throw new RuntimeException('Pending Coconut jobs could not be migrated. The legacy attachment column was not removed.');
        }

        Schema::table('attachments', function (Blueprint $table) {
            $table->dropColumn('coconut_id');
        });
    }

    protected function restoreLegacyAttachmentCoconutId(): void
    {
        if (!$this->tableExists('attachments')) {
            return;
        }

        if (!$this->hasColumn('attachments', 'coconut_id')) {
            Schema::table('attachments', function (Blueprint $table) {
                $table->string('coconut_id')->nullable()->after('message_id');
                $table->index('coconut_id');
            });
        }

        if (!$this->tableExists('attachment_transcodings')) {
            return;
        }

        DB::table('attachment_transcodings')
            ->where('driver', 'coconut')
            ->whereNotNull('provider_job_id')
            ->orderBy('attachment_id')
            ->chunk(100, function ($transcodings): void {
                foreach ($transcodings as $transcoding) {
                    DB::table('attachments')
                        ->where('id', $transcoding->attachment_id)
                        ->update(['coconut_id' => $transcoding->provider_job_id]);
                }
            });
    }

    protected function createMediaModerationTables(): void
    {
        if (!$this->tableExists('media_moderation_runs')) {
            Schema::create('media_moderation_runs', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->unsignedBigInteger('initiated_by')->nullable();
                $table->string('scan_type', 16)->default('media');
                $table->string('provider', 32)->default('sightengine');
                $table->char('model_signature', 64);
                $table->longText('models');
                $table->longText('scope');
                $table->longText('policy');
                $table->string('status', 32)->default('queued');
                $table->string('execution_mode', 32);
                $table->string('connection', 32);
                $table->string('queue', 191)->default('default');
                $table->unsignedInteger('max_items')->default(100);
                $table->unsignedInteger('max_operations')->default(100);
                $table->unsignedInteger('operations_reserved')->default(0);
                $table->unsignedInteger('operations_used')->default(0);
                $table->unsignedInteger('total_items')->default(0);
                $table->unsignedInteger('processed_items')->default(0);
                $table->unsignedInteger('safe_items')->default(0);
                $table->unsignedInteger('review_items')->default(0);
                $table->unsignedInteger('violation_items')->default(0);
                $table->unsignedInteger('failed_items')->default(0);
                $table->unsignedInteger('skipped_items')->default(0);
                $table->unsignedInteger('reused_items')->default(0);
                $table->timestamp('started_at')->nullable();
                $table->timestamp('last_activity_at')->nullable();
                $table->timestamp('cancel_requested_at')->nullable();
                $table->timestamp('completed_at')->nullable();
                $table->timestamp('failed_at')->nullable();
                $table->text('error')->nullable();
                $table->timestamps();

                $table->foreign('initiated_by', 'mmr_initiated_by_fk')
                    ->references('id')
                    ->on('users')
                    ->nullOnDelete();

                $table->index('status', 'mmr_status_idx');
                $table->index('created_at', 'mmr_created_at_idx');
                $table->index(['scan_type', 'created_at'], 'mmr_type_created_idx');
                $table->index(['provider', 'status'], 'mmr_provider_status_idx');
            });
        }

        if (!$this->tableExists('media_moderation_items')) {
            Schema::create('media_moderation_items', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->uuid('run_id');
                $table->uuid('attachment_id')->nullable();
                $table->string('source_type', 32)->nullable();
                $table->string('source_id', 64)->nullable();
                $table->text('source_path')->nullable();
                $table->string('source_disk', 32)->nullable();
                $table->unsignedBigInteger('owner_user_id')->nullable();
                $table->string('subject_type', 32)->nullable();
                $table->unsignedBigInteger('subject_id')->nullable();
                $table->string('media_type', 16);
                $table->string('provider', 32);
                $table->char('model_signature', 64);
                $table->char('policy_signature', 64);
                $table->char('fingerprint', 64)->nullable();
                $table->timestamp('source_updated_at')->nullable();
                $table->unsignedBigInteger('reused_from_item_id')->nullable();
                $table->string('scan_status', 32)->default('queued');
                $table->string('decision', 32)->nullable();
                $table->string('admin_outcome', 32)->default('unreviewed');
                $table->decimal('max_score', 8, 6)->nullable();
                $table->string('matched_class', 64)->nullable();
                $table->unsignedInteger('matched_at_ms')->nullable();
                $table->longText('scores')->nullable();
                $table->longText('provider_response')->nullable();
                $table->string('provider_request_id', 191)->nullable();
                $table->string('provider_media_id', 191)->nullable();
                $table->string('provider_processing_mode', 16)->nullable();
                $table->decimal('provider_progress', 6, 5)->nullable();
                $table->timestamp('provider_submitted_at')->nullable();
                $table->timestamp('provider_last_update_at')->nullable();
                $table->unsignedSmallInteger('provider_poll_attempts')->default(0);
                $table->timestamp('provider_next_poll_at')->nullable();
                $table->text('provider_poll_error')->nullable();
                $table->unsignedInteger('operations')->default(0);
                $table->unsignedInteger('operations_reserved')->default(0);
                $table->date('operation_reservation_date')->nullable();
                $table->unsignedSmallInteger('attempts')->default(0);
                $table->string('error_type', 64)->nullable();
                $table->string('error_code', 64)->nullable();
                $table->text('error_message')->nullable();
                $table->boolean('retryable')->default(false);
                $table->boolean('is_cached')->default(false);
                $table->timestamp('queued_at')->nullable();
                $table->timestamp('started_at')->nullable();
                $table->timestamp('scanned_at')->nullable();
                $table->timestamp('failed_at')->nullable();
                $table->unsignedBigInteger('reviewed_by')->nullable();
                $table->timestamp('reviewed_at')->nullable();
                $table->text('review_notes')->nullable();
                $table->unsignedBigInteger('deleted_by')->nullable();
                $table->timestamp('content_deleted_at')->nullable();
                $table->text('deletion_notes')->nullable();
                $table->timestamps();

                $table->foreign('run_id', 'mmi_run_fk')
                    ->references('id')
                    ->on('media_moderation_runs')
                    ->cascadeOnDelete();
                $table->foreign('attachment_id', 'mmi_attachment_fk')
                    ->references('id')
                    ->on('attachments')
                    ->nullOnDelete();
                $table->foreign('owner_user_id', 'mmi_owner_fk')
                    ->references('id')
                    ->on('users')
                    ->nullOnDelete();
                $table->foreign('reused_from_item_id', 'mmi_reused_from_fk')
                    ->references('id')
                    ->on('media_moderation_items')
                    ->nullOnDelete();
                $table->foreign('reviewed_by', 'mmi_reviewed_by_fk')
                    ->references('id')
                    ->on('users')
                    ->nullOnDelete();
                $table->foreign('deleted_by', 'mmi_deleted_by_fk')
                    ->references('id')
                    ->on('users')
                    ->nullOnDelete();

                $table->unique(['run_id', 'attachment_id'], 'mmi_run_attachment_uq');
                $table->unique(['run_id', 'source_type', 'source_id'], 'mmi_run_source_uq');
                $table->index(['run_id', 'scan_status'], 'mmi_run_status_idx');
                $table->index(['run_id', 'decision'], 'mmi_run_decision_idx');
                $table->index(['source_type', 'source_id'], 'mmi_source_lookup_idx');
                $table->index(['attachment_id', 'provider', 'model_signature'], 'mmi_attachment_model_idx');
                $table->index(['fingerprint', 'provider', 'model_signature'], 'mmi_fingerprint_model_idx');
                $table->index(['admin_outcome', 'decision'], 'mmi_review_queue_idx');
                $table->index(['scan_status', 'provider_next_poll_at'], 'mmi_provider_poll_idx');
            });
        }

        if (!$this->tableExists('media_moderation_usage')) {
            Schema::create('media_moderation_usage', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('provider', 32);
                $table->date('usage_date');
                $table->unsignedInteger('operations_used')->default(0);
                $table->unsignedInteger('operations_reserved')->default(0);
                $table->timestamps();

                $table->unique(['provider', 'usage_date'], 'mmu_provider_date_uq');
                $table->index('usage_date', 'mmu_usage_date_idx');
            });
        }

        $this->createTextModerationItemsTable();
    }

    protected function createTextModerationItemsTable(): void
    {
        if ($this->tableExists('text_moderation_items')) {
            return;
        }

        Schema::create('text_moderation_items', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->uuid('run_id');
            $table->string('source_type', 32);
            $table->string('source_id', 64);
            $table->string('source_field', 32);
            $table->unsignedBigInteger('owner_user_id')->nullable();
            $table->string('subject_type', 32);
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->longText('source_snapshot');
            $table->char('source_hash', 64);
            $table->timestamp('source_updated_at')->nullable();
            $table->string('provider', 32);
            $table->char('model_signature', 64);
            $table->char('policy_signature', 64);
            $table->unsignedBigInteger('reused_from_item_id')->nullable();
            $table->string('scan_status', 32)->default('queued');
            $table->string('decision', 32)->nullable();
            $table->string('admin_outcome', 32)->default('unreviewed');
            $table->decimal('max_score', 8, 6)->nullable();
            $table->string('matched_class', 64)->nullable();
            $table->longText('scores')->nullable();
            $table->longText('provider_response')->nullable();
            $table->string('provider_request_id', 191)->nullable();
            $table->unsignedInteger('operations')->default(0);
            $table->unsignedInteger('operations_reserved')->default(0);
            $table->date('operation_reservation_date')->nullable();
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->string('error_type', 64)->nullable();
            $table->string('error_code', 64)->nullable();
            $table->text('error_message')->nullable();
            $table->boolean('retryable')->default(false);
            $table->boolean('is_cached')->default(false);
            $table->timestamp('queued_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('scanned_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->unsignedBigInteger('reviewed_by')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_notes')->nullable();
            $table->unsignedBigInteger('deleted_by')->nullable();
            $table->timestamp('content_deleted_at')->nullable();
            $table->text('deletion_notes')->nullable();
            $table->timestamps();

            $table->foreign('run_id', 'tmi_run_fk')
                ->references('id')
                ->on('media_moderation_runs')
                ->cascadeOnDelete();
            $table->foreign('owner_user_id', 'tmi_owner_fk')
                ->references('id')
                ->on('users')
                ->nullOnDelete();
            $table->foreign('reused_from_item_id', 'tmi_reused_from_fk')
                ->references('id')
                ->on('text_moderation_items')
                ->nullOnDelete();
            $table->foreign('reviewed_by', 'tmi_reviewer_fk')
                ->references('id')
                ->on('users')
                ->nullOnDelete();
            $table->foreign('deleted_by', 'tmi_deleter_fk')
                ->references('id')
                ->on('users')
                ->nullOnDelete();

            $table->unique(['run_id', 'source_type', 'source_id', 'source_field'], 'tmi_run_source_uq');
            $table->index(['run_id', 'scan_status'], 'tmi_run_status_idx');
            $table->index(['run_id', 'decision'], 'tmi_run_decision_idx');
            $table->index(['source_type', 'source_id'], 'tmi_source_lookup_idx');
            $table->index(['source_hash', 'provider', 'model_signature'], 'tmi_hash_model_idx');
            $table->index(['admin_outcome', 'decision'], 'tmi_review_queue_idx');
        });
    }

    protected function tableExists(string $table): bool
    {
        return DB::table('information_schema.tables')
            ->whereRaw('table_schema = DATABASE()')
            ->where('table_name', $table)
            ->exists();
    }

    protected function hasColumn(string $table, string $column): bool
    {
        return DB::table('information_schema.columns')
            ->whereRaw('table_schema = DATABASE()')
            ->where('table_name', $table)
            ->where('column_name', $column)
            ->exists();
    }

    protected function addSettingsIfMissing(string $group, array $settings): void
    {
        foreach ($settings as $setting => $value) {
            $key = $group.'.'.$setting;

            if (!$this->migrator->exists($key)) {
                $this->migrator->add($key, $value);
            }
        }
    }

    protected function deleteSettings(string $group, array $settings): void
    {
        foreach ($settings as $setting) {
            $key = $group.'.'.$setting;

            if ($this->migrator->exists($key)) {
                $this->migrator->delete($key);
            }
        }
    }

    protected function getModerationSettings(): array
    {
        return [
            'enabled' => false,
            'provider' => 'sightengine',
            'sightengine_api_user' => null,
            'sightengine_api_secret' => null,
            'sightengine_model' => 'nudity-2.1',
            'flag_classes' => ['sexual_activity', 'sexual_display', 'erotica'],
            'review_threshold' => 0.65,
            'violation_threshold' => 0.90,
            'video_enabled' => false,
            'video_processing_mode' => 'auto',
            'video_interval_seconds' => 0.0,
            'max_video_duration_seconds' => 59,
            'request_timeout_seconds' => 120,
            'daily_operation_limit' => 450,
            'monthly_operation_limit' => 1800,
            'inline_item_limit' => 10,
            'deferred_item_limit' => 25,
            'background_item_limit' => 100,
            'worker_item_limit' => 5000,
            'stale_after_minutes' => 15,
            'async_provider_timeout_minutes' => 1440,
            'max_concurrent_video_jobs' => 1,
            'text_enabled' => false,
            'text_models' => ['general'],
            'text_languages' => ['en'],
            'text_flag_classes' => ['sexual', 'discriminatory', 'insulting', 'violent', 'toxic'],
            'text_review_threshold' => 0.65,
            'text_violation_threshold' => 0.90,
        ];
    }

    protected function getMediaModerationPermissions(): array
    {
        return [
            'View:ManageModerationSettings',
            'ViewAny:MediaModerationRun',
            'View:MediaModerationRun',
            'Create:MediaModerationRun',
            'Update:MediaModerationRun',
            'Delete:MediaModerationRun',
            'ViewAny:MediaModerationItem',
            'View:MediaModerationItem',
            'Update:MediaModerationItem',
            'ViewAny:TextModerationItem',
            'View:TextModerationItem',
            'Update:TextModerationItem',
        ];
    }

    protected function getReadOnlyMediaModerationPermissions(): array
    {
        return [
            'View:ManageModerationSettings',
            'ViewAny:MediaModerationRun',
            'View:MediaModerationRun',
            'ViewAny:MediaModerationItem',
            'View:MediaModerationItem',
            'ViewAny:TextModerationItem',
            'View:TextModerationItem',
        ];
    }

    protected function ensureMediaModerationPermissions(): void
    {
        if (!$this->tableExists('permissions')) {
            return;
        }

        foreach ($this->getMediaModerationPermissions() as $permission) {
            DB::table('permissions')->updateOrInsert(
                ['name' => $permission, 'guard_name' => 'web'],
                ['created_at' => now(), 'updated_at' => now()],
            );
        }

        if (!$this->tableExists('roles') || !$this->tableExists('role_has_permissions')) {
            return;
        }

        $adminRoleIds = $this->getBuiltInRoleIds([1], ['admin']);
        $demoRoleIds = $this->getBuiltInRoleIds([3], ['demo']);
        $adminPermissionIds = DB::table('permissions')
            ->where('guard_name', 'web')
            ->whereIn('name', $this->getMediaModerationPermissions())
            ->pluck('id');
        $demoPermissionIds = DB::table('permissions')
            ->where('guard_name', 'web')
            ->whereIn('name', $this->getReadOnlyMediaModerationPermissions())
            ->pluck('id');

        $this->grantPermissionsToRoles($adminRoleIds, $adminPermissionIds);
        $this->grantPermissionsToRoles($demoRoleIds, $demoPermissionIds);
    }

    protected function deleteMediaModerationPermissions(): void
    {
        if (!$this->tableExists('permissions')) {
            return;
        }

        $permissionIds = DB::table('permissions')
            ->where('guard_name', 'web')
            ->whereIn('name', $this->getMediaModerationPermissions())
            ->pluck('id');

        if ($this->tableExists('role_has_permissions')) {
            DB::table('role_has_permissions')->whereIn('permission_id', $permissionIds)->delete();
        }

        DB::table('permissions')->whereIn('id', $permissionIds)->delete();
    }

    protected function getBuiltInRoleIds(array $ids, array $names)
    {
        return DB::table('roles')
            ->where('guard_name', 'web')
            ->where(function ($query) use ($ids, $names) {
                $query->whereIn('id', $ids)->orWhereIn('name', $names);
            })
            ->pluck('id')
            ->unique()
            ->values();
    }

    protected function grantPermissionsToRoles($roleIds, $permissionIds): void
    {
        foreach ($roleIds as $roleId) {
            foreach ($permissionIds as $permissionId) {
                DB::table('role_has_permissions')->updateOrInsert([
                    'permission_id' => $permissionId,
                    'role_id' => $roleId,
                ]);
            }
        }
    }

    protected function getQueueSettings(): array
    {
        $existingConnection = (string) config('queue.default', 'sync');
        $executionMode = in_array($existingConnection, ['sync', 'deferred', 'background'], true)
            ? $existingConnection
            : (in_array($existingConnection, ['database', 'redis', 'sqs'], true) ? 'worker' : 'sync');
        $workerConnection = in_array($existingConnection, ['database', 'redis', 'sqs'], true)
            ? $existingConnection
            : 'database';
        $existingQueueName = trim((string) config("queue.connections.{$workerConnection}.queue", 'default'));
        $existingQueueName = preg_match('/^[A-Za-z0-9_.:{}-]+$/', $existingQueueName) ? $existingQueueName : 'default';
        $redisConnectionName = (string) config('queue.connections.redis.connection', 'default');
        $redis = (array) config("database.redis.{$redisConnectionName}", config('database.redis.default', []));
        $sqs = (array) config('queue.connections.sqs', []);

        return [
            'queue_execution_mode' => $executionMode,
            'queue_driver' => $workerConnection,
            'queue_name' => $existingQueueName,
            'queue_redis_url' => $redis['url'] ?? null,
            'queue_redis_scheme' => $redis['scheme'] ?? 'tcp',
            'queue_redis_host' => $redis['host'] ?? '127.0.0.1',
            'queue_redis_port' => (string) ($redis['port'] ?? '6379'),
            'queue_redis_username' => $redis['username'] ?? null,
            'queue_redis_password' => $redis['password'] ?? null,
            'queue_redis_database' => (string) ($redis['database'] ?? '0'),
            'queue_redis_prefix' => $redis['prefix'] ?? config('database.redis.options.prefix'),
            'queue_redis_retry_after' => max(960, (int) config('queue.connections.redis.retry_after', 960)),
            'queue_redis_block_for' => config('queue.connections.redis.block_for'),
            'queue_sqs_key' => $sqs['key'] ?? null,
            'queue_sqs_secret' => $sqs['secret'] ?? null,
            'queue_sqs_prefix' => $sqs['prefix'] ?? null,
            'queue_sqs_region' => ($sqs['region'] ?? null) ?: 'us-east-1',
        ];
    }
}
