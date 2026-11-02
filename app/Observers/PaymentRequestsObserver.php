<?php

namespace App\Observers;

use App\Helpers\PaymentHelper;
use App\Model\PaymentRequest;
use App\Model\Transaction;
use App\Model\User;
use App\Model\Wallet;
use App\Providers\EmailsServiceProvider;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class PaymentRequestsObserver
{
    public function __construct(private PaymentHelper $paymentHelper) {}

    public function saving(PaymentRequest $paymentRequest): void
    {
        if (!$paymentRequest->exists) {
            return;
        }

        // The model locks and reloads this original state before invoking observers.
        if ($paymentRequest->getOriginal('status') !== PaymentRequest::PENDING_STATUS) {
            if ($paymentRequest->isDirty(['status', 'amount', 'user_id', 'transaction_id'])) {
                throw ValidationException::withMessages(['status' => __('This deposit request has already been processed.')]);
            }

            return;
        }

        $statusChanged = $paymentRequest->isDirty('status');
        $amountChanged = $paymentRequest->isDirty('amount');
        if (!$statusChanged && !$amountChanged) {
            return;
        }
        if ($statusChanged && !in_array($paymentRequest->status, [PaymentRequest::APPROVED_STATUS, PaymentRequest::REJECTED_STATUS], true)) {
            throw ValidationException::withMessages(['status' => __('Select an approved or rejected status.')]);
        }

        $transaction = Transaction::query()->whereKey($paymentRequest->transaction_id)->lockForUpdate()->firstOrFail();
        if (in_array($transaction->status, [Transaction::APPROVED_STATUS, Transaction::DECLINED_STATUS], true)) {
            throw ValidationException::withMessages(['status' => __('This payment transaction has already been processed.')]);
        }
        if ($amountChanged) {
            $transaction->setAttribute('amount', $paymentRequest->amount);
        }
        if (!$statusChanged) {
            $transaction->save();

            return;
        }

        $approved = $paymentRequest->status === PaymentRequest::APPROVED_STATUS;
        $transaction->status = $approved ? Transaction::APPROVED_STATUS : Transaction::DECLINED_STATUS;
        $transaction->save();
        if ($approved) {
            Wallet::query()->where('user_id', $transaction->recipient_user_id)->lockForUpdate()->firstOrFail();
            $this->paymentHelper->creditReceiverForTransaction($transaction);
        }

        $this->notifyAfterCommit($paymentRequest, $paymentRequest->status);
    }

    public function deleting(PaymentRequest $paymentRequest): void
    {
        if ($paymentRequest->status !== PaymentRequest::PENDING_STATUS) {
            return;
        }
        $transaction = Transaction::query()->whereKey($paymentRequest->transaction_id)->lockForUpdate()->first();
        if ($transaction && $transaction->status !== Transaction::APPROVED_STATUS) {
            $transaction->update(['status' => Transaction::DECLINED_STATUS]);
        }
        $this->notifyAfterCommit($paymentRequest, PaymentRequest::REJECTED_STATUS);
    }

    private function notifyAfterCommit(PaymentRequest $paymentRequest, string $status): void
    {
        $paymentRequest = clone $paymentRequest;
        $paymentRequest->getConnection()->afterCommit(function () use ($paymentRequest, $status) {
            $locale = App::getLocale() ?: 'en';
            try {
                $user = User::findOrFail($paymentRequest->user_id);
                App::setLocale(data_get($user->settings, 'locale', 'en'));
                $approved = $status === PaymentRequest::APPROVED_STATUS;
                EmailsServiceProvider::sendGenericEmail([
                    'email' => $user->email,
                    'subject' => $approved ? __('Your deposit request has been approved.') : __('Your deposit request has been denied.'),
                    'title' => __('Hello, :name,', ['name' => $user->name]),
                    'content' => __('Email payment request processed', [
                        'siteName' => getSetting('site.name'), 'status' => __($status),
                    ]).($approved ? ' $'.$paymentRequest->amount.' '.__('have been credited to your account.') : ''),
                    'button' => [
                        'text' => $approved ? __('My payments') : __('Try again'),
                        'url' => route('my.settings', ['type' => $approved ? 'payments' : 'wallet']),
                    ],
                ]);
            } catch (\Throwable $exception) {
                Log::warning('Failed to send payment request status email.', [
                    'payment_request_id' => $paymentRequest->id, 'error' => $exception->getMessage(),
                ]);
            } finally {
                App::setLocale($locale);
            }
        });
    }
}
