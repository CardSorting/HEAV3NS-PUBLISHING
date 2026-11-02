<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;

class EnsureUserIsAdmin
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  Closure  $next
     * @return \Illuminate\Http\Response|\Illuminate\Http\RedirectResponse
     */
    public function handle($request, Closure $next)
    {

        $routeName = $request->route() ? $request->route()->getName() : null;

        // Allow specific open routes (login, assets, leave impersonation)
        if ($routeName && in_array($routeName, [
                'admin.leaveImpersonation',
            ])) {
            return $next($request);
        }

        // If user not logged in, redirect to login
        if (!Auth::check()) {
            return $request->expectsJson()
                ? abort(403, 'Unauthorized')
                : Redirect::route('login');
        }

        // If user is logged in but NOT an admin, abort with 403 (do NOT redirect to login!)
        if ((int) Auth::user()->role_id !== 1) {
            abort(403, 'Unauthorized');
        }

        // User is logged in and is an admin → allow access
        return $next($request);
    }
}
