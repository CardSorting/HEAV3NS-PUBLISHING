<?php

namespace App\Http\Middleware;

use App\Services\AgeCheck\AdultAccessCookie;
use App\Services\AgeCheck\AgeGate;
use App\Services\AgeCheck\CountryDetector;
use App\Providers\InstallerServiceProvider;
use App\Settings\ComplianceSettings;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureAdultAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        if (!$this->shouldInspect($request) || !$this->requiresVerification($request)) {
            return $next($request);
        }

        if (app(AdultAccessCookie::class)->isValid($request)) {
            return $next($request);
        }

        if ($request->expectsJson()) {
            abort(403, 'Age verification required.');
        }

        return redirect()->route('age-check.show', ['return' => $request->fullUrl()]);
    }

    protected function shouldInspect(Request $request): bool
    {
        if (!InstallerServiceProvider::checkIfInstalled()) {
            return false;
        }

        if ($request->is(
            'age-check',
            'age-check/*',
            'admin',
            'admin/*',
            'install',
            'install/*',
            'update',
            'update/*',
            'payment/*StatusUpdate',
            'transcoding/*',
            'moderation/sightengine/callback/*'
        )) {
            return false;
        }

        if (!app(AgeGate::class)->isAgeVerifOAuth()) {
            return false;
        }

        return !Auth::check() || (int) Auth::user()->role_id !== 1;
    }

    protected function requiresVerification(Request $request): bool
    {
        $settings = app(ComplianceSettings::class);

        if ($settings->age_gate_country_detection_driver === 'none') {
            return true;
        }

        if ($settings->age_gate_countries_mode === 'everyone') {
            return true;
        }

        $country = app(CountryDetector::class)->detect($request);

        if (!$country) {
            return (bool) $settings->age_gate_require_unknown_country;
        }

        return in_array($country, $this->selectedCountries($settings->age_gate_countries), true);
    }

    protected function selectedCountries(array $countries): array
    {
        return array_values(array_filter(array_map(
            fn ($country) => strtoupper(trim((string) $country)),
            $countries
        )));
    }
}
