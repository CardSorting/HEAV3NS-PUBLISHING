<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\LaravelSettings\Migrations\SettingsMigration;

class V1120 extends SettingsMigration
{
    public function up(): void
    {
        if (! $this->tableExists('user_deletion_requests')) {
            Schema::create('user_deletion_requests', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('user_id')->nullable();
                $table->string('requested_name')->nullable();
                $table->string('requested_email')->nullable();
                $table->string('requested_username')->nullable();
                $table->string('status', 32)->default('pending');
                $table->string('mode', 64)->default('manual_review_with_cooldown');
                $table->text('reason')->nullable();
                $table->text('admin_notes')->nullable();
                $table->text('rejection_reason')->nullable();
                $table->timestamp('requested_at')->nullable();
                $table->timestamp('eligible_for_deletion_at')->nullable();
                $table->unsignedBigInteger('reviewed_by')->nullable();
                $table->timestamp('reviewed_at')->nullable();
                $table->timestamp('canceled_at')->nullable();
                $table->timestamp('completed_at')->nullable();
                $table->timestamps();

                $table->foreign('user_id', 'udr_user_fk')->references('id')->on('users')->nullOnDelete();
                $table->foreign('reviewed_by', 'udr_reviewed_by_fk')->references('id')->on('users')->nullOnDelete();

                $table->index('status', 'udr_status_idx');
                $table->index('requested_at', 'udr_requested_at_idx');
                $table->index('eligible_for_deletion_at', 'udr_eligible_at_idx');
                $table->index(['user_id', 'status'], 'udr_user_status_idx');
                $table->index(['status', 'mode', 'eligible_for_deletion_at'], 'udr_status_mode_elig_idx');
            });
        } else {
            $this->ensureIndexOnColumns('user_deletion_requests', ['status'], 'udr_status_idx');
            $this->ensureIndexOnColumns('user_deletion_requests', ['requested_at'], 'udr_requested_at_idx');
            $this->ensureIndexOnColumns('user_deletion_requests', ['eligible_for_deletion_at'], 'udr_eligible_at_idx');
            $this->ensureIndexOnColumns('user_deletion_requests', ['user_id', 'status'], 'udr_user_status_idx');
            $this->ensureIndexOnColumns('user_deletion_requests', ['status', 'mode', 'eligible_for_deletion_at'], 'udr_status_mode_elig_idx');
        }

        if (! $this->tableExists('user_email_change_requests')) {
            Schema::create('user_email_change_requests', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('user_id');
                $table->string('current_email', 191);
                $table->string('new_email', 191);
                $table->string('status', 32)->default('pending');
                $table->timestamp('requested_at')->nullable();
                $table->timestamp('verified_at')->nullable();
                $table->timestamp('canceled_at')->nullable();
                $table->timestamp('expired_at')->nullable();
                $table->timestamp('failed_at')->nullable();
                $table->timestamps();

                $table->foreign('user_id', 'uecr_user_fk')->references('id')->on('users')->cascadeOnDelete();

                $table->index(['user_id', 'status'], 'uecr_user_status_idx');
                $table->index('requested_at', 'uecr_requested_idx');
            });
        } elseif (! $this->hasColumn('user_email_change_requests', 'failed_at')) {
            Schema::table('user_email_change_requests', function (Blueprint $table) {
                $table->timestamp('failed_at')->nullable()->after('expired_at');
            });
        }

        if (! $this->tableExists('pending_user_email_changes')) {
            Schema::create('pending_user_email_changes', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('user_id');
                $table->unsignedBigInteger('audit_id');
                $table->string('current_email', 191);
                $table->string('new_email', 191);
                $table->char('token_hash', 64);
                $table->timestamp('expires_at')->nullable();
                $table->timestamps();

                $table->foreign('user_id', 'puec_user_fk')->references('id')->on('users')->cascadeOnDelete();
                $table->foreign('audit_id', 'puec_audit_fk')->references('id')->on('user_email_change_requests')->cascadeOnDelete();

                $table->unique('user_id', 'puec_user_uq');
                $table->unique('new_email', 'puec_email_uq');
                $table->unique('audit_id', 'puec_audit_uq');
                $table->index('expires_at', 'puec_expires_idx');
            });
        }

        $this->ensurePostCommentReplyColumns();

        $this->addSettingsIfMissing('compliance', $this->getAccountDeletionSettings());
        $this->addSettingsIfMissing('compliance', $this->getEmailChangeSettings());
        $this->addSettingsIfMissing('site', $this->getExploreSettings());

        $this->ensureV112Permissions();
        Artisan::call('permission:cache-reset');
    }

    public function down(): void
    {
        $this->deleteSettings('compliance', array_keys($this->getAccountDeletionSettings()));
        $this->deleteSettings('compliance', array_keys($this->getEmailChangeSettings()));
        $this->deleteSettings('site', array_keys($this->getExploreSettings()));

        $this->deleteV112Permissions();
        Artisan::call('permission:cache-reset');

        $this->dropPostCommentReplyColumns();

        Schema::dropIfExists('pending_user_email_changes');
        Schema::dropIfExists('user_email_change_requests');
        Schema::dropIfExists('user_deletion_requests');
    }

    protected function ensurePostCommentReplyColumns(): void
    {
        if (! $this->tableExists('post_comments')) {
            return;
        }

        if (! $this->hasColumn('post_comments', 'parent_id')) {
            Schema::table('post_comments', function (Blueprint $table) {
                $table->unsignedBigInteger('parent_id')->nullable()->after('post_id');
            });
        }

        if (! $this->hasColumn('post_comments', 'reply_to_id')) {
            Schema::table('post_comments', function (Blueprint $table) {
                $table->unsignedBigInteger('reply_to_id')->nullable()->after('parent_id');
            });
        }

        $this->ensureIndexOnColumns('post_comments', ['post_id', 'parent_id', 'created_at'], 'pc_thread_idx');
        $this->ensureIndexOnColumns('post_comments', ['reply_to_id'], 'pc_reply_to_idx');

        if (! $this->hasForeignKey('post_comments', 'pc_parent_fk')) {
            Schema::table('post_comments', function (Blueprint $table) {
                $table->foreign('parent_id', 'pc_parent_fk')->references('id')->on('post_comments')->cascadeOnDelete();
            });
        }

        if (! $this->hasForeignKey('post_comments', 'pc_reply_to_fk')) {
            Schema::table('post_comments', function (Blueprint $table) {
                $table->foreign('reply_to_id', 'pc_reply_to_fk')->references('id')->on('post_comments')->nullOnDelete();
            });
        }
    }

    protected function dropPostCommentReplyColumns(): void
    {
        if (! $this->tableExists('post_comments')) {
            return;
        }

        if ($this->hasColumn('post_comments', 'reply_to_id')) {
            if ($this->hasForeignKey('post_comments', 'pc_reply_to_fk')) {
                Schema::table('post_comments', function (Blueprint $table) {
                    $table->dropForeign('pc_reply_to_fk');
                });
            }

            if ($this->hasIndex('post_comments', 'pc_reply_to_idx')) {
                Schema::table('post_comments', function (Blueprint $table) {
                    $table->dropIndex('pc_reply_to_idx');
                });
            }

            Schema::table('post_comments', function (Blueprint $table) {
                $table->dropColumn('reply_to_id');
            });
        }

        if ($this->hasColumn('post_comments', 'parent_id')) {
            if ($this->hasForeignKey('post_comments', 'pc_parent_fk')) {
                Schema::table('post_comments', function (Blueprint $table) {
                    $table->dropForeign('pc_parent_fk');
                });
            }

            if ($this->hasIndex('post_comments', 'pc_thread_idx')) {
                Schema::table('post_comments', function (Blueprint $table) {
                    $table->dropIndex('pc_thread_idx');
                });
            }

            Schema::table('post_comments', function (Blueprint $table) {
                $table->dropColumn('parent_id');
            });
        }
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

    protected function hasIndex(string $table, string $index): bool
    {
        return DB::table('information_schema.statistics')
            ->whereRaw('table_schema = DATABASE()')
            ->where('table_name', $table)
            ->where('index_name', $index)
            ->exists();
    }

    protected function hasForeignKey(string $table, string $foreignKey): bool
    {
        return DB::table('information_schema.table_constraints')
            ->whereRaw('constraint_schema = DATABASE()')
            ->where('table_name', $table)
            ->where('constraint_name', $foreignKey)
            ->where('constraint_type', 'FOREIGN KEY')
            ->exists();
    }

    protected function addSettingsIfMissing(string $group, array $settings): void
    {
        foreach ($settings as $setting => $value) {
            $key = $group.'.'.$setting;

            if (! $this->migrator->exists($key)) {
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

    protected function ensureIndexOnColumns(string $table, array $columns, string $index): void
    {
        if ($this->hasIndexOnColumns($table, $columns)) {
            return;
        }

        Schema::table($table, function (Blueprint $table) use ($columns, $index) {
            $table->index($columns, $index);
        });
    }

    protected function hasIndexOnColumns(string $table, array $columns): bool
    {
        $indexes = DB::table('information_schema.statistics')
            ->select(['index_name', 'column_name', 'seq_in_index'])
            ->whereRaw('table_schema = DATABASE()')
            ->where('table_name', $table)
            ->orderBy('index_name')
            ->orderBy('seq_in_index')
            ->get()
            ->map(fn ($row) => array_change_key_case((array) $row, CASE_LOWER))
            ->groupBy('index_name');

        foreach ($indexes as $indexColumns) {
            if ($indexColumns->pluck('column_name')->all() === $columns) {
                return true;
            }
        }

        return false;
    }

    protected function getAccountDeletionSettings(): array
    {
        return [
            'account_deletion_enabled' => false,
            'account_deletion_mode' => 'manual_review_with_cooldown',
            'account_deletion_cooldown_days' => 14,
            'account_deletion_allow_user_cancel' => true,
            'account_deletion_enforce_wallet_blocker' => true,
            'account_deletion_enforce_subscription_blocker' => true,
            'account_deletion_enforce_moderation_blocker' => true,
        ];
    }

    protected function getEmailChangeSettings(): array
    {
        return [
            'allow_user_email_changes' => true,
        ];
    }

    protected function getExploreSettings(): array
    {
        return [
            'explore_follow_button_enabled' => true,
        ];
    }

    protected function getUserDeletionRequestResourcePermissions(): array
    {
        return [
            'ViewAny:UserDeletionRequest',
            'View:UserDeletionRequest',
            'Create:UserDeletionRequest',
            'Update:UserDeletionRequest',
            'Delete:UserDeletionRequest',
        ];
    }

    protected function getV112Permissions(): array
    {
        return $this->getUserDeletionRequestResourcePermissions();
    }

    protected function getV112ReadOnlyPermissions(): array
    {
        return [
            'ViewAny:UserDeletionRequest',
            'View:UserDeletionRequest',
        ];
    }

    protected function ensureV112Permissions(): void
    {
        if (! $this->tableExists('permissions')) {
            return;
        }

        foreach ($this->getV112Permissions() as $permission) {
            DB::table('permissions')->updateOrInsert(
                [
                    'name' => $permission,
                    'guard_name' => 'web',
                ],
                [
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }

        $this->grantV112PermissionsToBuiltInRoles();
    }

    protected function grantV112PermissionsToBuiltInRoles(): void
    {
        if (! $this->tableExists('roles') || ! $this->tableExists('role_has_permissions')) {
            return;
        }

        $adminRoleIds = $this->getBuiltInRoleIds([1], ['admin']);
        $demoRoleIds = $this->getBuiltInRoleIds([3], ['demo']);

        $adminPermissionIds = DB::table('permissions')
            ->where('guard_name', 'web')
            ->whereIn('name', $this->getV112Permissions())
            ->pluck('id');

        $demoPermissionIds = DB::table('permissions')
            ->where('guard_name', 'web')
            ->whereIn('name', $this->getV112ReadOnlyPermissions())
            ->pluck('id');

        $this->grantPermissionsToRoles($adminRoleIds, $adminPermissionIds);
        $this->grantPermissionsToRoles($demoRoleIds, $demoPermissionIds);
    }

    protected function getBuiltInRoleIds(array $ids, array $names)
    {
        if (! $this->tableExists('roles')) {
            return collect();
        }

        return DB::table('roles')
            ->where('guard_name', 'web')
            ->where(function ($query) use ($ids, $names) {
                $query->whereIn('id', $ids)
                    ->orWhereIn('name', $names);
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

    protected function deleteV112Permissions(): void
    {
        if (! $this->tableExists('permissions')) {
            return;
        }

        $permissionIds = DB::table('permissions')
            ->where('guard_name', 'web')
            ->whereIn('name', $this->getV112Permissions())
            ->pluck('id');

        if ($this->tableExists('role_has_permissions')) {
            DB::table('role_has_permissions')
                ->whereIn('permission_id', $permissionIds)
                ->delete();
        }

        DB::table('permissions')
            ->whereIn('id', $permissionIds)
            ->delete();
    }
}
