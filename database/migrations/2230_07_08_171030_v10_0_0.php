<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\LaravelSettings\Migrations\SettingsBlueprint;
use Spatie\LaravelSettings\Migrations\SettingsMigration;

class V1000 extends SettingsMigration
{
    public function up(): void
    {

        Schema::create('sounds', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('title', 120);
            $table->string('artist', 120);
            $table->string('description', 255)->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });

        Schema::create('stories', function (Blueprint $table) {
            $table->bigIncrements('id');

            $table->foreignId('user_id')
                ->constrained()
                ->onDelete('cascade');

            // Story-level metadata (per card)
            $table->timestamp('expires_at')->nullable()->index();
            $table->boolean('is_highlight')->default(false)->index();
            $table->boolean('is_public')->default(false)->index();

            $table->string('mode', 16)->default('media')->index();
            $table->text('text')->nullable();
            $table->longText('overlay')->nullable();
            $table->string('bg_preset', 32)->nullable()->index();

            $table->string('link_url', 2048)->nullable();
            $table->string('link_text', 80)->nullable();


            $table->foreignId('sound_id')
                ->nullable()
                ->constrained('sounds')
                ->nullOnDelete(); // If you delete a sound from admin, you probably don’t want to delete all stories that used it
            $table->timestamps();
        });

        Schema::create('story_views', function (Blueprint $table) {
            $table->bigIncrements('id');

            $table->foreignId('story_id')
                ->constrained('stories')
                ->onDelete('cascade');

            $table->foreignId('user_id')
                ->constrained()
                ->onDelete('cascade');

            // When they last viewed this story
            $table->timestamp('seen_at')->nullable();

            $table->timestamps();

            // One row per user per story
            $table->unique(['story_id', 'user_id']);
        });


        Schema::table('attachments', function (Blueprint $table) {
            $table->unsignedBigInteger('story_id')
                ->nullable()
                ->after('message_id');

            $table->foreign('story_id')
                ->references('id')
                ->on('stories')
                ->onDelete('cascade');

            $table->unsignedInteger('length')
                ->nullable()
                ->after('has_blurred_preview')
                ->index();

            $table->unsignedBigInteger('sound_id')
                ->nullable()
                ->after('story_id');

            $table->foreign('sound_id')
                ->references('id')
                ->on('sounds')
                ->nullOnDelete();

        });

        if (Schema::hasTable('user_reports')) {
            Schema::table('user_reports', function (Blueprint $table) {
                $table->bigInteger('story_id')->after('stream_id')->nullable();
                $table->index('story_id');
            });
        }

        $this->migrator->add('stories.stories_enabled', true);
        $this->migrator->add('stories.allow_highlights', true);
        $this->migrator->add('stories.allow_public_stories', true);

        $this->migrator->add('stories.default_story_length_seconds', 5);
        $this->migrator->add('stories.max_video_length_seconds', 60);
        $this->migrator->add('stories.story_expires_hours', 24);

        $this->migrator->add('stories.max_text_length', 2000);

        $this->migrator->add('stories.allow_cta_links', true);

        $this->migrator->add('stories.allow_sounds', true);

        Schema::table('user_messages', function (Blueprint $table) {
            $table->unsignedBigInteger('story_id')->nullable()->after('receiver_id');
            $table->index('story_id');

            // If the story gets deleted, keep the message but null the reference.
            $table->foreign('story_id')
                ->references('id')
                ->on('stories')
                ->nullOnDelete();
        });

        $this->migrator->inGroup('profiles', function (SettingsBlueprint $blueprint): void {
            $blueprint->add('hide_profile_followers_count', '');
        });

    }

    public function down(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        Schema::dropIfExists('story_views');
        Schema::dropIfExists('stories');
        Schema::dropIfExists('sounds');
        Schema::table('attachments', function (Blueprint $table) {
            $table->dropForeign(['story_id']);
            $table->dropColumn('story_id');
            $table->dropColumn('length');
            $table->dropForeign(['sound_id']);
            $table->dropColumn('sound_id');

        });
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');

        if (Schema::hasTable('user_reports')) {
            Schema::table('user_reports', function (Blueprint $table) {
                $table->dropIndex(['story_id']);
                $table->dropColumn('story_id');
            });
        }

        $this->migrator->delete('stories.stories_enabled');
        $this->migrator->delete('stories.allow_highlights');
        $this->migrator->delete('stories.allow_public_stories');

        $this->migrator->delete('stories.default_story_length_seconds');
        $this->migrator->delete('stories.max_video_length_seconds');
        $this->migrator->delete('stories.story_expires_hours');

        $this->migrator->delete('stories.max_text_length');

        $this->migrator->delete('stories.allow_cta_links');
        $this->migrator->delete('stories.allow_sounds');

        $this->migrator->inGroup('profiles', function (SettingsBlueprint $blueprint): void {
            $blueprint->delete('hide_profile_followers_count');
        });

        Schema::table('user_messages', function (Blueprint $table) {
            $table->dropForeign(['story_id']);
            $table->dropIndex(['story_id']);
            $table->dropColumn('story_id');
        });

    }
}
