<?php

namespace App\Http\Middleware;

use App\Providers\InstallerServiceProvider;
use App\Services\Installer\InstallationState;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RedirectToInstallationProgress
{
    public function __construct(private InstallationState $installationState) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (InstallerServiceProvider::checkIfInstalled()) {
            return redirect()->route('home');
        }

        $token = (string) $request->session()->get('installer.token', '');
        if ($this->installationState->owns($token)) {
            return redirect()->route('installer.progress');
        }

        if ($this->installationState->isActive()) {
            return response()->view('installer.recovery', [], 409);
        }

        // A server-side reset cannot clear browser sessions. Do not reuse the old token.
        if ($token !== '') {
            $request->session()->forget([
                'installer', 'installer_start_token', 'installer_recovery',
                'license', 'licenseCode', InstallerServiceProvider::$lockCode,
            ]);
        }

        $response = $next($request);
        $response->headers->set('Cache-Control', 'no-store, private');

        return $response;
    }
}
