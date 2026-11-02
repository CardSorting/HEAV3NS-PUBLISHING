<?php

namespace App\Services;

use Closure;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Http\Client\Factory;
use RuntimeException;
use Throwable;
use ZipArchive;

class ProductThemeGenerator
{
    public const FORMAT = 'bootstrap5-vars';
    private const MAX_ARCHIVE_BYTES = 1048576;
    private const MAX_CSS_BYTES = 65536;
    private const COLOR_VARIABLES = [
        'primary', 'primary-hover', 'primary-active', 'on-primary',
        'primary-text-emphasis', 'primary-bg-subtle', 'primary-border-subtle',
        'link', 'link-hover', 'gradient-from', 'gradient-to',
    ];
    private const RGB_VARIABLES = ['primary-rgb', 'link-rgb', 'link-hover-rgb'];
    private const GRADIENT_RGB_VARIABLES = ['gradient-from-rgb', 'gradient-to-rgb'];
    private const BACKUP_PATTERN = '/\A[0-9]{8}-[0-9]{6}-[a-f0-9]{12}\z/D';

    public function __construct(
        private Factory $http,
        private Filesystem $files,
        private Repository $config
    ) {
    }

    /**
     * @param  array{theme_color_code: string, theme_gradient_from: string, theme_gradient_to: string}  $colors
     * @param  Closure(): void|null  $persistColors Commit settings before returning; failures restore the outgoing CSS.
     * @return array{installed: bool, download_url: string, stylesheet: string|null, backup: string|null}
     */
    public function generateAndInstall(array $colors, string $licenseCode, ?Closure $persistColors = null): array
    {
        $url = $this->validatedGeneratorUrl();

        if ($this->config->get('theme.format') !== self::FORMAT) {
            throw new RuntimeException('This application requires the bootstrap5-vars theme format.');
        }

        if (trim($licenseCode) === '') {
            throw new RuntimeException('Configure the product license before generating a theme.');
        }

        $payload = [
            'product' => $this->config->get('theme.product'),
            'theme_format' => self::FORMAT,
            'color_code' => $this->normalizeHex($colors['theme_color_code']),
            'gradient_from' => $this->normalizeHex($colors['theme_gradient_from']),
            'gradient_to' => $this->normalizeHex($colors['theme_gradient_to']),
            'code' => $licenseCode,
        ];

        try {
            $response = $this->http
                ->timeout(300)
                ->withOptions(['allow_redirects' => false])
                ->get($url, $payload);
        } catch (Throwable) {
            // HTTP exceptions can contain the license-bearing request URL.
            throw new RuntimeException('The theme service could not be reached. Existing theme files were preserved.');
        }

        $json = $response->json();
        if (!$response->successful() || !is_array($json) || ($json['success'] ?? false) !== true) {
            throw new RuntimeException('The theme service rejected the request. Check its availability and product license.');
        }

        if (($json['theme_format'] ?? null) !== self::FORMAT) {
            throw new RuntimeException('The theme service must support bootstrap5-vars for this product before upgrading.');
        }

        $path = $json['path'] ?? null;
        if (!is_string($path)
            || !preg_match('/\A[A-Za-z0-9][A-Za-z0-9_\/.\-]*\.zip\z/D', $path)
            || array_intersect(explode('/', $path), ['.', '..']) !== []) {
            throw new RuntimeException('The theme service returned an invalid archive path.');
        }

        // Only a relative path is accepted, so the archive stays on the configured service origin.
        $downloadUrl = $url.'/'.$path;

        if (!class_exists(ZipArchive::class)) {
            return [
                'installed' => false,
                'download_url' => $downloadUrl,
                'stylesheet' => null,
                'backup' => null,
            ];
        }

        try {
            $archive = $this->http
                ->timeout(300)
                ->withOptions(['allow_redirects' => false])
                ->get($downloadUrl);
        } catch (Throwable) {
            throw new RuntimeException('The generated theme archive could not be downloaded. Existing theme files were preserved.');
        }

        if (!$archive->successful() || strlen($archive->body()) > self::MAX_ARCHIVE_BYTES) {
            throw new RuntimeException('The generated theme archive could not be downloaded or is too large.');
        }

        $this->files->ensureDirectoryExists(storage_path('app/tmp'));
        $temporaryArchive = tempnam(storage_path('app/tmp'), 'theme-');
        if ($temporaryArchive === false) {
            throw new RuntimeException('Could not create a temporary theme archive.');
        }

        try {
            $archiveBody = $archive->body();
            if ($this->files->put($temporaryArchive, $archiveBody) !== strlen($archiveBody)) {
                throw new RuntimeException('Could not store the generated theme archive.');
            }

            $result = $this->installArchive($temporaryArchive, $persistColors);
        } finally {
            $this->files->delete($temporaryArchive);
        }

        return ['installed' => true, 'download_url' => $downloadUrl] + $result;
    }

    /** @return array{stylesheet: string, backup: string|null} */
    public function installArchive(string $archivePath, ?Closure $persistColors = null): array
    {
        if (!class_exists(ZipArchive::class)) {
            throw new RuntimeException('PHP ZIP support is required to validate and install a theme archive.');
        }

        if (!$this->files->isFile($archivePath) || $this->files->size($archivePath) > self::MAX_ARCHIVE_BYTES) {
            throw new RuntimeException('The theme archive is missing or exceeds the size limit.');
        }

        $zip = new ZipArchive;
        if ($zip->open($archivePath) !== true) {
            throw new RuntimeException('The theme archive is invalid.');
        }

        try {
            $entry = $zip->statIndex(0);
            if ($zip->numFiles !== 1
                || !is_array($entry)
                || ($entry['name'] ?? null) !== 'theme.css'
                || !is_int($entry['size'] ?? null)
                || $entry['size'] > self::MAX_CSS_BYTES) {
                throw new RuntimeException('A bootstrap5-vars archive must contain only a root theme.css file.');
            }

            $css = $zip->getFromIndex(0);
            if (!is_string($css)) {
                throw new RuntimeException('The theme stylesheet could not be read.');
            }
        } finally {
            $zip->close();
        }

        return $this->installStylesheet($this->validateStylesheet($css), $persistColors);
    }

    /** @return array{stylesheet: string, backup: string|null} */
    public function rollback(string $backup): array
    {
        if (!preg_match(self::BACKUP_PATTERN, $backup)) {
            throw new RuntimeException('Choose a valid theme backup identifier.');
        }

        $path = $this->backupDirectory().'/'.$backup.'/theme.css';
        if (!$this->files->isFile($path)) {
            throw new RuntimeException('The selected theme backup does not exist.');
        }

        return $this->installStylesheet($this->validateStylesheet($this->files->get($path)));
    }

    /**
     * Remove only the generated override, restoring it if settings persistence fails.
     * The callback must commit its settings transaction before returning.
     *
     * @param  Closure(): void  $persistDefaults
     * @return array{backup: string|null}
     */
    public function resetToDefaults(Closure $persistDefaults): array
    {
        $stylesheet = $this->stylesheetPath();
        $this->files->ensureDirectoryExists($this->backupDirectory(), 0700);
        $lock = fopen($this->backupDirectory().'/.install.lock', 'c');
        if ($lock === false) {
            throw new RuntimeException('Could not acquire the theme installation lock.');
        }

        try {
            if (!flock($lock, LOCK_EX)) {
                throw new RuntimeException('Could not acquire the theme installation lock.');
            }

            clearstatcache(true, $stylesheet);
            $backup = null;
            if ($this->files->exists($stylesheet) && !$this->files->isFile($stylesheet)) {
                throw new RuntimeException('The generated theme path is not a file.');
            }

            if ($this->files->isFile($stylesheet)) {
                $backup = $this->newBackupIdentifier();
                $directory = $this->backupDirectory().'/'.$backup;
                $this->files->ensureDirectoryExists($directory, 0700);

                if (!$this->files->copy($stylesheet, $directory.'/theme.css')) {
                    throw new RuntimeException('Could not back up the current theme. No reset was applied.');
                }

                if (!$this->files->delete($stylesheet)) {
                    throw new RuntimeException('Could not remove the generated theme. Saved colors were preserved.');
                }

                clearstatcache(true, $stylesheet);
            }

            try {
                $persistDefaults();
            } catch (Throwable $exception) {
                if ($backup !== null) {
                    $this->atomicWrite(
                        $stylesheet,
                        $this->files->get($this->backupDirectory().'/'.$backup.'/theme.css')
                    );
                }

                throw $exception;
            }

            return ['backup' => $backup];
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    /** @return list<string> */
    public function backups(): array
    {
        if (!$this->files->isDirectory($this->backupDirectory())) {
            return [];
        }

        $backups = [];
        foreach ($this->files->directories($this->backupDirectory()) as $directory) {
            $identifier = basename($directory);
            if (preg_match(self::BACKUP_PATTERN, $identifier)
                && $this->files->isFile($directory.'/theme.css')) {
                $backups[] = $identifier;
            }
        }

        rsort($backups);

        return $backups;
    }

    private function validatedGeneratorUrl(): string
    {
        $url = rtrim((string) $this->config->get('theme.generator_url'), '/');
        $requireHttps = (bool) $this->config->get('theme.generator_require_https', true);
        $allowedSchemes = $requireHttps ? ['https'] : ['http', 'https'];
        $parts = parse_url($url);

        if (filter_var($url, FILTER_VALIDATE_URL) === false
            || !is_array($parts)
            || empty($parts['host'])
            || !in_array(strtolower((string) ($parts['scheme'] ?? '')), $allowedSchemes, true)
            || isset($parts['user'])
            || isset($parts['pass'])
            || isset($parts['query'])
            || isset($parts['fragment'])) {
            throw new RuntimeException($requireHttps
                ? 'Configure a valid HTTPS theme generator URL.'
                : 'Configure a valid HTTP or HTTPS theme generator URL.');
        }

        return $url;
    }

    private function normalizeHex(string $value): string
    {
        $value = ltrim(trim($value), '#');
        if (!preg_match('/\A[0-9a-f]{6}\z/iD', $value)) {
            throw new RuntimeException('Theme colors must be six-digit hex values.');
        }

        return strtolower($value);
    }

    private function validateStylesheet(string $css): string
    {
        if (strlen($css) > self::MAX_CSS_BYTES) {
            throw new RuntimeException('The generated theme stylesheet exceeds the size limit.');
        }

        $css = preg_replace('~/\*.*?\*/~s', '', $css) ?? '';
        $pattern = '~\A\s*:root\s*,\s*\[data-bs-theme=["\']light["\']\]\s*\{([^{}]*)\}\s*'
            .'\[data-bs-theme=["\']dark["\']\]\s*\{([^{}]*)\}\s*\z~sD';

        if (!preg_match($pattern, $css, $blocks)) {
            throw new RuntimeException('The theme must contain only light and dark color-variable blocks.');
        }

        $normalizedBlocks = [];
        foreach ([$blocks[1], $blocks[2]] as $block) {
            $variables = [];
            foreach (explode(';', trim($block)) as $declaration) {
                if (trim($declaration) === '') {
                    continue;
                }

                if (!preg_match('/\A\s*--theme-([a-z-]+)\s*:\s*([^;]+?)\s*\z/sD', $declaration, $match)
                    || isset($variables[$match[1]])) {
                    throw new RuntimeException('The generated theme contains invalid or duplicate variables.');
                }

                $variables[$match[1]] = $match[2];
            }

            $required = array_merge(self::COLOR_VARIABLES, self::RGB_VARIABLES, ['focus-ring']);
            $allowed = array_merge($required, self::GRADIENT_RGB_VARIABLES);
            if (array_diff($required, array_keys($variables)) !== []
                || array_diff(array_keys($variables), $allowed) !== []) {
                throw new RuntimeException('The generated theme does not match the bootstrap5-vars color contract.');
            }

            foreach (self::COLOR_VARIABLES as $name) {
                if (!preg_match('/\A#[0-9a-f]{6}\z/iD', $variables[$name])) {
                    throw new RuntimeException('The generated theme contains an invalid color value.');
                }
            }

            // Early bootstrap5-vars archives omitted these additive tokens.
            foreach (self::GRADIENT_RGB_VARIABLES as $name) {
                if (!isset($variables[$name])) {
                    $hex = ltrim($variables[substr($name, 0, -4)], '#');
                    $variables[$name] = implode(', ', array_map('hexdec', str_split($hex, 2)));
                    $block = rtrim($block, " \t\r\n;").";\n  --theme-{$name}: {$variables[$name]};\n";
                }
            }

            foreach (array_merge(self::RGB_VARIABLES, self::GRADIENT_RGB_VARIABLES) as $name) {
                if (!$this->validRgb($variables[$name])) {
                    throw new RuntimeException('The generated theme contains an invalid RGB value.');
                }
            }

            if (!preg_match('/\Argba\(([^()]+),\s*(0(?:\.[0-9]+)?|1(?:\.0+)?)\)\z/D', $variables['focus-ring'], $ring)
                || !$this->validRgb($ring[1])) {
                throw new RuntimeException('The generated theme contains an invalid focus-ring color.');
            }

            $normalizedBlocks[] = rtrim($block);
        }

        return ":root,\n[data-bs-theme=\"light\"] {".$normalizedBlocks[0]."\n}\n"
            ."[data-bs-theme=\"dark\"] {".$normalizedBlocks[1]."\n}\n";
    }

    private function validRgb(string $value): bool
    {
        if (!preg_match('/\A[0-9]{1,3}\s*,\s*[0-9]{1,3}\s*,\s*[0-9]{1,3}\z/D', $value)) {
            return false;
        }

        return max(array_map('intval', explode(',', $value))) <= 255;
    }

    /** @return array{stylesheet: string, backup: string|null} */
    private function installStylesheet(string $css, ?Closure $persistColors = null): array
    {
        $stylesheet = $this->stylesheetPath();
        $this->files->ensureDirectoryExists(dirname($stylesheet));
        $this->files->ensureDirectoryExists($this->backupDirectory(), 0700);
        $lock = fopen($this->backupDirectory().'/.install.lock', 'c');
        if ($lock === false) {
            throw new RuntimeException('Could not acquire the theme installation lock.');
        }

        try {
            if (!flock($lock, LOCK_EX)) {
                throw new RuntimeException('Could not acquire the theme installation lock.');
            }

            clearstatcache(true, $stylesheet);
            if ($this->files->exists($stylesheet) && !$this->files->isFile($stylesheet)) {
                throw new RuntimeException('The generated theme path is not a file.');
            }

            $backup = null;
            if ($this->files->isFile($stylesheet)) {
                $backup = $this->newBackupIdentifier();
                $directory = $this->backupDirectory().'/'.$backup;
                $this->files->ensureDirectoryExists($directory, 0700);

                if (!$this->files->copy($stylesheet, $directory.'/theme.css')) {
                    throw new RuntimeException('Could not back up the current theme. No replacement was installed.');
                }
            }

            $this->atomicWrite($stylesheet, $css);

            try {
                // Keep the installation lock until the settings transaction commits.
                // Concurrent saves cannot commit colors out of order with their CSS.
                if ($persistColors !== null) {
                    $persistColors();
                }
            } catch (Throwable $exception) {
                if ($backup !== null) {
                    $this->atomicWrite($stylesheet, $this->files->get($this->backupDirectory().'/'.$backup.'/theme.css'));
                } elseif (!$this->files->delete($stylesheet)) {
                    throw new RuntimeException('Could not remove the new theme after settings persistence failed.', 0, $exception);
                }

                clearstatcache(true, $stylesheet);
                throw $exception;
            }

            // Base Bootstrap bundles and customer overrides are never replaced by branding generation.
            return ['stylesheet' => $stylesheet, 'backup' => $backup];
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    private function atomicWrite(string $stylesheet, string $css): void
    {
        $temporaryStylesheet = tempnam(dirname($stylesheet), '.theme-');
        if ($temporaryStylesheet === false) {
            throw new RuntimeException('Could not stage the replacement theme.');
        }

        try {
            if ($this->files->put($temporaryStylesheet, $css) !== strlen($css)
                || !chmod($temporaryStylesheet, 0644)
                || !rename($temporaryStylesheet, $stylesheet)) {
                throw new RuntimeException('Could not install the replacement theme.');
            }

            clearstatcache(true, $stylesheet);
        } finally {
            $this->files->delete($temporaryStylesheet);
        }
    }

    private function stylesheetPath(): string
    {
        return public_path((string) $this->config->get('theme.stylesheet'));
    }

    private function backupDirectory(): string
    {
        return storage_path('app/theme-backups');
    }

    private function newBackupIdentifier(): string
    {
        return gmdate('Ymd-His').'-'.bin2hex(random_bytes(6));
    }
}
