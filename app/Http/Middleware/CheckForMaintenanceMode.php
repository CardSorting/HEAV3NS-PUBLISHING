<?php

namespace App\Http\Middleware;

use App\Services\SiteMaintenanceService;
use Closure;
use Illuminate\Foundation\Http\Middleware\CheckForMaintenanceMode as Middleware;
use Illuminate\Support\Facades\Route;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

class CheckForMaintenanceMode extends Middleware
{
    // These paths retain their existing authentication and permission checks.
    protected $except = ['admin', 'admin/*', 'update', 'update/*'];

    public const PAYMENT_CALLBACKS = [
        'stripe.payment.update', 'stripeConnect.payment.update', 'paypal.payment.update',
        'yookassa.payment.update', 'mollie.payment.update', 'flutterwave.payment.update',
        'coingate.payment.update', 'xendit.payment.update', 'paddle.payment.update',
        'cryptocom.payment.update', 'nowPayments.payment.update', 'ccBill.payment.update',
        'paystack.payment.update', 'mercado.payment.update', 'verotel.payment.update',
        'razorpay.payment.update',
    ];

    public function handle($request, Closure $next)
    {
        if ($this->app->maintenanceMode()->active()
            && ($this->app->maintenanceMode()->data()['justfans_source'] ?? null) === SiteMaintenanceService::SOURCE) {
            try {
                // Resolve names without executing a controller or trusting Referer/payload data.
                $route = Route::getRoutes()->match($request);
            } catch (HttpExceptionInterface) {
                return parent::handle($request, $next);
            }
            $name = $route->getName();
            if (in_array($name, self::PAYMENT_CALLBACKS, true)
                || in_array($name, ['login', 'logout', 'age-check.show', 'age-check.start', 'age-check.callback'], true)
                || $route->getActionName() === 'App\\Http\\Controllers\\Auth\\LoginController@login') {
                return $next($request);
            }
            // Livewire appends this suffix to default and custom route names.
            if ($route->named('*livewire.update') || in_array($name, [
                'livewire.upload-file',
                'livewire.preview-file', 'livewire.script.test',
                '2fa.index', '2fa.post', '2fa.resend', '2fa.delete',
            ], true)) {
                // The web middleware verifies the authenticated panel user after session start.
                $request->attributes->set('maintenance_admin_support', true);

                return $next($request);
            }
        }

        return parent::handle($request, $next);
    }
}
