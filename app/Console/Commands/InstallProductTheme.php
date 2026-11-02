<?php

namespace App\Console\Commands;

use App\Services\ProductThemeGenerator;
use Illuminate\Console\Command;
use Throwable;

class InstallProductTheme extends Command
{
    protected $signature = 'theme:install {archive : Path to a bootstrap5-vars ZIP archive}';

    protected $description = 'Validate and install a previously generated theme archive without a network request';

    public function handle(ProductThemeGenerator $generator): int
    {
        try {
            $result = $generator->installArchive((string) $this->argument('archive'));
            $this->info('The theme is installed. Saved color settings and legacy theme files were preserved.');
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
