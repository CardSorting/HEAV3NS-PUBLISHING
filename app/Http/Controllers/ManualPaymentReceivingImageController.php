<?php

namespace App\Http\Controllers;

use App\Model\ManualPaymentMethod;
use App\Model\PaymentRequest;
use App\Services\ManualPayments\ManualPaymentReceivingImages;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;

class ManualPaymentReceivingImageController extends Controller
{
    public function show(Request $request)
    {
        $reference = $request->query('image');
        abort_unless(is_string($reference) && ($image = ManualPaymentReceivingImages::metadata($reference)), 404);
        $user = $request->user();
        $allowed = $user && $user->can('View:ManagePaymentsSettings');
        if (!$allowed && $user && $request->filled('payment_request')) {
            $id = $request->query('payment_request');
            $payment = (is_string($id) || is_int($id)) && preg_match('/^[1-9][0-9]*$/D', (string) $id) ? PaymentRequest::find($id) : null;
            $allowed = $payment && $payment->type === PaymentRequest::DEPOSIT_TYPE
                && ((int) $payment->user_id === (int) $user->id || Gate::forUser($user)->allows('view', $payment) || Gate::forUser($user)->allows('update', $payment))
                && ManualPaymentReceivingImages::contains($payment->details_snapshot['receiving_details'] ?? [], $reference);
        }
        if (!$allowed && $user && !$request->filled('payment_request') && getSetting('payments.allow_manual_payments')) {
            $id = $request->query('method');
            $method = is_string($id) || is_int($id) ? ManualPaymentMethod::findForDirection($id, ManualPaymentMethod::DEPOSIT) : null;
            $allowed = $method && ManualPaymentReceivingImages::contains($method->receiving_details ?? [], $reference);
        }
        abort_unless($allowed, 403);
        $disk = Storage::disk($image['disk']);
        abort_unless($disk->exists($image['path']), 404);

        return $disk->response($image['path'], 'receiving-image.png', [
            'Content-Type' => 'image/png', 'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store', 'Content-Security-Policy' => "sandbox; default-src 'none'",
        ], 'inline');
    }
}
