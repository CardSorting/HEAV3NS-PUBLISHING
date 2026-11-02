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

            $this->migrator->add("referrals.$key", $value);
        }
    }

    public function down(): void
    {
        foreach (array_keys($this->getSettings()) as $key) {
            $this->migrator->delete("referrals.$key");
        }
    }

    protected function getSettings(): array
    {
        return [
            'enabled' => [
                'voyager_key' => 'referrals.enabled',
                'default' => false,
                'cast' => 'bool',
            ],
            'fee_percentage' => [
                'voyager_key' => 'referrals.fee_percentage',
                'default' => 0,
                'cast' => 'int',
            ],
            'apply_for_months' => [
                'voyager_key' => 'referrals.apply_for_months',
                'default' => 0,
                'cast' => 'int',
            ],
            'fee_limit' => [
                'voyager_key' => 'referrals.fee_limit',
                'default' => 0,
                'cast' => 'int',
            ],
            'referrals_default_link_page' => [
                'voyager_key' => 'referrals.referrals_default_link_page',
                'default' => 'profile',
            ],
            'disable_for_non_verified' => [
                'voyager_key' => 'referrals.disable_for_non_verified',
                'default' => false,
                'cast' => 'bool',
            ],
            'auto_follow_the_user' => [
                'voyager_key' => 'referrals.auto_follow_the_user',
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
