<?php

namespace App\Services;

use Illuminate\Validation\ValidationException;

class GifService
{
    public const SURFACES = ['messenger', 'post_comments', 'reel_comments'];

    /** @return array{provider: string, id: string}|null */
    public function normalize(mixed $reference): ?array
    {
        if ($reference === null || $reference === '') {
            return null;
        }
        if (is_string($reference) && strlen($reference) <= 512) {
            $reference = json_decode($reference, true);
            if ($reference === null) {
                throw ValidationException::withMessages(['gif' => __('Please select a valid GIF.')]);
            }
        }
        if (!is_array($reference) || count($reference) !== 2
            || !in_array($reference['provider'] ?? null, ['klipy', 'giphy'], true)
            || !is_string($reference['id'] ?? null)
            || !preg_match('/\A[a-zA-Z0-9_-]{1,200}\z/', $reference['id'])) {
            throw ValidationException::withMessages(['gif' => __('Please select a valid GIF.')]);
        }

        return ['provider' => $reference['provider'], 'id' => $reference['id']];
    }

    /** @return array{provider: string, id: string}|null */
    public function validateSelection(mixed $reference, string $surface, ?array $existing = null): ?array
    {
        $reference = $this->normalize($reference);
        if ($reference === null) {
            return null;
        }
        if ($existing !== null && $reference === $this->normalize($existing)) {
            return $reference;
        }
        if (!$this->isEnabled($surface)) {
            throw ValidationException::withMessages(['gif' => __('GIFs are not available here.')]);
        }
        if ($reference['provider'] !== getSetting('media.gif_provider', 'klipy')) {
            throw ValidationException::withMessages(['gif' => __('The GIF provider changed. Please select your GIF again.')]);
        }

        return $reference;
    }

    public function isEnabled(string $surface): bool
    {
        if (!in_array($surface, self::SURFACES, true)
            || !getSetting('media.gif_picker_enabled', false)
            || !getSetting('media.gif_'.$surface.'_enabled', true)) {
            return false;
        }
        $provider = getSetting('media.gif_provider', 'klipy');

        return in_array($provider, ['klipy', 'giphy'], true) && $this->key($provider, $surface) !== '';
    }

    /** Browser credentials are public client keys, scoped to this content surface. */
    public function clientConfig(string $surface): array
    {
        if (!in_array($surface, self::SURFACES, true)) {
            return [];
        }

        return [
            'surface' => $surface,
            'enabled' => $this->isEnabled($surface),
            'provider' => getSetting('media.gif_provider', 'klipy'),
            'labels' => [
                'GIF unavailable' => __('GIF unavailable'),
                'Loading GIF…' => __('Loading GIF…'),
                'Play GIF' => __('Play GIF'),
                'Pause GIF' => __('Pause GIF'),
            ],
            'keys' => [
                'klipy' => $this->key('klipy', $surface),
                'giphy' => $this->key('giphy', $surface),
            ],
        ];
    }

    private function key(string $provider, string $surface): string
    {
        return trim((string) getSetting($provider === 'klipy'
            ? 'media.gif_klipy_key'
            : 'media.gif_giphy_'.$surface.'_key', ''));
    }
}
