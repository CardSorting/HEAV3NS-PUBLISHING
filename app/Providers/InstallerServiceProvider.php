<?php

namespace App\Providers;

use App\Support\PhpRuntimeRequirements;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\ServiceProvider;

class InstallerServiceProvider extends ServiceProvider
{
    /**
     * @var string
     */
    public static $lockCode = 'g9mZ)j5(GGGHsf';

    public static $activationService = 'https://license.qdev.tech/';

    /*
     *
     */
    public static $acError = 'Failed to connect to License Server. Please try again or contact us if the error persists.';

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
        //
    }

    /**
     * Return lock code.
     * @return mixed
     */
    public static function getLockCode() {
        return getLockCode();
    }

    /**
     * Returns list of script required extensions.
     *
     * @return array
     */
    public static function getRequiredExtensions()
    {
        return [
            'bcmath',
            'Ctype',
            'Fileinfo',
            'JSON',
            'Mbstring',
            'OpenSSL',
            'PDO',
            'Tokenizer',
            'XML',
            'cURL',
            'exif',
            'GD',
            'intl',
        ];
    }

    /**
     * GLK fn().
     */
    public static function glck() {
        return true;
    }

    /**
     * Check if server passes the script requirements.
     *
     * @return bool
     */
    public static function passesRequirements()
    {
        $extensions = self::getRequiredExtensions();
        $passes = true;
        foreach ($extensions as $extension) {
            if (!extension_loaded($extension)) {
                $passes = false;
            }
        }
        if (!PhpRuntimeRequirements::supports()) {
            $passes = false;
        }

        return $passes;
    }

    /**
     * Checks if script is already installed.
     * @return bool
     */
    public static function checkIfInstalled()
    {
        if (Storage::disk('local')->exists('installed')) {
            return true;
        }

        return false;
    }

    /**
     * Appends values to the env file during the installation.
     * @param $line
     */
    public static function appendToEnv($line)
    {
        file_put_contents(base_path().'/'.'.env', file_get_contents(base_path().'/'.'.env').$line."\r\n");
    }

    /**
     * Setting up the lock code.
     * @return bool
     */
    public static function setLockCode() {
        return setLockCode(self::$lockCode);
    }

    /**
     * Checks if envato license key is valid.
     * @param $code
     * @return mixed|object
     */
    public static function gld($code = '')
    {
        if(!self::setLockCode()){
            return (object)['success' => false, 'error' => 'Lock code failed to set up.'];
        }
        try{
            $context = stream_context_create(['http' => ['timeout' => 20]]);
            $response = file_get_contents(self::$activationService.'?code='.$code.'&activate=true', false, $context);
            if($response == false){
                $response = self::curlGetContent(self::$activationService.'?code='.$code.'&activate=true');
            }
            $response = json_decode($response);
            return $response;
        } catch (\Exception $exception) {
            return (object)['success' => false, 'error' => self::$acError.' Error: "'.$exception->getMessage().'"'];
        }
    }

    /**
     * Curl based license fetching method fallback.
     * @param $URL
     * @return bool|string
     */
    public static function curlGetContent($url) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_AUTOREFERER, true);
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
        curl_setopt($ch, CURLOPT_TIMEOUT, 20);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        $data = curl_exec($ch);
        curl_close($ch);
        return $data;
    }

    /** @return array{active: bool|null, until: CarbonImmutable|null} */
    public static function getLicenseSupportStatus(array $license): array
    {
        $active = match ($license['supported_now'] ?? null) {
            true, 'Yes' => true,
            false, 'No' => false,
            default => null,
        };
        // Older checker responses used this misspelled date key.
        $expiry = $license['supported_until'] ?? $license['supported_unil'] ?? null;
        if ($expiry === null || $expiry === '') {
            return ['active' => $active, 'until' => null];
        }
        if (!is_string($expiry) || trim($expiry) === '') {
            return ['active' => null, 'until' => null];
        }
        try {
            $until = CarbonImmutable::parse($expiry);
        } catch (\InvalidArgumentException) {
            return ['active' => null, 'until' => null];
        }

        // A cached positive flag must not remain active after its expiry date.
        return ['active' => $until->isFuture() && $active !== false, 'until' => $until];
    }

    public static function hasAvailableMigrations(): bool
    {
        $migrator = app('migrator');

        $paths = $migrator->paths();
        $paths[] = database_path('migrations');

        $files = $migrator->getMigrationFiles($paths);
        $ran = $migrator->getRepository()->getRan();

        $pending = array_diff(array_keys($files), $ran);

        return count($pending) > 0;
    }
}
