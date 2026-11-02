<?php

namespace App\Support;

use App\Providers\InstallerServiceProvider;
use Illuminate\Support\Facades\Cookie;

final class ThemePreference
{
    public const LIGHT = 'light';
    public const DARK = 'dark';
    public const AUTO = 'auto';

    /** @return list<string> */
    public static function options(): array
    {
        return [self::LIGHT, self::DARK, self::AUTO];
    }

    public static function normalize(?string $theme, string $fallback = self::LIGHT): string
    {
        $normalizedFallback = in_array($fallback, self::options(), true) ? $fallback : self::LIGHT;

        return in_array($theme, self::options(), true) ? $theme : $normalizedFallback;
    }

    public static function resolve(?string $cookieTheme, ?string $defaultTheme): string
    {
        return self::normalize($cookieTheme, self::normalize($defaultTheme));
    }

    public static function defaultTheme(): string
    {
        if (!InstallerServiceProvider::checkIfInstalled()) {
            return self::LIGHT;
        }

        $defaultTheme = getSetting('site.default_user_theme');

        return self::normalize(is_string($defaultTheme) ? $defaultTheme : null);
    }

    /**
     * A valid visitor cookie keeps the existing Fans precedence over the admin default.
     */
    public static function selectedTheme(): string
    {
        return self::resolvedTheme();
    }

    public static function selectedPreference(): string
    {
        return self::resolve(self::cookieValue('app_theme'), self::defaultTheme());
    }

    public static function resolvedTheme(): string
    {
        // The early browser runtime resolves Auto before CSS paints. Server markup
        // keeps a safe light fallback without guessing the visitor's device theme.
        return self::selectedPreference() === self::DARK ? self::DARK : self::LIGHT;
    }

    public static function nextPreference(): string
    {
        return match (self::selectedPreference()) {
            self::LIGHT => self::DARK,
            self::DARK => self::AUTO,
            default => self::LIGHT,
        };
    }

    public static function isDark(): bool
    {
        return self::resolvedTheme() === self::DARK;
    }

    private static function cookieValue(string $name): ?string
    {
        $value = Cookie::get($name);

        return is_string($value) ? $value : null;
    }
}
