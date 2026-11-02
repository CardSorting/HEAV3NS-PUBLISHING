<?php

namespace App\Services\AgeCheck;

use App\Settings\ComplianceSettings;
use App\Services\AbstractApiClient;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class CountryDetector
{
    public function __construct(
        protected ComplianceSettings $settings,
        protected AbstractApiClient $abstractApiClient,
    ) {
    }

    public function detect(Request $request): ?string
    {
        return match ($this->settings->age_gate_country_detection_driver) {
            'cloudflare' => $this->fromCloudflare($request),
            'abstract' => $this->fromAbstract($request),
            default => null,
        };
    }

    protected function fromCloudflare(Request $request): ?string
    {
        $country = strtoupper((string) $request->header('CF-IPCountry'));

        return preg_match('/^[A-Z]{2}$/', $country) ? $country : null;
    }

    protected function fromAbstract(Request $request): ?string
    {
        if (!filled($this->settings->age_gate_abstract_api_key)) {
            return null;
        }

        $ip = $request->ip();

        if (!$ip) {
            return null;
        }

        return Cache::remember('agecheck:abstract:country:'.$ip, now()->addHours(12), function () use ($ip) {
            return $this->abstractApiClient->ipCountry(
                (string) $this->settings->age_gate_abstract_api_key,
                $ip,
            );
        });
    }

    public function debugAbstract(Request $request): array
    {
        if ($this->settings->age_gate_country_detection_driver !== 'abstract') {
            return [];
        }

        if (!filled($this->settings->age_gate_abstract_api_key)) {
            return [
                'error' => 'missing API key',
            ];
        }

        $ip = $request->ip();

        if (!$ip) {
            return [
                'error' => 'missing request IP',
            ];
        }

        $result = $this->abstractApiClient->ipLookup(
            (string) $this->settings->age_gate_abstract_api_key,
            $ip,
        );

        return [
            'ip' => $ip,
            'provider' => $result['provider'] ?? 'none',
            'status' => $result['status'] ?? 'none',
            'successful' => $result['country_code'] ? 'yes' : 'no',
            'country_code' => $result['country_code'] ?? 'none',
            'body' => mb_substr($result['body'], 0, 1200),
            'error' => $result['error'] ?? 'none',
        ];
    }
}
