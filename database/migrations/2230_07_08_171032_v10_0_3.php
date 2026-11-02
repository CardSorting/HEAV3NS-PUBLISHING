<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\LaravelSettings\Migrations\SettingsMigration;

class V1003 extends SettingsMigration
{
    public function up(): void
    {
        /**
         * hashtags
         */
        Schema::create('hashtags', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('tag', 64)->unique(); // store normalized lowercase in code
            $table->timestamps();
        });

        /**
         * hashtag_links
         * - Single table for hashtags attached to posts OR post comments
         * - post_id / post_comment_id are nullable; you enforce population in code
         */
        Schema::create('hashtag_links', function (Blueprint $table) {
            $table->bigIncrements('id');

            $table->unsignedBigInteger('hashtag_id');

            $table->unsignedBigInteger('post_id')->nullable();
            $table->unsignedBigInteger('post_comment_id')->nullable();

            $table->timestamps();

            // Unambiguous FK
            $table->foreign('hashtag_id')
                ->references('id')
                ->on('hashtags')
                ->onDelete('cascade');

            // FKs (with cascade deletes)
            $table->foreign('post_id')->references('id')->on('posts')->onDelete('cascade');
            $table->foreign('post_comment_id')->references('id')->on('post_comments')->onDelete('cascade');

            // Query indexes (optional; FK/unique may already create indexes, but explicit is fine)
            $table->index('post_id');
            $table->index('post_comment_id');

            // Optional dedupe constraints (recommended)
            $table->unique(['hashtag_id', 'post_id'], 'uq_hashtag_post');
            $table->unique(['hashtag_id', 'post_comment_id'], 'uq_hashtag_post_comment');
        });

        /**
         * mentions
         * - Single table for mentions attached to posts OR post comments
         */
        Schema::create('mentions', function (Blueprint $table) {
            $table->bigIncrements('id');

            $table->unsignedBigInteger('mentioned_user_id');     // who got mentioned
            $table->unsignedBigInteger('mentioned_by_user_id');  // who wrote it

            $table->unsignedBigInteger('post_id')->nullable();
            $table->unsignedBigInteger('post_comment_id')->nullable();

            $table->timestamps();

            // Users are unambiguous, so FKs are safe & useful
            $table->foreign('mentioned_user_id')
                ->references('id')
                ->on('users')
                ->onDelete('cascade');

            $table->foreign('mentioned_by_user_id')
                ->references('id')
                ->on('users')
                ->onDelete('cascade');

            // FKs (with cascade deletes)
            $table->foreign('post_id')->references('id')->on('posts')->onDelete('cascade');
            $table->foreign('post_comment_id')->references('id')->on('post_comments')->onDelete('cascade');

            // Query indexes (optional; FK/unique may already create indexes, but explicit is fine)
            $table->index('mentioned_user_id');
            $table->index('post_id');
            $table->index('post_comment_id');

            // Optional dedupe constraints (recommended)
            $table->unique(['mentioned_user_id', 'post_id'], 'uq_mention_post');
            $table->unique(['mentioned_user_id', 'post_comment_id'], 'uq_mention_post_comment');
        });

        $this->migrator->add('feed.enable_hashtags', true);
        $this->migrator->add('feed.enable_mentions', true);
        $this->migrator->add('feed.max_hashtags', 10);
        $this->migrator->add('feed.max_mentions', 10);
        $this->migrator->add('feed.enable_mention_suggestions', true);

        $this->migrator->add('feed.popular_hashtags_widget_disable', false);
        $this->migrator->add('feed.popular_hashtags_days', 14);

        $this->migrator->add('site.explore_enabled', true);
        $this->migrator->add('site.explore_menu_visibility', 'guest');
        $this->migrator->add('site.explore_mode', 'paywall');

        // Resetting permissions per latest changes
        // TODO: This might actually crash if instance has deleted the demo role?
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


    }

    public function down(): void
    {
        Schema::dropIfExists('mentions');
        Schema::dropIfExists('hashtag_links');
        Schema::dropIfExists('hashtags');

        $this->migrator->delete('feed.enable_hashtags');
        $this->migrator->delete('feed.enable_mentions');
        $this->migrator->delete('feed.max_hashtags');
        $this->migrator->delete('feed.max_mentions');
        $this->migrator->delete('feed.enable_mention_suggestions');
        $this->migrator->delete('feed.popular_hashtags_widget_disable');
        $this->migrator->delete('feed.popular_hashtags_days');

        $this->migrator->delete('site.explore_enabled', true);
        $this->migrator->delete('site.explore_menu_visibility', true);
        $this->migrator->delete('site.explore_mode', 'paywall');

    }
}
