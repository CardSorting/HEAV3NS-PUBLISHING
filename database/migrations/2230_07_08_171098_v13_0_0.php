<?php

use App\Model\CreatorEnrollment;
use App\Model\Post;
use App\Model\UserVerify;
use App\Services\ManualPayments\ManualPaymentDefaults;
use App\Support\MigrationSchema;
use Database\Seeders\BanUserPermissionsSeeder;
use Database\Seeders\ContentReviewPermissionsSeeder;
use Database\Seeders\SystemHealthPermissionsSeeder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Spatie\LaravelSettings\Migrations\SettingsMigration;
use Spatie\LaravelSettings\SettingsRepositories\SettingsRepository;
use Spatie\LaravelSettings\Support\SettingsCacheFactory;

return new class extends SettingsMigration {
    public $withinTransaction = false;

    public function up(): void
    {
        $this->addFeedAdSettings();
        $this->addFontSourceSetting();
        $this->upgradeReelReplies();
        $this->upgradeAccountSuspensions();
        $this->upgradeGifPicker();
        $this->grantMaintenancePermission();
        $this->addFeedAutoplaySetting();
        $this->addFooterCopyrightSetting();
        $this->upgradeMessengerTips();
        $this->upgradePublicPageLinks();
        $this->upgradeMessagePins();
        $this->upgradeCreatorAccess();
        $this->addPixDepositMethod();
        $this->upgradePublicationReview();
        $this->upgradeFileAttachments();
        $this->clearCaches();
    }

    public function down(): void
    {
        throw new RuntimeException('The v13.0.0 upgrade contains creator decisions, publication reviews, messages, and account suspension history. Use a forward repair or restore a reviewed backup with its matching code.');
    }

    // 2230_07_08_171098_v12_1_0.php: saved-value-preserving defaults.
    private function addFeedAdSettings(): void
    {
        $this->addMissingSettings([
            'site.ads_feed_enabled' => false,
            'site.ads_feed_code' => '',
            'site.ads_feed_frequency' => 6,
            'site.ads_feed_visibility' => 'mobile',
        ]);
    }

    // 2230_07_08_171099_add_font_source_setting.php: saved-value-preserving defaults.
    private function addFontSourceSetting(): void
    {
        $this->addMissingSettings([
            'site.font_source' => 'google',
        ]);
    }

    // 2230_07_08_171102_add_footer_copyright_setting.php: saved-value-preserving defaults.
    private function addFooterCopyrightSetting(): void
    {
        $this->addMissingSettings([
            'site.footer_copyright_text' => null,
        ]);
    }

    // 2230_07_08_171102_add_feed_video_autoplay.php: saved-value-preserving defaults.
    private function addFeedAutoplaySetting(): void
    {
        $this->addMissingSettings([
            'feed.autoplay_videos' => false,
        ]);
    }

    // 2230_07_08_171099_v12_1_1.php
    private function upgradeReelReplies(): void
    {
        foreach ([
            ['reel_comments', 'reply_to_id', 'reel_comments', 'rc_reply_to_idx', 'rc_reply_to_fk', 'set null'],
            ['notifications', 'reel_id', 'reels', 'notifications_reel_idx', 'notifications_reel_fk', 'cascade'],
            ['notifications', 'reel_comment_id', 'reel_comments', 'notifications_reel_comment_idx', 'notifications_reel_comment_fk', 'cascade'],
        ] as [$table, $column, $target, $index, $constraint, $onDelete]) {
            if (!MigrationSchema::hasColumn($table, $column)) {
                Schema::table($table, fn (Blueprint $blueprint) => $blueprint->unsignedBigInteger($column)->nullable());
            }
            if (!$this->hasIndex($table, $index)) {
                Schema::table($table, fn (Blueprint $blueprint) => $blueprint->index($column, $index));
            }
            if (!$this->hasForeignKey($table, $column, $target)) {
                $constraint = DB::connection()->getTablePrefix().$constraint;
                Schema::table($table, fn (Blueprint $blueprint) => $blueprint->foreign($column, $constraint)->references('id')->on($target)->onDelete($onDelete));
            }
        }

        if (!$this->hasIndex('reel_comments', 'rc_thread_idx')) {
            Schema::table('reel_comments', fn (Blueprint $table) => $table->index(['reel_id', 'parent_id', 'created_at', 'id'], 'rc_thread_idx'));
        }
    }

    // 2230_07_08_171100_add_account_suspensions.php
    private function upgradeAccountSuspensions(): void
    {
        if (!MigrationSchema::hasTable('user_bans')) {
            Schema::create('user_bans', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('user_id')->index();
                $table->unsignedBigInteger('banned_by')->nullable();
                $table->text('reason');
                $table->string('policy_reference', 500)->nullable();
                $table->timestamp('expires_at')->nullable();
                $table->timestamp('lifted_at')->nullable();
                $table->unsignedBigInteger('lifted_by')->nullable();
                $table->timestamps();
                $table->index(['user_id', 'lifted_at', 'expires_at']);
            });
        }

        if (!MigrationSchema::hasColumn('users', 'last_ban_id')) {
            Schema::table('users', fn (Blueprint $table) => $table->unsignedBigInteger('last_ban_id')->nullable()->index());
        }

        if (!MigrationSchema::hasTable('subscription_cancellation_requests')) {
            Schema::create('subscription_cancellation_requests', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('user_ban_id')->index();
                $table->unsignedBigInteger('subscription_id')->nullable()->unique();
                $table->unsignedBigInteger('transaction_id')->nullable()->unique();
                $table->string('status', 30)->default('pending');
                $table->unsignedInteger('attempts')->default(0);
                $table->timestamp('attempted_at')->nullable();
                $table->timestamp('next_attempt_at')->nullable();
                $table->timestamp('completed_at')->nullable();
                $table->unsignedBigInteger('confirmed_by')->nullable();
                $table->string('failure_code', 60)->nullable();
                $table->timestamps();
                $table->index(['status', 'next_attempt_at']);
            });
        }

        // Earlier unreleased builds may already have added this metadata.
        foreach ([
            'withdrawals' => ['suspension_review_required_at', 'suspension_reviewed_at', 'suspension_reviewed_by'],
            'streams' => ['suspension_stop_requested_at', 'suspension_stopped_at'],
        ] as $table => $columns) {
            foreach ($columns as $column) {
                if (MigrationSchema::hasColumn($table, $column)) {
                    Schema::table($table, fn (Blueprint $blueprint) => $blueprint->dropColumn($column));
                }
            }
        }

        app(BanUserPermissionsSeeder::class)->run();
    }

    // 2230_07_08_171100_add_gif_picker.php
    private function upgradeGifPicker(): void
    {
        foreach (['user_messages', 'post_comments', 'reel_comments'] as $table) {
            if (!MigrationSchema::hasColumn($table, 'gif')) {
                Schema::table($table, fn (Blueprint $blueprint) => $blueprint->longText('gif')->nullable());
            }
        }

        $this->addMissingSettings([
            'media.gif_picker_enabled' => false,
            'media.gif_provider' => 'klipy',
            'media.gif_klipy_key' => null,
            'media.gif_giphy_messenger_key' => null,
            'media.gif_giphy_post_comments_key' => null,
            'media.gif_giphy_reel_comments_key' => null,
            'media.gif_messenger_enabled' => true,
            'media.gif_post_comments_enabled' => true,
            'media.gif_reel_comments_enabled' => true,
        ]);
    }

    // 2230_07_08_171101_add_maintenance_permission.php
    private function grantMaintenancePermission(): void
    {
        app(SystemHealthPermissionsSeeder::class)->run();
    }

    // 2230_07_08_171102_add_messenger_tip_messages.php
    private function upgradeMessengerTips(): void
    {
        if (!MigrationSchema::hasColumn('user_messages', 'tip_transaction_id')) {
            Schema::table('user_messages', fn (Blueprint $table) => $table->unsignedBigInteger('tip_transaction_id')->nullable());
        }
        $prefix = DB::connection()->getTablePrefix();
        $indexName = $prefix.'um_tip_transaction_unique';
        if (!$this->hasIndex('user_messages', $indexName, 'unique')) {
            Schema::table('user_messages', fn (Blueprint $table) => $table->unique('tip_transaction_id', $indexName));
        }
        if (!$this->hasForeignKey('user_messages', 'tip_transaction_id', 'transactions')) {
            Schema::table('user_messages', fn (Blueprint $table) => $table->foreign('tip_transaction_id', $prefix.'um_tip_transaction_foreign')
                ->references('id')->on('transactions')->cascadeOnDelete());
        }
    }

    // 2230_07_08_171102_add_public_page_external_links.php
    private function upgradePublicPageLinks(): void
    {
        if (!MigrationSchema::hasColumn('public_pages', 'external_url')) {
            Schema::table('public_pages', fn (Blueprint $table) => $table->text('external_url')->nullable());
        }
        if (!MigrationSchema::hasColumn('public_pages', 'open_in_new_tab')) {
            Schema::table('public_pages', fn (Blueprint $table) => $table->boolean('open_in_new_tab')->default(false));
        }
    }

    // 2230_07_08_171103_add_shared_message_pins.php
    private function upgradeMessagePins(): void
    {
        if (!MigrationSchema::hasColumn('user_messages', 'pinned_at')) {
            Schema::table('user_messages', fn (Blueprint $table) => $table->timestamp('pinned_at')->nullable());
        }
        if (!MigrationSchema::hasColumn('user_messages', 'pinned_by')) {
            Schema::table('user_messages', fn (Blueprint $table) => $table->unsignedBigInteger('pinned_by')->nullable());
        }
        $indexName = DB::connection()->getTablePrefix().'um_conversation_pins_idx';
        if (!$this->hasIndex('user_messages', $indexName)) {
            Schema::table('user_messages', fn (Blueprint $table) => $table->index(['sender_id', 'receiver_id', 'pinned_at'], $indexName));
        }
    }

    // 2230_07_08_171104_add_pix_deposit_method.php
    private function addPixDepositMethod(): void
    {
        ManualPaymentDefaults::seedPixDepositMethod();
    }

    /**
     * Creator access, application details, and legacy publishing-ID cutover.
     * Consolidates 171104_add_creator_enrollments, 171105_separate_creator_publishing_identity,
     * 171106_enroll_existing_identity_creators, 171107_simplify_creator_access_modes,
     * 171108_add_creator_application_details, and 171113_add_closed_creator_access_mode.
     * 171110/171112 creation-link controls and 171111 application acceptance are retired:
     * clean upgrades never create those settings; prerelease choices are consumed below.
     */
    private function upgradeCreatorAccess(): void
    {
        if (!MigrationSchema::hasTable('creator_enrollments')) {
            Schema::create('creator_enrollments', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('user_id')->unique()->constrained('users', 'id', DB::connection()->getTablePrefix().'creator_enrollments_user_id_foreign')->cascadeOnDelete();
                $table->string('status', 16)->default('pending')->index();
                $table->timestamp('applied_at')->nullable();
                $table->timestamp('agreement_accepted_at')->nullable();
                $table->timestamp('reviewed_at')->nullable();
                $table->foreignId('reviewed_by')->nullable()->constrained('users', 'id', DB::connection()->getTablePrefix().'creator_enrollments_reviewed_by_foreign')->nullOnDelete();
                $table->text('rejection_reason')->nullable();
                $table->unsignedInteger('application_version')->default(0);
                $table->text('application_message')->nullable();
                $table->timestamps();
            });
        } elseif (!MigrationSchema::hasColumn('creator_enrollments', 'application_message')) {
            // Earlier development installations already have enrollment decisions.
            Schema::table('creator_enrollments', fn (Blueprint $table) => $table->text('application_message')->nullable());
        }

        DB::transaction(function (): void {
            $repository = app(SettingsRepository::class);
            $hasMode = $this->migrator->exists('profiles.creator_access_mode');
            $hasRequirement = $this->migrator->exists('profiles.require_id_before_publishing');
            $legacyIdentity = $repository->checkIfPropertyExists('site', 'enforce_user_identity_checks')
                && (bool) $repository->getPropertyPayload('site', 'enforce_user_identity_checks');
            $installed = !$hasMode && Storage::disk('local')->exists('installed');
            $mode = $hasMode ? $repository->getPropertyPayload('profiles', 'creator_access_mode')
                : ($installed && $legacyIdentity ? 'instant' : 'everyone');
            $identityCutover = $mode === 'id_verification'
                || (!$hasRequirement && $legacyIdentity && (($hasMode && $mode === 'everyone') || $installed));

            // Defaults, import, final mode, and checkpoint commit together. A cache retry
            // must never import users verified later or override a subsequent admin choice.
            $this->addMissingSettings([
                'profiles.creator_access_mode' => $mode,
                'profiles.require_id_before_publishing' => ($hasMode || $installed) && $legacyIdentity,
                'profiles.show_locked_creation_pages' => true,
                'profiles.creator_application_instructions' => '',
                'profiles.require_creator_application_message' => false,
            ]);
            if ($identityCutover) {
                $this->migrator->update('profiles.creator_access_mode', fn () => 'instant');
                $this->migrator->update('profiles.require_id_before_publishing', fn () => true);
            }
            if (!$this->migrator->exists('profiles.id_creator_enrollment_backfill_completed')) {
                if ($identityCutover) {
                    DB::table('users')->whereNotNull('birthdate')->whereNotNull('email_verified_at')
                        ->whereNotExists(fn ($query) => $query->selectRaw('1')->from('creator_enrollments')
                            ->whereColumn('creator_enrollments.user_id', 'users.id'))
                        ->where(fn ($query) => $query->select('status')->from('user_verifies')
                            ->whereColumn('user_verifies.user_id', 'users.id')->limit(1), UserVerify::APPROVED_STATUS)
                        ->select('id')->chunkById(500, function ($users): void {
                            $timestamp = now();
                            DB::table('creator_enrollments')->insert($users->map(fn ($user): array => [
                                'user_id' => $user->id,
                                'status' => CreatorEnrollment::APPROVED_STATUS,
                                'application_version' => 0,
                                'created_at' => $timestamp,
                                'updated_at' => $timestamp,
                            ])->all());
                        });
                    // Run before publication-review backfill so held schedules enter its queue.
                    Post::query()->where('status', Post::APPROVED_STATUS)->where('release_date', '>', now())
                        ->whereDoesntHave('user', function ($query): void {
                            $query->whereHas('creatorEnrollment', fn ($enrollment) => $enrollment->where('status', CreatorEnrollment::APPROVED_STATUS))
                                ->whereNotNull('birthdate')->whereNotNull('email_verified_at')
                                ->where(fn ($verification) => $verification->select('status')->from('user_verifies')
                                    ->whereColumn('user_verifies.user_id', 'users.id')->limit(1), UserVerify::APPROVED_STATUS);
                        })->update(['status' => Post::PENDING_STATUS]);
                }
                $this->migrator->add('profiles.id_creator_enrollment_backfill_completed', true);
            }
            if ($this->migrator->exists('profiles.accept_creator_applications')) {
                $mode = $repository->getPropertyPayload('profiles', 'creator_access_mode');
                if ($repository->getPropertyPayload('profiles', 'accept_creator_applications') === false
                    && in_array($mode, ['instant', 'approval'], true)) {
                    $this->migrator->update('profiles.creator_access_mode', fn () => 'closed');
                }
                $this->migrator->delete('profiles.accept_creator_applications');
            }
        });
    }

    /**
     * Publication review: 2230_07_08_171105_add_content_publication_review.php and
     * 2230_07_08_171109_simplify_publication_review_columns.php. Create the final schema directly.
     */
    private function upgradePublicationReview(): void
    {
        if (!MigrationSchema::hasTable('content_reviews')) {
            Schema::create('content_reviews', function (Blueprint $table): void {
                $table->id();
                $table->string('source_type', 16);
                $table->unsignedBigInteger('source_id');
                $table->unsignedBigInteger('owner_user_id');
                $table->string('status', 16)->default('pending');
                $table->char('fingerprint', 64);
                $table->string('mode', 16)->default('manual');
                $table->longText('policy');
                $table->uuid('media_run_id')->nullable()->index();
                $table->uuid('text_run_id')->nullable()->index();
                $table->text('attention_reason')->nullable();
                $table->text('rejection_reason')->nullable();
                $table->text('review_notes')->nullable();
                $table->unsignedBigInteger('reviewed_by')->nullable();
                $table->timestamp('reviewed_at')->nullable();
                $table->string('decision_source', 32)->nullable();
                $table->timestamps();
                $table->index(['status', 'created_at'], 'cr_status_created_idx');
                $table->index(['source_type', 'source_id', 'id'], 'cr_source_current_idx');
                $table->index(['owner_user_id', 'created_at'], 'cr_owner_created_idx');
            });
        }

        foreach (['reels', 'stories'] as $name) {
            // Normalize an already-applied prerelease schema without resetting its decisions.
            if (MigrationSchema::hasColumn($name, 'moderation_status')) {
                if (MigrationSchema::hasColumn($name, 'status')) {
                    throw new RuntimeException('Both status columns exist on '.$name.'. Resolve the conflicting publication states before retrying the upgrade.');
                }
                Schema::table($name, fn (Blueprint $table) => $table->renameColumn('moderation_status', 'status'));
            } elseif (!MigrationSchema::hasColumn($name, 'status')) {
                Schema::table($name, fn (Blueprint $table) => $table->unsignedTinyInteger('status')->default(1));
            }
        }
        if (!MigrationSchema::hasColumn('stories', 'published_at')) {
            Schema::table('stories', fn (Blueprint $table) => $table->dateTime('published_at')->nullable());
        }
        if (!$this->hasIndex('content_reviews', 'cr_source_current_idx')) {
            Schema::table('content_reviews', fn (Blueprint $table) => $table->index(['source_type', 'source_id', 'id'], 'cr_source_current_idx'));
        }
        if ($this->hasIndex('content_reviews', 'cr_source_created_idx')) {
            Schema::table('content_reviews', fn (Blueprint $table) => $table->dropIndex('cr_source_created_idx'));
        }

        DB::table('stories')->whereNull('published_at')->where('status', 1)
            ->whereNotExists(fn ($query) => $query->selectRaw('1')->from('content_reviews')
                ->where('source_type', 'story')->whereColumn('source_id', 'stories.id'))
            ->update(['published_at' => DB::raw('created_at')]);
        $this->backfillPendingPosts();

        // Only existing prerelease schemas need their obsolete parent pointers retired.
        foreach (['posts', 'reels', 'stories'] as $name) {
            if (!MigrationSchema::hasColumn($name, 'content_review_id')) {
                continue;
            }
            $index = DB::connection()->getTablePrefix().$name.'_content_review_idx';
            if ($this->hasIndex($name, $index)) {
                Schema::table($name, fn (Blueprint $table) => $table->dropIndex($index));
            }
            Schema::table($name, fn (Blueprint $table) => $table->dropColumn('content_review_id'));
        }

        $this->addMissingSettings([
            'moderation.publication_review_enabled' => false,
            'moderation.publication_review_areas' => ['posts', 'reels', 'stories'],
            'moderation.publication_review_mode' => 'manual',
        ]);
        (new ContentReviewPermissionsSeeder)->run();
    }

    private function backfillPendingPosts(): void
    {
        DB::table('posts')->where('status', 0)
            ->where(fn ($query) => $query
                ->whereNotExists(fn ($review) => $review->selectRaw('1')->from('content_reviews')
                    ->where('source_type', 'post')->whereColumn('source_id', 'posts.id'))
                ->orWhere(fn ($review) => $review->select('status')->from('content_reviews')
                    ->where('source_type', 'post')->whereColumn('source_id', 'posts.id')->latest('id')->limit(1), 'approved'))
            ->orderBy('id')->chunkById(100, function ($posts): void {
                foreach ($posts as $post) {
                    DB::transaction(function () use ($post): void {
                        $current = DB::table('posts')->where('id', $post->id)->lockForUpdate()->first();
                        if (!$current || (int) $current->status !== 0) {
                            return;
                        }
                        $review = DB::table('content_reviews')->where('source_type', 'post')->where('source_id', $current->id)->latest('id')->lockForUpdate()->first();
                        // Creator cutover can hold a previously approved scheduled post.
                        // Keep that decision and add a pending review; other current decisions stay intact.
                        if ($review && $review->status !== 'approved') {
                            return;
                        }
                        DB::table('content_reviews')->insert([
                            'source_type' => 'post', 'source_id' => $current->id, 'owner_user_id' => $current->user_id,
                            'status' => 'pending', 'fingerprint' => hash('sha256', 'legacy-pending-post:'.$current->id),
                            'mode' => 'manual', 'policy' => json_encode(['legacy_pending' => true], JSON_THROW_ON_ERROR),
                            'created_at' => $current->created_at ?: now(), 'updated_at' => now(),
                        ]);
                    }, 3);
                }
            });
    }

    /**
     * Private files: 2230_07_08_171106_add_private_file_attachments.php,
     * 2230_07_08_171107_remove_file_storage_location_fingerprint.php and
     * 2230_07_08_171108_use_file_attachment_flag.php.
     */
    private function upgradeFileAttachments(): void
    {
        foreach (['original_name', 'mime_type', 'size', 'is_file_attachment'] as $column) {
            if (!MigrationSchema::hasColumn('attachments', $column)) {
                Schema::table('attachments', function (Blueprint $table) use ($column): void {
                    match ($column) {
                        'size' => $table->unsignedBigInteger($column)->nullable(),
                        'is_file_attachment' => $table->boolean($column)->default(false),
                        default => $table->string($column)->nullable(),
                    };
                });
            }
        }
        // Existing prerelease files retain their classification, metadata and recorded paths.
        if (MigrationSchema::hasColumn('attachments', 'storage_scope')) {
            DB::table('attachments')->where('storage_scope', 'private-files')->update(['is_file_attachment' => true]);
            Schema::table('attachments', fn (Blueprint $table) => $table->dropColumn('storage_scope'));
        }
        if (MigrationSchema::hasColumn('attachments', 'storage_location_fingerprint')) {
            Schema::table('attachments', fn (Blueprint $table) => $table->dropColumn('storage_location_fingerprint'));
        }
        $this->addMissingSettings([
            'media.file_attachments_enabled' => false,
            'media.file_attachment_formats' => ['pdf', 'zip'],
        ]);
    }

    private function addMissingSettings(array $defaults): void
    {
        foreach ($defaults as $setting => $default) {
            if (!$this->migrator->exists($setting)) {
                $this->migrator->add($setting, $default);
            }
        }
    }

    private function clearCaches(): void
    {
        // The browser updater needs the cache factory; the settings command is console-only.
        foreach (app(SettingsCacheFactory::class)->all() as $cache) {
            $cache->clear();
        }
        if (Artisan::call('minify:clear', ['--type' => 'all']) !== 0) {
            throw new RuntimeException('The v13.0.0 upgrade was applied, but clearing frontend assets failed. Resolve the cache error and rerun the upgrade.');
        }
    }

    private function hasIndex(string $table, string $index, ?string $type = null): bool
    {
        $connection = DB::connection();
        if (!in_array($connection->getDriverName(), ['mysql', 'mariadb'], true)) {
            return Schema::hasIndex($table, $index, $type);
        }

        return DB::selectOne(
            'SELECT 1 FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = ? AND index_name = ?'.($type === 'unique' ? ' AND non_unique = 0' : '').' LIMIT 1',
            [$connection->getTablePrefix().$table, $index]
        ) !== null;
    }

    private function hasForeignKey(string $table, string $column, string $target): bool
    {
        $connection = DB::connection();
        if (in_array($connection->getDriverName(), ['mysql', 'mariadb'], true)) {
            return DB::selectOne(
                'SELECT 1 FROM information_schema.key_column_usage WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ? AND referenced_table_name = ? LIMIT 1',
                [$connection->getTablePrefix().$table, $column, $connection->getTablePrefix().$target]
            ) !== null;
        }

        foreach (Schema::getForeignKeys($table) as $foreignKey) {
            if ($foreignKey['columns'] === [$column] && $foreignKey['foreign_table'] === $connection->getTablePrefix().$target) {
                return true;
            }
        }

        return false;
    }
};
