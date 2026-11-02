<?php

use Illuminate\Support\Facades\DB;
use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        foreach ($this->getSettings() as $key => $meta) {
            $voyagerKey = $meta['voyager_key'];
            $value = DB::table('settings')->where('key', $voyagerKey)->value('value');

            if (is_null($value)) {
                $value = is_callable($meta['default'] ?? null)
                    ? call_user_func($meta['default'])
                    : ($meta['default'] ?? null);
            }

            if (isset($meta['cast'])) {
                $value = $this->cast($meta['cast'], $value);
            }

            $this->migrator->add("profiles.$key", $value);
        }
    }

    public function down(): void
    {
        foreach (array_keys($this->getSettings()) as $key) {
            $this->migrator->delete("profiles.$key");
        }
    }

    protected function getSettings(): array
    {
        return [
            'allow_profile_qr_code' => [
                'voyager_key' => 'profiles.allow_profile_qr_code',
                'default' => false,
                'cast' => 'bool',
            ],
            'allow_gender_pronouns' => [
                'voyager_key' => 'profiles.allow_gender_pronouns',
                'default' => true,
                'cast' => 'bool',
            ],
            'default_profile_type_on_register' => [
                'voyager_key' => 'profiles.default_profile_type_on_register',
                'default' => 'paid',
            ],
            'default_users_to_follow' => [
                'voyager_key' => 'profiles.default_users_to_follow',
                'default' => null,
            ],
            'default_user_privacy_setting_on_register' => [
                'voyager_key' => 'profiles.default_user_privacy_setting_on_register',
                'default' => 'private',
            ],
            'allow_users_enabling_open_profiles' => [
                'voyager_key' => 'profiles.allow_users_enabling_open_profiles',
                'default' => false,
                'cast' => 'bool',
            ],
            'default_wallet_balance_on_register' => [
                'voyager_key' => 'profiles.default_wallet_balance_on_register',
                'default' => 0,
                'cast' => 'int',
            ],
            'allow_profile_bio_markdown' => [
                'voyager_key' => 'profiles.allow_profile_bio_markdown',
                'default' => true,
                'cast' => 'bool',
            ],
            'disable_profile_bio_excerpt' => [
                'voyager_key' => 'profiles.disable_profile_bio_excerpt',
                'default' => false,
                'cast' => 'bool',
            ],
            'max_profile_bio_length' => [
                'voyager_key' => 'profiles.max_profile_bio_length',
                'default' => 1500,
                'cast' => 'int',
            ],
            'enable_new_post_notification_setting' => [
                'voyager_key' => 'profiles.enable_new_post_notification_setting',
                'default' => true,
                'cast' => 'bool',
            ],
            'default_new_post_notification_setting' => [
                'voyager_key' => 'profiles.default_new_post_notification_setting',
                'default' => false,
                'cast' => 'bool',
            ],
            'disable_website_link_on_profile' => [
                'voyager_key' => 'profiles.disable_website_link_on_profile',
                'default' => false,
                'cast' => 'bool',
            ],
            'disable_profile_offers' => [
                'voyager_key' => 'profiles.disable_profile_offers',
                'default' => false,
                'cast' => 'bool',
            ],
            'allow_hyperlinks' => [
                'voyager_key' => 'profiles.allow_hyperlinks',
                'default' => true,
                'cast' => 'bool',
            ],
            'show_online_users_indicator' => [
                'voyager_key' => 'profiles.show_online_users_indicator',
                'default' => true,
                'cast' => 'bool',
            ],
            'record_users_last_activity_time' => [
                'voyager_key' => 'profiles.record_users_last_activity_time',
                'default' => true,
                'cast' => 'bool',
            ],
            'record_users_last_ip_address' => [
                'voyager_key' => 'profiles.record_users_last_ip_address',
                'default' => true,
                'cast' => 'bool',
            ],
        ];
    }

    protected function cast(string $type, mixed $value): mixed
    {
        return match ($type) {
            'bool' => (bool) $value,
            'int' => (int) $value,
            'float' => (float) $value,
            default => $value,
        };
    }
};
