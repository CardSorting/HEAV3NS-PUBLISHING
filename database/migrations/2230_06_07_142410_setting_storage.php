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
                $value = is_callable($meta['default'] ?? null)
                    ? call_user_func($meta['default'])
                    : ($meta['default'] ?? null);
            }

            if (isset($meta['cast'])) {
                $value = $this->cast($meta['cast'], $value);
            }

            $this->migrator->add("storage.$spatieKey", $value);
        }
    }

    public function down(): void
    {
        foreach (array_keys($this->getSettings()) as $key) {
            $this->migrator->delete("storage.$key");
        }
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

    protected function getSettings(): array
    {
        return [
            'driver' => [
                'voyager_key' => 'storage.driver',
                'default' => 'public',
            ],
            'aws_access_key' => [
                'voyager_key' => 'storage.aws_access_key',
                'default' => null,
            ],
            'aws_secret_key' => [
                'voyager_key' => 'storage.aws_secret_key',
                'default' => null,
            ],
            'aws_region' => [
                'voyager_key' => 'storage.aws_region',
                'default' => null,
            ],
            'aws_bucket_name' => [
                'voyager_key' => 'storage.aws_bucket_name',
                'default' => null,
            ],
            'aws_cdn_enabled' => [
                'voyager_key' => 'storage.aws_cdn_enabled',
                'default' => false,
                'cast' => 'bool',
            ],
            'aws_cdn_presigned_urls_enabled' => [
                'voyager_key' => 'storage.aws_cdn_presigned_urls_enabled',
                'default' => false,
                'cast' => 'bool',
            ],
            'aws_cdn_key_pair_id' => [
                'voyager_key' => 'storage.aws_cdn_key_pair_id',
                'default' => null,
            ],
            'aws_cdn_private_key_path' => [
                'voyager_key' => 'storage.aws_cdn_private_key_path',
                'default' => null,
            ],
            'cdn_domain_name' => [
                'voyager_key' => 'storage.cdn_domain_name',
                'default' => null,
            ],
            'was_access_key' => [
                'voyager_key' => 'storage.was_access_key',
                'default' => null,
            ],
            'was_secret_key' => [
                'voyager_key' => 'storage.was_secret_key',
                'default' => null,
            ],
            'was_region' => [
                'voyager_key' => 'storage.was_region',
                'default' => null,
            ],
            'was_bucket_name' => [
                'voyager_key' => 'storage.was_bucket_name',
                'default' => null,
            ],
            'do_access_key' => [
                'voyager_key' => 'storage.do_access_key',
                'default' => null,
            ],
            'do_secret_key' => [
                'voyager_key' => 'storage.do_secret_key',
                'default' => null,
            ],
            'do_region' => [
                'voyager_key' => 'storage.do_region',
                'default' => null,
            ],
            'do_bucket_name' => [
                'voyager_key' => 'storage.do_bucket_name',
                'default' => null,
            ],
            'minio_access_key' => [
                'voyager_key' => 'storage.minio_access_key',
                'default' => null,
            ],
            'minio_secret_key' => [
                'voyager_key' => 'storage.minio_secret_key',
                'default' => null,
            ],
            'minio_region' => [
                'voyager_key' => 'storage.minio_region',
                'default' => null,
            ],
            'minio_bucket_name' => [
                'voyager_key' => 'storage.minio_bucket_name',
                'default' => null,
            ],
            'minio_endpoint' => [
                'voyager_key' => 'storage.minio_endpoint',
                'default' => null,
            ],
            'pushr_access_key' => [
                'voyager_key' => 'storage.pushr_access_key',
                'default' => null,
            ],
            'pushr_secret_key' => [
                'voyager_key' => 'storage.pushr_secret_key',
                'default' => null,
            ],
            'pushr_cdn_hostname' => [
                'voyager_key' => 'storage.pushr_cdn_hostname',
                'default' => null,
            ],
            'pushr_bucket_name' => [
                'voyager_key' => 'storage.pushr_bucket_name',
                'default' => null,
            ],
            'pushr_endpoint' => [
                'voyager_key' => 'storage.pushr_endpoint',
                'default' => null,
            ],
        ];
    }
};
