<?php

namespace App\Http\Middleware;

use Illuminate\Foundation\Http\Middleware\PreventRequestForgery as Middleware;

class VerifyCsrfToken extends Middleware
{
    /**
     * Indicates whether the XSRF-TOKEN cookie should be set on the response.
     *
     * @var bool
     */
    protected $addHttpCookie = true;

    /**
     * The URIs that should be excluded from CSRF verification.
     *
     * @var array<int, string>
     */
    protected $except = [
        'payment/paypalStatusUpdate',
        'payment/stripeStatusUpdate',
        'payment/yookassaStatusUpdate',
        'payment/mollieStatusUpdate',
        'payment/flutterwaveStatusUpdate',
        'payment/coingateStatusUpdate',
        'payment/xenditStatusUpdate',
        'payment/paddleStatusUpdate',
        'payment/cryptocomStatusUpdate',
        'payment/nowPaymentsStatusUpdate',
        'payment/ccBillPaymentStatusUpdate',
        'payment/paystackPaymentStatusUpdate',
        'payment/mercadoPaymentStatusUpdate',
        'payment/verotelPaymentStatusUpdate',
        'payment/razorPayPaymentStatusUpdate',
        'transcoding/coconut/update',
        'moderation/sightengine/callback/*',
        'payment/stripeConnectStatusUpdate',
    ];
}
