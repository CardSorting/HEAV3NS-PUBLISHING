<?php

namespace App\Console\Commands;

use App\Providers\InstallerServiceProvider;
use App\Services\Installer\InstallationState;
use Illuminate\Console\Command;
use Throwable;

class ResetInstallation extends Command
{
    protected $signature = 'installer:reset {--force : Confirm clearing saved installer progress without prompting}';

    protected $description = 'Clear unfinished installer progress without deleting database data';

    public function handle(InstallationState $installationState): int
    {
        if (InstallerServiceProvider::checkIfInstalled()) {
            $this->error('The application is already installed. Installer recovery is not available.');

            return self::FAILURE;
        }

        $this->warn('This clears saved installer progress only. It does not delete database data or fix installation errors.');
        if (!$this->option('force') && !$this->confirm('Clear the unfinished installation and start over?', false)) {
            return self::SUCCESS;
        }

        try {
            $installationState->reset();
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->info('Installer progress cleared. Prepare an empty database, then reopen /install in your browser.');

        return self::SUCCESS;
    }
}
