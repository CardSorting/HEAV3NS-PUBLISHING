<?php

namespace App\Providers;

use App\Services\PwaAssetGenerator;
use App\Services\Queue\QueueRuntimeService;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\ServiceProvider;

class SettingsServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     *
     * @return void
     */
    public function register()
    {
        //
    }

    /**
     * Bootstrap services.
     *
     * @return void
     */
    public function boot()
    {

        // Backwards comp for migrating filament settings on fresh installments
        if (config("settings.admin_version") === 'v1') {
            Config::set('settings.repositories.database.table', 'settings_new');
        }

        if (!InstallerServiceProvider::checkIfInstalled()) {
            return;
        }

        // Overriding config values for 3rd party implementations with DB values
        config(['laravel-ffmpeg.ffmpeg.binaries' => getSetting('media.ffmpeg_path', config('laravel-ffmpeg.ffmpeg.binaries'))]);
        config(['laravel-ffmpeg.ffprobe.binaries' => getSetting('media.ffprobe_path', config('laravel-ffmpeg.ffprobe.binaries'))]);

        // Websockets settings handling
        config(['broadcasting.default' => 'pusher']);
        if(self::hasPusherSettings()){
            if (getSetting('websockets.pusher_app_key')) {
                config(['broadcasting.connections.pusher.key' => getSetting('websockets.pusher_app_key')]);
            }
            if (getSetting('websockets.pusher_app_id')) {
                config(['broadcasting.connections.pusher.app_id' => getSetting('websockets.pusher_app_id')]);
            }
            if (getSetting('websockets.pusher_app_secret')) {
                config(['broadcasting.connections.pusher.secret' => getSetting('websockets.pusher_app_secret')]);
            }
            if (getSetting('websockets.pusher_app_cluster')) {
                config(['broadcasting.connections.pusher.options.cluster' => getSetting('websockets.pusher_app_cluster')]);
            }
        }
        if(self::hasSoketiSettings()){
            if (getSetting('websockets.soketi_app_key')) {
                config(['broadcasting.connections.soketi.key' => getSetting('websockets.soketi_app_key')]);
            }
            if (getSetting('websockets.soketi_app_id')) {
                config(['broadcasting.connections.soketi.app_id' => getSetting('websockets.soketi_app_id')]);
            }
            if (getSetting('websockets.soketi_app_secret')) {
                config(['broadcasting.connections.soketi.secret' => getSetting('websockets.soketi_app_secret')]);
            }
            if (getSetting('websockets.soketi_host_address')) {
                config(['broadcasting.connections.soketi.options.host' => getSetting('websockets.soketi_host_address')]);
            }
            if (getSetting('websockets.soketi_host_port')) {
                config(['broadcasting.connections.soketi.options.port' => getSetting('websockets.soketi_host_port')]);
            }
            if (getSetting('websockets.soketi_use_TSL')) {
                config(['broadcasting.connections.soketi.options.scheme' => 'https']);
                config(['broadcasting.connections.soketi.options.useTLS' => true]);
            }
        }
        if(getSetting('websockets.driver') == 'soketi'){
            config(['broadcasting.connections.pusher' => config('broadcasting.connections.soketi')]);
        }

        config(['paypal.settings.mode' => getSetting('payments.paypal_live_mode') ? 'live' : 'sandbox']);

        if (getSetting('payments.paypal_client_id')) {
            config(['paypal.client_id' => getSetting('payments.paypal_client_id')]);
        }

        if (getSetting('payments.paypal_secret')) {
            config(['paypal.secret' => getSetting('payments.paypal_secret')]);
        }

        config(['app.url' => getSetting('site.app_url')]);
        self::setUpEmailCredentials();
        self::setUpStorageCredentials();
        self::setDefaultStorageDriver();
        self::setUpRuntimeCredentials();
        self::setUpQueueConfiguration();
        self::setupLocalStorage();

        if (getSetting('payments.currency_code') != null && !empty(getSetting('payments.currency_code'))) {
            config(['app.site.currency_code' => getSetting('payments.currency_code')]);
        }

        if (getSetting('payments.currency_symbol') !== null && !empty(getSetting('payments.currency_symbol'))) {
            config(['app.site.currency_symbol' => getSetting('payments.currency_symbol')]);
        }

        $this->setupPwaConfig();

        // Social logins overrides
        if (getSetting('profiles.social_auth_facebook_client_id')) {
            config(['services.facebook.client_id' => getSetting('profiles.social_auth_facebook_client_id')]);
            config(['services.facebook.client_secret' => getSetting('profiles.social_auth_facebook_secret')]);
            config(['services.facebook.redirect' => rtrim(getSetting('site.app_url'), '/').'/socialAuth/facebook/callback']);
        }
        if (getSetting('profiles.social_auth_twitter_client_id')) {
            config(['services.twitter.client_id' => getSetting('profiles.social_auth_twitter_client_id')]);
            config(['services.twitter.client_secret' => getSetting('profiles.social_auth_twitter_secret')]);
            config(['services.twitter.redirect' => rtrim(getSetting('site.app_url'), '/').'/socialAuth/twitter/callback']);
        }
        if (getSetting('profiles.social_auth_google_client_id')) {
            config(['services.google.client_id' => getSetting('profiles.social_auth_google_client_id')]);
            config(['services.google.client_secret' => getSetting('profiles.social_auth_google_secret')]);
            config(['services.google.redirect' => rtrim(getSetting('site.app_url'), '/').'/socialAuth/google/callback']);
        }

        // Allow proxied requests, fixing 403 email verify issues on nginx and load balancers
        // TODO: Check if this still works with L9
        config(['trustedproxy.proxies' => '*']);

        if(getSetting('security.captcha_driver') !== 'none'){
            if(getSetting('security.captcha_driver') == 'recaptcha'){
                if(getSetting('security.recaptcha_site_key')){
                    config(['captcha.sitekey' => getSetting('security.recaptcha_site_key')]);
                }
                if(getSetting('security.recaptcha_site_secret_key')){
                    config(['captcha.secret' => getSetting('security.recaptcha_site_secret_key')]);
                }
            }
            if(getSetting('security.captcha_driver') == 'hcaptcha'){
                if(getSetting('security.hcaptcha_site_key')){
                    config(['captcha.sitekey' => getSetting('security.hcaptcha_site_key')]);
                }
                if(getSetting('security.hcaptcha_site_secret_key')){
                    config(['captcha.secret' => getSetting('security.hcaptcha_site_secret_key')]);
                }
            }
            if(getSetting('security.captcha_driver') == 'turnstile'){
                if(getSetting('security.turnstile_site_key')){
                    config(['captcha.sitekey' => getSetting('security.turnstile_site_key')]);
                }
                if(getSetting('security.turnstile_site_secret_key')){
                    config(['captcha.secret' => getSetting('security.turnstile_site_secret_key')]);
                }
            }

            if(config('captcha.sitekey') && config('captcha.secret')){
                config(['captcha.driver' => getSetting('security.captcha_driver')]);
            }
        }

        if(getSetting('profiles.allow_hyperlinks')){
            config(['purifier.settings.default' => array_merge(config('purifier.settings.default'), [
                'HTML.Allowed' => 'b,strong,blockquote,code,pre,i,em,u,ul,ol,li,p,br,span,a[href|title]',
            ])]);
        }
    }

    public static function setUpRuntimeCredentials(bool $applySessionConfig = true): void
    {
        try {
            $cacheDriver = getSetting('runtime.cache_driver', config('cache.default'));
            $cachePrefix = getSetting('runtime.cache_prefix');
            $sessionDriver = getSetting('runtime.session_driver', config('session.driver'));
        } catch (\Throwable $e) {
            return;
        }

        config([
            'cache.default' => $cacheDriver,
        ]);

        if ($applySessionConfig) {
            config([
                'session.driver' => $sessionDriver,
                'session.lifetime' => (int) getSetting('runtime.session_lifetime', config('session.lifetime')),
                'session.expire_on_close' => (bool) getSetting('runtime.session_expire_on_close', config('session.expire_on_close')),
                'session.encrypt' => (bool) getSetting('runtime.session_encrypt', config('session.encrypt')),
                'session.connection' => config('session.connection'),
                'session.table' => config('session.table', 'sessions'),
                'session.store' => null,
            ]);
        }

        if ($cachePrefix) {
            config(['cache.prefix' => $cachePrefix]);
        }

        $redisConfigChanged = false;

        if ($cacheDriver === 'redis') {
            config([
                'database.redis.cache' => [
                    'url' => null,
                    'host' => getSetting('runtime.cache_redis_host', config('database.redis.cache.host', '127.0.0.1')),
                    'password' => getSetting('runtime.cache_redis_password', config('database.redis.cache.password')),
                    'port' => getSetting('runtime.cache_redis_port', config('database.redis.cache.port', '6379')),
                    'database' => config('database.redis.cache.database', '1'),
                ],
                'cache.stores.redis.connection' => 'cache',
            ]);

            $redisConfigChanged = true;
        }

        if ($applySessionConfig && $sessionDriver === 'redis') {
            config([
                'database.redis.sessions' => [
                    'url' => null,
                    'host' => getSetting('runtime.session_redis_host', config('database.redis.default.host', '127.0.0.1')),
                    'password' => getSetting('runtime.session_redis_password', config('database.redis.default.password')),
                    'port' => getSetting('runtime.session_redis_port', config('database.redis.default.port', '6379')),
                    'database' => '2',
                ],
                'session.connection' => 'sessions',
            ]);

            $redisConfigChanged = true;
        }

        if ($redisConfigChanged) {
            app()->forgetInstance('redis');
        }

    }

    public static function setUpQueueConfiguration(): void
    {
        try {
            $mode = QueueRuntimeService::normalizeMode((string) getSetting('runtime.queue_execution_mode', 'sync'));
            $driver = QueueRuntimeService::normalizeWorkerConnection((string) getSetting('runtime.queue_driver', 'database'));
            $queueName = QueueRuntimeService::normalizeQueueName((string) getSetting('runtime.queue_name', 'default'));
        } catch (\Throwable $e) {
            return;
        }

        $connection = QueueRuntimeService::connectionFor($mode, $driver);

        config([
            'queue.runtime_mode' => $mode,
            'queue.default' => $connection,
            'queue.failed.driver' => 'database',
            'queue.connections.sync.after_commit' => true,
            'queue.connections.deferred' => [
                'driver' => 'deferred',
                'after_commit' => true,
            ],
            'queue.connections.background' => [
                'driver' => 'background',
                'after_commit' => true,
            ],
            'queue.connections.database.table' => 'jobs',
            'queue.connections.database.queue' => $queueName,
            'queue.connections.database.retry_after' => 960,
            'queue.connections.database.after_commit' => true,
            'database.redis.queue' => [
                'url' => getSetting('runtime.queue_redis_url'),
                'scheme' => getSetting('runtime.queue_redis_scheme', 'tcp'),
                'host' => getSetting('runtime.queue_redis_host', '127.0.0.1'),
                'username' => getSetting('runtime.queue_redis_username'),
                'password' => getSetting('runtime.queue_redis_password'),
                'port' => getSetting('runtime.queue_redis_port', '6379'),
                'database' => getSetting('runtime.queue_redis_database', '0'),
                'prefix' => getSetting('runtime.queue_redis_prefix', config('database.redis.options.prefix')),
            ],
            'queue.connections.redis.connection' => 'queue',
            'queue.connections.redis.queue' => $queueName,
            'queue.connections.redis.retry_after' => max(960, (int) getSetting('runtime.queue_redis_retry_after', 960)),
            'queue.connections.redis.block_for' => getSetting('runtime.queue_redis_block_for'),
            'queue.connections.redis.after_commit' => true,
            'queue.connections.sqs.key' => getSetting('runtime.queue_sqs_key'),
            'queue.connections.sqs.secret' => getSetting('runtime.queue_sqs_secret'),
            'queue.connections.sqs.prefix' => getSetting('runtime.queue_sqs_prefix'),
            'queue.connections.sqs.queue' => $queueName,
            'queue.connections.sqs.region' => getSetting('runtime.queue_sqs_region', 'us-east-1'),
            'queue.connections.sqs.after_commit' => true,
        ]);

        app()->forgetInstance('redis');
    }

    /**
     * Gets site's currency symbol with currency code fallback.
     * @return \Illuminate\Config\Repository|mixed|string
     */
    public static function getWebsiteCurrencySymbol()
    {
        $symbol = '$';
        if (getSetting('payments.currency_symbol') != null && !empty(getSetting('payments.currency_symbol'))) {
            $symbol = getSetting('payments.currency_symbol');
        } elseif (getSetting('payments.currency_code') != null && !empty(getSetting('payments.currency_code'))) {
            $symbol = getSetting('payments.currency_code');
        }

        return $symbol;
    }

    /**
     * Gets site's currency symbol.
     * @return bool|\Illuminate\Config\Repository|mixed
     */
    public static function getAppCurrencySymbol()
    {
        if (getSetting('payments.currency_symbol') != null && !empty(getSetting('payments.currency_symbol'))) {
            return getSetting('payments.currency_symbol');
        }

        return false;
    }

    /**
     * Gets site's currency code.
     * @return \Illuminate\Config\Repository|mixed|string
     */
    public static function getAppCurrencyCode()
    {
        $symbol = 'USD';
        if (getSetting('payments.currency_code') != null && !empty(getSetting('payments.currency_code'))) {
            $symbol = getSetting('payments.currency_code');
        }

        return $symbol;
    }

    /**
     * Check if website has pusher settings set.
     * @return bool
     */
    public static function hasPusherSettings() {
        return getSetting('websockets.pusher_app_cluster')
            && getSetting('websockets.pusher_app_key')
            && getSetting('websockets.pusher_app_secret')
            && getSetting('websockets.pusher_app_id');
    }

    /**
     * Check if website has soketi settings set.
     * @return bool
     */
    private static function hasSoketiSettings() {
        return getSetting('websockets.soketi_host_address')
            && getSetting('websockets.soketi_host_port')
            && getSetting('websockets.soketi_app_id')
            && getSetting('websockets.soketi_app_key')
            && getSetting('websockets.soketi_app_secret');
    }

    /**
     * Check if admin provided CCBill DataLink credentials.
     * @return bool
     */
    public static function providedCCBillSubscriptionCancellingCredentials() {
        return getSetting('payments.ccbill_datalink_username')
            && getSetting('payments.ccbill_datalink_password');
    }

    public static function allowWithdrawals($user)
    {
        if ($user->isBanned()) {
            return false;
        }
        $onlyVerified = (bool) getSetting('payments.withdrawal_allow_only_for_verified');

        return !$onlyVerified
            || (
                $user->email_verified_at
                && $user->birthdate
                && $user->verification
                && $user->verification->status == 'verified'
            );
    }

    public static function setDefaultStorageDriver($storageDriver = false) {
        if($storageDriver === false){
            $storageDriver = getSetting('storage.driver') != null ? getSetting('storage.driver') : 'public';
        }
        config(['filesystems.default' => $storageDriver]);
        config(['filesystems.defaultFilesystemDriver' => $storageDriver]);
        config(['livewire.temporary_file_upload.disk' => 'local']);
        config(['filament.default_filesystem_disk' => $storageDriver]); //todo: maybe if doing this, ->disk wont be required in resource
        config(['livewire.temporary_file_upload.rules' => 'file|max:'.AttachmentServiceProvider::getUploadMaxFilesize()]);
    }

    public static function getWebsiteCurrencyPosition() {
        $currencyPosition = 'left';
        $adminCurrencyPosition = getSetting('payments.currency_position');

        if(!empty($adminCurrencyPosition)) {
            $currencyPosition = $adminCurrencyPosition;
        }

        return $currencyPosition;
    }

    /**
     * @return bool
     */
    public static function leftAlignedCurrencyPosition() {
        return self::getWebsiteCurrencyPosition() === 'left';
    }

    /**
     * Format amount using the website currency symbol and the currency position
     * The default is symbol in front of amount if not specified in admin.
     *
     * @param $amount
     * @return string
     */
    public static function getWebsiteFormattedAmount($amount) {
        $currencySymbol = self::getWebsiteCurrencySymbol();

        return self::leftAlignedCurrencyPosition() ? $currencySymbol.$amount : $amount.$currencySymbol;
    }

    public static function setUpStorageCredentials() {

        // Storage
        $awsRegion = getSetting('storage.aws_region') != null ? getSetting('storage.aws_region') : 'us-east-1';
        config(['filesystems.disks.s3.key' => getSetting('storage.aws_access_key')]);
        config(['filesystems.disks.s3.secret' => getSetting('storage.aws_secret_key')]);
        config(['filesystems.disks.s3.region' => $awsRegion]);
        config(['filesystems.disks.s3.bucket' => getSetting('storage.aws_bucket_name')]);

        config(['filesystems.disks.wasabi.key' => getSetting('storage.was_access_key')]);
        config(['filesystems.disks.wasabi.secret' => getSetting('storage.was_secret_key')]);
        config(['filesystems.disks.wasabi.region' => getSetting('storage.was_region')]);
        config(['filesystems.disks.wasabi.bucket' => getSetting('storage.was_bucket_name')]);
        config(['filesystems.disks.wasabi.endpoint' => 'https://s3.'.getSetting('storage.was_region').'.wasabisys.com/']);

        config(['filesystems.disks.do_spaces.key' => getSetting('storage.do_access_key')]);
        config(['filesystems.disks.do_spaces.secret' => getSetting('storage.do_secret_key')]);
        config(['filesystems.disks.do_spaces.region' => getSetting('storage.do_region')]);
        config(['filesystems.disks.do_spaces.bucket' => getSetting('storage.do_bucket_name')]);
        config(['filesystems.disks.do_spaces.endpoint' => 'https://'.getSetting('storage.do_region').'.digitaloceanspaces.com']);

        config(['filesystems.disks.minio.key' => getSetting('storage.minio_access_key')]);
        config(['filesystems.disks.minio.secret' => getSetting('storage.minio_secret_key')]);
        config(['filesystems.disks.minio.region' => getSetting('storage.minio_region')]);
        config(['filesystems.disks.minio.bucket' => getSetting('storage.minio_bucket_name')]);
        config(['filesystems.disks.minio.endpoint' => rtrim(getSetting('storage.minio_endpoint'), '/')]);
        config(['filesystems.disks.minio.url' => rtrim(getSetting('storage.minio_endpoint'), '/').'/'.getSetting('storage.minio_bucket_name').'/']);

        config(['filesystems.disks.pushr.key' => getSetting('storage.pushr_access_key')]);
        config(['filesystems.disks.pushr.secret' => getSetting('storage.pushr_secret_key')]);
        config(['filesystems.disks.pushr.bucket' => getSetting('storage.pushr_bucket_name')]);
        config(['filesystems.disks.pushr.endpoint' => rtrim(getSetting('storage.pushr_endpoint'), '/')]);
        config(['filesystems.disks.pushr.url' => getSetting('storage.pushr_cdn_hostname')]);

        config(['filesystems.disks.r2.key' => getSetting('storage.r2_access_key')]);
        config(['filesystems.disks.r2.secret' => getSetting('storage.r2_secret_key')]);
        config(['filesystems.disks.r2.bucket' => getSetting('storage.r2_bucket_name')]);
        config(['filesystems.disks.r2.region' => getSetting('storage.r2_region')]);
        config(['filesystems.disks.r2.endpoint' => rtrim(getSetting('storage.r2_endpoint'), '/')]);
        config(['filesystems.disks.r2.url' => rtrim(getSetting('storage.r2_custom_url'), '/')]);

        $b2CustomUrl = trim((string) getSetting('storage.b2_custom_url'));
        config(['filesystems.disks.b2.key' => getSetting('storage.b2_access_key')]);
        config(['filesystems.disks.b2.secret' => getSetting('storage.b2_secret_key')]);
        config(['filesystems.disks.b2.bucket' => getSetting('storage.b2_bucket_name')]);
        config(['filesystems.disks.b2.region' => getSetting('storage.b2_region')]);
        config(['filesystems.disks.b2.endpoint' => rtrim((string) getSetting('storage.b2_endpoint'), '/')]);
        config(['filesystems.disks.b2.url' => $b2CustomUrl !== '' ? rtrim($b2CustomUrl, '/') : null]);

        // TODO: Not sure if these still required in latest version
        config(['services.ses.key' => getSetting('storage.aws_access_key')]);
        config(['services.ses.secret' => getSetting('storage.aws_secret_key')]);
        config(['services.ses.s3.region' => $awsRegion]);

        config(['queue.connections.sqs.key' => getSetting('storage.aws_access_key')]);
        config(['queue.connections.sqs.secret' => getSetting('storage.aws_secret_key')]);
        config(['queue.connections.sqs.region' => $awsRegion]);

    }

    public static function setUpEmailCredentials(): void
    {
        $driver = getSetting('emails.driver') ?: 'log';
        $encryption = strtolower(trim((string) getSetting('emails.smtp_encryption')));
        $encryption = in_array($encryption, ['tls', 'ssl'], true) ? $encryption : null;

        config([
            'mail.default' => $driver,

            'mail.from.address' => getSetting('emails.from_address') ?: 'no-reply@domain.com',
            'mail.from.name' => getSetting('emails.from_name') ?: __('Admin'),

            'mail.mailers.smtp.transport' => 'smtp',
            'mail.mailers.smtp.host' => trim((string) getSetting('emails.smtp_host')),
            'mail.mailers.smtp.port' => (int) getSetting('emails.smtp_port'),
            'mail.mailers.smtp.encryption' => $encryption,
            'mail.mailers.smtp.username' => trim((string) getSetting('emails.smtp_username')),
            'mail.mailers.smtp.password' => (string) getSetting('emails.smtp_password'),
        ]);

        config([
            'services.mailgun.domain' => getSetting('emails.mailgun_domain'),
            'services.mailgun.secret' => getSetting('emails.mailgun_secret'),
            'services.mailgun.endpoint' => getSetting('emails.mailgun_endpoint'),
        ]);
    }

    public static function setupLocalStorage() {

        // Forgetting and re-instantiating the local storage driver early
        app('filesystem')->forgetDisk('public'); // is the app_url we're actually looking to reset
        config(['filesystems.disks.public.url' =>  getSetting('site.app_url').'/storage']); // this probably does nothing

        // Overriding default config values for logos & favicons, appending public path to them
        config(['app.site.light_logo' => asset(config('app.site.light_logo'))]);
        config(['app.site.dark_logo' => asset(config('app.site.dark_logo'))]);
        config(['app.site.favicon' => asset(config('app.site.favicon'))]);
        config(['app.admin.light_logo' => asset(config('app.admin.light_logo'))]);
        config(['app.admin.dark_logo' => asset(config('app.admin.dark_logo'))]);
    }

    protected function setupPwaConfig(): void
    {
        /** @var PwaAssetGenerator $generator */
        $generator = app(PwaAssetGenerator::class);

        config([
            'laravelpwa.manifest.name' => getSetting('site.name'),
            'laravelpwa.manifest.short_name' => getSetting('site.name'),
            'laravelpwa.manifest.start_url' => '/',
            'laravelpwa.manifest.theme_color' => getSetting('site.pwa_theme_color') ?: '#ffffff',
            'laravelpwa.manifest.background_color' => getSetting('site.pwa_background_color') ?: '#ffffff',
            'laravelpwa.manifest.icons' => $generator->getGeneratedManifestIcons(),
            'laravelpwa.manifest.splash' => $generator->getGeneratedSplashMap(),
        ]);
    }
}
