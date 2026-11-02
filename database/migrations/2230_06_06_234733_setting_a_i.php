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

            $this->migrator->add("ai.$key", $value);
        }
    }

    public function down(): void
    {
        foreach (array_keys($this->getSettings()) as $key) {
            $this->migrator->delete("ai.$key");
        }
    }

    protected function getSettings(): array
    {
        return [
            'open_ai_enabled' => [
                'voyager_key' => 'ai.open_ai_enabled',
                'default' => false,
                'cast' => 'bool',
            ],
            'open_ai_api_key' => [
                'voyager_key' => 'ai.open_ai_api_key',
                'default' => '',
            ],
            'open_ai_completion_max_tokens' => [
                'voyager_key' => 'ai.open_ai_completion_max_tokens',
                'default' => 100,
                'cast' => 'int',
            ],
            'open_ai_completion_temperature' => [
                'voyager_key' => 'ai.open_ai_completion_temperature',
                'default' => 1.0,
                'cast' => 'float',
            ],
            'open_ai_model' => [
                'voyager_key' => 'ai.open_ai_model',
                'default' => 'o4-mini',
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
