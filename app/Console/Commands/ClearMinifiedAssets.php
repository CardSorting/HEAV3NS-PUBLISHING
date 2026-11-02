<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;

class ClearMinifiedAssets extends Command
{
    protected $signature = 'minify:clear {--type=all : Generated asset type to clear: all, css, or js}';

    protected $description = 'Delete generated CSS and JavaScript minifier builds.';

    public function handle(Filesystem $filesystem): int
    {
        $type = strtolower((string) $this->option('type'));

        if (!in_array($type, ['all', 'css', 'js'], true)) {
            $this->error('The type must be all, css, or js.');

            return self::FAILURE;
        }

        $types = $type === 'all' ? ['css', 'js'] : [$type];
        $deleted = 0;

        foreach ($types as $assetType) {
            $directory = $this->buildDirectory($assetType);

            if ($directory === null || !$filesystem->isDirectory($directory)) {
                continue;
            }

            foreach ($filesystem->files($directory) as $file) {
                if (strtolower($file->getExtension()) !== $assetType) {
                    continue;
                }

                if (!$filesystem->delete($file->getPathname()) || $filesystem->exists($file->getPathname())) {
                    $this->error(__('A generated minifier build could not be removed. Check filesystem permissions before retrying.'));

                    return self::FAILURE;
                }
                $deleted++;
            }
        }

        $this->info("Deleted {$deleted} generated minifier ".($deleted === 1 ? 'build.' : 'builds.'));

        return self::SUCCESS;
    }

    private function buildDirectory(string $assetType): ?string
    {
        $relativePath = trim((string) config("minify.{$assetType}_build_path"), '/\\');

        if ($relativePath === '' || str_contains($relativePath, '..')) {
            $this->warn("Skipped an invalid {$assetType} build path.");

            return null;
        }

        return public_path($relativePath);
    }
}
