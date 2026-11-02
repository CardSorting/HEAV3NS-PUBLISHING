<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Notifications\Messages\MailMessage;

class VerifyEmailNotification extends VerifyEmail
{
    private const DEFAULT_BUTTON_COLOR = 'cb0c9f';

    /**
     * Build the verification email without changing other action emails.
     */
    protected function buildMailMessage($url)
    {
        $buttonColors = self::resolveButtonColors(getSetting('colors.theme_color_code'));

        return (new MailMessage())
            ->subject(__('Confirm your email address'))
            ->markdown('emails.verify-email', [
                'verificationUrl' => $url,
                'siteName' => getSetting('site.name') ?: config('app.name'),
                'expirationMinutes' => config('auth.verification.expire', 60),
                'buttonBackground' => $buttonColors['background'],
                'buttonForeground' => $buttonColors['foreground'],
            ]);
    }

    /**
     * Normalize the configured color and choose the higher-contrast text color.
     *
     * @return array{background: string, foreground: string}
     */
    public static function resolveButtonColors(?string $themeColor): array
    {
        $hex = ltrim(trim((string) $themeColor), '#');

        if (!preg_match('/^[0-9a-f]{6}$/i', $hex)) {
            $hex = self::DEFAULT_BUTTON_COLOR;
        }

        $red = hexdec(substr($hex, 0, 2)) / 255;
        $green = hexdec(substr($hex, 2, 2)) / 255;
        $blue = hexdec(substr($hex, 4, 2)) / 255;

        $luminance = 0.2126 * self::linearizeColorChannel($red)
            + 0.7152 * self::linearizeColorChannel($green)
            + 0.0722 * self::linearizeColorChannel($blue);

        $whiteContrast = 1.05 / ($luminance + 0.05);
        $blackContrast = ($luminance + 0.05) / 0.05;

        return [
            'background' => '#'.strtolower($hex),
            'foreground' => $blackContrast > $whiteContrast ? '#000000' : '#ffffff',
        ];
    }

    private static function linearizeColorChannel(float $channel): float
    {
        return $channel <= 0.04045
            ? $channel / 12.92
            : (($channel + 0.055) / 1.055) ** 2.4;
    }
}
