<?php

namespace App\Services\AgeCheck;

use App\Settings\ComplianceSettings;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Cookie;

class AdultAccessCookie
{
    public function __construct(
        protected ComplianceSettings $settings,
    ) {
    }

    public function isValid(Request $request): bool
    {
        $payload = $this->payload($request);

        if (!$payload) {
            return false;
        }

        return ($payload['verified'] ?? false) === true
            && (int) ($payload['age_threshold'] ?? 0) >= $this->minimumAge()
            && (int) ($payload['expires_at'] ?? 0) > now()->timestamp;
    }

    public function payload(Request $request): ?array
    {
        $value = $request->cookie($this->name());

        if (!is_string($value) || $value === '') {
            return null;
        }

        $payload = json_decode($value, true);

        return is_array($payload) ? $payload : null;
    }

    public function make(array $payload): Cookie
    {
        $expiresAt = (int) ($payload['expires_at'] ?? now()->addDays($this->lifetimeDays())->timestamp);
        $minutes = max(1, (int) ceil(($expiresAt - now()->timestamp) / 60));

        return cookie(
            $this->name(),
            json_encode($payload),
            $minutes,
            null,
            null,
            (bool) config('session.secure'),
            true,
            false,
            'lax'
        );
    }

    public function name(): string
    {
        return config('agecheck.cookie_name', 'adult_access');
    }

    public function lifetimeDays(): int
    {
        return max(1, (int) ($this->settings->age_gate_cookie_lifetime_days ?: 90));
    }

    public function minimumAge(): int
    {
        return max(18, (int) ($this->settings->age_gate_minimum_age ?: 18));
    }
}
