<?php

namespace App\Console\Commands;

use App\Services\ProductThemeGenerator;
use App\Settings\ColorsSettings;
use App\Settings\LicenseSettings;
use Illuminate\Console\Command;
use Throwable;

class GenerateProductTheme extends Command
{
    protected $signature = 'theme:generate';

    protected $description = 'Generate a Bootstrap 5 theme from the saved Fans colors';

    public function handle(ProductThemeGenerator $generator, ColorsSettings $colors, LicenseSettings $license): int
    {
        try {
            $values = [];
            foreach ((array) config('theme.default_colors') as $key => $default) {
                $value = trim((string) $colors->{$key});
                $values[$key] = $value !== '' ? $value : $default;
            }

            $result = $generator->generateAndInstall($values, (string) $license->product_license_key);

            if (!$result['installed']) {
                $this->warn('PHP ZIP support is unavailable. Download the archive and use theme:install on a server with ZIP support:');
                $this->line($result['download_url']);

                return self::FAILURE;
            }

            $this->info('The Bootstrap 5 theme is installed. Saved color settings and legacy theme files were preserved.');
            if ($result['backup'] !== null) {
                $this->line('Previous theme backup: '.$result['backup']);
            }

            return self::SUCCESS;
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }
    }
}
