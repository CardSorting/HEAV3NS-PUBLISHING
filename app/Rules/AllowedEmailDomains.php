<?php

namespace App\Rules;

use App\Settings\SecuritySettings;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class AllowedEmailDomains implements ValidationRule
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

        $policy = $this->settings->email_domain_policy ?? 'allow_all';
        $allow = $this->normalizeDomains($this->settings->email_allowedlist_domains ?? []);
        $block = $this->normalizeDomains($this->settings->email_blocklist_domains ?? []);

        if ($policy === 'allow_all') {
            return;
        }

        $domain = $this->extractDomain($value);

        if (!$domain) {
            return;
        }

        if ($policy === 'allowlist_only' && !$this->matchesAny($domain, $allow)) {
            $fail(__('Email addresses from :domain are not allowed.', ['domain' => $domain]));
            return;
        }

        if ($policy === 'blocklist_only' && $this->matchesAny($domain, $block)) {
            $fail(__('Email addresses from :domain are not allowed.', ['domain' => $domain]));
        }
    }

    protected function extractDomain(string $email): ?string
    {
        $parts = explode('@', trim($email));

        if (count($parts) !== 2 || $parts[1] === '') {
            return null;
        }

        return $this->normalizeDomain($parts[1]);
    }

    protected function normalizeDomains(array $domains): array
    {
        $out = [];

        foreach ($domains as $domain) {
            if (!is_string($domain)) {
                continue;
            }

            $domain = trim($domain);

            if ($domain === '') {
                continue;
            }

            $out[] = $this->normalizeDomain($domain);
        }

        return array_values(array_unique($out));
    }

    protected function normalizeDomain(string $domain): string
    {
        $domain = trim(mb_strtolower($domain));
        return preg_replace('/^\.+|\.+$/', '', $domain);
    }

    protected function matchesAny(string $domain, array $rules): bool
    {
        foreach ($rules as $rule) {
            if ($rule === $domain) {
                return true;
            }

            if (str_starts_with($rule, '*.')) {
                $base = substr($rule, 2);

                if ($base !== '' && $domain !== $base && str_ends_with($domain, '.'.$base)) {
                    return true;
                }
            }
        }

        return false;
    }
}
