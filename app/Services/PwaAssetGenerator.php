<?php

namespace App\Services;

use Illuminate\Support\Facades\Storage;
use Intervention\Image\Facades\Image;
use RuntimeException;

class PwaAssetGenerator
{
    protected array $iconSizes = [192, 512];

    protected array $splashSizes = [
        '640x1136',
        '750x1334',
        '828x1792',
        '1125x2436',
        '1170x2532',
        '1179x2556',
        '1206x2622',
        '1242x2208',
        '1242x2688',
        '1260x2736',
        '1284x2778',
        '1290x2796',
        '1320x2868',
        '1536x2048',
        '1668x2224',
        '1668x2388',
        '2048x2732',
    ];

    protected string $disk;

    public function __construct(?string $disk = null)
    {
        $this->disk = $disk ?: config('filesystems.default');
    }

    public function generate(): void
    {
        $this->cleanupGeneratedAssets();

        if (!getSetting('site.pwa_enabled')) {
            return;
        }

        $this->generateIcons();
        $this->generateSplashes();
    }

    public function generateIcons(): void
    {
        $icon = getSetting('site.pwa_icon');

        if (!$icon || !$this->sourceExists($icon)) {
            throw new RuntimeException('PWA icon is required and must exist.');
        }

        foreach ($this->iconSizes as $size) {
            $this->generateIconVariant(
                sourcePath: $icon,
                targetPath: $this->generatedPath("manifest-icon-{$size}.png"),
                size: $size
            );
        }
    }

    public function generateSplashes(): void
    {
        $splashLogo = getSetting('site.pwa_splash_logo');
        $icon = getSetting('site.pwa_icon');

        $source = ($splashLogo && $this->sourceExists($splashLogo))
            ? $splashLogo
            : $icon;

        if (!$source || !$this->sourceExists($source)) {
            return;
        }

        $backgroundColor = getSetting('site.pwa_background_color') ?: '#ffffff';

        foreach ($this->splashSizes as $size) {
            [$width, $height] = $this->parseSize($size);

            $canvas = Image::canvas($width, $height, $backgroundColor);
            $logo = $this->makeImageFromStorage($source);

            $logoSize = (int) floor(min($width, $height) * 0.28);

            $logo->resize($logoSize, $logoSize, function ($constraint) {
                $constraint->aspectRatio();
            });

            $canvas->insert($logo, 'center');

            $this->putGeneratedFile(
                $this->generatedPath("apple-splash-{$size}.jpg"),
                (string) $canvas->encode('jpg', 90)
            );
        }
    }

    public function getGeneratedManifestIcons(): array
    {
        return [
            '192x192' => [
                'path' => $this->generatedUrl('manifest-icon-192.png'),
                'purpose' => 'any maskable',
            ],
            '512x512' => [
                'path' => $this->generatedUrl('manifest-icon-512.png'),
                'purpose' => 'any maskable',
            ],
        ];
    }

    public function getGeneratedSplashMap(): array
    {
        $output = [];

        foreach ($this->splashSizes as $size) {
            $output[$size] = $this->generatedUrl("apple-splash-{$size}.jpg");
        }

        return $output;
    }

    public function generatedUrl(string $file): string
    {
        return Storage::disk($this->disk)->url($this->generatedPath($file));
    }

    protected function generateIconVariant(string $sourcePath, string $targetPath, int $size): void
    {
        $image = $this->makeImageFromStorage($sourcePath);

        $image->fit($size, $size);

        $this->putGeneratedFile(
            $targetPath,
            (string) $image->encode('png', 100)
        );
    }

    protected function makeImageFromStorage(string $path)
    {
        $tmpPath = $this->copySourceToTmp($path);

        try {
            return Image::make(Storage::disk('tmp')->path($tmpPath));
        } finally {
            Storage::disk('tmp')->delete($tmpPath);
        }
    }

    protected function copySourceToTmp(string $sourcePath): string
    {
        $extension = strtolower(pathinfo($sourcePath, PATHINFO_EXTENSION) ?: 'png');
        $tmpPath = 'pwa/'.md5($sourcePath.microtime(true)).'.'.$extension;

        Storage::disk('tmp')->put(
            $tmpPath,
            Storage::disk($this->disk)->get($sourcePath)
        );

        return $tmpPath;
    }

    protected function putGeneratedFile(string $path, string $contents): void
    {
        Storage::disk($this->disk)->put($path, $contents);
    }

    protected function sourceExists(string $path): bool
    {
        return Storage::disk($this->disk)->exists($path);
    }

    protected function cleanupGeneratedAssets(): void
    {
        $directory = $this->generatedDirectory();

        if (!Storage::disk($this->disk)->exists($directory)) {
            return;
        }

        $files = Storage::disk($this->disk)->files($directory);

        if ($files) {
            Storage::disk($this->disk)->delete($files);
        }
    }

    protected function generatedDirectory(): string
    {
        return 'assets/pwa/generated';
    }

    protected function generatedPath(string $file): string
    {
        return $this->generatedDirectory().'/'.$file;
    }

    protected function parseSize(string $size): array
    {
        [$width, $height] = explode('x', $size);

        return [(int) $width, (int) $height];
    }
}
