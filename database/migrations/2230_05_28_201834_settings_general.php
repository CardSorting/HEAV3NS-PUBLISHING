<?php

use Illuminate\Support\Facades\DB;
use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        foreach ($this->getSettings() as $spatieKey => $meta) {
            $voyagerKey = $meta['voyager_key'];
            $value = DB::table('settings')->where('key', $voyagerKey)->value('value');
            if (!empty($meta['is_file'])) {
                $value = resolveVoyagerFilePath($value);
            }

            if (is_null($value)) {
                $value = is_callable($meta['default'] ?? null)
                    ? call_user_func($meta['default'])
                    : ($meta['default'] ?? null);
            }

            if (isset($meta['cast'])) {
                $value = $this->cast($meta['cast'], $value);
            }

            $this->migrator->add("site.$spatieKey", $value);
        }
    }

    public function down(): void
    {
        foreach ($this->getSettings() as $key => $_) {
            $this->migrator->delete("site.$key");
        }
    }

    protected function getSettings(): array
    {
        return [
            'name' => [
                'voyager_key' => 'site.name',
                'default' => 'My Site',
            ],
            'description' => [
                'voyager_key' => 'site.description',
                'default' => null,
            ],
            'light_logo' => [
                'voyager_key' => 'site.light_logo',
                'default' => null,
                'is_file' => true,
            ],
            'dark_logo' => [
                'voyager_key' => 'site.dark_logo',
                'default' => null,
                'is_file' => true,
            ],
            'favicon' => [
                'voyager_key' => 'site.favicon',
                'default' => null,
                'is_file' => true,
            ],
            'allow_theme_switch' => [
                'voyager_key' => 'site.allow_theme_switch',
                'default' => false,
                'cast' => 'bool',
            ],
            'default_user_theme' => [
                'voyager_key' => 'site.default_user_theme',
                'default' => 'light',
            ],
            'allow_direction_switch' => [
                'voyager_key' => 'site.allow_direction_switch',
                'default' => false,
                'cast' => 'bool',
            ],
            'default_site_direction' => [
                'voyager_key' => 'site.default_site_direction',
                'default' => 'ltr',
            ],
            'allow_language_switch' => [
                'voyager_key' => 'site.allow_language_switch',
                'default' => false,
                'cast' => 'bool',
            ],
            'default_site_language' => [
                'voyager_key' => 'site.default_site_language',
                'default' => 'en',
            ],
            'homepage_type' => [
                'voyager_key' => 'site.homepage_type',
                'default' => 'landing',
            ],
            'enforce_user_identity_checks' => [
                'voyager_key' => 'site.enforce_user_identity_checks',
                'default' => false,
                'cast' => 'bool',
            ],
            'app_url' => [
                'voyager_key' => 'site.app_url',
                'default' => fn () => config('app.url'),
            ],
            'allow_pwa_installs' => [
                'voyager_key' => 'site.allow_pwa_installs',
                'default' => false,
                'cast' => 'bool',
            ],
            'hide_identity_checks' => [
                'voyager_key' => 'site.hide_identity_checks',
                'default' => false,
                'cast' => 'bool',
            ],
            'slogan' => [
                'voyager_key' => 'site.slogan',
                'default' => null,
            ],
            'redirect_page_after_register' => [
                'voyager_key' => 'site.redirect_page_after_register',
                'default' => 'feed',
            ],
            'enforce_email_validation' => [
                'voyager_key' => 'site.enforce_email_validation',
                'default' => false,
                'cast' => 'bool',
            ],
            'homepage_redirect' => [
                'voyager_key' => 'site.homepage_redirect',
                'default' => null,
            ],
            'hide_create_post_menu' => [
                'voyager_key' => 'site.hide_create_post_menu',
                'default' => false,
                'cast' => 'bool',
            ],
            'default_og_image' => [
                'voyager_key' => 'site.default_og_image',
                'default' => null,
                'is_file' => true,
            ],
            'use_browser_language_if_available' => [
                'voyager_key' => 'site.use_browser_language_if_available',
                'default' => false,
                'cast' => 'bool',
            ],
            'timezone' => [
                'voyager_key' => 'site.timezone',
                'default' => 'UTC',
            ],
            'hide_stream_create_menu' => [
                'voyager_key' => 'site.hide_stream_create_menu',
                'default' => false,
                'cast' => 'bool',
            ],
            'login_page_background_image' => [
                'voyager_key' => 'site.login_page_background_image',
                'default' => null,
                'is_file' => true,
            ],
            'enable_smooth_page_change_transitions' => [
                'voyager_key' => 'site.enable_smooth_page_change_transitions',
                'default' => false,
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
