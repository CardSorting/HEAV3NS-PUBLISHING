<?php

namespace App\Http\Middleware;

use App\Model\User;
use App\Services\TwoFactorAuthenticationService;
use Closure;
use Illuminate\Http\Request;
use Session;

class Check2FA
{
    public function __construct(
        private readonly TwoFactorAuthenticationService $twoFactorAuthenticationService,
    ) {
    }

    /**
     * Handle an incoming request.
     *
     * @param  Request  $request
     * @param  Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        if (Session::has('force2fa') && Session::get('force2fa') == true) {
            /** @var User|null $user */
            $user = $request->user();

            if (!$user || !$this->twoFactorAuthenticationService->requiresVerification($user)) {
                Session::forget('force2fa');

                return $next($request);
            }

            return redirect()->route('2fa.index');
        }

        return $next($request);
    }
}
