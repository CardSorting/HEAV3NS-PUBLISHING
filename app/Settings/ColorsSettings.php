<?php

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

class ColorsSettings extends Settings
{
    public ?string $theme_color_code;

    public ?string $theme_gradient_from;

    public ?string $theme_gradient_to;

    public static function group(): string
    {
        return 'colors';
    }
}
