<?php

namespace App\Settings;

use App\Settings\Casts\EncryptedStringSettingsCast;
use Spatie\LaravelSettings\Settings;

class ModerationSettings extends Settings
{
    public const VIDEO_PROCESSING_AUTO = 'auto';
    public const VIDEO_PROCESSING_SYNC = 'sync';
    public const VIDEO_PROCESSING_ASYNC = 'async';

    public bool $publication_review_enabled = false;

    /** @var array<int, string> */
    public array $publication_review_areas = ['posts', 'reels', 'stories'];

    public string $publication_review_mode = 'manual';

    public bool $enabled = false;

    public string $provider = 'sightengine';

    public ?string $sightengine_api_user = null;

    public ?string $sightengine_api_secret = null;

    public string $sightengine_model = 'nudity-2.1';

    /** @var array<int, string> */
    public array $flag_classes = ['sexual_activity', 'sexual_display', 'erotica'];

    public float $review_threshold = 0.65;

    public float $violation_threshold = 0.90;

    public bool $text_enabled = false;

    /** @var array<int, string> */
    public array $text_models = ['general'];

    /** @var array<int, string> */
    public array $text_languages = ['en'];

    /** @var array<int, string> */
    public array $text_flag_classes = ['sexual', 'discriminatory', 'insulting', 'violent', 'toxic'];

    public float $text_review_threshold = 0.65;

    public float $text_violation_threshold = 0.90;

    public bool $video_enabled = false;

    public string $video_processing_mode = self::VIDEO_PROCESSING_AUTO;

    public float $video_interval_seconds = 0.0;

    public int $max_concurrent_video_jobs = 1;

    public int $max_video_duration_seconds = 59;

    public int $request_timeout_seconds = 120;

    public int $daily_operation_limit = 450;

    public int $monthly_operation_limit = 1800;

    public int $inline_item_limit = 10;

    public int $deferred_item_limit = 25;

    public int $background_item_limit = 100;

    public int $worker_item_limit = 5000;

    public int $stale_after_minutes = 15;

    public int $async_provider_timeout_minutes = 1440;

    public static function casts(): array
    {
        return [
            'sightengine_api_user' => EncryptedStringSettingsCast::class,
            'sightengine_api_secret' => EncryptedStringSettingsCast::class,
        ];
    }

    public static function group(): string
    {
        return 'moderation';
    }
}
