<?php

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

class ReelsSettings extends Settings
{
    public ?bool $reels_enabled = true;

    public ?bool $allow_public_reels = true;

    public ?int $max_video_length_seconds = 90;

    public ?bool $allow_sounds = true;

    public ?bool $allow_progress_scrubbing = true;

    public ?bool $feed_widget_enabled = true;

    public ?string $feed_widget_placement_mode = 'once';

    public ?int $feed_widget_first_after_posts = 3;

    public ?int $feed_widget_repeat_every_posts = 10;

    public ?int $feed_widget_cards_per_widget = 12;

    public static function group(): string
    {
        return 'reels';
    }
}
