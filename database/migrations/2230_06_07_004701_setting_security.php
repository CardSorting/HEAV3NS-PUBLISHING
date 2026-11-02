<?php

use Illuminate\Support\Facades\DB;
use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        foreach ($this->getSettings() as $key => $meta) {
            $voyagerKey = $meta['voyager_key'];
            $value = DB::table('settings')->where('key', $voyagerKey)->value('value') ?? $meta['default'] ?? null;

            if (isset($meta['cast'])) {
                $value = match ($meta['cast']) {
                    'bool' => (bool) $value,
                    default => $value,
                };
            }

            $this->migrator->add("security.$key", $value);
        }
    }

    public function down(): void
    {
        foreach (array_keys($this->getSettings()) as $key) {
            $this->migrator->delete("security.$key");
        }
    }

    protected function getSettings(): array
    {
        return [
            'enable_2fa' => ['voyager_key' => 'security.enable_2fa', 'default' => false, 'cast' => 'bool'],
            'default_2fa_on_register' => ['voyager_key' => 'security.default_2fa_on_register', 'default' => false, 'cast' => 'bool'],
            'allow_users_2fa_switch' => ['voyager_key' => 'security.allow_users_2fa_switch', 'default' => true, 'cast' => 'bool'],
            'enforce_app_ssl' => ['voyager_key' => 'security.enforce_app_ssl', 'default' => false, 'cast' => 'bool'],
            'allow_geo_blocking' => ['voyager_key' => 'security.allow_geo_blocking', 'default' => false, 'cast' => 'bool'],
            'enforce_email_valid_check' => ['voyager_key' => 'security.enforce_email_valid_check', 'default' => false, 'cast' => 'bool'],
            'email_abstract_api_key' => ['voyager_key' => 'security.email_abstract_api_key'],
            'abstract_api_key' => ['voyager_key' => 'security.abstract_api_key'],
            'captcha_driver' => ['voyager_key' => 'security.captcha_driver', 'default' => 'none'],
            'recaptcha_site_key' => ['voyager_key' => 'security.recaptcha_site_key'],
            'recaptcha_site_secret_key' => ['voyager_key' => 'security.recaptcha_site_secret_key'],
            'turnstile_site_key' => ['voyager_key' => 'security.turnstile_site_key'],
            'turnstile_site_secret_key' => ['voyager_key' => 'security.turnstile_site_secret_key'],
            'hcaptcha_site_key' => ['voyager_key' => 'security.hcaptcha_site_key'],
            'hcaptcha_site_secret_key' => ['voyager_key' => 'security.hcaptcha_site_secret_key'],
        ];
    }
};
