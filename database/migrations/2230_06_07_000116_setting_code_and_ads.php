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

            $this->migrator->add("code-and-ads.$key", $value);
        }
    }

    public function down(): void
    {
        foreach (array_keys($this->getSettings()) as $key) {
            $this->migrator->delete("code-and-ads.$key");
        }
    }

    protected function getSettings(): array
    {
        return [
            'custom_css' => [
                'voyager_key' => 'code-and-ads.custom_css',
                'default' => null,
            ],
            'custom_js' => [
                'voyager_key' => 'code-and-ads.custom_js',
                'default' => null,
            ],
            'sidebar_ad_spot' => [
                'voyager_key' => 'code-and-ads.sidebar_ad_spot',
                'default' => null,
            ],
        ];
    }
};
