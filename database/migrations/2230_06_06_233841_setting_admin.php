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

            $this->migrator->add("admin.$key", $value);
        }
    }

    public function down(): void
    {
        foreach (array_keys($this->getSettings()) as $key) {
            $this->migrator->delete("admin.$key");
        }
    }

    protected function getSettings(): array
    {
        return [
            'title' => [
                'voyager_key' => 'admin.title',
                'default' => 'Admin Panel',
            ],
            'light_logo' => [
                'voyager_key' => 'admin.light_logo',
                'default' => null,
                'is_file' => true,
            ],
            'dark_logo' => [
                'voyager_key' => 'admin.dark_logo',
                'default' => null,
                'is_file' => true,
            ],
            'send_notifications_on_contact' => [
                'voyager_key' => 'admin.send_notifications_on_contact',
                'default' => false,
                'cast' => 'bool',
            ],
            'send_notifications_on_pending_posts' => [
                'voyager_key' => 'admin.send_notifications_on_pending_posts',
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
