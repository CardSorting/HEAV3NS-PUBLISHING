<?php

use Illuminate\Database\Schema\Blueprint;
use Spatie\LaravelSettings\Migrations\SettingsMigration;
use Illuminate\Support\Facades\Schema;

class V1005 extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('media.ffmpeg_video_encoder', 'libx264');
        $this->migrator->add('media.ffmpeg_video_speed_preset', 'ultrafast');
        $this->migrator->add('media.watermark_position', 'bottom-right');
        $this->migrator->add('media.watermark_scale_percent', 25);
        $this->migrator->add('media.watermark_opacity', 90);

        Schema::create('user_spotify_accounts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->unique();
            $table->string('spotify_id')->nullable();
            $table->string('display_name')->nullable();
            $table->string('avatar')->nullable();

            $table->text('access_token')->nullable();
            $table->text('refresh_token')->nullable();
            $table->timestamp('expires_at')->nullable();

            $table->string('anthem_track_id')->nullable();
            $table->longText('top_artists')->nullable();

            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        });

        // Feature toggle
        $this->migrator->add('profiles.spotify_enabled', false);

        // OAuth (stored in DB)
        $this->migrator->add('profiles.spotify_client_id', '');
        $this->migrator->add('profiles.spotify_client_secret', '');

        // Snapshot/widget config
        $this->migrator->add('profiles.spotify_top_artists_limit', 7);
        $this->migrator->add('profiles.spotify_top_artists_ranges', ['short_term', 'medium_term', 'long_term']);


    }

    public function down(): void
    {
        $this->migrator->delete('media.ffmpeg_video_encoder');
        $this->migrator->delete('media.ffmpeg_video_speed_preset');
        $this->migrator->delete('media.watermark_position');
        $this->migrator->delete('media.watermark_scale_percent');
        $this->migrator->delete('media.watermark_opacity');


        $this->migrator->delete('profiles.spotify_enabled');
        $this->migrator->delete('profiles.spotify_client_id');
        $this->migrator->delete('profiles.spotify_client_secret');
        $this->migrator->delete('profiles.spotify_top_artists_limit');
        $this->migrator->delete('profiles.spotify_top_artists_ranges');


        Schema::dropIfExists('user_spotify_accounts');
    }
}
