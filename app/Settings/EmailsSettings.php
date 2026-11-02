<?php

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

class EmailsSettings extends Settings
{
    public string $driver;

    public ?string $from_name;

    public ?string $from_address;

    public ?string $mailgun_domain;

    public ?string $mailgun_secret;

    public ?string $mailgun_endpoint;

    public ?string $smtp_host;

    public ?string $smtp_port;

    public ?string $smtp_encryption;

    public ?string $smtp_username;

    public ?string $smtp_password;

    public static function group(): string
    {
        return 'emails';
    }
}
