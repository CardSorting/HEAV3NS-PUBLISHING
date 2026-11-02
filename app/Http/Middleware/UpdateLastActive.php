<?php

namespace App\Http\Middleware;

use App\Providers\InstallerServiceProvider;
use Closure;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

class UpdateLastActive
{
    public function handle($request, Closure $next)
    {

        if (!InstallerServiceProvider::checkIfInstalled()) {
            // Just pass the request along untouched
            return $next($request);
        }

        // If the feature is disabled, skip everything (if you have that toggle)
        if(!getSetting('profiles.record_users_last_activity_time')) {
            return $next($request);
        }

        if (Auth::check()) {
            $user = Auth::user();

            // Only update once every 5 minutes
            $threshold = Carbon::now()->subMinutes(5);

            if ($user->last_active_at === null || $user->last_active_at < $threshold) {
                $user->last_active_at = Carbon::now();
                $user->save();
            }
        }

        return $next($request);
    }
}
