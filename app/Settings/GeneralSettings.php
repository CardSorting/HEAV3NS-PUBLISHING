<?php

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

class GeneralSettings extends Settings
{
    public string $name;

    public ?string $description;

    public ?string $slogan;

    public ?string $light_logo;

    public ?string $dark_logo;

    public ?string $favicon;

    public ?string $default_og_image;

    public ?string $login_page_background_image;

    public ?string $footer_copyright_text = null;

    public bool $allow_theme_switch;

    public string $default_user_theme;

    public string $default_site_direction;

    public string $font_source = 'google';

    public bool $allow_language_switch;

    public string $default_site_language;

    public string $homepage_type;

    public ?string $homepage_redirect;

    public bool $enforce_user_identity_checks;

    public bool $enforce_email_validation;

    public bool $hide_identity_checks;

    public bool $use_browser_language_if_available;

    public bool $enable_smooth_page_change_transitions;

    public string $redirect_page_after_register;

    public string $timezone;

    // == Social medlia links
    public ?string $social_facebook_url = '';

    public ?string $social_instagram_url = '';

    public ?string $social_twitter_url = '';

    public ?string $social_whatsapp_url = '';

    public ?string $social_tiktok_url = '';

    public ?string $social_youtube_url = '';

    public ?string $social_telegram_link = '';

    public ?string $social_reddit_url = '';

    // == Code & ads
    public ?string $custom_code_css = '';

    public ?string $custom_code_js = '';

    public ?string $ads_sidebar_spot = '';

    public bool $ads_feed_enabled = false;

    public ?string $ads_feed_code = '';

    public int $ads_feed_frequency = 6;

    public string $ads_feed_visibility = 'mobile';

    public ?bool $explore_enabled = true;

    public ?string $explore_menu_visibility = 'guest';

    public ?string $explore_mode = 'paywall';

    public ?bool $explore_follow_button_enabled = true;

    public bool $pwa_enabled = false;

    public ?string $pwa_theme_color = '';

    public ?string $pwa_background_color = '';

    public bool $pwa_install_prompt_enabled = false;

    public ?string $pwa_icon = '';

    public ?string $pwa_splash_logo = '';

    public ?string $app_url = null;

    public static function group(): string
    {
        return 'site';
    }
}
