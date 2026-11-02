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

            if (is_null($value)) {
                $value = $meta['default'] ?? null;
            }

            $this->migrator->add("colors.$spatieKey", $value);
        }
    }

    public function down(): void
    {
        foreach (array_keys($this->getSettings()) as $key) {
            $this->migrator->delete("colors.$key");
        }
    }

    protected function getSettings(): array
    {
        return [
            // General config
            'theme_color_code' => ['voyager_key' => 'colors.theme_color_code'],
            'theme_gradient_from' => ['voyager_key' => 'colors.theme_gradient_from'],
            'theme_gradient_to' => ['voyager_key' => 'colors.theme_gradient_to'],
        ];
    }
};
