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
                $value = $meta['default'] ?? null;
            }

            if (isset($meta['cast'])) {
                $value = $this->cast($meta['cast'], $value);
            }

            $this->migrator->add("compliance.$key", $value);
        }
    }

    public function down(): void
    {
        foreach (array_keys($this->getSettings()) as $key) {
            $this->migrator->delete("compliance.$key");
        }
    }

    protected function getSettings(): array
    {
        return [
            'enable_cookies_box' => [
                'voyager_key' => 'compliance.enable_cookies_box',
                'default' => false,
                'cast' => 'bool',
            ],
            'enable_age_verification_dialog' => [
                'voyager_key' => 'compliance.enable_age_verification_dialog',
                'default' => false,
                'cast' => 'bool',
            ],
            'age_verification_cancel_url' => [
                'voyager_key' => 'compliance.age_verification_cancel_url',
                'default' => null,
            ],
            'admin_approved_posts_limit' => [
                'voyager_key' => 'compliance.admin_approved_posts_limit',
                'default' => 0,
                'cast' => 'int',
            ],
            'minimum_posts_until_creator' => [
                'voyager_key' => 'compliance.minimum_posts_until_creator',
                'default' => 0,
                'cast' => 'int',
            ],
            'minimum_posts_deletion_limit' => [
                'voyager_key' => 'compliance.minimum_posts_deletion_limit',
                'default' => 0,
                'cast' => 'int',
            ],
            'monthly_posts_before_inactive' => [
                'voyager_key' => 'compliance.monthly_posts_before_inactive',
                'default' => 0,
                'cast' => 'int',
            ],
            'disable_creators_ppv_delete' => [
                'voyager_key' => 'compliance.disable_creators_ppv_delete',
                'default' => false,
                'cast' => 'bool',
            ],
            'allow_text_only_ppv' => [
                'voyager_key' => 'compliance.allow_text_only_ppv',
                'default' => false,
                'cast' => 'bool',
            ],
            'enforce_tos_check_on_id_verify' => [
                'voyager_key' => 'compliance.enforce_tos_check_on_id_verify',
                'default' => false,
                'cast' => 'bool',
            ],
            'enforce_media_agreement_on_id_verify' => [
                'voyager_key' => 'compliance.enforce_media_agreement_on_id_verify',
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
