<?php

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

class StreamsSettings extends Settings
{
    public string $streaming_driver;

    public ?string $max_live_duration;

    public ?string $allow_free_streams;

    public ?string $pushr_key;

    public ?string $pushr_zone_id;

    public ?string $pushr_encoder;

    public ?string $pushr_allow_dvr;

    public ?string $pushr_allow_mux;

    public ?string $pushr_allow_360p;

    public ?string $pushr_allow_480p;

    public ?string $pushr_allow_576p;

    public ?string $pushr_allow_720p;

    public ?string $pushr_allow_1080p;

    public ?string $livekit_api_key;

    public ?string $livekit_api_secret;

    public ?string $livekit_ws_url;

    public static function group(): string
    {
        return 'streams';
    }
}
