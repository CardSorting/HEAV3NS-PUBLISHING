<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use RuntimeException;
use Throwable;

class NpmPublish extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'npm:publish';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Builds client assets and publishes framework-managed assets';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Builds client assets and publishes framework-managed assets.
     *
     * @return mixed
     */
    public function handle()
    {
        $this->info('[*]['.date('H:i:s').'] Building Bootstrap 5 assets.');

        if ($this->buildFrontendAssets() !== self::SUCCESS) {
            $this->error('[!] Frontend build failed. Check the build output before retrying.');

            return self::FAILURE;
        }

        try {
            // Filament & plugins
            $this->info('[*] ['.now()->format('H:i:s').'] Publishing Filament assets...');
            if (Artisan::call('filament:assets') !== self::SUCCESS) {
                throw new RuntimeException(__('Filament asset publication failed. Check the command output before retrying.'));
            }

            $this->publishLiveWire();
        } catch (Throwable $exception) {
            $this->error('[!] '.$exception->getMessage());

            return self::FAILURE;
        }

        $this->info('[*]['.date('H:i:s')."] Assets published successfully.");

        return self::SUCCESS;
    }

    protected function buildFrontendAssets(): int
    {
        $npm = PHP_OS_FAMILY === 'Windows' ? 'npm.cmd' : 'npm';
        passthru($npm.' run build', $status);

        return $status;
    }

    /**
     * Publishing livewire assets on each build
     * Relying on livewire's default assets loading (route-based) was not reliable on shared hosting/sub-directories usage.
     * @return void
     */
    public function publishLiveWire()
    {
        $this->info('[*] ['.now()->format('H:i:s').'] Publishing Livewire assets...');

        $source = public_path('vendor/livewire');
        $destination = public_path('libs/livewire');
        // Require fresh output from the installed package rather than accepting an old publication.
        $this->removePublishedDirectory($source);
        if (Artisan::call('vendor:publish', [
            '--tag' => 'livewire:assets',
            '--force' => true,
        ]) !== self::SUCCESS) {
            throw new RuntimeException(__('Livewire asset publication failed. Check the command output before retrying.'));
        }
        foreach (['livewire.js', 'livewire.min.js', 'manifest.json'] as $file) {
            if (!File::isFile($source.'/'.$file)) {
                throw new RuntimeException(__('Livewire publication is missing :file.', ['file' => $file]));
            }
        }

        $staging = $destination.'-staging-'.bin2hex(random_bytes(8));
        $backup = $staging.'-backup';
        try {
            if (!File::copyDirectory($source, $staging)) {
                throw new RuntimeException(__('Livewire assets could not be copied. The previous public/libs/livewire assets were preserved.'));
            }
            foreach (File::allFiles($source) as $file) {
                $copy = $staging.'/'.$file->getRelativePathname();
                if (!File::isFile($copy) || File::hash($file->getPathname()) !== File::hash($copy)) {
                    throw new RuntimeException(__('Livewire asset verification failed. The previous public/libs/livewire assets were preserved.'));
                }
            }
            if (File::exists($destination) && !File::moveDirectory($destination, $backup)) {
                throw new RuntimeException(__('The previous Livewire assets could not be backed up.'));
            }
            if (!File::moveDirectory($staging, $destination)) {
                if (File::exists($backup) && !File::moveDirectory($backup, $destination)) {
                    throw new RuntimeException(__('Livewire publication failed. Restore the previous assets from :path.', ['path' => $backup]));
                }
                throw new RuntimeException(__('Livewire assets could not be published. The previous assets were restored.'));
            }
            $this->removePublishedDirectory($backup);
            $this->removePublishedDirectory($source);
            $this->line("[-] [".now()->format('H:i:s')."] Livewire assets published to public/libs/livewire.");
        } finally {
            if (File::exists($staging)) {
                $this->removePublishedDirectory($staging);
            }
        }

        $vendorPath = public_path('vendor');
        if (File::isDirectory($vendorPath) && count(File::allFiles($vendorPath)) === 0 && count(File::directories($vendorPath)) === 0) {
            $this->removePublishedDirectory($vendorPath);
        }
    }

    private function removePublishedDirectory(string $directory): void
    {
        if (!File::exists($directory)) {
            return;
        }
        $deleted = File::deleteDirectory($directory);
        clearstatcache(true, $directory);
        if (!$deleted || file_exists($directory)) {
            throw new RuntimeException(__('Asset publication could not remove :path. Check filesystem permissions before retrying.', ['path' => $directory]));
        }
    }
}
