<?php

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

class FeedSettings extends Settings
{
    public ?int $feed_posts_per_page;

    public ?int $min_post_description;

    public ?int $post_box_max_height;

    public bool $allow_gallery_zoom;

    public bool $youtube_embeds_enabled = false;

    public bool $autoplay_videos = false;

    public bool $allow_post_scheduling;

    public bool $enable_post_description_excerpts;

    public bool $disable_posts_text_preview;

    public bool $allow_post_polls;

    public bool $hide_suggestions_slider;

    public bool $mobile_suggestions_enabled = true;

    public bool $suggestions_skip_empty_profiles;

    public bool $suggestions_skip_unverified_profiles;

    public bool $suggestions_use_featured_users_list;

    public bool $feed_suggestions_autoplay;

    public ?int $feed_suggestions_card_per_page;

    public ?int $feed_suggestions_total_cards;

    public bool $expired_subs_widget_hide;

    public bool $expired_subs_widget_autoplay;

    public ?int $expired_subs_widget_card_per_page;

    public ?int $expired_subs_widget_total_cards;

    public bool $search_widget_hide;

    public ?string $default_search_widget_filter;

    public bool $hide_non_verified_users_from_search;

    public bool $enable_hashtags = false;

    public bool $enable_mentions = false;

    public int $max_hashtags = 10;

    public int $max_mentions = 10;

    public bool $enable_mention_suggestions = true;

    public bool $popular_hashtags_widget_disable = false;

    public ?int $popular_hashtags_days = 14;

    public static function group(): string
    {
        return 'feed';
    }
}
