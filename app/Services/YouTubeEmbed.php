<?php

namespace App\Services;

use App\Rules\AllowedHyperlinks;
use Illuminate\Translation\PotentiallyTranslatedString;

class YouTubeEmbed
{
    private const HOSTS = [
        'youtube.com', 'www.youtube.com', 'm.youtube.com', 'music.youtube.com',
        'youtu.be', 'www.youtu.be', 'youtube-nocookie.com', 'www.youtube-nocookie.com',
    ];

    /** @return list<string> */
    public function videoIds(?string $text): array
    {
        $videoIds = [];
        foreach ($this->candidates($text) as [$url, $videoId]) {
            $videoIds[] = $videoId;
        }

        return array_values(array_unique($videoIds));
    }

    /** @return list<string> */
    public function allowedHosts(): array
    {
        $rule = app(AllowedHyperlinks::class);
        $hosts = [];
        foreach (self::HOSTS as $host) {
            $allowed = true;
            $rule->validate('text', 'https://'.$host, function (string $message, ?string $attribute = null) use (&$allowed): PotentiallyTranslatedString {
                $allowed = false;

                return new PotentiallyTranslatedString($message, app('translator'));
            });
            if ($allowed) {
                $hosts[] = $host;
            }
        }

        return $hosts;
    }

    /** @return list<string> */
    public function forPost(?string $text): array
    {
        if (!getSetting('feed.youtube_embeds_enabled', false)) {
            return [];
        }

        $rule = app(AllowedHyperlinks::class);
        $videoIds = [];
        foreach ($this->candidates($text) as [$url, $videoId]) {
            $allowed = true;
            $rule->validate('text', $url, function (string $message, ?string $attribute = null) use (&$allowed): PotentiallyTranslatedString {
                $allowed = false;

                return new PotentiallyTranslatedString($message, app('translator'));
            });

            if ($allowed) {
                $videoIds[] = $videoId;
            }
        }

        return array_values(array_unique($videoIds));
    }

    /**
     * Keep URL parsing separate from rendering: callers only receive a validated
     * video ID and must build the iframe URL themselves.
     *
     * @return iterable<array{string, string}>
     */
    private function candidates(?string $text): iterable
    {
        if (!$text) {
            return;
        }

        // The boundary prevents extracting a trusted suffix from another URL,
        // such as ftp://www.youtube.com or example.com/https://youtu.be/....
        preg_match_all('~(?<![a-z0-9_@:/\\\\.\-])(?:https?://|www\.)[^\s<>"\'`]+~iu', $text, $matches);

        foreach ($matches[0] as $candidate) {
            $url = rtrim($candidate, '.,!?;:)]}');
            if (str_starts_with(strtolower($url), 'www.')) {
                $url = 'https://'.$url;
            }

            $videoId = $this->videoId($url);
            if ($videoId !== null) {
                yield [$url, $videoId];
            }
        }
    }

    private function videoId(string $url): ?string
    {
        if (str_contains($url, '\\')) {
            return null;
        }

        if (preg_match('~^https?://([^/?#]*)~i', $url, $authority) && str_ends_with($authority[1], ':')) {
            return null;
        }

        $parts = parse_url($url);
        if (!is_array($parts) || isset($parts['user']) || isset($parts['pass'])) {
            return null;
        }

        $scheme = strtolower($parts['scheme'] ?? '');
        $host = strtolower($parts['host'] ?? '');
        if (!in_array($scheme, ['http', 'https'], true) || !in_array($host, self::HOSTS, true)) {
            return null;
        }

        if (isset($parts['port']) && $parts['port'] !== ($scheme === 'https' ? 443 : 80)) {
            return null;
        }

        $path = $parts['path'] ?? '';
        $videoId = null;
        if (in_array($host, ['youtu.be', 'www.youtu.be'], true)) {
            if (preg_match('~^/([A-Za-z0-9_-]{11})/?$~D', $path, $matches)) {
                $videoId = $matches[1];
            }
        } elseif ($path === '/watch') {
            // Do not URL-decode IDs or accept array/duplicate v parameters.
            $values = [];
            foreach (explode('&', $parts['query'] ?? '') as $parameter) {
                [$key, $value] = array_pad(explode('=', $parameter, 2), 2, null);
                $decodedKey = rawurldecode($key);
                if (str_starts_with($decodedKey, 'v[') || ($decodedKey === 'v' && $key !== 'v')) {
                    return null;
                }
                if ($key === 'v') {
                    $values[] = $value;
                }
            }
            if (count($values) === 1) {
                $videoId = $values[0];
            }
        } elseif (preg_match('~^/(?:shorts|embed|live)/([A-Za-z0-9_-]{11})/?$~D', $path, $matches)) {
            $videoId = $matches[1];
        }

        return is_string($videoId) && preg_match('/^[A-Za-z0-9_-]{11}$/D', $videoId) ? $videoId : null;
    }
}
