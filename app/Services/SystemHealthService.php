<?php

namespace App\Services;

use App\Model\ManualPaymentMethod;
use App\Providers\InstallerServiceProvider;
use App\Services\Queue\QueueRuntimeService;
use App\Support\PhpRuntimeRequirements;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

class SystemHealthService
{
    public const SCHEDULER_HEARTBEAT = 'system-health:scheduler-last-run';
    private const PROVIDER_CREDENTIALS = [
        'stripe' => ['stripe_secret_key'],
        'paypal' => ['paypal_client_id', 'paypal_secret'],
        'nowpayments' => ['nowpayments_api_key'],
        'ccbill' => ['ccbill_account_number', 'ccbill_salt_key'],
        'verotel' => ['verotel_merchant_id', 'verotel_signature_key'],
        'paystack' => ['paystack_secret_key'],
        'yookassa' => ['yookassa_shop_id', 'yookassa_secret_key'],
        'mollie' => ['mollie_api_key'],
        'flutterwave' => ['flutterwave_secret_key'],
        'coingate' => ['coingate_api_token'],
        'xendit' => ['xendit_secret_key'],
        'paddle' => ['paddle_api_key'],
        'cryptocom' => ['cryptocom_secret_key'],
        'mercado' => ['mercado_access_token'],
        'razorpay' => ['razorpay_api_key', 'razorpay_api_secret'],
    ];

    // Only these setting values can appear in a support report.
    private const SETTINGS = [
        'media' => ['transcoding_driver', 'max_file_upload_size', 'max_avatar_cover_file_size', 'allowed_file_extensions', 'use_chunked_uploads', 'upload_chunk_size', 'enforce_mp4_conversion', 'max_videos_length', 'vault_enabled', 'voice_messages_enabled'],
        'payments' => ['currency_code', 'billing_details_collection', 'deposit_min_amount', 'deposit_max_amount', 'withdrawal_min_amount', 'withdrawal_max_amount', 'withdrawal_allow_fees', 'withdrawal_default_fee_percentage', 'withdrawal_allow_only_for_verified', 'withdrawal_enable_stripe_connect', 'invoices_enabled', 'paypal_live_mode', 'coingate_mode'],
        'features' => ['reels.reels_enabled', 'stories.stories_enabled', 'streams.streaming_driver'],
    ];

    public function __construct(private QueueRuntimeService $queueRuntime) {}

    public static function recordSchedulerHeartbeat(): void
    {
        try {
            Cache::put(self::SCHEDULER_HEARTBEAT, now()->toIso8601String(), 86400);
        } catch (Throwable) {
            // Diagnostics must never prevent other scheduled commands from running.
        }
    }

    public function snapshot(): array
    {
        $checks = [];
        foreach (['application', 'database', 'cache', 'storage', 'queue', 'scheduler', 'media'] as $name) {
            try {
                $checks[$name] = $this->{$name.'Check'}();
            } catch (Throwable) {
                // Exception messages can contain connection credentials or paths.
                $checks[$name] = $this->check('error', 'This check could not be completed. Review the application logs or the relevant settings.');
            }
        }

        $settings = $this->diagnosticSettings();
        $statuses = array_column($checks, 'status');
        $overall = in_array('error', $statuses, true) ? 'error'
            : (in_array('warning', $statuses, true) ? 'warning'
                : (in_array('unknown', $statuses, true) ? 'unknown' : 'ok'));
        $report = [
            'checked_at' => now()->toIso8601String(),
            'overall' => $overall,
            'checks' => $checks,
            'settings' => $settings,
        ];

        return [...$report, 'diagnostics' => json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE)];
    }

    protected function check(string $status, string $message, array $details = []): array
    {
        return compact('status', 'message', 'details');
    }

    protected function applicationCheck(): array
    {
        $missing = array_values(array_filter(InstallerServiceProvider::getRequiredExtensions(), static fn ($extension) => !extension_loaded($extension)));
        $supported = PhpRuntimeRequirements::supports();
        $debug = (bool) config('app.debug');
        $productionDebug = app()->environment('production') && $debug;

        return $this->check(
            !$supported || $missing !== [] ? 'error' : ($productionDebug ? 'warning' : 'ok'),
            !$supported || $missing !== [] ? 'PHP requirements are not met.' : ($productionDebug ? 'Debug mode is enabled in production.' : 'PHP requirements are met.'),
            [
                'script_version' => is_readable(public_path('version')) ? trim(file_get_contents(public_path('version'))) : 'unknown',
                'php_version' => PHP_VERSION,
                'minimum_php_version' => PhpRuntimeRequirements::MINIMUM_VERSION,
                'laravel_version' => app()->version(),
                'environment' => app()->environment(),
                'timezone' => config('app.timezone'),
                'debug' => $debug,
                'missing_extensions' => $missing,
                'memory_limit' => ini_get('memory_limit'),
                'max_execution_time' => ini_get('max_execution_time'),
                'upload_max_filesize' => ini_get('upload_max_filesize'),
                'post_max_size' => ini_get('post_max_size'),
                'session_driver' => config('session.driver'),
                'session_lifetime_minutes' => config('session.lifetime'),
            ]
        );
    }

    protected function databaseCheck(): array
    {
        DB::select('SELECT 1');
        $pending = InstallerServiceProvider::hasAvailableMigrations();

        return $this->check($pending ? 'warning' : 'ok', $pending ? 'Database is reachable; migrations are pending.' : 'Database is reachable and migrations are up to date.', [
            'driver' => DB::connection()->getDriverName(),
            'version' => DB::connection()->getPdo()->getAttribute(\PDO::ATTR_SERVER_VERSION),
            'pending_migrations' => $pending,
        ]);
    }

    protected function cacheCheck(): array
    {
        $key = 'system-health:probe:'.bin2hex(random_bytes(12));
        $value = bin2hex(random_bytes(12));
        try {
            Cache::put($key, $value, 30);
            $working = Cache::get($key) === $value;
        } finally {
            Cache::forget($key);
        }

        return $this->check($working ? 'ok' : 'error', $working ? 'Cache write/read test passed.' : 'Cache write/read test failed.', ['driver' => config('cache.default')]);
    }

    protected function storageCheck(): array
    {
        $directories = [
            'app_writable' => storage_path('app'),
            'logs_writable' => storage_path('logs'),
            'framework_writable' => storage_path('framework'),
            'bootstrap_cache_writable' => base_path('bootstrap/cache'),
        ];
        $details = ['driver' => $this->setting('storage.driver')];
        foreach ($directories as $label => $directory) {
            $details[$label] = is_dir($directory) && is_writable($directory);
        }
        $details['public_link_valid'] = is_dir(public_path('storage')) && realpath(public_path('storage')) === realpath(storage_path('app/public'));
        $writable = !in_array(false, array_intersect_key($details, $directories), true);
        $local = in_array($details['driver'], ['public', 'local'], true);

        return $this->check(
            !$writable ? 'error' : ($local && !$details['public_link_valid'] ? 'warning' : 'ok'),
            !$writable ? 'Required application directories are not writable.' : ($local && !$details['public_link_valid'] ? 'The public storage link is missing or points to the wrong directory.' : 'Local storage permissions are available. Remote storage connectivity is not tested.'),
            $details
        );
    }

    protected function queueCheck(): array
    {
        $snapshot = $this->queueRuntime->snapshot();
        $observed = $snapshot['runtime_state']['status'];
        $status = match ($observed) {
            'online', 'completed' => 'ok',
            'failed', 'unavailable' => 'error',
            'checking', 'not_responding' => 'warning',
            default => $snapshot['mode'] === QueueRuntimeService::MODE_SYNC ? 'ok' : 'unknown',
        };
        if ($snapshot['failed_jobs_count'] > 0 && $status === 'ok') {
            $status = 'warning';
        }

        return $this->check($status, $snapshot['mode'] === QueueRuntimeService::MODE_SYNC ? 'Jobs run within the request; no worker is required.' : $snapshot['runtime_state']['label'], [
            'execution_mode' => $snapshot['mode'],
            'connection' => $snapshot['connection'],
            'queue_name' => $snapshot['queue_name'],
            'observed_status' => $observed,
            'failed_jobs_count' => $snapshot['failed_jobs_count'],
            'waiting' => $snapshot['database']['waiting'] ?? null,
            'delayed' => $snapshot['database']['delayed'] ?? null,
            'processing' => $snapshot['database']['processing'] ?? null,
            ...($snapshot['mode'] === QueueRuntimeService::MODE_BACKGROUND ? [
                'background_php_version' => $snapshot['background_runtime']['php_version'] ?? null,
                'background_php_status' => $snapshot['background_runtime']['status'] ?? 'unavailable',
                'background_missing_extensions' => $snapshot['background_runtime']['missing_extensions'] ?? [],
            ] : []),
        ]);
    }

    protected function schedulerCheck(): array
    {
        $lastRun = Cache::get(self::SCHEDULER_HEARTBEAT);
        if (!is_string($lastRun) || $lastRun === '') {
            return $this->check('unknown', 'No heartbeat detected. Run schedule:run every minute via cron.', ['last_run_at' => null]);
        }
        $observed = CarbonImmutable::parse($lastRun);
        $recent = $observed->between(now()->subMinutes(15), now()->addMinute());

        return $this->check($recent ? 'ok' : 'warning', $recent ? 'The scheduler ran recently.' : 'No scheduler heartbeat was observed within the last 15 minutes.', ['last_run_at' => $observed->toIso8601String()]);
    }

    protected function mediaCheck(): array
    {
        $driver = $this->setting('media.transcoding_driver');
        if ($driver !== 'ffmpeg') {
            return $this->check('unknown', 'Local FFmpeg checks are not required for this configuration. Remote transcoding is not tested.', ['transcoding_driver' => $driver]);
        }
        $ffmpeg = $this->binaryAvailable((string) config('laravel-ffmpeg.ffmpeg.binaries', 'ffmpeg'));
        $ffprobe = $this->binaryAvailable((string) config('laravel-ffmpeg.ffprobe.binaries', 'ffprobe'));

        $available = $ffmpeg && $ffprobe && function_exists('proc_open');

        return $this->check($available ? 'ok' : 'warning', $available ? 'FFmpeg and FFprobe executable files were found; conversion is not tested.' : 'FFmpeg, FFprobe or process execution is unavailable. Check media configuration and hosting restrictions.', [
            'transcoding_driver' => $driver,
            'ffmpeg_executable_found' => $ffmpeg,
            'ffprobe_executable_found' => $ffprobe,
            'process_execution_available' => function_exists('proc_open'),
        ]);
    }

    protected function binaryAvailable(string $binary): bool
    {
        if (str_contains($binary, '/') || str_contains($binary, '\\')) {
            return is_file($binary) && is_executable($binary);
        }
        foreach (explode(PATH_SEPARATOR, (string) getenv('PATH')) as $directory) {
            if ($directory !== '' && is_file($directory.DIRECTORY_SEPARATOR.$binary) && is_executable($directory.DIRECTORY_SEPARATOR.$binary)) {
                return true;
            }
        }

        return false;
    }

    protected function setting(string $key): mixed
    {
        try {
            return getSetting($key);
        } catch (Throwable) {
            return null;
        }
    }

    protected function diagnosticSettings(): array
    {
        $settings = [];
        foreach (self::SETTINGS as $group => $keys) {
            foreach ($keys as $key) {
                $settings[$group][$key] = $this->setting(str_contains($key, '.') ? $key : $group.'.'.$key);
            }
        }
        foreach (self::PROVIDER_CREDENTIALS as $provider => $credentialKeys) {
            $settings['payments']['providers'][$provider]['checkout_disabled'] = $this->setting('payments.'.$provider.'_checkout_disabled');
            $settings['payments']['providers'][$provider]['credentials_configured'] = !in_array(false, array_map(fn ($key) => !empty($this->setting('payments.'.$key)), $credentialKeys), true);
        }
        $stripeKey = $this->setting('payments.stripe_secret_key');
        $settings['payments']['providers']['stripe']['mode'] = match (true) {
            !is_string($stripeKey) || $stripeKey === '' => 'not_configured',
            str_starts_with($stripeKey, 'sk_test_'), str_starts_with($stripeKey, 'rk_test_') => 'test',
            str_starts_with($stripeKey, 'sk_live_'), str_starts_with($stripeKey, 'rk_live_') => 'live',
            default => 'unknown',
        };
        $settings['email'] = [
            'driver' => $this->setting('emails.driver'),
            'smtp_host_configured' => !empty($this->setting('emails.smtp_host')),
            'smtp_port' => $this->setting('emails.smtp_port'),
            'smtp_encryption' => $this->setting('emails.smtp_encryption'),
            'smtp_credentials_configured' => !empty($this->setting('emails.smtp_username')) && !empty($this->setting('emails.smtp_password')),
            'mailgun_credentials_configured' => !empty($this->setting('emails.mailgun_secret')),
            'delivery_tested' => false,
        ];
        $settings['storage'] = ['driver' => $this->setting('storage.driver'), 'cdn_enabled' => $this->setting('storage.aws_cdn_enabled')];
        try {
            $settings['payments']['manual_methods'] = Schema::hasTable('manual_payment_methods')
                ? ManualPaymentMethod::query()->orderBy('direction')->orderBy('sort_order')->get(['name', 'direction', 'enabled'])->toArray()
                : [];
        } catch (Throwable) {
            $settings['payments']['manual_methods'] = null;
        }

        return $settings;
    }
}
