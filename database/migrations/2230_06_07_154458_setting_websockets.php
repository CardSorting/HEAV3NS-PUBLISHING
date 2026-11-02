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

            $this->migrator->add("websockets.$spatieKey", $value);
        }
    }

    public function down(): void
    {
        foreach (array_keys($this->getSettings()) as $key) {
            $this->migrator->delete("websockets.$key");
        }
    }

    protected function getSettings(): array
    {
        return [
            'driver' => [
                'voyager_key' => 'websockets.driver',
                'default' => 'pusher',
            ],
            'pusher_app_id' => [
                'voyager_key' => 'websockets.pusher_app_id',
                'default' => null,
            ],
            'pusher_app_key' => [
                'voyager_key' => 'websockets.pusher_app_key',
                'default' => null,
            ],
            'pusher_app_secret' => [
                'voyager_key' => 'websockets.pusher_app_secret',
                'default' => null,
            ],
            'pusher_app_cluster' => [
                'voyager_key' => 'websockets.pusher_app_cluster',
                'default' => null,
            ],
            'soketi_host_address' => [
                'voyager_key' => 'websockets.soketi_host_address',
                'default' => null,
            ],
            'soketi_host_port' => [
                'voyager_key' => 'websockets.soketi_host_port',
                'default' => null,
            ],
            'soketi_app_id' => [
                'voyager_key' => 'websockets.soketi_app_id',
                'default' => null,
            ],
            'soketi_app_key' => [
                'voyager_key' => 'websockets.soketi_app_key',
                'default' => null,
            ],
            'soketi_app_secret' => [
                'voyager_key' => 'websockets.soketi_app_secret',
                'default' => null,
            ],
            'soketi_use_TSL' => [
                'voyager_key' => 'websockets.soketi_use_TSL',
                'default' => '0',
            ],
        ];
    }
};
