<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\LaravelSettings\Migrations\SettingsMigration;

class V1100 extends SettingsMigration
{
    public function up(): void
    {
        if (! $this->tableExists('reels')) {
            Schema::create('reels', function (Blueprint $table) {
                $table->bigIncrements('id');

                $table->foreignId('user_id')
                    ->constrained()
                    ->onDelete('cascade');

                $table->longText('caption')->nullable();
                $table->boolean('is_public')->default(false)->index();
                $table->longText('overlay')->nullable();

                $table->foreignId('sound_id')
                    ->nullable()
                    ->constrained('sounds')
                    ->nullOnDelete();

                $table->timestamps();
            });
        }

        if (! $this->tableExists('reel_views')) {
            Schema::create('reel_views', function (Blueprint $table) {
                $table->bigIncrements('id');

                $table->foreignId('reel_id')
                    ->constrained('reels')
                    ->onDelete('cascade');

                $table->foreignId('user_id')
                    ->constrained()
                    ->onDelete('cascade');

                $table->timestamp('seen_at')->nullable();
                $table->timestamps();

                $table->unique(['reel_id', 'user_id']);
            });
        }

        if (! $this->tableExists('reel_comments')) {
            Schema::create('reel_comments', function (Blueprint $table) {
                $table->bigIncrements('id');

                $table->foreignId('reel_id')
                    ->constrained('reels')
                    ->onDelete('cascade');

                $table->foreignId('user_id')
                    ->constrained()
                    ->onDelete('cascade');

                $table->unsignedBigInteger('parent_id')->nullable();
                $table->text('message');
                $table->timestamps();

                $table->foreign('parent_id')
                    ->references('id')
                    ->on('reel_comments')
                    ->onDelete('cascade');
            });
        }

        $needsAttachmentBlurredFilename = ! $this->hasColumn('attachments', 'blurred_filename');
        $needsAttachmentReelId = ! $this->hasColumn('attachments', 'reel_id');

        if ($needsAttachmentBlurredFilename || $needsAttachmentReelId) {
            Schema::table('attachments', function (Blueprint $table) use ($needsAttachmentBlurredFilename, $needsAttachmentReelId) {
                if ($needsAttachmentBlurredFilename) {
                    $table->string('blurred_filename')
                        ->nullable()
                        ->after('has_blurred_preview');
                }

                if ($needsAttachmentReelId) {
                    $table->unsignedBigInteger('reel_id')
                        ->nullable()
                        ->after('story_id');

                    $table->foreign('reel_id')
                        ->references('id')
                        ->on('reels')
                        ->onDelete('cascade');

                    $table->index('reel_id');
                }
            });
        }

        $needsReactionReelId = ! $this->hasColumn('reactions', 'reel_id');
        $needsReactionReelCommentId = ! $this->hasColumn('reactions', 'reel_comment_id');

        if ($needsReactionReelId || $needsReactionReelCommentId) {
            Schema::table('reactions', function (Blueprint $table) use ($needsReactionReelId, $needsReactionReelCommentId) {
                if ($needsReactionReelId) {
                    $table->unsignedBigInteger('reel_id')
                        ->nullable()
                        ->after('post_comment_id');

                    $table->foreign('reel_id')
                        ->references('id')
                        ->on('reels')
                        ->onDelete('cascade');

                    $table->unique(['user_id', 'reel_id']);
                }

                if ($needsReactionReelCommentId) {
                    $table->unsignedBigInteger('reel_comment_id')
                        ->nullable()
                        ->after('reel_id');

                    $table->foreign('reel_comment_id')
                        ->references('id')
                        ->on('reel_comments')
                        ->onDelete('cascade');

                    $table->unique(['user_id', 'reel_comment_id']);
                }
            });
        }

        $this->makeUserBookmarkPostNullable();

        if (! $this->hasColumn('user_bookmarks', 'reel_id')) {
            Schema::table('user_bookmarks', function (Blueprint $table) {
                $table->unsignedBigInteger('reel_id')
                    ->nullable()
                    ->after('post_id');

                $table->foreign('reel_id')
                    ->references('id')
                    ->on('reels')
                    ->onDelete('cascade');

                $table->unique(['user_id', 'reel_id']);
            });
        }

        $needsUserReportReelId = ! $this->hasColumn('user_reports', 'reel_id');
        $needsUserReportReelCommentId = ! $this->hasColumn('user_reports', 'reel_comment_id');

        if ($this->tableExists('user_reports') && ($needsUserReportReelId || $needsUserReportReelCommentId)) {
            Schema::table('user_reports', function (Blueprint $table) use ($needsUserReportReelId, $needsUserReportReelCommentId) {
                if ($needsUserReportReelId) {
                    $table->unsignedBigInteger('reel_id')
                        ->nullable()
                        ->after('story_id');

                    $table->foreign('reel_id')
                        ->references('id')
                        ->on('reels')
                        ->onDelete('cascade');

                    $table->index('reel_id');
                }

                if ($needsUserReportReelCommentId) {
                    $table->unsignedBigInteger('reel_comment_id')
                        ->nullable()
                        ->after('reel_id');

                    $table->foreign('reel_comment_id')
                        ->references('id')
                        ->on('reel_comments')
                        ->onDelete('cascade');

                    $table->index('reel_comment_id');
                }
            });
        }

        if ($this->tableExists('contact_messages') && ! $this->hasColumn('contact_messages', 'replied_at')) {
            Schema::table('contact_messages', function (Blueprint $table) {
                $table->unsignedBigInteger('replied_by')
                    ->nullable()
                    ->after('message');

                $table->timestamp('replied_at')
                    ->nullable()
                    ->after('replied_by');

                $table->foreign('replied_by')
                    ->references('id')
                    ->on('users')
                    ->nullOnDelete();

                $table->index('replied_at');
                $table->index('replied_by');
            });
        }

        if (! $this->hasColumn('posts', 'notify_followers')) {
            Schema::table('posts', function (Blueprint $table) {
                $table->boolean('notify_followers')
                    ->default(false)
                    ->after('is_pinned');

                $table->timestamp('notifications_sent_at')
                    ->nullable()
                    ->after('notify_followers');

                $table->index(
                    ['status', 'notify_followers', 'notifications_sent_at', 'release_date'],
                    'posts_notification_release_index'
                );
            });
        }

        if (! $this->tableExists('release_forms')) {
            Schema::create('release_forms', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('user_id');
                $table->string('title')->nullable();
                $table->text('notes')->nullable();
                $table->text('files');
                $table->string('status')->default('pending');
                $table->text('rejection_reason')->nullable();
                $table->unsignedBigInteger('reviewed_by')->nullable();
                $table->timestamp('reviewed_at')->nullable();
                $table->timestamps();

                $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
                $table->foreign('reviewed_by')->references('id')->on('users')->nullOnDelete();

                $table->index(['user_id', 'status']);
                $table->index('reviewed_by');
            });
        }

        $this->addSettingsIfMissing('reels', $this->getReelsSettings());
        $this->addSettingsIfMissing('security', $this->getReelsRateLimitSettings());
        $this->addSettingsIfMissing('compliance', $this->getReleaseFormSettings());

        $this->ensureV11Permissions();
        Artisan::call('permission:cache-reset');
    }

    public function down(): void
    {
        Schema::dropIfExists('release_forms');

        if ($this->tableExists('contact_messages') && $this->hasColumn('contact_messages', 'replied_at')) {
            Schema::table('contact_messages', function (Blueprint $table) {
                $table->dropForeign(['replied_by']);
                $table->dropIndex(['replied_at']);
                $table->dropIndex(['replied_by']);
                $table->dropColumn(['replied_by', 'replied_at']);
            });
        }

        if ($this->hasColumn('posts', 'notify_followers')) {
            Schema::table('posts', function (Blueprint $table) {
                $table->dropIndex('posts_notification_release_index');
                $table->dropColumn(['notify_followers', 'notifications_sent_at']);
            });
        }

        if ($this->tableExists('user_reports') && $this->hasColumn('user_reports', 'reel_comment_id')) {
            Schema::table('user_reports', function (Blueprint $table) {
                $table->dropForeign(['reel_comment_id']);
                $table->dropForeign(['reel_id']);
                $table->dropIndex(['reel_comment_id']);
                $table->dropIndex(['reel_id']);
                $table->dropColumn(['reel_comment_id', 'reel_id']);
            });
        }

        if ($this->hasColumn('user_bookmarks', 'reel_id')) {
            Schema::table('user_bookmarks', function (Blueprint $table) {
                $table->dropUnique(['user_id', 'reel_id']);
                $table->dropForeign(['reel_id']);
                $table->dropColumn('reel_id');
            });
        }

        $this->restoreUserBookmarkPostRequired();

        if ($this->hasColumn('reactions', 'reel_comment_id')) {
            Schema::table('reactions', function (Blueprint $table) {
                $table->dropUnique(['user_id', 'reel_comment_id']);
                $table->dropUnique(['user_id', 'reel_id']);
                $table->dropForeign(['reel_comment_id']);
                $table->dropForeign(['reel_id']);
                $table->dropColumn(['reel_comment_id', 'reel_id']);
            });
        }

        $hasAttachmentReelId = $this->hasColumn('attachments', 'reel_id');
        $hasAttachmentBlurredFilename = $this->hasColumn('attachments', 'blurred_filename');

        if ($hasAttachmentReelId || $hasAttachmentBlurredFilename) {
            Schema::table('attachments', function (Blueprint $table) use ($hasAttachmentReelId, $hasAttachmentBlurredFilename) {
                if ($hasAttachmentReelId) {
                    $table->dropForeign(['reel_id']);
                    $table->dropIndex(['reel_id']);
                    $table->dropColumn('reel_id');
                }

                if ($hasAttachmentBlurredFilename) {
                    $table->dropColumn('blurred_filename');
                }
            });
        }

        Schema::dropIfExists('reel_comments');
        Schema::dropIfExists('reel_views');
        Schema::dropIfExists('reels');

        $this->deleteSettings('reels', array_keys($this->getReelsSettings()));
        $this->deleteSettings('security', array_keys($this->getReelsRateLimitSettings()));
        $this->deleteSettings('compliance', array_keys($this->getReleaseFormSettings()));
        $this->deleteV11Permissions();
        Artisan::call('permission:cache-reset');
    }

    protected function makeUserBookmarkPostNullable(): void
    {
        if (! $this->hasColumn('user_bookmarks', 'post_id')) {
            return;
        }

        DB::statement('ALTER TABLE user_bookmarks MODIFY post_id BIGINT UNSIGNED NULL');
    }

    protected function restoreUserBookmarkPostRequired(): void
    {
        if (! $this->hasColumn('user_bookmarks', 'post_id')) {
            return;
        }

        DB::table('user_bookmarks')->whereNull('post_id')->delete();
        DB::statement('ALTER TABLE user_bookmarks MODIFY post_id BIGINT UNSIGNED NOT NULL');
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

    protected function getReelsSettings(): array
    {
        return [
            'reels_enabled' => true,
            'allow_public_reels' => true,
            'max_video_length_seconds' => 90,
            'allow_sounds' => true,
            'allow_progress_scrubbing' => true,
            'feed_widget_enabled' => true,
            'feed_widget_placement_mode' => 'repeat',
            'feed_widget_first_after_posts' => 0,
            'feed_widget_repeat_every_posts' => 3,
            'feed_widget_cards_per_widget' => 12,
        ];
    }

    protected function getReelsRateLimitSettings(): array
    {
        return [
            'rate_limit_reels_store_enabled' => false,
            'rate_limit_reels_store_max_attempts' => 10,
            'rate_limit_reels_store_decay_seconds' => 60,
            'rate_limit_reels_comments_add_enabled' => false,
            'rate_limit_reels_comments_add_max_attempts' => 20,
            'rate_limit_reels_comments_add_decay_seconds' => 60,
        ];
    }

    protected function getReleaseFormSettings(): array
    {
        return [
            'enable_release_forms' => false,
            'release_forms_verified_users_only' => false,
            'release_forms_custom_message_box' => null,
        ];
    }

    protected function getReelResourcePermissions(): array
    {
        return [
            'ViewAny:Reel',
            'View:Reel',
            'Create:Reel',
            'Update:Reel',
            'Delete:Reel',
        ];
    }

    protected function getReleaseFormResourcePermissions(): array
    {
        return [
            'ViewAny:ReleaseForm',
            'View:ReleaseForm',
            'Create:ReleaseForm',
            'Update:ReleaseForm',
            'Delete:ReleaseForm',
        ];
    }

    protected function getSettingsPagePermissions(): array
    {
        return [
            'View:ManageReelsSettings',
        ];
    }

    protected function getV11Permissions(): array
    {
        return array_values(array_unique(array_merge(
            $this->getSettingsPagePermissions(),
            $this->getReelResourcePermissions(),
            $this->getReleaseFormResourcePermissions(),
        )));
    }

    protected function getV11ReadOnlyPermissions(): array
    {
        return [
            'View:ManageReelsSettings',
            'ViewAny:Reel',
            'View:Reel',
            'ViewAny:ReleaseForm',
            'View:ReleaseForm',
        ];
    }

    protected function ensureV11Permissions(): void
    {
        if (! $this->tableExists('permissions')) {
            return;
        }

        foreach ($this->getV11Permissions() as $permission) {
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

        $this->grantV11PermissionsToBuiltInRoles();
    }

    protected function grantV11PermissionsToBuiltInRoles(): void
    {
        if (! $this->tableExists('roles') || ! $this->tableExists('role_has_permissions')) {
            return;
        }

        $adminRoleIds = $this->getBuiltInRoleIds([1], ['admin']);
        $demoRoleIds = $this->getBuiltInRoleIds([3], ['demo']);

        $adminPermissionIds = DB::table('permissions')
            ->where('guard_name', 'web')
            ->whereIn('name', $this->getV11Permissions())
            ->pluck('id');

        $demoPermissionIds = DB::table('permissions')
            ->where('guard_name', 'web')
            ->whereIn('name', $this->getV11ReadOnlyPermissions())
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

    protected function deleteV11Permissions(): void
    {
        if (! $this->tableExists('permissions')) {
            return;
        }

        $permissionIds = DB::table('permissions')
            ->where('guard_name', 'web')
            ->whereIn('name', $this->getV11Permissions())
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
