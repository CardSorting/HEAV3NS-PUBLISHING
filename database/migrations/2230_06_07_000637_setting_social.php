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

            $this->migrator->add("social.$key", $value);
        }
    }

    public function down(): void
    {
        foreach (array_keys($this->getSettings()) as $key) {
            $this->migrator->delete("social.$key");
        }
    }

    protected function getSettings(): array
    {
        return [
            'facebook_url' => ['voyager_key' => 'social.facebook_url'],
            'instagram_url' => ['voyager_key' => 'social.instagram_url'],
            'twitter_url' => ['voyager_key' => 'social.twitter_url'],
            'whatsapp_url' => ['voyager_key' => 'social.whatsapp_url'],
            'tiktok_url' => ['voyager_key' => 'social.tiktok_url'],
            'youtube_url' => ['voyager_key' => 'social.youtube_url'],
            'telegram_link' => ['voyager_key' => 'social.telegram_link'],
            'reddit_url' => ['voyager_key' => 'social.reddit_url'],

            'facebook_client_id' => ['voyager_key' => 'social.facebook_client_id'],
            'facebook_secret' => ['voyager_key' => 'social.facebook_secret'],
            'twitter_client_id' => ['voyager_key' => 'social.twitter_client_id'],
            'twitter_secret' => ['voyager_key' => 'social.twitter_secret'],
            'google_client_id' => ['voyager_key' => 'social.google_client_id'],
            'google_secret' => ['voyager_key' => 'social.google_secret'],
        ];
    }
};
