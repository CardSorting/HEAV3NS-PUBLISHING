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

            if (is_null($value)) {
                $value = $meta['default'] ?? null;
            }

            $this->migrator->add("streams.$spatieKey", $value);
        }
    }

    public function down(): void
    {
        foreach (array_keys($this->getSettings()) as $key) {
            $this->migrator->delete("streams.$key");
        }
    }

    protected function getSettings(): array
    {
        return [
            'streaming_driver' => ['voyager_key' => 'streams.streaming_driver', 'default' => 'livekit'],
            'allow_free_streams' => ['voyager_key' => 'streams.allow_free_streams', 'default' => '1'],
            'max_live_duration' => ['voyager_key' => 'streams.max_live_duration', 'default' => '1'],

            'pushr_key' => ['voyager_key' => 'streams.pushr_key', 'default' => null],
            'pushr_zone_id' => ['voyager_key' => 'streams.pushr_zone_id', 'default' => null],
            'pushr_encoder' => ['voyager_key' => 'streams.pushr_encoder', 'default' => null],
            'pushr_allow_dvr' => ['voyager_key' => 'streams.pushr_allow_dvr', 'default' => '0'],
            'pushr_allow_mux' => ['voyager_key' => 'streams.pushr_allow_mux', 'default' => '0'],
            'pushr_allow_360p' => ['voyager_key' => 'streams.pushr_allow_360p', 'default' => '0'],
            'pushr_allow_480p' => ['voyager_key' => 'streams.pushr_allow_480p', 'default' => '0'],
            'pushr_allow_576p' => ['voyager_key' => 'streams.pushr_allow_576p', 'default' => '0'],
            'pushr_allow_720p' => ['voyager_key' => 'streams.pushr_allow_720p', 'default' => '0'],
            'pushr_allow_1080p' => ['voyager_key' => 'streams.pushr_allow_1080p', 'default' => '1'],

            'livekit_api_key' => ['voyager_key' => 'streams.livekit_api_key', 'default' => null],
            'livekit_api_secret' => ['voyager_key' => 'streams.livekit_api_secret', 'default' => null],
            'livekit_ws_url' => ['voyager_key' => 'streams.livekit_ws_url', 'default' => null],
        ];
    }
};
