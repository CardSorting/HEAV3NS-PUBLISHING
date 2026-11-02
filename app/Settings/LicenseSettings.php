<?php

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

class LicenseSettings extends Settings
{
    public ?string $product_license_key;

    public static function group(): string
    {
        return 'license';
    }
}
