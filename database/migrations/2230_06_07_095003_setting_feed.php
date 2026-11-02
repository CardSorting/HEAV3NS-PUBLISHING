<?php

use Illuminate\Support\Facades\DB;
use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        foreach ($this->getSettings() as $key => $meta) {
            $voyagerKey = $meta['voyager_key'];
            $value = DB::table('settings')->where('key', $voyagerKey)->value('value') ?? $meta['default'] ?? null;

            if (isset($meta['cast'])) {
                $value = match ($meta['cast']) {
                    'bool' => (bool) $value,
                    'int' => (int) $value,
                    default => $value,
                };
            }

            $this->migrator->add("feed.$key", $value);
        }
    }

    public function down(): void
    {
        foreach (array_keys($this->getSettings()) as $key) {
            $this->migrator->delete("feed.$key");
        }
    }

    protected function getSettings(): array
    {
        return [
            'feed_posts_per_page' => ['voyager_key' => 'feed.feed_posts_per_page', 'default' => 10, 'cast' => 'int'],
            'min_post_description' => ['voyager_key' => 'feed.min_post_description', 'default' => 0, 'cast' => 'int'],
            'post_box_max_height' => ['voyager_key' => 'feed.post_box_max_height', 'default' => 300, 'cast' => 'int'],

            'allow_gallery_zoom' => ['voyager_key' => 'feed.allow_gallery_zoom', 'default' => false, 'cast' => 'bool'],
            'allow_post_scheduling' => ['voyager_key' => 'feed.allow_post_scheduling', 'default' => false, 'cast' => 'bool'],
            'allow_post_polls' => ['voyager_key' => 'feed.allow_post_polls', 'default' => false, 'cast' => 'bool'],
            'enable_post_description_excerpts' => ['voyager_key' => 'feed.enable_post_description_excerpts', 'default' => false, 'cast' => 'bool'],
            'disable_posts_text_preview' => ['voyager_key' => 'feed.disable_posts_text_preview', 'default' => false, 'cast' => 'bool'],

            'hide_suggestions_slider' => ['voyager_key' => 'feed.hide_suggestions_slider', 'default' => false, 'cast' => 'bool'],
            'suggestions_skip_empty_profiles' => ['voyager_key' => 'feed.suggestions_skip_empty_profiles', 'default' => false, 'cast' => 'bool'],
            'suggestions_skip_unverified_profiles' => ['voyager_key' => 'feed.suggestions_skip_unverified_profiles', 'default' => false, 'cast' => 'bool'],
            'suggestions_use_featured_users_list' => ['voyager_key' => 'feed.suggestions_use_featured_users_list', 'default' => false, 'cast' => 'bool'],
            'feed_suggestions_autoplay' => ['voyager_key' => 'feed.feed_suggestions_autoplay', 'default' => false, 'cast' => 'bool'],
            'feed_suggestions_card_per_page' => ['voyager_key' => 'feed.feed_suggestions_card_per_page', 'default' => 6, 'cast' => 'int'],
            'feed_suggestions_total_cards' => ['voyager_key' => 'feed.feed_suggestions_total_cards', 'default' => 12, 'cast' => 'int'],

            'expired_subs_widget_hide' => ['voyager_key' => 'feed.expired_subs_widget_hide', 'default' => false, 'cast' => 'bool'],
            'expired_subs_widget_autoplay' => ['voyager_key' => 'feed.expired_subs_widget_autoplay', 'default' => false, 'cast' => 'bool'],
            'expired_subs_widget_card_per_page' => ['voyager_key' => 'feed.expired_subs_widget_card_per_page', 'default' => 6, 'cast' => 'int'],
            'expired_subs_widget_total_cards' => ['voyager_key' => 'feed.expired_subs_widget_total_cards', 'default' => 12, 'cast' => 'int'],

            'search_widget_hide' => ['voyager_key' => 'feed.search_widget_hide', 'default' => false, 'cast' => 'bool'],
            'default_search_widget_filter' => ['voyager_key' => 'feed.default_search_widget_filter', 'default' => 'top'],
            'hide_non_verified_users_from_search' => [
                'voyager_key' => 'profiles.hide_non_verified_users_from_search',
                'default' => false,
            ],
        ];
    }
};
