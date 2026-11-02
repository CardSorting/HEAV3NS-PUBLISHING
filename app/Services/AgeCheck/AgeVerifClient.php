<?php

namespace App\Services\AgeCheck;

use App\Settings\ComplianceSettings;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

class AgeVerifClient
{
    public function __construct(
        protected ComplianceSettings $settings,
    ) {
    }

    public function authorizationUrl(string $state, string $redirectUri): string
    {
        $query = [
            'client_id' => $this->settings->age_gate_ageverif_oauth_client_id,
            'redirect_uri' => $redirectUri,
            'response_type' => 'code',
            'scope' => 'read',
            'state' => $state,
        ];

        $challenges = $this->normalizedChallenges();

        if ($challenges !== '') {
            $query['challenges'] = $challenges;
        }

        return config('agecheck.ageverif.authorization_endpoint').'?'.http_build_query($query);
    }

    public function exchangeCode(string $code, string $redirectUri): array
    {
        $clientId = (string) $this->settings->age_gate_ageverif_oauth_client_id;
        $clientSecret = (string) $this->settings->age_gate_ageverif_oauth_client_secret;

        $response = Http::asForm()
            ->withBasicAuth($clientId, $clientSecret)
            ->post(config('agecheck.ageverif.token_endpoint'), [
                'grant_type' => 'authorization_code',
                'code' => $code,
                'redirect_uri' => $redirectUri,
            ]);

        if (!$response->successful()) {
            throw new RuntimeException('AgeVerif token exchange failed.');
        }

        return $response->json();
    }

    public function resources(string $accessToken): ?array
    {
        $response = Http::withToken($accessToken)
            ->get(config('agecheck.ageverif.resources_endpoint'));

        if (!$response->successful()) {
            return null;
        }

        return $response->json('resources');
    }

    public function hasOAuthCredentials(): bool
    {
        return filled($this->settings->age_gate_ageverif_oauth_client_id)
            && filled($this->settings->age_gate_ageverif_oauth_client_secret);
    }

    public function randomState(): string
    {
        return Str::random(48);
    }

    protected function normalizedChallenges(): string
    {
        $challenges = $this->settings->age_gate_ageverif_challenges;

        return implode(',', array_values(array_filter(array_map(
            fn ($challenge) => trim((string) $challenge),
            is_array($challenges) ? $challenges : explode(',', (string) $challenges)
        ))));
    }
}
