<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;

class ThemeIllustrationService
{
    private const DEFAULT_PRIMARY = '#cb0c9f';
    private const DEFAULT_GRADIENT_FROM = '#7928CA';
    private const DEFAULT_GRADIENT_TO = '#FF0080';

    public function src(string $assetPath): string
    {
        $normalizedPath = ltrim($assetPath, '/');
        $path = public_path($normalizedPath);

        if (!File::exists($path)) {
            return asset($normalizedPath);
        }

        if (strtolower(pathinfo($normalizedPath, PATHINFO_EXTENSION)) !== 'svg') {
            return asset($normalizedPath);
        }

        $palette = $this->palette();

        if ($this->usesDefaultPalette($palette)) {
            return asset($normalizedPath);
        }

        $svg = $this->svgForAsset($normalizedPath, $palette);

        if ($svg === '') {
            return asset($normalizedPath);
        }

        return 'data:image/svg+xml;base64,'.base64_encode($svg);
    }

    protected function svgForAsset(string $assetPath, ?array $palette = null): string
    {
        $path = public_path(ltrim($assetPath, '/'));

        if (!File::exists($path)) {
            return '';
        }

        $palette = $palette ?: $this->palette();
        $cacheKey = 'theme_illustrations.'.md5(
            $assetPath.'|'.
            File::lastModified($path).'|'.json_encode($palette)
        );

        return Cache::rememberForever($cacheKey, function () use ($path, $palette) {
            $svg = File::get($path);

            if ($this->usesDefaultPalette($palette)) {
                return $svg;
            }

            return $this->replaceAccentPalette($svg, $palette);
        });
    }

    protected function palette(): array
    {
        return [
            'primary' => $this->normalizeHex(getSetting('colors.theme_color_code'), self::DEFAULT_PRIMARY),
            'gradient_from' => $this->normalizeHex(getSetting('colors.theme_gradient_from'), self::DEFAULT_GRADIENT_FROM),
            'gradient_to' => $this->normalizeHex(getSetting('colors.theme_gradient_to'), self::DEFAULT_GRADIENT_TO),
        ];
    }

    protected function usesDefaultPalette(array $palette): bool
    {
        return $palette['primary'] === strtoupper(self::DEFAULT_PRIMARY)
            && $palette['gradient_from'] === strtoupper(self::DEFAULT_GRADIENT_FROM)
            && $palette['gradient_to'] === strtoupper(self::DEFAULT_GRADIENT_TO);
    }

    protected function replaceAccentPalette(string $svg, array $palette): string
    {
        $accentHot = $palette['gradient_to'];
        $accentDeep = $palette['gradient_from'];
        $accentPrimary = $palette['primary'];

        $replacements = [
            '#CB0C9F' => $accentPrimary,
            '#D7159C' => $accentPrimary,
            '#A814A8' => $accentDeep,
            '#9F1BA2' => $accentDeep,
            '#921BA6' => $this->mixHex($accentPrimary, $accentDeep, 0.65),
            '#5E116B' => $this->darkenHex($accentDeep, 0.22),
            '#EA04A1' => $accentHot,
            '#FD018C' => $accentHot,
            '#FD018B' => $accentHot,
            '#BD14C0' => $accentDeep,
            '#F20087' => $this->mixHex($accentHot, $accentDeep, 0.18),
            '#D40082' => $this->mixHex($accentHot, $accentDeep, 0.36),
            '#A9007B' => $this->mixHex($accentHot, $accentDeep, 0.58),
            '#750070' => $this->mixHex($accentHot, $accentDeep, 0.82),
            '#69006E' => $this->darkenHex($accentDeep, 0.14),
        ];

        return str_ireplace(array_keys($replacements), array_values($replacements), $svg);
    }

    protected function normalizeHex(?string $hex, string $fallback): string
    {
        $value = strtoupper(trim((string) ($hex ?: $fallback)));

        if ($value === '') {
            $value = strtoupper($fallback);
        }

        if ($value[0] !== '#') {
            $value = '#'.$value;
        }

        return preg_match('/^#[0-9A-F]{6}$/', $value) ? $value : strtoupper($fallback);
    }

    protected function mixHex(string $from, string $to, float $ratio): string
    {
        $fromRgb = $this->hexToRgb($from);
        $toRgb = $this->hexToRgb($to);

        $ratio = max(0, min(1, $ratio));

        $mixed = [
            (int) round($fromRgb[0] + (($toRgb[0] - $fromRgb[0]) * $ratio)),
            (int) round($fromRgb[1] + (($toRgb[1] - $fromRgb[1]) * $ratio)),
            (int) round($fromRgb[2] + (($toRgb[2] - $fromRgb[2]) * $ratio)),
        ];

        return $this->rgbToHex($mixed);
    }

    protected function darkenHex(string $hex, float $ratio): string
    {
        $rgb = $this->hexToRgb($hex);
        $ratio = max(0, min(1, $ratio));

        $darkened = [
            (int) round($rgb[0] * (1 - $ratio)),
            (int) round($rgb[1] * (1 - $ratio)),
            (int) round($rgb[2] * (1 - $ratio)),
        ];

        return $this->rgbToHex($darkened);
    }

    protected function hexToRgb(string $hex): array
    {
        $hex = ltrim($hex, '#');

        return [
            hexdec(substr($hex, 0, 2)),
            hexdec(substr($hex, 2, 2)),
            hexdec(substr($hex, 4, 2)),
        ];
    }

    protected function rgbToHex(array $rgb): string
    {
        return sprintf('#%02X%02X%02X', $rgb[0], $rgb[1], $rgb[2]);
    }
}
