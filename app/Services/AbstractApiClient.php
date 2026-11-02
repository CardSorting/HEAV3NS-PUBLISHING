<?php

namespace App\Services;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AbstractApiClient
{
    private const PROVIDER_FALLBACK_DELAY_MILLISECONDS = 1100;
    private const EMAIL_ENDPOINTS = [
        'reputation' => 'https://emailreputation.abstractapi.com/v1/',
        'validation' => 'https://emailvalidation.abstractapi.com/v1/',
    ];
    private const IP_ENDPOINTS = [
        'geolocation' => 'https://ipgeolocation.abstractapi.com/v1/',
        'intelligence' => 'https://ip-intelligence.abstractapi.com/v1/',
    ];

    public function __construct(
        private int $providerFallbackDelayMilliseconds = self::PROVIDER_FALLBACK_DELAY_MILLISECONDS,
    ) {
    }

    public function emailDeliverability(string $apiKey, string $email): ?string
    {
        $apiKey = trim($apiKey);

        if ($apiKey === '') {
            return null;
        }

        $result = $this->requestWithFallback(
            'email',
            $apiKey,
            self::EMAIL_ENDPOINTS,
            fn (string $provider) => [
                'api_key' => $apiKey,
                'email' => $email,
            ],
            10,
        );

        $response = $result['response'];

        if (!$response?->successful()) {
            $this->logFailure('email', $result);

            return null;
        }

        $path = $result['provider'] === 'reputation'
            ? 'email_deliverability.status'
            : 'deliverability';
        $status = strtolower(trim((string) $response->json($path)));

        if (!in_array($status, ['deliverable', 'undeliverable', 'unknown'], true)) {
            Log::warning('Abstract API returned an unexpected email deliverability value.', [
                'provider' => $result['provider'],
                'status' => $response->status(),
            ]);

            return null;
        }

        return $status;
    }

    public function ipCountry(string $apiKey, string $ip): ?string
    {
        $cacheKey = 'abstract-api:ip-country:'
            .hash('sha256', trim($apiKey))
            .':'
            .hash('sha256', $ip);

        try {
            return Cache::remember(
                $cacheKey,
                60 * 60 * 12,
                fn () => $this->ipLookup($apiKey, $ip)['country_code'],
            );
        } catch (\Throwable) {
            return $this->ipLookup($apiKey, $ip)['country_code'];
        }
    }

    /**
     * @return array{country_code: ?string, status: ?int, provider: ?string, body: string, error: ?string}
     */
    public function ipLookup(string $apiKey, string $ip): array
    {
        $apiKey = trim($apiKey);

        if ($apiKey === '' || $ip === '') {
            return $this->emptyIpResult('missing API key or IP address');
        }

        $result = $this->requestWithFallback(
            'ip',
            $apiKey,
            self::IP_ENDPOINTS,
            fn (string $provider) => [
                'api_key' => $apiKey,
                'ip_address' => $ip,
                'fields' => $provider === 'intelligence' ? 'location' : 'country_code',
            ],
            3,
        );

        $response = $result['response'];

        if (!$response?->successful()) {
            $this->logFailure('ip', $result);
            $apiError = $response?->json('error.message');

            return [
                'country_code' => null,
                'status' => $response?->status(),
                'provider' => $result['provider'],
                'body' => $response?->body() ?? '',
                'error' => is_string($apiError) ? $apiError : $result['error'],
            ];
        }

        $path = $result['provider'] === 'intelligence'
            ? 'location.country_code'
            : 'country_code';
        $countryCode = strtoupper(trim((string) $response->json($path)));
        $countryCode = preg_match('/^[A-Z]{2}$/', $countryCode) ? $countryCode : null;

        return [
            'country_code' => $countryCode,
            'status' => $response->status(),
            'provider' => $result['provider'],
            'body' => $response->body(),
            'error' => null,
        ];
    }

    /**
     * @param array<string, string> $endpoints
     * @param callable(string): array<string, string> $query
     * @return array{response: ?Response, provider: ?string, error: ?string}
     */
    private function requestWithFallback(
        string $service,
        string $apiKey,
        array $endpoints,
        callable $query,
        int $timeout,
    ): array {
        $cacheKey = 'abstract-api:provider:'.$service.':'.hash('sha256', $apiKey);
        try {
            $preferredProvider = Cache::get($cacheKey);
        } catch (\Throwable) {
            $preferredProvider = null;
        }
        $providers = array_keys($endpoints);

        if (is_string($preferredProvider) && isset($endpoints[$preferredProvider])) {
            $providers = array_values(array_unique([
                $preferredProvider,
                ...$providers,
            ]));
        }

        $lastResponse = null;
        $lastProvider = null;
        $lastError = null;

        foreach ($providers as $index => $provider) {
            $lastProvider = $provider;

            try {
                $lastResponse = Http::acceptJson()
                    ->timeout($timeout)
                    ->get($endpoints[$provider], $query($provider));
                $lastError = null;
            } catch (\Throwable $exception) {
                $lastResponse = null;
                $lastError = $exception::class;

                continue;
            }

            if ($lastResponse->successful()) {
                $this->rememberProvider($cacheKey, $provider);

                return [
                    'response' => $lastResponse,
                    'provider' => $provider,
                    'error' => null,
                ];
            }

            if ($lastResponse->status() !== 401) {
                // A non-authentication response means this key belongs to this API generation.
                $this->rememberProvider($cacheKey, $provider);

                break;
            }

            if (isset($providers[$index + 1]) && $this->providerFallbackDelayMilliseconds > 0) {
                usleep($this->providerFallbackDelayMilliseconds * 1000);
            }
        }

        return [
            'response' => $lastResponse,
            'provider' => $lastProvider,
            'error' => $lastError,
        ];
    }

    private function rememberProvider(string $cacheKey, string $provider): void
    {
        try {
            Cache::put($cacheKey, $provider, 60 * 60 * 24 * 30);
        } catch (\Throwable) {
            // Endpoint detection is an optimization; API validation must still work without cache.
        }
    }

    /**
     * @param array{response: ?Response, provider: ?string, error: ?string} $result
     */
    private function logFailure(string $service, array $result): void
    {
        Log::warning('Abstract API request failed.', [
            'service' => $service,
            'provider' => $result['provider'],
            'status' => $result['response']?->status(),
            'api_error' => $result['response']?->json('error.code'),
            'exception' => $result['error'],
        ]);
    }

    /**
     * @return array{country_code: null, status: null, provider: null, body: string, error: string}
     */
    private function emptyIpResult(string $error): array
    {
        return [
            'country_code' => null,
            'status' => null,
            'provider' => null,
            'body' => '',
            'error' => $error,
        ];
    }
}
