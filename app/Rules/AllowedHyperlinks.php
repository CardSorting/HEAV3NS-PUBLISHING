<?php

namespace App\Rules;

use App\Settings\SecuritySettings;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class AllowedHyperlinks implements ValidationRule
{
    public function __construct(
        protected ?SecuritySettings $settings = null,
    ) {
        $this->settings ??= app(SecuritySettings::class);
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (!is_string($value) || trim($value) === '') {
            return;
        }

        $policy = $this->settings->domain_policy ?? 'allow_all';
        $allow = $this->normalizeDomains($this->settings->allowedlist_domains ?? []);
        $block = $this->normalizeDomains($this->settings->blocklist_domains ?? []);

        // Allow everything
        if ($policy === 'allow_all') {
            return;
        }

        $urls = $this->extractUrls($value);

        foreach ($urls as $url) {
            $host = $this->extractHost($url);

            // If we can't parse host, ignore (or you can fail hard)
            if (!$host) {
                continue;
            }

            $host = $this->normalizeDomain($host);

            if ($policy === 'allowlist_only') {
                if (!$this->matchesAny($host, $allow)) {
                    $fail(__('Links to :domain are not allowed.', ['domain' => $host]));
                    return;
                }
            }

            if ($policy === 'blocklist_only') {
                if ($this->matchesAny($host, $block)) {
                    $fail(__('Links to :domain are not allowed.', ['domain' => $host]));
                    return;
                }
            }
        }
    }

    /**
     * Extract URLs from plain text + markdown links.
     */
    protected function extractUrls(string $text): array
    {
        $urls = [];

        // 1) Markdown: [title](https://example.com/path)
        if (preg_match_all('/\]\((https?:\/\/[^\s)]+)\)/i', $text, $m)) {
            $urls = array_merge($urls, $m[1]);
        }

        // 2) Plain URLs: https://..., http://...
        if (preg_match_all('/https?:\/\/[^\s<>"\')]+/i', $text, $m)) {
            $urls = array_merge($urls, $m[0]);
        }

        // 3) "www.example.com/..." (no scheme)
        if (preg_match_all('/\bwww\.[a-z0-9.-]+\.[a-z]{2,}(\/[^\s<>"\')]*)?/i', $text, $m)) {
            // Prefix scheme so parse_url works
            $urls = array_merge($urls, array_map(fn ($u) => 'https://'.$u, $m[0]));
        }

        // de-dupe
        $urls = array_values(array_unique($urls));

        return $urls;
    }

    protected function extractHost(string $url): ?string
    {
        $parts = parse_url($url);

        if (!is_array($parts)) {
            return null;
        }

        $host = $parts['host'] ?? null;

        // Some weird strings may end up in path if missing scheme, but we add scheme for www.*
        if (!$host) {
            return null;
        }

        // strip trailing dot, lowercase later
        return rtrim($host, '.');
    }

    protected function normalizeDomains(array $domains): array
    {
        $out = [];

        foreach ($domains as $d) {
            if (!is_string($d)) {
                continue;
            }
            $d = trim($d);
            if ($d === '') {
                continue;
            }

            // Allow users to paste full URLs by accident; keep only host
            if (str_contains($d, '://')) {
                $h = $this->extractHost($d);
                $d = $h ?: $d;
            }

            $out[] = $this->normalizeDomain($d);
        }

        return array_values(array_unique($out));
    }

    protected function normalizeDomain(string $domain): string
    {
        $domain = trim(mb_strtolower($domain));
        $domain = preg_replace('/^\.+|\.+$/', '', $domain); // remove leading/trailing dots
        return $domain;
    }

    /**
     * Supports exact + wildcard "*.example.com" matches.
     * Wildcard matches subdomains only (not the root).
     */
    protected function matchesAny(string $host, array $rules): bool
    {
        foreach ($rules as $rule) {
            if ($rule === $host) {
                return true;
            }

            // Wildcard: "*.example.com"
            if (str_starts_with($rule, '*.')) {
                $base = substr($rule, 2); // remove "*."
                if ($base !== '' && $host !== $base && str_ends_with($host, '.'.$base)) {
                    return true;
                }
            }
        }

        return false;
    }
}
