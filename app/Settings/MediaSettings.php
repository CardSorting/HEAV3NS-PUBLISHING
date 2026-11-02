<?php

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

class MediaSettings extends Settings
{
    public ?string $ffmpeg_path = null;

    public ?string $ffprobe_path = null;

    public string $transcoding_driver;

    public string $ffmpeg_video_conversion_quality_preset;

    public string $ffmpeg_audio_encoder;

    public ?string $coconut_api_key;

    public string $coconut_audio_encoder;

    public string $coconut_video_conversion_quality_preset;

    public bool $coconut_enforce_mp4_conversion;

    public ?string $coconut_video_region;

    public bool $enforce_mp4_conversion;

    public int $max_videos_length;

    public ?string $allowed_file_extensions;

    public int $max_file_upload_size;

    public bool $use_chunked_uploads;

    public int $upload_chunk_size;

    public bool $apply_watermark;

    public bool $use_url_watermark;

    public ?string $watermark_image;

    public bool $disable_media_right_click;

    public int $max_avatar_cover_file_size;

    public string $users_covers_size;

    public string $users_avatars_size;

    public bool $use_blurred_previews_for_locked_posts;

    public bool $voice_messages_enabled = true;

    public bool $vault_enabled = true;

    public bool $file_attachments_enabled = false;

    public array $file_attachment_formats = ['pdf', 'zip'];

    public bool $gif_picker_enabled = false;

    public string $gif_provider = 'klipy';

    public ?string $gif_klipy_key = null;

    public ?string $gif_giphy_messenger_key = null;

    public ?string $gif_giphy_post_comments_key = null;

    public ?string $gif_giphy_reel_comments_key = null;

    public bool $gif_messenger_enabled = true;

    public bool $gif_post_comments_enabled = true;

    public bool $gif_reel_comments_enabled = true;

    public string $ffmpeg_video_encoder = 'libx264';

    public string $ffmpeg_video_speed_preset = 'ultrafast';

    public ?string $watermark_position = 'bottom-right';

    public ?int $watermark_scale_percent = 25;

    public ?int $watermark_opacity = 90;

    public static function group(): string
    {
        return 'media';
    }
}
