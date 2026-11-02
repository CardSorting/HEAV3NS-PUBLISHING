<?php

namespace App\Http\Controllers;

use App\Services\AgeCheck\AdultAccessCookie;
use App\Services\AgeCheck\AgeGate;
use App\Services\AgeCheck\AgeVerifClient;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use RuntimeException;

class AgeCheckController extends Controller
{
    public function show(Request $request, AgeGate $ageGate): View|RedirectResponse
    {
        if (!$ageGate->isAgeVerifOAuth()) {
            return redirect($this->safeReturnUrl($request->query('return')));
        }

        $accessCookie = app(AdultAccessCookie::class);

        if ($accessCookie->isValid($request)) {
            return redirect($this->safeReturnUrl($request->query('return')));
        }

        if ($request->filled('return')) {
            session([config('agecheck.return_url_key') => $this->safeReturnUrl($request->query('return'))]);
        }

        return view('age-check.index');
    }

    public function start(Request $request, AgeGate $ageGate): RedirectResponse
    {
        if (!$ageGate->isAgeVerifOAuth()) {
            return redirect($this->safeReturnUrl($request->query('return')));
        }

        $client = app(AgeVerifClient::class);

        if (!$client->hasOAuthCredentials()) {
            return redirect()
                ->route('age-check.show')
                ->with('error', __('Age verification is not configured yet.'));
        }

        $state = $client->randomState();

        session([
            config('agecheck.oauth_state_key') => $state,
            config('agecheck.return_url_key') => $this->safeReturnUrl($request->query('return')),
        ]);

        return redirect()->away($client->authorizationUrl($state, route('age-check.callback')));
    }

    public function callback(
        Request $request,
        AgeGate $ageGate
    ): RedirectResponse {
        if (!$ageGate->isAgeVerifOAuth()) {
            return redirect($this->safeReturnUrl($request->query('return')));
        }

        $client = app(AgeVerifClient::class);
        $accessCookie = app(AdultAccessCookie::class);

        if (!hash_equals((string) session(config('agecheck.oauth_state_key')), (string) $request->query('state'))) {
            return redirect()
                ->route('age-check.show')
                ->with('error', __('Age verification session expired. Please try again.'));
        }

        if (!$request->filled('code')) {
            return redirect()
                ->route('age-check.show')
                ->with('error', __('Age verification was not completed.'));
        }

        try {
            $token = $client->exchangeCode((string) $request->query('code'), route('age-check.callback'));
            $resources = $client->resources((string) ($token['access_token'] ?? ''));
        } catch (RuntimeException) {
            return redirect()
                ->route('age-check.show')
                ->with('error', __('Age verification could not be confirmed. Please try again.'));
        }

        $minimumAge = $accessCookie->minimumAge();
        $verified = (bool) ($resources['verified'] ?? true);
        $ageThreshold = (int) ($resources['age_threshold'] ?? $minimumAge);

        if (!$verified || $ageThreshold < $minimumAge) {
            return redirect()
                ->route('age-check.show')
                ->with('error', __('Age verification failed.'));
        }

        $localExpiry = now()->addDays($accessCookie->lifetimeDays())->timestamp;
        $providerExpiry = (int) ($resources['expires_at'] ?? $localExpiry);
        $expiresAt = min($localExpiry, $providerExpiry);

        $payload = [
            'provider' => 'ageverif',
            'verified' => true,
            'uid' => $resources['uid'] ?? null,
            'country' => $resources['country'] ?? null,
            'country_subdivision' => $resources['country_subdivision'] ?? null,
            'assurance_level' => $resources['assurance_level'] ?? null,
            'age_threshold' => $ageThreshold,
            'verified_at' => now()->timestamp,
            'expires_at' => $expiresAt,
        ];

        session()->forget(config('agecheck.oauth_state_key'));

        $returnUrl = session()->pull(config('agecheck.return_url_key'), route('home'));

        return redirect($this->safeReturnUrl($returnUrl))->withCookie($accessCookie->make($payload));
    }

    protected function safeReturnUrl(mixed $url): string
    {
        if (!is_string($url) || $url === '') {
            return route('home');
        }

        if (str_starts_with($url, '/') && !str_starts_with($url, '//')) {
            return $url;
        }

        if (str_starts_with($url, url('/'))) {
            return $url;
        }

        return route('home');
    }
}
