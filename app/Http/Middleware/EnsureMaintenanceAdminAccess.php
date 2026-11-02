<?php

namespace App\Http\Middleware;

use App\Model\User;
use Closure;
use Filament\Facades\Filament;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;

class EnsureMaintenanceAdminAccess
{
    public function handle(Request $request, Closure $next)
    {
        if ($request->attributes->get('maintenance_admin_support')) {
            $user = $request->user();
            if (!$user instanceof User || !$user->canAccessPanel(Filament::getPanel('admin'))) {
                throw new HttpException(503, 'Service Unavailable', null, ['Retry-After' => '60']);
            }
        }

        return $next($request);
    }
}
