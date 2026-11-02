<?php

namespace App\Services\AgeCheck;

use App\Providers\InstallerServiceProvider;
use App\Settings\ComplianceSettings;
use Illuminate\Http\Request;

class AgeGate
{
    public const DRIVER_NONE = 'none';
    public const DRIVER_BUILT_IN = 'built_in';
    public const DRIVER_AGEVERIF_CHECKER = 'ageverif_checker';
    public const DRIVER_AGEVERIF_OAUTH = 'ageverif_oauth';

    public function driver(): string
    {
        if (!$this->usesModernSettings()) {
            return getSetting('compliance.enable_age_verification_dialog', false)
                ? self::DRIVER_BUILT_IN
                : self::DRIVER_NONE;
        }

        $settings = $this->settings();
        $driver = $settings->age_gate_driver ?: self::DRIVER_NONE;

        if (in_array($driver, [
            self::DRIVER_BUILT_IN,
            self::DRIVER_AGEVERIF_CHECKER,
            self::DRIVER_AGEVERIF_OAUTH,
        ], true)) {
            return $driver;
        }

        if ($settings->age_gate_enabled) {
            return $settings->age_gate_mode === 'oauth'
                ? self::DRIVER_AGEVERIF_OAUTH
                : self::DRIVER_AGEVERIF_CHECKER;
        }

        if ($settings->enable_age_verification_dialog) {
            return self::DRIVER_BUILT_IN;
        }

        return self::DRIVER_NONE;
    }

    public function isBuiltIn(): bool
    {
        return $this->driver() === self::DRIVER_BUILT_IN;
    }

    public function isAgeVerifChecker(): bool
    {
        return $this->driver() === self::DRIVER_AGEVERIF_CHECKER;
    }

    public function isAgeVerifOAuth(): bool
    {
        return $this->driver() === self::DRIVER_AGEVERIF_OAUTH;
    }

    public function checkerScriptUrl(Request $request): ?string
    {
        if (!$this->canRenderChecker($request)) {
            return null;
        }

        $settings = $this->settings();
        $query = ['key' => $settings->age_gate_ageverif_public_key];
        $challenges = $this->normalizedChallenges($settings->age_gate_ageverif_challenges);

        if ($challenges !== '') {
            $query['challenges'] = $challenges;
        }

        return config('agecheck.ageverif.checker_script_url').'?'.http_build_query($query);
    }

    protected function canRenderChecker(Request $request): bool
    {
        if (!InstallerServiceProvider::checkIfInstalled()) {
            return false;
        }

        if ($request->is('admin*', 'install*', 'update*', 'age-check*')) {
            return false;
        }

        if (auth()->check() && (int) auth()->user()->role_id === 1) {
            return false;
        }

        if (!$this->isAgeVerifChecker()) {
            return false;
        }

        return filled($this->settings()->age_gate_ageverif_public_key);
    }

    protected function normalizedChallenges(string|array|null $challenges): string
    {
        return implode(',', array_values(array_filter(array_map(
            fn ($challenge) => trim((string) $challenge),
            is_array($challenges) ? $challenges : explode(',', (string) $challenges)
        ))));
    }

    protected function settings(): ComplianceSettings
    {
        return app(ComplianceSettings::class);
    }

    protected function usesModernSettings(): bool
    {
        return config('settings.admin_version') === 'v2';
    }
}
