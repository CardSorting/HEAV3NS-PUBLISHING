<?php

namespace App\Settings\Casts;

use Spatie\LaravelSettings\SettingsCasts\SettingsCast;

class EncryptedStringSettingsCast implements SettingsCast
{
    protected const PREFIX = 'encrypted:v1:';

    public function get($payload): ?string
    {
        if ($payload === null) {
            return null;
        }

        $payload = (string) $payload;

        if (!str_starts_with($payload, self::PREFIX)) {
            return $payload;
        }

        return (string) decrypt(substr($payload, strlen(self::PREFIX)));
    }

    public function set($payload): ?string
    {
        if ($payload === null) {
            return null;
        }

        return self::PREFIX.encrypt((string) $payload);
    }
}
