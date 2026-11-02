<?php

namespace App\Http\Middleware;

use App\Model\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAccountIsNotSuspended
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (!$user instanceof User || !$user->isBanned() || $this->isPermitted($request)) {
            return $next($request);
        }

        if ($request->expectsJson()) {
            return response()->json([
                'message' => __('Your account is suspended.'),
                'code' => 'account_suspended',
                'redirect' => route('account.suspension'),
            ], 403);
        }

        if (!$request->isMethodSafe()) {
            abort(403, __('Your account is suspended.'));
        }

        return redirect()->route('account.suspension');
    }

    private function isPermitted(Request $request): bool
    {
        if ($request->routeIs('my.settings')) {
            return $request->isMethodSafe()
                && in_array($request->route('type'), ['account', 'subscriptions', 'payments'], true);
        }

        // Settlement callbacks must remain processable, including when a browser
        // returns with the session of a user who was suspended after checkout.
        if ($request->routeIs(
            'stripe.payment.update',
            'stripeConnect.payment.update',
            'paypal.payment.update',
            'yookassa.payment.update',
            'mollie.payment.update',
            'flutterwave.payment.update',
            'coingate.payment.update',
            'xendit.payment.update',
            'paddle.payment.update',
            'cryptocom.payment.update',
            'nowPayments.payment.update',
            'ccBill.payment.update',
            'paystack.payment.update',
            'mercado.payment.update',
            'verotel.payment.update',
            'razorpay.payment.update',
            'transcoding.coconut.update',
            'moderation.sightengine.callback',
            'payment.executePaypalPayment',
            'payment.checkStripePaymentStatus',
            'payment.checkYooKassaPaymentStatus',
            'payment.checkMolliePaymentStatus',
            'payment.checkFlutterwavePaymentStatus',
            'payment.checkCoinGatePaymentStatus',
            'payment.checkXenditPaymentStatus',
            'payment.checkPaddlePaymentStatus',
            'payment.checkCryptoComPaymentStatus',
            'payment.checkNowPaymentStatus',
            'payment.checkCCBillPaymentStatus',
            'payment.checkPaystackPaymentStatus',
            'payment.checkMercadoPaymentStatus',
            'payment.checkVerotelPaymentStatus',
            'payment.checkRazorPayPaymentStatus'
        )) {
            return true;
        }

        return $request->routeIs(
            'account.suspension',
            'logout',
            'login',
            'password.request',
            'password.email',
            'password.reset',
            'password.update',
            'password.confirm',
            'password.confirmation',
            'verification.notice',
            'verification.verify',
            'verification.resend',
            'verfication.resend',
            'social.login.start',
            'social.login.callback',
            '2fa.index',
            '2fa.post',
            '2fa.resend',
            '2fa.delete',
            'age-check.show',
            'age-check.start',
            'age-check.callback',
            'contact',
            'contact.send',
            'pages.get',
            'language',
            'subscriptions.cancel',
            'invoices.get',
            'my.settings.account.save',
            'my.settings.account.email.store',
            'my.settings.account.email.resend',
            'my.settings.account.email.cancel',
            'my.settings.account.email.verify',
            'my.settings.account.delete-request',
            'my.settings.account.delete-request.cancel',
            'admin.leaveImpersonation'
        );
    }
}
