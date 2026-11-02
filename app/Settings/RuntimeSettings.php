<?php

namespace App\Settings;

use App\Settings\Casts\EncryptedStringSettingsCast;
use Spatie\LaravelSettings\Settings;

class RuntimeSettings extends Settings
{
    public string $cache_driver = 'file';

    public ?string $cache_prefix = null;

    public ?string $cache_redis_host = '127.0.0.1';

    public ?string $cache_redis_port = '6379';

    public ?string $cache_redis_password = null;

    public string $session_driver = 'file';

    public int $session_lifetime = 43200;

    public bool $session_expire_on_close = false;

    public bool $session_encrypt = false;

    public ?string $session_redis_host = '127.0.0.1';

    public ?string $session_redis_port = '6379';

    public ?string $session_redis_password = null;

    public string $queue_execution_mode = 'sync';

    public string $queue_driver = 'database';

    public string $queue_name = 'default';

    public ?string $queue_redis_url = null;

    public ?string $queue_redis_scheme = 'tcp';

    public ?string $queue_redis_host = '127.0.0.1';

    public ?string $queue_redis_port = '6379';

    public ?string $queue_redis_username = null;

    public ?string $queue_redis_password = null;

    public ?string $queue_redis_database = '0';

    public ?string $queue_redis_prefix = null;

    public ?int $queue_redis_retry_after = 960;

    public ?int $queue_redis_block_for = null;

    public ?string $queue_sqs_key = null;

    public ?string $queue_sqs_secret = null;

    public ?string $queue_sqs_prefix = null;

    public ?string $queue_sqs_region = 'us-east-1';

    public static function casts(): array
    {
        return [
            'queue_redis_url' => EncryptedStringSettingsCast::class,
            'queue_redis_password' => EncryptedStringSettingsCast::class,
            'queue_sqs_secret' => EncryptedStringSettingsCast::class,
        ];
    }

    public static function group(): string
    {
        return 'runtime';
    }
}
