<?php

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

class WebsocketsSettings extends Settings
{
    public string $driver;

    public ?string $pusher_app_id;

    public ?string $pusher_app_key;

    public ?string $pusher_app_secret;

    public ?string $pusher_app_cluster;

    public ?string $soketi_host_address;

    public ?string $soketi_host_port;

    public ?string $soketi_app_id;

    public ?string $soketi_app_key;

    public ?string $soketi_app_secret;

    public ?string $soketi_use_TSL;

    public static function group(): string
    {
        return 'websockets';
    }
}
