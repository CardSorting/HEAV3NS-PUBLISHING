<?php

namespace App\Services;

use App\Model\UserSpotifyAccount;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;

class SpotifyService
{
    public function authUrl(string $state): string
    {
        $scopes = [
            'user-read-email',
            'user-read-private',
            'user-top-read',
        ];

        return 'https://accounts.spotify.com/authorize?'.http_build_query([
                'client_id' => getSetting('profiles.spotify_client_id'),
                'response_type' => 'code',
                'redirect_uri' => $this->redirectUri(),
                'scope' => implode(' ', $scopes),
                'state' => $state,
            ]);
    }

    public function exchangeCode(string $code): array
    {
        $res = Http::asForm()
            ->withBasicAuth(getSetting('profiles.spotify_client_id'), getSetting('profiles.spotify_client_secret'))
            ->post('https://accounts.spotify.com/api/token', [
                'grant_type' => 'authorization_code',
                'code' => $code,
                'redirect_uri' => $this->redirectUri(),
            ]);

        $res->throw();

        return $res->json();
    }

    public function refreshToken(UserSpotifyAccount $acc): void
    {
        if (!$acc->refresh_token) return;

        $res = Http::asForm()
            ->withBasicAuth(getSetting('profiles.spotify_client_id'), getSetting('profiles.spotify_client_secret'))
            ->post('https://accounts.spotify.com/api/token', [
                'grant_type' => 'refresh_token',
                'refresh_token' => $acc->refresh_token,
            ]);

        $res->throw();

        $data = $res->json();

        $acc->access_token = $data['access_token'] ?? $acc->access_token;
        if (!empty($data['refresh_token'])) {
            $acc->refresh_token = $data['refresh_token'];
        }
        $acc->expires_at = Carbon::now()->addSeconds((int)($data['expires_in'] ?? 3600))->subSeconds(30);
        $acc->save();
    }

    public function apiGet(UserSpotifyAccount $acc, string $path, array $query = []): array
    {
        if (!$acc->access_token) {
            throw new \RuntimeException(__('Spotify not connected.'));
        }

        if ($acc->expires_at && $acc->expires_at->isPast()) {
            $this->refreshToken($acc);
        }

        $res = Http::withToken($acc->access_token)
            ->get('https://api.spotify.com/v1'.$path, $query);

        $res->throw();

        return $res->json();
    }

    protected function redirectUri(): string
    {
        return (string) (config('services.spotify.redirect_url') ?: route('my.settings.spotify.callback'));
    }
}
