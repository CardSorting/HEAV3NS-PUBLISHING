<?php

use Illuminate\Support\Facades\DB;
use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        foreach ($this->getSettings() as $spatieKey => $meta) {
            $voyagerKey = $meta['voyager_key'];
            $value = DB::table('settings')->where('key', $voyagerKey)->value('value');
            if (!empty($meta['is_file'])) {
                $value = resolveVoyagerFilePath($value);
            }

            if (is_null($value)) {
                $value = is_callable($meta['default'] ?? null)
                    ? call_user_func($meta['default'])
                    : ($meta['default'] ?? null);
            }

            if (isset($meta['cast'])) {
                $value = $this->cast($meta['cast'], $value);
            }

            $this->migrator->add("media.$spatieKey", $value);
        }
    }

    public function down(): void
    {
        foreach (array_keys($this->getSettings()) as $key) {
            $this->migrator->delete("media.$key");
        }
    }

    protected function cast(string $type, mixed $value): mixed
    {
        return match ($type) {
            'bool' => (bool) $value,
            'int' => (int) $value,
            'float' => (float) $value,
            default => $value,
        };
    }

    protected function getSettings(): array
    {
        return [
            'ffmpeg_path' => [
                'voyager_key' => 'media.ffmpeg_path',
                'default' => null,
            ],
            'ffprobe_path' => [
                'voyager_key' => 'media.ffprobe_path',
                'default' => null,
            ],
            'transcoding_driver' => [
                'voyager_key' => 'media.transcoding_driver',
                'default' => 'ffmpeg',
            ],
            'ffmpeg_video_conversion_quality_preset' => [
                'voyager_key' => 'media.ffmpeg_video_conversion_quality_preset',
                'default' => 'size',
            ],
            'ffmpeg_audio_encoder' => [
                'voyager_key' => 'media.ffmpeg_audio_encoder',
                'default' => 'aac',
            ],
            'coconut_api_key' => [
                'voyager_key' => 'media.coconut_api_key',
                'default' => null,
            ],
            'coconut_audio_encoder' => [
                'voyager_key' => 'media.coconut_audio_encoder',
                'default' => 'aac',
            ],
            'coconut_video_conversion_quality_preset' => [
                'voyager_key' => 'media.coconut_video_conversion_quality_preset',
                'default' => 'coconut_balanced',
            ],
            'coconut_enforce_mp4_conversion' => [
                'voyager_key' => 'media.coconut_enforce_mp4_conversion',
                'default' => false,
                'cast' => 'bool',
            ],
            'coconut_video_region' => [
                'voyager_key' => 'media.coconut_video_region',
                'default' => null,
            ],
            'enforce_mp4_conversion' => [
                'voyager_key' => 'media.enforce_mp4_conversion',
                'default' => true,
                'cast' => 'bool',
            ],
            'max_videos_length' => [
                'voyager_key' => 'media.max_videos_length',
                'default' => 0,
                'cast' => 'int',
            ],
            'allowed_file_extensions' => [
                'voyager_key' => 'media.allowed_file_extensions',
                'default' => null,
            ],
            'max_file_upload_size' => [
                'voyager_key' => 'media.max_file_upload_size',
                'default' => 512,
                'cast' => 'int',
            ],
            'use_chunked_uploads' => [
                'voyager_key' => 'media.use_chunked_uploads',
                'default' => true,
                'cast' => 'bool',
            ],
            'upload_chunk_size' => [
                'voyager_key' => 'media.upload_chunk_size',
                'default' => 1,
                'cast' => 'int',
            ],
            'apply_watermark' => [
                'voyager_key' => 'media.apply_watermark',
                'default' => false,
                'cast' => 'bool',
            ],
            'use_url_watermark' => [
                'voyager_key' => 'media.use_url_watermark',
                'default' => false,
                'cast' => 'bool',
            ],
            'watermark_image' => [
                'voyager_key' => 'media.watermark_image',
                'default' => null,
                'is_file' => true,
            ],
            'disable_media_right_click' => [
                'voyager_key' => 'media.disable_media_right_click',
                'default' => false,
                'cast' => 'bool',
            ],
            'max_avatar_cover_file_size' => [
                'voyager_key' => 'media.max_avatar_cover_file_size',
                'default' => 4,
                'cast' => 'int',
            ],
            'users_covers_size' => [
                'voyager_key' => 'media.users_covers_size',
                'default' => '599x180',
            ],
            'users_avatars_size' => [
                'voyager_key' => 'media.users_avatars_size',
                'default' => '96x96',
            ],
            'use_blurred_previews_for_locked_posts' => [
                'voyager_key' => 'media.use_blurred_previews_for_locked_posts',
                'default' => false,
                'cast' => 'bool',
            ],
        ];
    }
};
