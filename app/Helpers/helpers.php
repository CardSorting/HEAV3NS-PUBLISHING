<?php

use App\Providers\GenericHelperServiceProvider;
use App\Providers\InstallerServiceProvider;
use App\Providers\SettingsServiceProvider;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

function getSetting(string $key, mixed $default = null): mixed
{
    if (config("settings.admin_version") === 'v2') {
        return getSettingFilament($key, $default);
    }
    return getSettingVoyager($key, $default);
}

function getSettingFilament(string $key, mixed $default = null): mixed
{
    [$group, $settingKey] = explode('.', $key) + [null, null];

    if (!$group || !$settingKey) {
        return $default;
    }

    $groupToClassMap = [
        'site' => App\Settings\GeneralSettings::class,
        // other groups...
    ];

    $className = $groupToClassMap[$group] ?? 'App\\Settings\\'.Str::studly($group).'Settings';

    if (!class_exists($className)) {
        return config("app.$group.$settingKey", $default);
    }

    /** @var Spatie\LaravelSettings\Settings $settings */
    $settings = app($className);

    $value = property_exists($settings, $settingKey) ? $settings->$settingKey : null;

    if (is_null($value) || $value === '') {
        return config("app.$group.$settingKey", $default);
    }

    // Convert file paths to URLs
    $fileKeys = [
        'site.light_logo',
        'site.dark_logo',
        'site.favicon',
        'site.default_og_image',
        'site.login_page_background_image',
        'media.watermark_image',
        'admin.light_logo',
        'admin.dark_logo',
    ];

    if (in_array($key, $fileKeys, true)) {
        SettingsServiceProvider::setUpStorageCredentials();
        SettingsServiceProvider::setDefaultStorageDriver();
        return filled($value)
            ? GenericHelperServiceProvider::getFilePathByActiveStorageDriver($value)
            : null;
    }

    return $value;
}

/**
 * Fallback voyager settings getter
 * This is not the original function, but a replacement.
 * @param string $key
 * @param $default
 * @return Closure|Illuminate\Config\Repository|Illuminate\Contracts\Foundation\Application|Illuminate\Foundation\Application|mixed|string|null
 */
function getSettingVoyager(string $key, $default = null)
{
    $useCache = config('settings.cache', true);
    static $settingCache = null;

    $cacheSupportsTags = Cache::getStore() instanceof Illuminate\Cache\TaggableStore;

    // Try cache if supported
    if ($useCache && $cacheSupportsTags && Cache::tags(['settings'])->has($key)) {
        return Cache::tags(['settings'])->get($key);
    }

    if ($settingCache === null && InstallerServiceProvider::checkIfInstalled()) {
        if (!Schema::hasTable('settings')) {
            return config('app.'.$key, $default);
        }

        $settingCache = [];

        $settings = DB::table('settings')->orderBy('order')->get();

        foreach ($settings as $setting) {
            $parts = explode('.', $setting->key);

            if (count($parts) === 2) {
                $settingCache[$parts[0]][$parts[1]] = $setting->value;
            } else {
                $settingCache[$parts[0]] = $setting->value;
            }

            if ($useCache) {
                if ($cacheSupportsTags) {
                    Cache::tags(['settings'])->forever($setting->key, $setting->value);
                } else {
                    Cache::forever("settings::{$setting->key}", $setting->value);
                }
            }
        }
    }

    $parts = explode('.', $key);
    $settingValue = count($parts) === 2
        ? $settingCache[$parts[0]][$parts[1]] ?? null
        : $settingCache[$parts[0]] ?? null;

    if (is_string($settingValue) && str_contains($settingValue, 'download_link')) {
        $decoded = json_decode($settingValue);

        if (is_array($decoded) && isset($decoded[0]->download_link)) {
            return Storage::url(str_replace('\\', '/', $decoded[0]->download_link));
        }
    }

    return $settingValue ?? config('app.'.$key, $default);
}

if (!function_exists('resolveVoyagerFilePath')) {
    function resolveVoyagerFilePath(?string $value): ?string
    {
        if (is_null($value)) {
            return null;
        }

        // Try to decode as JSON
        $decoded = json_decode($value, true);

        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            // Check if it's a list of files (voyager_files)
            if (isset($decoded[0]['download_link'])) {
                return $decoded[0]['download_link'];
            }

            // Maybe it's a single file array (edge case)
            if (isset($decoded['download_link'])) {
                return $decoded['download_link'];
            }
        }

        // Fallback: use raw value
        return $value;
    }
}

function getLockCode() {
    if(session()->get(InstallerServiceProvider::$lockCode) == config('app.key')){
        return true;
    }
    else{
        return false;
    }
}

function setLockCode($code) {
    $sessData = [];
    $sessData[$code] = config('app.key');
    session($sessData);
    return true;
}

function getUserAvatarAttribute($a) {
    return GenericHelperServiceProvider::getStorageAvatarPath($a);
}

function getLicenseType() {
    $licenseType = 'Unlicensed';
    if(file_exists(storage_path('app/installed'))){
        $licenseV = json_decode(file_get_contents(storage_path('app/installed')));
        if(isset($licenseV->data) && isset($licenseV->data->license)){
            $licenseType = $licenseV->data->license;
        }
    }
    return $licenseType;
}

function handledExec($command, $throw_exception = true) {
    exec('('.$command.')', $output, $return_code);
    if (($return_code !== 0) && $throw_exception) {
        throw new Exception('Error processing command: '.$command."\n\n".implode("\n", $output)."\n\n");
    }
    return ['output' => implode("\n", $output), 'return_code' => $return_code];
}

function checkMysqlndForPDO() {
    $connection = config('database.default');
    $dbConfig = config("database.connections.$connection");

    $dbHost = $dbConfig['host'] ?? null;
    $dbUser = $dbConfig['username'] ?? null;
    $dbPass = $dbConfig['password'] ?? null;
    $dbName = $dbConfig['database'] ?? null;

    $pdo = new PDO('mysql:host='.$dbHost.';dbname='.$dbName, $dbUser, $dbPass);
    if (strpos($pdo->getAttribute(PDO::ATTR_CLIENT_VERSION), 'mysqlnd') !== false) {
        return true;
    }
    return false;
}

function checkForMysqlND() {
    if (extension_loaded('mysqlnd')) {
        return true;
    }
    return false;
}

/**
 * Custom, multi step, downscalling blur.
 * @param $gdImage
 * @param $scaleFactor
 * @param $blurIntensity
 * @param $finalBlur
 * @return mixed
 */
function multiStepBlur($gdImage, $scaleFactor = 4, $blurIntensity = 40, $finalBlur = 25)
{
    // Get original dimensions
    $originalWidth = imagesx($gdImage);
    $originalHeight = imagesy($gdImage);

    // Step 1: Downscale to smaller size
    $smallWidth = intval($originalWidth / $scaleFactor);
    $smallHeight = intval($originalHeight / $scaleFactor);
    $smallImage = imagecreatetruecolor($smallWidth, $smallHeight);
    imagecopyresampled($smallImage, $gdImage, 0, 0, 0, 0, $smallWidth, $smallHeight, $originalWidth, $originalHeight);

    // Apply Gaussian blur to the downscaled image
    for ($i = 1; $i <= $blurIntensity; $i++) {
        imagefilter($smallImage, IMG_FILTER_GAUSSIAN_BLUR);
    }

    // Add smoothing and brightness filters
    imagefilter($smallImage, IMG_FILTER_SMOOTH, 99);
    imagefilter($smallImage, IMG_FILTER_BRIGHTNESS, 10);

    // Step 2: Upscale to a larger size
    $mediumWidth = intval($originalWidth / 2);
    $mediumHeight = intval($originalHeight / 2);
    $mediumImage = imagecreatetruecolor($mediumWidth, $mediumHeight);
    imagecopyresampled($mediumImage, $smallImage, 0, 0, 0, 0, $mediumWidth, $mediumHeight, $smallWidth, $smallHeight);
    imagedestroy($smallImage);

    // Apply Gaussian blur to the upscaled image
    for ($i = 1; $i <= $finalBlur; $i++) {
        imagefilter($mediumImage, IMG_FILTER_GAUSSIAN_BLUR);
    }

    // Add smoothing and brightness filters
    imagefilter($mediumImage, IMG_FILTER_SMOOTH, 99);
    imagefilter($mediumImage, IMG_FILTER_BRIGHTNESS, 10);

    // Step 3: Restore to the original size
    imagecopyresampled($gdImage, $mediumImage, 0, 0, 0, 0, $originalWidth, $originalHeight, $mediumWidth, $mediumHeight);
    imagedestroy($mediumImage);

    return $gdImage;
}

if (!function_exists('short_number')) {
    function short_number($num, $precision = 1)
    {
        if ($num < 1000) {
            return (string) $num;
        }

        $units = ['', 'K', 'M', 'B', 'T'];

        $k = 0;
        while ($num >= 1000 && $k < count($units) - 1) {
            $num /= 1000;
            $k++;
        }

        // Format with decimals, remove trailing zeros
        $formatted = number_format($num, $precision, '.', '');
        $formatted = rtrim(rtrim($formatted, '0'), '.');

        return $formatted.$units[$k];
    }
}
