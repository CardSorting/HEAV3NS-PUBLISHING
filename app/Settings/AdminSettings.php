<?php

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

class AdminSettings extends Settings
{
    public ?string $title;

    public ?string $light_logo;

    public ?string $dark_logo;

    public bool $send_notifications_on_contact;

    public bool $send_notifications_on_pending_posts;

    public bool $show_staff_badges = true;

    public static function group(): string
    {
        return 'admin';
    }
}
