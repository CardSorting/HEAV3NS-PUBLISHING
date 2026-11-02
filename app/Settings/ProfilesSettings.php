<?php

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

class ProfilesSettings extends Settings
{
    public bool $allow_profile_qr_code;

    public bool $allow_gender_pronouns;

    public string $default_profile_type_on_register;

    public string $default_user_privacy_setting_on_register;

    public bool $allow_users_enabling_open_profiles;

    public string $profile_monetization_mode = 'mixed';

    public string $creator_access_mode = 'everyone';

    public bool $require_id_before_publishing = false;

    public bool $show_locked_creation_pages = true;

    public string $creator_application_instructions = '';

    public bool $require_creator_application_message = false;

    public ?int $default_wallet_balance_on_register;

    public bool $allow_profile_bio_markdown;

    public bool $disable_profile_bio_excerpt;

    public ?int $max_profile_bio_length;

    public bool $enable_new_post_notification_setting;

    public bool $default_new_post_notification_setting;

    public bool $disable_website_link_on_profile;

    public bool $disable_profile_offers;

    public bool $allow_hyperlinks;

    public bool $show_online_users_indicator;

    public bool $record_users_last_activity_time;

    public bool $record_users_last_ip_address;

    public string|array|null $default_users_to_follow = [];

    public bool $hide_profile_followers_count = false;

    public bool $social_links_enabled = false;

    public array $allowed_social_network_keys = [];

    // Social Auth
    public ?string $social_auth_facebook_client_id = '';

    public ?string $social_auth_facebook_secret = '';

    public ?string $social_auth_twitter_client_id = '';

    public ?string $social_auth_twitter_secret = '';

    public ?string $social_auth_google_client_id = '';

    public ?string $social_auth_google_secret = '';

    public bool $spotify_enabled = false;

    public ?string $spotify_client_id = '';

    public ?string $spotify_client_secret = '';

    public int $spotify_top_artists_limit = 5;

    public array $spotify_top_artists_ranges = ['short_term', 'medium_term', 'long_term'];

    // Push settings
    public bool $push_notifications_enabled = false;

    public ?string $webpush_contact_email = '';

    public ?string $webpush_public_key = '';

    public ?string $webpush_private_key = '';

    public bool $enable_toast_notification_setting = true;

    public static function group(): string
    {
        return 'profiles';
    }
}
