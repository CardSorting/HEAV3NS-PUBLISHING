<?php

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

class StoriesSettings extends Settings
{
    // General
    public ?bool $stories_enabled = true;

    public ?bool $allow_highlights = true;

    public ?bool $allow_public_stories = true;

    // Lengths & Expiry
    public ?int $default_story_length_seconds = 5;

    public ?int $max_video_length_seconds = 60;

    public ?int $story_expires_hours = 24;

    // Limits
    public ?int $max_text_length = 2000;

    // Sounds / CTA
    public ?bool $allow_cta_links = true;

    public ?bool $allow_sounds = true;

    public static function group(): string
    {
        return 'stories';
    }
}
