<?php

/**
 * Created by PhpStorm.
 * User: Lab #2
 * Date: 6/6/2021
 * Time: 4:10 PM.
 */

namespace App\Helpers;

use App\Http\Requests\CreateTransactionRequest;
use App\Http\Requests\QuoteTaxesRequest;
use App\Model\Country;
use App\Model\Post;
use App\Model\Stream;
use App\Model\Subscription;
use App\Model\Tax;
use App\Model\Transaction;
use App\Model\UserMessage;
use App\Providers\GenericHelperServiceProvider;
use App\Providers\CryptoComServiceProvider;
use App\Providers\CoinGateServiceProvider;
use App\Providers\FlutterwaveServiceProvider;
use App\Providers\InvoiceServiceProvider;
use App\Providers\MollieServiceProvider;
use App\Providers\NotificationServiceProvider;
use App\Providers\PaddleServiceProvider;
use App\Providers\PaymentsServiceProvider;
use App\Providers\PaypalAPIServiceProvider;
use App\Providers\RazorPayServiceProvider;
use App\Providers\ProfileMonetizationServiceProvider;
use App\Providers\SettingsServiceProvider;
use App\Providers\VerotelServiceProvider;
use App\Providers\XenditServiceProvider;
use App\Providers\YooKassaServiceProvider;
use App\Model\User;
use App\Services\ConversionTracking\PurchaseConversionService;
use App\Services\Payments\CheckoutBilling;
use App\Services\UserBanService;
use Illuminate\Validation\ValidationException;
use App\Services\Moderation\PublicationReviewService;
use Carbon\Carbon;
use DateTime;
use DateTimeZone;
use GuzzleHttp\Client;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redirect;
use MercadoPago\Preference;
use MercadoPago\SDK;
use Ramsey\Uuid\Uuid;
use Stripe\StripeClient;
use Yabacon\Paystack;
use Yabacon\Paystack\Exception\ApiException;

class PaymentHelper
{
    public function generatePaypalSubscriptionByTransaction(Transaction $transaction): ?string
    {
        //initiate the recurring payment, send back the link for the user to approve it.
        if ($transaction['payment_provider'] === Transaction::PAYPAL_PROVIDER) {
            $paypalPlan = PaypalAPIServiceProvider::createPlan($transaction);
            $paypalSubscription = PaypalAPIServiceProvider::createSubscriptionByPlanAndTransaction(
                $paypalPlan['id'],
                $transaction
            );

            $existingSubscription = $this->getSubscriptionBySenderAndReceiverAndProvider(
                $transaction['sender_user_id'],
                $transaction['recipient_user_id'],
                Transaction::PAYPAL_PROVIDER
            );

            if ($existingSubscription != null) {
                $subscription = $existingSubscription;
            } else {
                $subscription = $this->createSubscriptionFromTransaction($transaction);
            }

            $subscription['paypal_agreement_id'] = $paypalSubscription['id'];
            $subscription['paypal_plan_id'] = $paypalPlan['id'];
            $subscription->save();

            $approvalUrl = PaypalAPIServiceProvider::getApprovalUrlByResource($paypalSubscription, 'approve');
            $paypalTransactionToken = PaypalAPIServiceProvider::getPayPalTransactionTokenFromApprovalLink($approvalUrl);
            $transaction['paypal_transaction_token'] = $paypalTransactionToken;
            $transaction['subscription_id'] = $subscription['id'];

            return $approvalUrl;
        }

        return null;
    }

    private function createSubscriptionFromTransaction(Transaction $transaction): Subscription
    {
        $subscription = new Subscription();

        if ($transaction['recipient_user_id'] != null && $transaction['sender_user_id'] != null) {
            $subscription['recipient_user_id'] = $transaction['recipient_user_id'];
            $subscription['sender_user_id'] = $transaction['sender_user_id'];
            $subscription['provider'] = $transaction['payment_provider'];
            $subscription['type'] = $transaction['type'];
            $subscription['status'] = Transaction::PENDING_STATUS;
        }

        return $subscription;
    }

    public function initiateOneTimePaypalTransaction(Transaction $transaction): string
    {
        $paypalOrder = PaypalAPIServiceProvider::createOrderByTransaction($transaction);

        $transaction['paypal_transaction_token'] = $paypalOrder['id'];

        // fetch and return redirect url from the creation order response
        return PaypalAPIServiceProvider::getApprovalUrlByResource($paypalOrder, 'payer-action');
    }

    public function executePaypalSubscriptionPayment($transaction): ?Transaction {
        try {
            $subscription = Subscription::query()->where('id', $transaction->subscription_id)->first();
            if($subscription && $subscription->paypal_agreement_id) {
                $transaction = $this->verifyPaypalSubscriptionPayment($subscription->paypal_agreement_id, null, $transaction);
            }
        } catch (\Exception $exception) {
            Log::channel('payments')
                ->error('Failed executing PayPal subscription payment: '.$exception->getMessage());
        }

        return $transaction;
    }

    public function verifyPaypalSubscriptionPayment(
        string $subscriptionId,
        string $paypalPaymentId = null,
        $transaction = null
    ): ?Transaction {
        $subscription = Subscription::query()->where(['paypal_agreement_id' => $subscriptionId])->first();

        if($subscription) {
            $paypalSubscription = PaypalAPIServiceProvider::getSubscription($subscriptionId);
            Log::channel('payments')
                ->debug("PayPal Sub Data: ", [$paypalSubscription]);
            // only fetch the last subscription transaction if this call does come from hooks, and it's the first sub payment
            if(!$transaction && $this->isFirstPaymentForPaypalSubscription($paypalSubscription)) {
                // handles PayPal initial payment for recurring subscription
                // find last initiated transaction by subscription and update its status
                $existingTransaction = Transaction::query()->where([
                    'subscription_id' => $subscription->id,
                    'payment_provider' => Transaction::PAYPAL_PROVIDER,
                ])->orderBy('id', 'desc')->first();

                if ($existingTransaction instanceof Transaction) {
                    // if transaction was already approved by the callback call
                    // we'll only update the paypal_transaction_id for the transaction entry
                    if($existingTransaction->status === Transaction::APPROVED_STATUS) {
                        $existingTransaction->paypal_transaction_id = $paypalPaymentId;
                        $existingTransaction->save();

                        return $existingTransaction;
                    }

                    if($existingTransaction->status === Transaction::INITIATED_STATUS) {
                        $transaction = $existingTransaction;
                    }

                    Log::channel('payments')
                        ->debug("Found existing transaction for subscription: ".$transaction->id);
                }
            }

            // if we have a transaction at this point it means this call is triggered by the initial subscription payment,
            // so we want to validate if user paid the initial setup fee
            if ($transaction && !$this->validatePaypalSubscriptionInitialPayment($paypalSubscription, $transaction)) {
                return null;
            }

            // fetch subscription next billing date
            $subNextBillingDate = $paypalSubscription['billing_info']['next_billing_time'] ?? null;
            if (!$subNextBillingDate) {
                return null;
            }

            // handles PayPal subscription renewal payments
            $renewalTransaction = null;
            if ($subscription->status == Subscription::ACTIVE_STATUS
                || $subscription->status == Subscription::SUSPENDED_STATUS
                || $subscription->status == Subscription::EXPIRED_STATUS) {
                $renewalTransaction = $this->createSubscriptionRenewalTransaction($subscription, $paymentSucceeded = true, $paypalPaymentId);
            }

            $nextBillingDate = Carbon::parse($subNextBillingDate, 'UTC');

            $subscription->expires_at = $nextBillingDate;
            $subscription->status = Subscription::ACTIVE_STATUS;

            // handles initial recurring payment transaction update
            if ($transaction) {
                $transaction->status = Transaction::APPROVED_STATUS;
                $subscription->amount = $transaction->amount;

                if(isset($paypalSubscription['subscriber']) && isset($paypalSubscription['subscriber']['payer_id'])) {
                    $transaction->paypal_payer_id = $paypalSubscription['subscriber']['payer_id'];
                }

                // handle scenario where the callback call was missed so the transaction isn't approved,
                // and we need to set the paypal_transaction_id
                $startTime = new DateTime('-1 hour', new DateTimeZone('UTC'));
                $endTime = new DateTime('now', new DateTimeZone('UTC'));
                $paypalSubPayments = PaypalAPIServiceProvider::getTransactionsBySubscription(
                    $subscriptionId,
                    $startTime->format('Y-m-d\TH:i:s.v\Z'),
                    $endTime->format('Y-m-d\TH:i:s.v\Z')
                );

                if(isset($paypalSubPayments['transactions'])) {
                    $subPaypalTransaction = $paypalSubPayments['transactions'][0];
                    $transaction->paypal_transaction_id = $subPaypalTransaction['id'];
                }

                $transaction->save();

                // credit receiver for transaction
                $this->creditReceiverForTransaction($transaction);
            }

            $subscription->save();

            if ($transaction) {
                app(PurchaseConversionService::class)->complete($transaction);
            } elseif ($renewalTransaction) {
                app(PurchaseConversionService::class)->complete($renewalTransaction);
            }

            NotificationServiceProvider::createNewSubscriptionNotification($subscription);
        }

        return $transaction;
    }

    private function isFirstPaymentForPaypalSubscription(array $paypalSubscription): bool {
        if ($paypalSubscription
            && isset($paypalSubscription['billing_info'])
            && isset($paypalSubscription['billing_info']['cycle_executions']))
        {
            $cycleExecution = $paypalSubscription['billing_info']['cycle_executions'][0];
            if (isset($cycleExecution['sequence']) && isset($cycleExecution['cycles_completed'])) {
                return $cycleExecution['sequence'] === 1 && $cycleExecution['cycles_completed'] === 0;
            }
        }

        return false;
    }

    private function validatePaypalSubscriptionInitialPayment(array $subscriptionData, $transaction): bool {
        $paypalSubLastPaymentAmount = null;
        if(isset($subscriptionData['billing_info'])
            && isset($subscriptionData['billing_info']['last_payment'])
            && isset($subscriptionData['billing_info']['last_payment']['amount'])
        ) {
            $paypalSubLastPaymentAmount = $subscriptionData['billing_info']['last_payment']['amount']['value'] ?? null;
        }

        // if the amount is null stop here
        if(!$paypalSubLastPaymentAmount) {
            return false;
        }

        // if the amount doesn't match stop here
        if($paypalSubLastPaymentAmount != $transaction['amount']) {
            return false;
        }

        return true;
    }

    public function capturePaymentForOrder($transaction): Transaction {
        try {
            $paypalOrderCapture = PaypalAPIServiceProvider::capturePaymentForOrder($transaction);
            $paypalTransactionId = null;
            $paypalPayerId = null;

            if(isset($paypalOrderCapture['purchase_units'])
                && isset($paypalOrderCapture['purchase_units'][0])
                && isset($paypalOrderCapture['purchase_units'][0]['payments'])
                && isset($paypalOrderCapture['purchase_units'][0]['payments']['captures'])
                && isset($paypalOrderCapture['purchase_units'][0]['payments']['captures'][0])
            ) {
                $paypalTransactionId = $paypalOrderCapture['purchase_units'][0]['payments']['captures'][0]['id'] ?? null;
            }

            if(isset($paypalOrderCapture['payer'])) {
                $paypalPayerId = $paypalOrderCapture['payer']['payer_id'] ?? null;
            }

            // Stop processing here if we cannot find transaction / payer id in capture order response
            if(!$paypalTransactionId || !$paypalPayerId) {
                Log::channel('payments')->error(
                    "Missing PayPal transaction / payer id",
                    [
                        'internalTransactionId' => $transaction['id'],
                        'paypalTransactionId' => $paypalTransactionId,
                        'paypalPayerId' => $paypalPayerId,
                    ]
                );

                // return here
                return $transaction;
            }

            if ($paypalOrderCapture['status'] === 'COMPLETED') {
                $saleStatus = Transaction::APPROVED_STATUS;
            } elseif (in_array($paypalOrderCapture['status'], ['DECLINED', 'FAILED'])) {
                $saleStatus = Transaction::DECLINED_STATUS;
            } else {
                $saleStatus = Transaction::PENDING_STATUS;
            }

            $transaction->status = $saleStatus;
            $transaction->paypal_transaction_id = $paypalTransactionId;
            $transaction->paypal_payer_id = $paypalPayerId;

            $transaction->save();

            if ($transaction->status == Transaction::APPROVED_STATUS) {
                // credit receiver for transaction
                $this->creditReceiverForTransaction($transaction);
                app(PurchaseConversionService::class)->complete($transaction);
            }

            if ($transaction->status === Transaction::APPROVED_STATUS
                && ($transaction->type === Transaction::TIP_TYPE || $transaction->type === Transaction::CHAT_TIP_TYPE)) {
                NotificationServiceProvider::createNewTipNotification($transaction);
            }
        } catch (\Exception $ex) {
            Log::channel('payments')->error('Failed capturing one time paypal payment: '.$ex->getMessage());
        }

        return $transaction;
    }

    public function creditReceiverForTransaction($transaction): void
    {
        if ($transaction->type != null && $transaction->status == Transaction::APPROVED_STATUS) {
            $user = User::query()->where('id', $transaction->recipient_user_id)->first();

            if ($user != null) {
                $userWallet = $user->wallet;

                // Adding available balance
                $amountWithTaxesDeducted = PaymentsServiceProvider::getTransactionAmountWithTaxesDeducted($transaction);

                $userWallet->increment('total', $amountWithTaxesDeducted);
            }
        }
    }

    public function updateTransactionByStripeSessionId($sessionId)
    {
        $transaction = Transaction::query()->where(['stripe_session_id' => $sessionId])->first();
        if ($transaction != null) {
            try {
                $stripeClient = new StripeClient(getSetting('payments.stripe_secret_key'));
                $stripeSession = $stripeClient->checkout->sessions->retrieve($sessionId);
                $purchaseCompleted = false;
                if ($stripeSession != null) {
                    if (isset($stripeSession->payment_status)) {
                        $transaction->stripe_transaction_id = $stripeSession->payment_intent;
                        if ($stripeSession->payment_status == 'paid') {
                            if ($transaction->status != Transaction::APPROVED_STATUS) {
                                $transaction->status = Transaction::APPROVED_STATUS;
                                $subscription = Subscription::query()->where('id', $transaction->subscription_id)->first();
                                if ($subscription != null && $this->isSubscriptionPayment($transaction->type)) {
                                    if ($stripeSession->subscription != null) {
                                        $subscription->stripe_subscription_id = $stripeSession->subscription;
                                        $stripeSubscription = $stripeClient->subscriptions->retrieve($stripeSession->subscription);
                                        if($stripeSubscription != null){
                                            $latestInvoiceForSubscription = $stripeClient->invoices->retrieve($stripeSubscription->latest_invoice);
                                            if($latestInvoiceForSubscription != null){
                                                $transaction->stripe_transaction_id = $latestInvoiceForSubscription->payment_intent;
                                            }
                                        }
                                    }

                                    $expiresDate = Carbon::now('UTC')->addMonths((int) PaymentsServiceProvider::getSubscriptionMonthlyIntervalByTransactionType($transaction->type));
                                    if ($subscription->status != Subscription::ACTIVE_STATUS) {
                                        $subscription->status = Subscription::ACTIVE_STATUS;
                                        $subscription->expires_at = $expiresDate;

                                        NotificationServiceProvider::createNewSubscriptionNotification($subscription);
                                    } else {
                                        $subscription->expires_at = $expiresDate;
                                    }

                                    $subscription->update();

                                    $this->creditReceiverForTransaction($transaction);
                                    $purchaseCompleted = true;
                                } else {
                                    $this->creditReceiverForTransaction($transaction);
                                    $purchaseCompleted = true;
                                }
                            }
                        } else {
                            $transaction->status = Transaction::CANCELED_STATUS;

                            $subscription = Subscription::query()->where('id', $transaction->subscription_id)->first();

                            if ($subscription != null && $subscription->status == Subscription::ACTIVE_STATUS && $subscription->expires_at <= new DateTime()) {
                                $subscription->status = Subscription::CANCELED_STATUS;

                                $subscription->update();
                            }
                        }
                    }

                    $transaction->update();
                    if ($purchaseCompleted) {
                        app(PurchaseConversionService::class)->complete($transaction);
                    }
                }
            } catch (\Exception $exception) {
                Log::channel('payments')->error($exception->getMessage());
            }
        }

        return $transaction;
    }

    public function generateStripeSubscriptionByTransaction($transaction)
    {
        $existingSubscription = $this->getSubscriptionBySenderAndReceiverAndProvider(
            $transaction['sender_user_id'],
            $transaction['recipient_user_id'],
            Transaction::STRIPE_PROVIDER
        );

        if ($existingSubscription != null) {
            $subscription = $existingSubscription;
        } else {
            $subscription = $this->createSubscriptionFromTransaction($transaction);
            $subscription['amount'] = $transaction['amount'];

            $subscription->save();
        }
        $transaction['subscription_id'] = $subscription['id'];

        return $subscription;
    }

    public function createSubscriptionRenewalTransaction($subscription, $paymentSucceeded, $paymentId = null)
    {
        if ($paymentSucceeded && $subscription->provider === Transaction::CREDIT_PROVIDER
            && app(UserBanService::class)->requiresRenewalStop($subscription)) {
            throw ValidationException::withMessages(['subscription' => __('Renewal is disabled for this subscription.')]);
        }

        $transaction = new Transaction();
        $transaction['sender_user_id'] = $subscription->sender_user_id;
        $transaction['recipient_user_id'] = $subscription->recipient_user_id;
        $transaction['type'] = Transaction::SUBSCRIPTION_RENEWAL;
        $transaction['status'] = $paymentSucceeded ? Transaction::APPROVED_STATUS : Transaction::DECLINED_STATUS;
        $transaction['amount'] = $subscription->amount;
        $transaction['currency'] = config('app.site.currency_code');
        $transaction['payment_provider'] = $subscription->provider;
        $transaction['subscription_id'] = $subscription->id;

        // find latest transaction for subscription to get taxes
        $lastTransactionForSubscription = Transaction::query()
            ->where('subscription_id', $subscription->id)
            ->orderBy('created_at', 'desc')
            ->first();

        if ($lastTransactionForSubscription != null) {
            $transaction['taxes'] = $lastTransactionForSubscription->taxes;
        }

        if ($paymentId != null) {
            if ($transaction['payment_provider'] === Transaction::PAYPAL_PROVIDER) {
                $transaction['paypal_transaction_id'] = $paymentId;
            } elseif ($transaction['payment_provider'] === Transaction::STRIPE_PROVIDER) {
                $transaction['stripe_transaction_id'] = $paymentId;
            } elseif ($transaction['payment_provider'] === Transaction::CCBILL_PROVIDER) {
                $transaction['ccbill_transaction_id'] = $paymentId;
                $transaction['ccbill_subscription_id'] = $subscription->ccbill_subscription_id;
            } elseif ($transaction['payment_provider'] === Transaction::VEROTEL_PROVIDER) {
                $transaction['verotel_sale_id'] = $paymentId;
            }
        }

        app(PurchaseConversionService::class)->inheritAttribution($transaction, $subscription);
        $transaction->save();

        $this->creditReceiverForTransaction($transaction);

        if ($transaction['status'] === Transaction::APPROVED_STATUS && $transaction['payment_provider'] === Transaction::CREDIT_PROVIDER) {
            $this->deductMoneyFromUserWalletForCreditTransaction($transaction, $subscription->subscriber->wallet);
        }

        try {
            $invoice = InvoiceServiceProvider::createInvoiceByTransaction($transaction);
            if ($invoice != null) {
                $transaction->invoice_id = $invoice->id;
                $transaction->save();
            }
        } catch (\Exception $exception) {
            Log::channel('payments')->error("Failed generating invoice for transaction: ".$transaction->id." error: ".$exception->getMessage());
        }

        return $transaction;
    }

    public function cancelPaypalSubscription(string $subscriptionId): bool {
        return PaypalAPIServiceProvider::cancelSubscription($subscriptionId);
    }

    public function cancelStripeSubscription($stripeSubscriptionId): bool
    {
        $http = \Stripe\HttpClient\CurlClient::instance();
        $timeout = $http->getTimeout();
        $connectTimeout = $http->getConnectTimeout();
        $http->setTimeout(15);
        $http->setConnectTimeout(5);
        try {
            $stripe = new StripeClient(getSetting('payments.stripe_secret_key'));
            $subscription = $stripe->subscriptions->retrieve($stripeSubscriptionId);
            if ($subscription->status === 'canceled') {
                return true;
            }

            return $stripe->subscriptions->cancel($stripeSubscriptionId)->status === 'canceled';
        } finally {
            $http->setTimeout($timeout);
            $http->setConnectTimeout($connectTimeout);
        }
    }

    public function deductMoneyFromUserForRefundedTransaction($transaction)
    {
        if ($transaction->type != null && $transaction->status == Transaction::REFUNDED_STATUS) {
            switch ($transaction->type) {
                case Transaction::DEPOSIT_TYPE:
                case Transaction::TIP_TYPE:
                case Transaction::CHAT_TIP_TYPE:
                case Transaction::ONE_MONTH_SUBSCRIPTION:
                case Transaction::THREE_MONTHS_SUBSCRIPTION:
                case Transaction::SIX_MONTHS_SUBSCRIPTION:
                case Transaction::YEARLY_SUBSCRIPTION:
                case Transaction::SUBSCRIPTION_RENEWAL:
                    $user = User::query()->where('id', $transaction->recipient_user_id)->first();
                    $amountWithTaxesDeducted = PaymentsServiceProvider::getTransactionAmountWithTaxesDeducted($transaction);
                    if ($user != null) {
                        $user->wallet->update(['total' => $user->wallet->total - $amountWithTaxesDeducted]);
                    }
                    break;
            }
        }
    }

    public function getLoggedUserAvailableAmount()
    {
        $amount = 0.00;
        if (Auth::user() != null && Auth::user()->wallet != null) {
            $amount = Auth::user()->wallet->total;
        }

        return $amount;
    }

    public function generateOneTimeCreditTransaction($transaction)
    {
        $this->ensurePaymentIsAllowed($transaction);
        $userAvailableAmount = $this->getLoggedUserAvailableAmount();
        if ($transaction['amount'] <= $userAvailableAmount) {
            $transaction['status'] = Transaction::APPROVED_STATUS;
        }
    }

    public function deductMoneyFromUserWalletForCreditTransaction($transaction, $userWallet)
    {
        if ($userWallet != null) {
            $userWallet->update([
                'total' => $userWallet->total - floatval($transaction['amount']),
            ]);
        }
    }

    private function getSubscriptionBySenderAndReceiverAndProvider($senderId, $receiverId, $provider)
    {
        $queryCriteria = [
            'recipient_user_id' => $receiverId,
            'sender_user_id' => $senderId,
            'provider' => $provider,
        ];

        $subscription = Subscription::query()->where($queryCriteria)->latest('id')->first();

        // A new checkout after unban creates a new subscription; old stop intent is permanent.
        return $subscription && app(UserBanService::class)->requiresRenewalStop($subscription) ? null : $subscription;
    }

    public function generateCreditSubscriptionByTransaction($transaction)
    {
        $this->ensurePaymentIsAllowed($transaction);
        $existingSubscription = $this->getSubscriptionBySenderAndReceiverAndProvider(
            $transaction['sender_user_id'],
            $transaction['recipient_user_id'],
            Transaction::CREDIT_PROVIDER
        );

        if ($existingSubscription != null) {
            $subscription = $existingSubscription;
        } else {
            $subscription = $this->createSubscriptionFromTransaction($transaction);
        }
        $subscription['amount'] = $transaction['amount'];
        $subscription['expires_at'] = Carbon::now('UTC')->addMonths((int) PaymentsServiceProvider::getSubscriptionMonthlyIntervalByTransactionType($transaction->type));
        $subscription['status'] = Subscription::ACTIVE_STATUS;
        $transaction['status'] = Transaction::APPROVED_STATUS;

        $subscription->save();

        // only send the notification for new subs
        if($existingSubscription === null){
            NotificationServiceProvider::createNewSubscriptionNotification($subscription);
        }
        $transaction['subscription_id'] = $subscription['id'];

        return $subscription;
    }

    public function createNewTipNotificationForCreditTransaction($transaction)
    {
        if ($transaction != null
            && $transaction->payment_provider === Transaction::CREDIT_PROVIDER
            && $transaction->status === Transaction::APPROVED_STATUS
            && ($transaction->type === Transaction::TIP_TYPE || $transaction->type === Transaction::CHAT_TIP_TYPE)) {
            NotificationServiceProvider::createNewTipNotification($transaction);
        }
    }

    public function generateStripeSessionByTransaction(Transaction $transaction)
    {
        $this->ensurePaymentIsAllowed($transaction);
        $redirectLink = null;
        $transactionType = $transaction->type;
        if ($transactionType == null || empty($transactionType)) {
            return null;
        }

        try {
            \Stripe\Stripe::setApiKey(getSetting('payments.stripe_secret_key'));
            $isSubscriptionPayment = $this->isSubscriptionPayment($transactionType);
            if ($isSubscriptionPayment) {
                // generate stripe product
                $product = \Stripe\Product::create([
                    'name' => $this->getPaymentDescriptionByTransaction($transaction),
                ]);

                // generate stripe price
                $price = \Stripe\Price::create([
                    'product' => $product->id,
                    'unit_amount' => $transaction->amount * 100,
                    'currency' => config('app.site.currency_code'),
                    'recurring' => [
                        'interval' => 'month',
                        'interval_count' => PaymentsServiceProvider::getSubscriptionMonthlyIntervalByTransactionType($transactionType),
                    ],
                ]);

                $stripeLineItems = [
                    'price' => $price->id,
                    'quantity' => 1,
                ];
            } else {
                $stripeLineItems = [
                    'price_data' => [
                        // To accept `oxxo`, all line items must have currency: mxn
                        'currency' => config('app.site.currency_code'),
                        'product_data' => [
                            'name' => $this->getPaymentDescriptionByTransaction($transaction),
                            'description' => $this->getPaymentDescriptionByTransaction($transaction),
                        ],
                        'unit_amount' => $transaction->amount * 100,
                    ],
                    'quantity' => 1,
                ];
            }

            $data = [
                'payment_method_types' => ['card'],
                'line_items' => [$stripeLineItems],
                'locale' => 'auto',
                'customer_email' => Auth::user()->email,
                'metadata' => [
                    'transactionType' => $transaction->type,
                    'user_id' => Auth::user()->id,
                ],
                'mode' => $transactionType == $this->isSubscriptionPayment($transaction->type) ? 'subscription' : 'payment',
                'success_url' => route('payment.checkStripePaymentStatus').'?session_id={CHECKOUT_SESSION_ID}',
                'cancel_url' => route('payment.checkStripePaymentStatus').'?session_id={CHECKOUT_SESSION_ID}',
            ];

            if($transaction->payment_provider === Transaction::OXXO_PROVIDER) {
                $data['payment_method_types'] = ['oxxo'];
            }

            // Enable some one time payment providers through Stripe checkout
            if(!$isSubscriptionPayment) {
                $currencyCode = strtolower(getSetting('payments.currency_code'));
                // only enable some payment providers if currency is eur
                if($currencyCode === 'eur') {
                    // iDEAL
                    if(getSetting('payments.stripe_ideal_provider_enabled')) {
                        $data['payment_method_types'][] = 'ideal';
                    }

                    // Bancontact
                    if(getSetting('payments.stripe_bancontact_provider_enabled')) {
                        $data['payment_method_types'][] = 'bancontact';
                    }

                    // EPS
                    if(getSetting('payments.stripe_eps_provider_enabled')) {
                        $data['payment_method_types'][] = 'eps';
                    }

                    // Giropay
                    if(getSetting('payments.stripe_giropay_provider_enabled')) {
                        $data['payment_method_types'][] = 'giropay';
                    }
                }

                // only enable Blik if currency is pln
                if(getSetting('payments.stripe_blik_provider_enabled') && $currencyCode === 'pln') {
                    $data['payment_method_types'][] = 'blik';
                }

                // only enable Przelewy24 if currency is eur / pln
                if(getSetting('payments.stripe_przelewy_provider_enabled') && in_array($currencyCode, ['eur', 'pln'])) {
                    $data['payment_method_types'][] = 'p24';
                }
            }

            $session = \Stripe\Checkout\Session::create($data);

            $transaction['stripe_session_id'] = $session->id;
            $redirectLink = $session->url;
        } catch (\Exception $e) {
            Log::channel('payments')->error('Failed generating stripe session for transaction: '.$transaction->id.' error: '.$e->getMessage());
        }

        return $redirectLink;
    }

    /**
     * Verify if payment is made for a subscription.
     *
     * @param $transactionType
     * @return bool
     */
    public function isSubscriptionPayment($transactionType)
    {
        return $transactionType != null
            && ($transactionType === Transaction::SIX_MONTHS_SUBSCRIPTION
                || $transactionType === Transaction::THREE_MONTHS_SUBSCRIPTION
                || $transactionType === Transaction::ONE_MONTH_SUBSCRIPTION
                || $transactionType === Transaction::YEARLY_SUBSCRIPTION);
    }

    /**
     * Get payment description by transaction type.
     *
     * @param $transaction
     * @return string
     */
    public function getPaymentDescriptionByTransaction($transaction)
    {
        $description = 'Default payment description';
        if ($transaction != null) {
            $recipientUsername = null;
            if ($transaction->recipient_user_id != null) {
                $recipientUser = User::query()->where(['id' => $transaction->recipient_user_id])->first();
                if ($recipientUser != null) {
                    $recipientUsername = $recipientUser->name;
                }
            }

            if ($this->isSubscriptionPayment($transaction->type)) {
                if ($recipientUsername == null) {
                    $recipientUsername = 'creator';
                }

                $description = $recipientUsername.' for '.SettingsServiceProvider::getWebsiteFormattedAmount($transaction->amount);
            } else {
                if ($transaction->type === Transaction::DEPOSIT_TYPE) {
                    $description = SettingsServiceProvider::getWebsiteFormattedAmount($transaction->amount).' '.__('wallet top-up');
                } elseif ($transaction->type === Transaction::TIP_TYPE || $transaction->type === Transaction::CHAT_TIP_TYPE) {
                    $tipPaymentDescription = SettingsServiceProvider::getWebsiteFormattedAmount($transaction->amount).' tip';
                    if ($transaction->recipient_user_id != null) {
                        $recipientUser = User::query()->where(['id' => $transaction->recipient_user_id])->first();
                        if ($recipientUser != null) {
                            $tipPaymentDescription = $tipPaymentDescription.' for '.$recipientUser->name;
                        }
                    }

                    $description = $tipPaymentDescription;
                } elseif ($transaction->type === Transaction::POST_UNLOCK) {
                    $description = __('Unlock post for').' '.SettingsServiceProvider::getWebsiteFormattedAmount($transaction->amount);
                } elseif ($transaction->type === Transaction::STREAM_ACCESS) {
                    $description = __('Join streaming for').' '.SettingsServiceProvider::getWebsiteFormattedAmount($transaction->amount);
                } elseif ($transaction->type === Transaction::MESSAGE_UNLOCK) {
                    $description = __('Unlock message for').' '.SettingsServiceProvider::getWebsiteFormattedAmount($transaction->amount);
                }
            }
        }

        return $description;
    }

    /**
     * Redirect user to proper page after payment process.
     *
     * @param $transaction
     * @param string|null $message
     * @return RedirectResponse
     */
    public function redirectByTransaction($transaction, $message = null)
    {

        // Not sure why translation locale is not being applied here, re-appliying it
        App::setLocale(GenericHelperServiceProvider::getPreferredLanguage());

        $errorMessage = __('Payment failed.');
        if ($message != null) {
            $errorMessage = $message;
        }
        if ($transaction != null) {
            // handles approved status
            $recipient = User::query()->where(['id' => $transaction->recipient_user_id])->first();
            if ($transaction->status === Transaction::APPROVED_STATUS) {
                $successMessage = __('Payment succeeded');
                if ($this->isSubscriptionPayment($transaction->type)) {
                    $successMessage = __('You can now access this user profile.');
                } elseif ($transaction->type === Transaction::DEPOSIT_TYPE) {
                    $key = SettingsServiceProvider::leftAlignedCurrencyPosition()
                        ? 'You have been credited :currencySymbol:amount Happy spending!'
                        : 'You have been credited :amount:currencySymbol Happy spending!';
                    $successMessage = __($key, ['amount' => $transaction->amount, 'currencySymbol' => SettingsServiceProvider::getWebsiteCurrencySymbol()]);
                } elseif ($transaction->type === Transaction::TIP_TYPE || $transaction->type === Transaction::CHAT_TIP_TYPE) {
                    $key = SettingsServiceProvider::leftAlignedCurrencyPosition()
                        ? 'You successfully sent a tip of :currencySymbol:amount.'
                        : 'You successfully sent a tip of :amount:currencySymbol.';
                    $successMessage = __($key, ['amount' => $transaction->amount, 'currencySymbol' => SettingsServiceProvider::getWebsiteCurrencySymbol()]);
                } elseif ($transaction->type === Transaction::POST_UNLOCK) {
                    $successMessage = __('You successfully unlocked this post.');
                } elseif ($transaction->type === Transaction::STREAM_ACCESS) {
                    $successMessage = __('You successfully paid for this streaming.');
                } elseif ($transaction->type === Transaction::MESSAGE_UNLOCK) {
                    $successMessage = __('You successfully unlocked this message.');
                }

                return $this->handleRedirectByTransaction($transaction, $recipient, $successMessage, $success = true);
                // handles any other status
            } else {
                return $this->handleRedirectByTransaction($transaction, $recipient, $errorMessage, $success = false);
            }
        } else {
            return Redirect::route('feed')
                ->with('error', $errorMessage);
        }
    }

    /**
     * Handles redirect by transaction type.
     *
     * @param $transaction
     * @param $recipient
     * @param $message
     * @param bool $success
     * @return RedirectResponse
     */
    private function handleRedirectByTransaction($transaction, $recipient, $message, $success = false)
    {
        $labelType = $success ? 'success' : 'error';
        if ($this->isSubscriptionPayment($transaction->type)) {
            if(in_array($transaction->payment_provider, [Transaction::CCBILL_PROVIDER, Transaction::VEROTEL_PROVIDER])
                && $transaction->status === Transaction::INITIATED_STATUS) {
                $labelType = 'warning';
                $message = __('Your payment have been successfully initiated but needs to await for approval');
            }

            if($transaction->stream_id){
                return Redirect::route('public.stream.get', ['streamID' => $transaction->stream_id, 'slug' => $transaction->stream->slug])
                    ->with($labelType, $message);
            }
            return Redirect::route('profile', ['username' => $recipient->username])
                ->with($labelType, $message);
        } elseif ($transaction->type === Transaction::DEPOSIT_TYPE) {
            if(in_array($transaction->payment_provider, Transaction::PENDING_PAYMENT_PROCESSORS)){
                if($transaction->status === Transaction::INITIATED_STATUS || $transaction->status === Transaction::PENDING_STATUS){
                    $labelType = 'warning';
                    $message = __('Your payment have been successfully initiated but needs to await for approval');
                } elseif($transaction->status === Transaction::CANCELED_STATUS){
                    $message = __('Payment canceled');
                }
            } elseif($transaction->payment_provider === Transaction::MANUAL_PROVIDER) {
                $labelType = 'warning';
                $message = __('Your payment have been successfully initiated but needs to await for processing');
            }

            return Redirect::route('my.settings', ['type' => 'wallet'])
                ->with($labelType, $message);
        } elseif ($transaction->type === Transaction::TIP_TYPE || $transaction->type === Transaction::CHAT_TIP_TYPE) {
            if(in_array($transaction->payment_provider, Transaction::PENDING_PAYMENT_PROCESSORS)){
                if($transaction->status === Transaction::INITIATED_STATUS || $transaction->status === Transaction::PENDING_STATUS){
                    $labelType = 'warning';
                    $message = __('Your payment have been successfully initiated but needs to await for approval');
                } elseif($transaction->status === Transaction::CANCELED_STATUS){
                    $message = __('Payment canceled');
                }
            }

            if ($transaction->post_id != null) {
                return Redirect::route('posts.get', ['post_id' => $transaction->post_id, 'username' => $recipient->username])
                    ->with($labelType, $message);
            }
            if($transaction->stream_id){
                return Redirect::route('public.stream.get', ['streamID' => $transaction->stream_id, 'slug' => $transaction->stream->slug])
                    ->with($labelType, $message);
            }
            if($transaction->type === Transaction::CHAT_TIP_TYPE) {
                return Redirect::route('my.messenger.get', ['tip'=>1])->with($labelType, $message);
            }
            return Redirect::route('profile', ['username' => $recipient->username])
                ->with($labelType, $message);
        } elseif ($transaction->type === Transaction::POST_UNLOCK) {
            if(in_array($transaction->payment_provider, Transaction::PENDING_PAYMENT_PROCESSORS)) {
                if($transaction->status === Transaction::INITIATED_STATUS || $transaction->status === Transaction::PENDING_STATUS){
                    $labelType = 'warning';
                    $message = __('Your payment have been successfully initiated but needs to await for approval');
                } elseif($transaction->status === Transaction::CANCELED_STATUS){
                    $message = __('Payment canceled');
                }
            }
            return Redirect::route('posts.get', ['post_id' => $transaction->post_id, 'username' => $recipient->username])
                ->with($labelType, $message);
        } elseif ($transaction->type === Transaction::STREAM_ACCESS) {
            if(in_array($transaction->payment_provider, Transaction::PENDING_PAYMENT_PROCESSORS)) {
                if($transaction->status === Transaction::INITIATED_STATUS || $transaction->status === Transaction::PENDING_STATUS){
                    $labelType = 'warning';
                    $message = __('Your payment have been successfully initiated but needs to await for approval');
                } elseif($transaction->status === Transaction::CANCELED_STATUS){
                    $message = __('Payment canceled');
                }
            }
            return Redirect::route('public.stream.get', ['streamID' => $transaction->stream_id, 'slug' => $transaction->stream->slug])
                ->with($labelType, $message);
        } elseif ($transaction->type === Transaction::MESSAGE_UNLOCK) {
            if(in_array($transaction->payment_provider, Transaction::PENDING_PAYMENT_PROCESSORS)) {
                if($transaction->status === Transaction::INITIATED_STATUS || $transaction->status === Transaction::PENDING_STATUS){
                    $labelType = 'warning';
                    $message = __('Your payment have been successfully initiated but needs to await for approval');
                } elseif($transaction->status === Transaction::CANCELED_STATUS){
                    $message = __('Payment canceled');
                }
            }
            return Redirect::route('my.messenger.get', ['messageUnlock' => 1, 'token' => $transaction->user_message_id])->with($labelType, $message);
        }

        return Redirect::route('feed')->with($labelType, $message);
    }

    /**
     * Generate now payments transaction.
     * @param $transaction
     * @return string|null
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function generateNowPaymentsTransaction($transaction)
    {
        $redirectUrl = null;
        $httpClient = new Client();
        $orderId = self::generateNowPaymentsOrderId($transaction);
        $coinBaseCheckoutRequest = $httpClient->request(
            'POST',
            Transaction::NOWPAYMENTS_API_BASE_PATH.'invoice',
            [
                'headers' => [
                    'Content-Type' => 'application/json',
                    'x-api-key' => getSetting('payments.nowpayments_api_key'),
                ],
                'body' => json_encode(array_merge_recursive([
                    'price_amount' => $transaction->amount,
                    'price_currency' => $transaction->currency,
                    'ipn_callback_url' => route('nowPayments.payment.update'),
                    'order_id' => $orderId,
                    'success_url' => route('payment.checkNowPaymentStatus').'?orderId='.$orderId,
                    'cancel_url' => route('payment.checkNowPaymentStatus').'?orderId='.$orderId,
                ])),
            ]
        );

        $response = json_decode($coinBaseCheckoutRequest->getBody(), true);
        if (isset($response['payment_id'])) {
            $transaction->nowpayments_payment_id = $response['payment_id'];
        }
        if(isset($response['order_id'])) {
            $transaction->nowpayments_order_id = $response['order_id'];
        }
        if(isset($response['invoice_url'])) {
            $redirectUrl = $response['invoice_url'];
        }

        return $redirectUrl;
    }

    /**
     * @param Transaction $transaction
     * @return string|null
     * @throws \Exception
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function generateYooKassaTransaction(Transaction $transaction): ?string
    {
        $paymentToken = $this->generateYooKassaPaymentToken($transaction);
        $paymentData = YooKassaServiceProvider::createPaymentByTransaction($transaction, $paymentToken);

        if (isset($paymentData['id'])) {
            $transaction->yookassa_payment_id = $paymentData['id'];
        }

        return data_get($paymentData, 'confirmation.confirmation_url');
    }

    /**
     * @param Transaction $transaction
     * @return string
     * @throws \Exception
     */
    private function generateYooKassaPaymentToken(Transaction $transaction): string
    {
        do {
            $id = Uuid::uuid4()->getHex();
        } while (Transaction::query()->where('yookassa_payment_token', $id)->first() != null);

        $transaction->yookassa_payment_token = $id;

        return $id;
    }

    /**
     * @param string|null $paymentToken
     * @return Transaction|null
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function verifyYooKassaTransactionByToken(?string $paymentToken): ?Transaction
    {
        if (!$paymentToken) {
            return null;
        }

        $transaction = Transaction::query()->where('yookassa_payment_token', $paymentToken)->first();
        if (!$transaction || !$transaction->yookassa_payment_id) {
            return $transaction;
        }

        $paymentData = YooKassaServiceProvider::getPaymentData($transaction->yookassa_payment_id);

        return $this->syncYooKassaTransaction($transaction, $paymentData);
    }

    /**
     * @param string|null $paymentId
     * @return Transaction|null
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function verifyYooKassaTransactionByPaymentId(?string $paymentId): ?Transaction
    {
        if (!$paymentId) {
            return null;
        }

        $paymentData = YooKassaServiceProvider::getPaymentData($paymentId);
        $transaction = Transaction::query()->where('yookassa_payment_id', $paymentId)->first();

        if (!$transaction) {
            $paymentToken = data_get($paymentData, 'metadata.payment_token');
            if ($paymentToken) {
                $transaction = Transaction::query()->where('yookassa_payment_token', $paymentToken)->first();
            }
        }

        if (!$transaction) {
            return null;
        }

        return $this->syncYooKassaTransaction($transaction, $paymentData);
    }

    /**
     * @param Transaction $transaction
     * @param array<string, mixed> $paymentData
     * @return Transaction
     */
    private function syncYooKassaTransaction(Transaction $transaction, array $paymentData): Transaction
    {
        if (isset($paymentData['id']) && empty($transaction->yookassa_payment_id)) {
            $transaction->yookassa_payment_id = $paymentData['id'];
        }

        $status = data_get($paymentData, 'status');

        if (in_array($status, ['pending', 'waiting_for_capture'], true)
            && $transaction->status === Transaction::INITIATED_STATUS) {
            $transaction->status = Transaction::PENDING_STATUS;
            $transaction->save();
        } elseif ($status === 'canceled' && $transaction->status !== Transaction::APPROVED_STATUS) {
            $transaction->status = Transaction::CANCELED_STATUS;
            $transaction->save();
        } elseif ($status === 'succeeded' && $transaction->status !== Transaction::APPROVED_STATUS) {
            $transaction->status = Transaction::APPROVED_STATUS;
            $transaction->save();

            $this->creditReceiverForTransaction($transaction);
            app(PurchaseConversionService::class)->complete($transaction);
            NotificationServiceProvider::createTipNotificationByTransaction($transaction);
            NotificationServiceProvider::createPPVNotificationByTransaction($transaction);
        }

        return $transaction;
    }

    /**
     * @param Transaction $transaction
     * @return string|null
     * @throws \Exception
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function generateMollieTransaction(Transaction $transaction): ?string
    {
        $paymentToken = $this->generateMolliePaymentToken($transaction);
        $paymentData = MollieServiceProvider::createPaymentByTransaction($transaction, $paymentToken);

        if (isset($paymentData['id'])) {
            $transaction->mollie_payment_id = $paymentData['id'];
        }

        return data_get($paymentData, '_links.checkout.href');
    }

    /**
     * @param Transaction $transaction
     * @return string
     * @throws \Exception
     */
    private function generateMolliePaymentToken(Transaction $transaction): string
    {
        do {
            $id = Uuid::uuid4()->getHex();
        } while (Transaction::query()->where('mollie_payment_token', $id)->first() != null);

        $transaction->mollie_payment_token = $id;

        return $id;
    }

    /**
     * @param string|null $paymentToken
     * @return Transaction|null
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function verifyMollieTransactionByToken(?string $paymentToken): ?Transaction
    {
        if (!$paymentToken) {
            return null;
        }

        $transaction = Transaction::query()->where('mollie_payment_token', $paymentToken)->first();
        if (!$transaction || !$transaction->mollie_payment_id) {
            return $transaction;
        }

        $paymentData = MollieServiceProvider::getPaymentData($transaction->mollie_payment_id);

        return $this->syncMollieTransaction($transaction, $paymentData);
    }

    /**
     * @param string|null $paymentId
     * @return Transaction|null
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function verifyMollieTransactionByPaymentId(?string $paymentId): ?Transaction
    {
        if (!$paymentId) {
            return null;
        }

        $paymentData = MollieServiceProvider::getPaymentData($paymentId);
        $transaction = Transaction::query()->where('mollie_payment_id', $paymentId)->first();

        if (!$transaction) {
            $paymentToken = data_get($paymentData, 'metadata.payment_token');
            if ($paymentToken) {
                $transaction = Transaction::query()->where('mollie_payment_token', $paymentToken)->first();
            }
        }

        if (!$transaction) {
            return null;
        }

        return $this->syncMollieTransaction($transaction, $paymentData);
    }

    /**
     * @param Transaction $transaction
     * @param array<string, mixed> $paymentData
     * @return Transaction
     */
    private function syncMollieTransaction(Transaction $transaction, array $paymentData): Transaction
    {
        if (isset($paymentData['id']) && empty($transaction->mollie_payment_id)) {
            $transaction->mollie_payment_id = $paymentData['id'];
        }

        if (isset($paymentData['metadata']['payment_token']) && empty($transaction->mollie_payment_token)) {
            $transaction->mollie_payment_token = $paymentData['metadata']['payment_token'];
        }

        $status = strtolower((string) data_get($paymentData, 'status', ''));

        if (in_array($status, ['open', 'pending', 'authorized'], true)
            && $transaction->status === Transaction::INITIATED_STATUS) {
            $transaction->status = Transaction::PENDING_STATUS;
            $transaction->save();
        } elseif (in_array($status, ['canceled', 'expired', 'failed'], true)
            && $transaction->status !== Transaction::APPROVED_STATUS) {
            $transaction->status = Transaction::CANCELED_STATUS;
            $transaction->save();
        } elseif ($status === 'paid' && $transaction->status !== Transaction::APPROVED_STATUS) {
            $transaction->status = Transaction::APPROVED_STATUS;
            $transaction->save();

            $this->creditReceiverForTransaction($transaction);
            app(PurchaseConversionService::class)->complete($transaction);
            NotificationServiceProvider::createTipNotificationByTransaction($transaction);
            NotificationServiceProvider::createPPVNotificationByTransaction($transaction);
        }

        return $transaction;
    }

    /**
     * @param Transaction $transaction
     * @return string|null
     * @throws \Exception
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function generateXenditTransaction(Transaction $transaction): ?string
    {
        $paymentToken = $this->generateXenditPaymentToken($transaction);
        $paymentData = XenditServiceProvider::createPaymentSessionByTransaction($transaction, $paymentToken);

        if (isset($paymentData['payment_session_id'])) {
            $transaction->xendit_payment_id = $paymentData['payment_session_id'];
        }

        return $paymentData['payment_link_url'] ?? null;
    }

    /**
     * @param Transaction $transaction
     * @return string
     * @throws \Exception
     */
    private function generateXenditPaymentToken(Transaction $transaction): string
    {
        do {
            $id = Uuid::uuid4()->getHex();
        } while (Transaction::query()->where('xendit_payment_token', $id)->first() != null);

        $transaction->xendit_payment_token = $id;

        return $id;
    }

    /**
     * @param string|null $paymentToken
     * @return Transaction|null
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function verifyXenditTransactionByToken(?string $paymentToken): ?Transaction
    {
        if (!$paymentToken) {
            return null;
        }

        $transaction = Transaction::query()->where('xendit_payment_token', $paymentToken)->first();
        if (!$transaction || !$transaction->xendit_payment_id) {
            return $transaction;
        }

        $paymentData = XenditServiceProvider::getSessionData($transaction->xendit_payment_id);

        return $this->syncXenditTransaction($transaction, $paymentData);
    }

    /**
     * @param string|null $sessionId
     * @return Transaction|null
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function verifyXenditTransactionBySessionId(?string $sessionId): ?Transaction
    {
        if (!$sessionId) {
            return null;
        }

        $paymentData = XenditServiceProvider::getSessionData($sessionId);
        $transaction = Transaction::query()->where('xendit_payment_id', $sessionId)->first();

        if (!$transaction) {
            $paymentToken = data_get($paymentData, 'reference_id') ?? data_get($paymentData, 'metadata.payment_token');
            if ($paymentToken) {
                $transaction = Transaction::query()->where('xendit_payment_token', $paymentToken)->first();
            }
        }

        if (!$transaction) {
            return null;
        }

        return $this->syncXenditTransaction($transaction, $paymentData);
    }

    /**
     * @param Transaction $transaction
     * @param array<string, mixed> $paymentData
     * @return Transaction
     */
    private function syncXenditTransaction(Transaction $transaction, array $paymentData): Transaction
    {
        if (isset($paymentData['payment_session_id']) && empty($transaction->xendit_payment_id)) {
            $transaction->xendit_payment_id = $paymentData['payment_session_id'];
        }

        if (isset($paymentData['reference_id']) && empty($transaction->xendit_payment_token)) {
            $transaction->xendit_payment_token = $paymentData['reference_id'];
        }

        $status = strtoupper((string) data_get($paymentData, 'status', ''));

        if ($status === 'ACTIVE' && $transaction->status === Transaction::INITIATED_STATUS) {
            $transaction->status = Transaction::PENDING_STATUS;
            $transaction->save();
        } elseif (in_array($status, ['EXPIRED', 'CANCELED'], true) && $transaction->status !== Transaction::APPROVED_STATUS) {
            $transaction->status = Transaction::CANCELED_STATUS;
            $transaction->save();
        } elseif ($status === 'COMPLETED' && $transaction->status !== Transaction::APPROVED_STATUS) {
            $transaction->status = Transaction::APPROVED_STATUS;
            $transaction->save();

            $this->creditReceiverForTransaction($transaction);
            app(PurchaseConversionService::class)->complete($transaction);
            NotificationServiceProvider::createTipNotificationByTransaction($transaction);
            NotificationServiceProvider::createPPVNotificationByTransaction($transaction);
        }

        return $transaction;
    }

    /**
     * @param Transaction $transaction
     * @return string|null
     * @throws \Exception
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function generatePaddleTransaction(Transaction $transaction): ?string
    {
        $paymentToken = $this->generatePaddlePaymentToken($transaction);
        $transactionData = PaddleServiceProvider::createTransactionByTransaction($transaction, $paymentToken);

        if (isset($transactionData['id'])) {
            $transaction->paddle_transaction_id = $transactionData['id'];
        }

        return PaddleServiceProvider::generateHostedCheckoutUrl($transaction, $transactionData);
    }

    /**
     * @param Transaction $transaction
     * @return string
     * @throws \Exception
     */
    private function generatePaddlePaymentToken(Transaction $transaction): string
    {
        do {
            $id = Uuid::uuid4()->getHex();
        } while (Transaction::query()->where('paddle_transaction_token', $id)->first() != null);

        $transaction->paddle_transaction_token = $id;

        return $id;
    }

    /**
     * @param string|null $paymentToken
     * @return Transaction|null
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function verifyPaddleTransactionByToken(?string $paymentToken): ?Transaction
    {
        if (!$paymentToken) {
            return null;
        }

        $transaction = Transaction::query()->where('paddle_transaction_token', $paymentToken)->first();
        if (!$transaction || !$transaction->paddle_transaction_id) {
            return $transaction;
        }

        $transactionData = PaddleServiceProvider::getTransactionData($transaction->paddle_transaction_id);

        return $this->syncPaddleTransaction($transaction, $transactionData);
    }

    /**
     * @param string|null $transactionId
     * @return Transaction|null
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function verifyPaddleTransactionById(?string $transactionId): ?Transaction
    {
        if (!$transactionId) {
            return null;
        }

        $transactionData = PaddleServiceProvider::getTransactionData($transactionId);
        $transaction = Transaction::query()->where('paddle_transaction_id', $transactionId)->first();

        if (!$transaction) {
            $paymentToken = data_get($transactionData, 'custom_data.payment_token');
            if ($paymentToken) {
                $transaction = Transaction::query()->where('paddle_transaction_token', $paymentToken)->first();
            }
        }

        if (!$transaction) {
            return null;
        }

        return $this->syncPaddleTransaction($transaction, $transactionData);
    }

    /**
     * @param Transaction $transaction
     * @param array<string, mixed> $transactionData
     * @return Transaction
     */
    private function syncPaddleTransaction(Transaction $transaction, array $transactionData): Transaction
    {
        if (isset($transactionData['id']) && empty($transaction->paddle_transaction_id)) {
            $transaction->paddle_transaction_id = $transactionData['id'];
        }

        if (isset($transactionData['custom_data']['payment_token']) && empty($transaction->paddle_transaction_token)) {
            $transaction->paddle_transaction_token = $transactionData['custom_data']['payment_token'];
        }

        $status = strtolower((string) data_get($transactionData, 'status', ''));

        if (in_array($status, ['draft', 'ready', 'billed', 'paid'], true)
            && $transaction->status === Transaction::INITIATED_STATUS) {
            $transaction->status = Transaction::PENDING_STATUS;
            $transaction->save();
        } elseif (in_array($status, ['canceled', 'past_due'], true) && $transaction->status !== Transaction::APPROVED_STATUS) {
            $transaction->status = Transaction::CANCELED_STATUS;
            $transaction->save();
        } elseif ($status === 'completed' && $transaction->status !== Transaction::APPROVED_STATUS) {
            $transaction->status = Transaction::APPROVED_STATUS;
            $transaction->save();

            $this->creditReceiverForTransaction($transaction);
            app(PurchaseConversionService::class)->complete($transaction);
            NotificationServiceProvider::createTipNotificationByTransaction($transaction);
            NotificationServiceProvider::createPPVNotificationByTransaction($transaction);
        }

        return $transaction;
    }

    /**
     * @param Transaction $transaction
     * @return string|null
     * @throws \Exception
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function generateCryptoComTransaction(Transaction $transaction): ?string
    {
        $paymentToken = $this->generateCryptoComPaymentToken($transaction);
        $paymentData = CryptoComServiceProvider::createPaymentByTransaction($transaction, $paymentToken);

        if (isset($paymentData['id'])) {
            $transaction->cryptocom_payment_id = $paymentData['id'];
        }

        return $paymentData['payment_url'] ?? null;
    }

    /**
     * @param Transaction $transaction
     * @return string
     * @throws \Exception
     */
    private function generateCryptoComPaymentToken(Transaction $transaction): string
    {
        do {
            $id = Uuid::uuid4()->getHex();
        } while (Transaction::query()->where('cryptocom_payment_token', $id)->first() != null);

        $transaction->cryptocom_payment_token = $id;

        return $id;
    }

    /**
     * @param string|null $paymentToken
     * @return Transaction|null
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function verifyCryptoComTransactionByToken(?string $paymentToken): ?Transaction
    {
        if (!$paymentToken) {
            return null;
        }

        $transaction = Transaction::query()->where('cryptocom_payment_token', $paymentToken)->first();
        if (!$transaction || !$transaction->cryptocom_payment_id) {
            return $transaction;
        }

        $paymentData = CryptoComServiceProvider::getPaymentData($transaction->cryptocom_payment_id);

        return $this->syncCryptoComTransaction($transaction, $paymentData);
    }

    /**
     * @param string|null $paymentId
     * @return Transaction|null
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function verifyCryptoComTransactionByPaymentId(?string $paymentId): ?Transaction
    {
        if (!$paymentId) {
            return null;
        }

        $paymentData = CryptoComServiceProvider::getPaymentData($paymentId);
        $transaction = Transaction::query()->where('cryptocom_payment_id', $paymentId)->first();

        if (!$transaction) {
            $paymentToken = data_get($paymentData, 'metadata.payment_token') ?? data_get($paymentData, 'order_id');
            if ($paymentToken) {
                $transaction = Transaction::query()->where('cryptocom_payment_token', $paymentToken)->first();
            }
        }

        if (!$transaction) {
            return null;
        }

        return $this->syncCryptoComTransaction($transaction, $paymentData);
    }

    /**
     * @param Transaction $transaction
     * @param array<string, mixed> $paymentData
     * @return Transaction
     */
    private function syncCryptoComTransaction(Transaction $transaction, array $paymentData): Transaction
    {
        if (isset($paymentData['id']) && empty($transaction->cryptocom_payment_id)) {
            $transaction->cryptocom_payment_id = $paymentData['id'];
        }

        if (isset($paymentData['metadata']['payment_token']) && empty($transaction->cryptocom_payment_token)) {
            $transaction->cryptocom_payment_token = $paymentData['metadata']['payment_token'];
        }

        $status = strtolower((string) data_get($paymentData, 'status', ''));

        if ($status === 'pending' && $transaction->status === Transaction::INITIATED_STATUS) {
            $transaction->status = Transaction::PENDING_STATUS;
            $transaction->save();
        } elseif ($status === 'cancelled' && $transaction->status !== Transaction::APPROVED_STATUS) {
            $transaction->status = Transaction::CANCELED_STATUS;
            $transaction->save();
        } elseif ($status === 'succeeded' && $transaction->status !== Transaction::APPROVED_STATUS) {
            $transaction->status = Transaction::APPROVED_STATUS;
            $transaction->save();

            $this->creditReceiverForTransaction($transaction);
            app(PurchaseConversionService::class)->complete($transaction);
            NotificationServiceProvider::createTipNotificationByTransaction($transaction);
            NotificationServiceProvider::createPPVNotificationByTransaction($transaction);
        }

        return $transaction;
    }

    /**
     * @param Transaction $transaction
     * @return string|null
     * @throws \Exception
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function generateFlutterwaveTransaction(Transaction $transaction): ?string
    {
        $paymentToken = $this->generateFlutterwavePaymentToken($transaction);
        $paymentData = FlutterwaveServiceProvider::createPaymentByTransaction($transaction, $paymentToken);

        if (isset($paymentData['data']['id'])) {
            $transaction->flutterwave_payment_id = (string) $paymentData['data']['id'];
        }

        return data_get($paymentData, 'data.link');
    }

    /**
     * @param Transaction $transaction
     * @return string
     * @throws \Exception
     */
    private function generateFlutterwavePaymentToken(Transaction $transaction): string
    {
        do {
            $id = Uuid::uuid4()->getHex();
        } while (Transaction::query()->where('flutterwave_payment_token', $id)->first() != null);

        $transaction->flutterwave_payment_token = $id;

        return $id;
    }

    /**
     * @param string|null $paymentToken
     * @param string|null $transactionId
     * @param string|null $redirectStatus
     * @return Transaction|null
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function verifyFlutterwaveTransactionByToken(?string $paymentToken, ?string $transactionId = null, ?string $redirectStatus = null): ?Transaction
    {
        if (!$paymentToken) {
            return null;
        }

        $transaction = Transaction::query()->where('flutterwave_payment_token', $paymentToken)->first();
        if (!$transaction) {
            return null;
        }

        if ($transactionId) {
            return $this->verifyFlutterwaveTransactionById($transactionId);
        }

        if ($transaction->flutterwave_payment_id) {
            return $this->verifyFlutterwaveTransactionById($transaction->flutterwave_payment_id);
        }

        if (in_array(strtolower((string) $redirectStatus), ['cancelled', 'canceled', 'failed'], true)
            && $transaction->status !== Transaction::APPROVED_STATUS) {
            $transaction->status = Transaction::CANCELED_STATUS;
            $transaction->save();
        }

        return $transaction;
    }

    /**
     * @param string|null $transactionId
     * @return Transaction|null
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function verifyFlutterwaveTransactionById(?string $transactionId): ?Transaction
    {
        if (!$transactionId) {
            return null;
        }

        $paymentData = FlutterwaveServiceProvider::verifyTransaction($transactionId);
        $transaction = Transaction::query()->where('flutterwave_payment_id', (string) $transactionId)->first();

        if (!$transaction) {
            $paymentToken = (string) (data_get($paymentData, 'data.tx_ref') ?? data_get($paymentData, 'data.meta.payment_token') ?? '');
            if ($paymentToken !== '') {
                $transaction = Transaction::query()->where('flutterwave_payment_token', $paymentToken)->first();
            }
        }

        if (!$transaction) {
            return null;
        }

        return $this->syncFlutterwaveTransaction($transaction, $paymentData);
    }

    /**
     * @param Transaction $transaction
     * @param array<string, mixed> $paymentData
     * @return Transaction
     */
    private function syncFlutterwaveTransaction(Transaction $transaction, array $paymentData): Transaction
    {
        $verifiedId = data_get($paymentData, 'data.id');
        if ($verifiedId && empty($transaction->flutterwave_payment_id)) {
            $transaction->flutterwave_payment_id = (string) $verifiedId;
        }

        $verifiedToken = data_get($paymentData, 'data.tx_ref') ?? data_get($paymentData, 'data.meta.payment_token');
        if ($verifiedToken && empty($transaction->flutterwave_payment_token)) {
            $transaction->flutterwave_payment_token = (string) $verifiedToken;
        }

        $status = strtolower((string) data_get($paymentData, 'data.status', ''));
        $verifiedCurrency = strtoupper((string) data_get($paymentData, 'data.currency', ''));
        $verifiedAmount = (float) data_get($paymentData, 'data.amount', 0);
        $verifiedTokenMatches = (string) $verifiedToken === (string) $transaction->flutterwave_payment_token;
        $currencyMatches = $verifiedCurrency === strtoupper((string) $transaction->currency);
        $amountMatches = $verifiedAmount >= (float) $transaction->amount;

        if (in_array($status, ['pending'], true) && $transaction->status === Transaction::INITIATED_STATUS) {
            $transaction->status = Transaction::PENDING_STATUS;
            $transaction->save();
        } elseif (in_array($status, ['cancelled', 'canceled', 'failed'], true)
            && $transaction->status !== Transaction::APPROVED_STATUS) {
            $transaction->status = Transaction::CANCELED_STATUS;
            $transaction->save();
        } elseif ($status === 'successful' && $transaction->status !== Transaction::APPROVED_STATUS) {
            if ($verifiedTokenMatches && $currencyMatches && $amountMatches) {
                $transaction->status = Transaction::APPROVED_STATUS;
                $transaction->save();

                $this->creditReceiverForTransaction($transaction);
                app(PurchaseConversionService::class)->complete($transaction);
                NotificationServiceProvider::createTipNotificationByTransaction($transaction);
                NotificationServiceProvider::createPPVNotificationByTransaction($transaction);
            } else {
                Log::channel('payments')->warning('Flutterwave verification mismatch.', [
                    'transaction_id' => $transaction->id,
                    'flutterwave_payment_id' => $transaction->flutterwave_payment_id,
                    'expected_token' => $transaction->flutterwave_payment_token,
                    'verified_token' => $verifiedToken,
                    'expected_amount' => $transaction->amount,
                    'verified_amount' => $verifiedAmount,
                    'expected_currency' => $transaction->currency,
                    'verified_currency' => $verifiedCurrency,
                ]);
            }
        }

        return $transaction;
    }

    /**
     * @param Transaction $transaction
     * @return string|null
     * @throws \Exception
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function generateCoinGateTransaction(Transaction $transaction): ?string
    {
        $paymentToken = $this->generateCoinGatePaymentToken($transaction);
        $orderData = CoinGateServiceProvider::createOrderByTransaction($transaction, $paymentToken);

        if (isset($orderData['id'])) {
            $transaction->coingate_order_id = (string) $orderData['id'];
        }

        return $orderData['payment_url'] ?? null;
    }

    /**
     * @param Transaction $transaction
     * @return string
     * @throws \Exception
     */
    private function generateCoinGatePaymentToken(Transaction $transaction): string
    {
        do {
            $id = Uuid::uuid4()->getHex();
        } while (Transaction::query()->where('coingate_payment_token', $id)->first() != null);

        $transaction->coingate_payment_token = $id;

        return $id;
    }

    /**
     * @param string|null $paymentToken
     * @return Transaction|null
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function verifyCoinGateTransactionByToken(?string $paymentToken): ?Transaction
    {
        if (!$paymentToken) {
            return null;
        }

        $transaction = Transaction::query()->where('coingate_payment_token', $paymentToken)->first();
        if (!$transaction || !$transaction->coingate_order_id) {
            return $transaction;
        }

        $orderData = CoinGateServiceProvider::getOrderData($transaction->coingate_order_id);

        return $this->syncCoinGateTransaction($transaction, $orderData);
    }

    /**
     * @param string|null $orderId
     * @return Transaction|null
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function verifyCoinGateTransactionById(?string $orderId): ?Transaction
    {
        if (!$orderId) {
            return null;
        }

        $orderData = CoinGateServiceProvider::getOrderData($orderId);
        $transaction = Transaction::query()->where('coingate_order_id', (string) $orderId)->first();

        if (!$transaction) {
            $paymentToken = data_get($orderData, 'token') ?? data_get($orderData, 'order_id');
            if ($paymentToken) {
                $transaction = Transaction::query()->where('coingate_payment_token', (string) $paymentToken)->first();
            }
        }

        if (!$transaction) {
            return null;
        }

        return $this->syncCoinGateTransaction($transaction, $orderData);
    }

    /**
     * @param Transaction $transaction
     * @param array<string, mixed> $orderData
     * @return Transaction
     */
    private function syncCoinGateTransaction(Transaction $transaction, array $orderData): Transaction
    {
        if (isset($orderData['id']) && empty($transaction->coingate_order_id)) {
            $transaction->coingate_order_id = (string) $orderData['id'];
        }

        $verifiedToken = data_get($orderData, 'token') ?? data_get($orderData, 'order_id');
        if ($verifiedToken && empty($transaction->coingate_payment_token)) {
            $transaction->coingate_payment_token = (string) $verifiedToken;
        }

        $status = strtolower((string) data_get($orderData, 'status', ''));
        $verifiedAmount = (float) data_get($orderData, 'price_amount', 0);
        $verifiedCurrency = strtoupper((string) data_get($orderData, 'price_currency', ''));
        $amountMatches = $verifiedAmount >= (float) $transaction->amount;
        $currencyMatches = $verifiedCurrency === strtoupper((string) $transaction->currency);
        $tokenMatches = !$verifiedToken || (string) $verifiedToken === (string) $transaction->coingate_payment_token;

        if (in_array($status, ['new', 'pending', 'confirming'], true)
            && $transaction->status === Transaction::INITIATED_STATUS) {
            $transaction->status = Transaction::PENDING_STATUS;
            $transaction->save();
        } elseif (in_array($status, ['expired', 'invalid', 'canceled', 'cancelled'], true)
            && $transaction->status !== Transaction::APPROVED_STATUS) {
            $transaction->status = Transaction::CANCELED_STATUS;
            $transaction->save();
        } elseif ($status === 'paid' && $transaction->status !== Transaction::APPROVED_STATUS) {
            if ($amountMatches && $currencyMatches && $tokenMatches) {
                $transaction->status = Transaction::APPROVED_STATUS;
                $transaction->save();

                $this->creditReceiverForTransaction($transaction);
                app(PurchaseConversionService::class)->complete($transaction);
                NotificationServiceProvider::createTipNotificationByTransaction($transaction);
                NotificationServiceProvider::createPPVNotificationByTransaction($transaction);
            } else {
                Log::channel('payments')->warning('CoinGate verification mismatch.', [
                    'transaction_id' => $transaction->id,
                    'coingate_order_id' => $transaction->coingate_order_id,
                    'expected_token' => $transaction->coingate_payment_token,
                    'verified_token' => $verifiedToken,
                    'expected_amount' => $transaction->amount,
                    'verified_amount' => $verifiedAmount,
                    'expected_currency' => $transaction->currency,
                    'verified_currency' => $verifiedCurrency,
                ]);
            }
        }

        return $transaction;
    }

    /**
     * @param $transaction
     * @return string
     * @throws \Exception
     */
    private function generateNowPaymentsOrderId($transaction)
    {
        // generate unique token for transaction
        do {
            $id = Uuid::uuid4()->getHex();
        } while (Transaction::query()->where('nowpayments_order_id', $id)->first() != null);
        $transaction->nowpayments_order_id = $id;

        return $id;
    }

    /**
     * Generates a unique identifier for ccbill transaction.
     * @param $transaction
     * @return string
     * @throws \Exception
     */
    private function generateCCBillUniqueTransactionToken($transaction)
    {
        // generate unique token for transaction
        do {
            $id = Uuid::uuid4()->getHex();
        } while (Transaction::query()->where('ccbill_payment_token', $id)->first() != null);
        $transaction->ccbill_payment_token = $id;

        return $id;
    }

    /**
     * @param $transaction
     * @return string|null
     * @throws \Exception
     */
    public function generateCCBillOneTimePaymentTransaction($transaction) {
        $redirectUrl = null;
        if(PaymentsServiceProvider::ccbillCredentialsProvided()) {
            // generate a unique token for transaction and prepare dynamic pricing for the flex form
            $this->generateCCBillUniqueTransactionToken($transaction);

            $redirectUrl = $this->generateCCBillRedirectUrlByTransaction($transaction);
        }

        return $redirectUrl;
    }

    /**
     * Generates redirect url for ccbill payment.
     * @param $transaction
     * @return string
     */
    private function generateCCBillRedirectUrlByTransaction($transaction) {
        $user = Auth::user();
        $amount = $transaction->amount;
        $ccBillInitialPeriod = $this->getCCBillRecurringPeriodInDaysByTransaction($transaction);
        $ccBillNumRebills = 99;
        $isSubscriptionPayment = $this->isSubscriptionPayment($transaction->type);
        $ccBillClientAcc = getSetting('payments.ccbill_account_number');
        $ccBillClientSubAccRecurring = getSetting('payments.ccbill_subaccount_number_recurring');
        $ccBillClientSubAccOneTime = getSetting('payments.ccbill_subaccount_number_one_time');
        $ccBillSalt = getSetting('payments.ccbill_salt_key');
        $ccBillFlexFormId = getSetting('payments.ccbill_flex_form_id');
        $ccBillCurrencyCode = $this->getCCBillCurrencyCodeByCurrency(SettingsServiceProvider::getAppCurrencyCode());
        $ccBillRecurringPeriod = $this->getCCBillRecurringPeriodInDaysByTransaction($transaction);
        $formattedAmount = number_format((float) $amount, 2, '.', '');
        $ccBillFormDigest = $isSubscriptionPayment
            ? md5($formattedAmount.$ccBillInitialPeriod.$formattedAmount.$ccBillRecurringPeriod.$ccBillNumRebills.$ccBillCurrencyCode.$ccBillSalt)
            : md5($formattedAmount.$ccBillInitialPeriod.$ccBillCurrencyCode.$ccBillSalt);

        // common form metadata for both one time & recurring payments
        $parameters = [
            'clientAccnum' => $ccBillClientAcc,
            'initialPrice' => $formattedAmount,
            'initialPeriod' => $ccBillInitialPeriod,
            'currencyCode' => $ccBillCurrencyCode,
            'formDigest' => $ccBillFormDigest,
            'email' => $user->email ?? '',
            'token' => $transaction->ccbill_payment_token,
        ];

        if (CheckoutBilling::collectsDetails()) {
            $country = Country::query()->where('name', $user->country)->first();
            $parameters += [
                'customer_fname' => $user->first_name ?? '',
                'customer_lname' => $user->last_name ?? '',
                'address1' => $user->billing_address ?? '',
                'city' => $user->city ?? '',
                'state' => $user->state ?? '',
                'zipcode' => $user->postcode ?? '',
                'country' => $country->country_code ?? $user->country ?? '',
            ];
        }

        // set client sub account for recurring payments & add extra params
        if($isSubscriptionPayment){
            $parameters['clientSubacc'] = $ccBillClientSubAccRecurring;
            $parameters['recurringPrice'] = $formattedAmount;
            $parameters['recurringPeriod'] = $ccBillRecurringPeriod;
            $parameters['numRebills'] = $ccBillNumRebills;
        // set client sub account for one time payments & add extra params
        } else {
            $parameters['clientSubacc'] = $ccBillClientSubAccOneTime;
        }

        return Transaction::CCBILL_FLEX_FORM_BASE_PATH.$ccBillFlexFormId.'?'.http_build_query(
            $parameters,
            '',
            '&',
            PHP_QUERY_RFC3986
        );
    }

    /**
     * Get ccbill subscription recurring billing period in days.
     * @param $transaction
     * @return float|int
     */
    public function getCCBillRecurringPeriodInDaysByTransaction($transaction) {
        return PaymentsServiceProvider::getSubscriptionMonthlyIntervalByTransactionType($transaction->type) * 30;
    }

    /**
     * @param $currency
     * @return mixed
     */
    public function getCCBillCurrencyCodeByCurrency($currency) {
        $availableCurrencies = [
            'EUR' => '978',
            'AUD' => '036',
            'CAD' => '124',
            'GBP' => '826',
            'JPY' => '392',
            'USD' => '840',
        ];

        return $availableCurrencies[$currency];
    }

    /**
     * @param $transaction
     * @return int|string|null
     * @throws \Exception
     */
    public function generateCCBillSubscriptionPayment($transaction) {
        $redirectUrl = null;
        if(PaymentsServiceProvider::ccbillCredentialsProvided()) {
            // generate a unique token for transaction and prepare dynamic pricing for the flex form
            $this->generateCCBillUniqueTransactionToken($transaction);
            $this->generateCCBillSubscriptionByTransaction($transaction);
            $redirectUrl = $this->generateCCBillRedirectUrlByTransaction($transaction);
        }

        return $redirectUrl;
    }

    /**
     * @param $transaction
     * @return Subscription|\Illuminate\Database\Eloquent\Builder|\Illuminate\Database\Eloquent\Model|object|null
     * @throws \Exception
     */
    public function generateCCBillSubscriptionByTransaction($transaction)
    {
        $existingSubscription = $this->getSubscriptionBySenderAndReceiverAndProvider(
            $transaction['sender_user_id'],
            $transaction['recipient_user_id'],
            Transaction::CCBILL_PROVIDER
        );

        if ($existingSubscription != null) {
            $subscription = $existingSubscription;
        } else {
            $subscription = $this->createSubscriptionFromTransaction($transaction);
            $subscription['amount'] = $transaction['amount'];
            $subscription['ccbill_subscription_id'] = $transaction['ccbill_subscription_id'];

            $subscription->save();
        }
        $transaction['subscription_id'] = $subscription['id'];

        return $subscription;
    }

    /**
     * Makes the call to CCBill API to cancel a subscription.
     * @param $stripeSubscriptionId
     * @return bool
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function cancelCCBillSubscription($stripeSubscriptionId)
    {
        $client = new Client(['connect_timeout' => 5, 'timeout' => 15]);
        $cancellationData = [
            'clientAccnum' => getSetting('payments.ccbill_account_number'),
            'clientSubacc' => getSetting('payments.ccbill_subaccount_number_recurring'),
            'username' => getSetting('payments.ccbill_datalink_username'),
            'password' => getSetting('payments.ccbill_datalink_password'),
            'subscriptionId' => $stripeSubscriptionId,
            'action' => 'cancelSubscription',
        ];
        if(getSetting('payments.ccbill_skip_subaccount_from_cancellations')){
            unset($cancellationData['clientSubacc']);
        }
        $res = $client->request('GET', 'https://datalink.ccbill.com/utils/subscriptionManagement.cgi', [
            'query' => $cancellationData,
        ]);
        $response = $res->getBody()->getContents();
        if($response) {
            $responseAsArray = preg_split('/\r?\n/', trim($response));
            if(isset($responseAsArray[0]) && isset($responseAsArray[1])) {
                if($responseAsArray[0] === 'results' && $responseAsArray[1] === '1') {
                    return true;
                }
            }
        }
        return false;
    }

    /**
     * @param $transaction
     * @return string
     * @throws \Exception
     */
    private function generatePaystackUniqueTransactionToken($transaction)
    {
        // generate unique token for transaction
        do {
            $id = Uuid::uuid4()->getHex();
        } while (Transaction::query()->where('paystack_payment_token', $id)->first() != null);
        $transaction->paystack_payment_token = $id;

        return $id;
    }

    /**
     * @param $transaction
     * @param $email
     * @return mixed
     * @throws \Exception
     */
    public function generatePaystackTransaction($transaction, $email) {
        $paystack = new Paystack(getSetting('payments.paystack_secret_key'));
        $reference = self::generatePaystackUniqueTransactionToken($transaction);
        // @phpstan-ignore property.notFound (Paystack exposes API resources through magic properties.)
        $paystackTransaction = $paystack->transaction->initialize([
            'amount'=>$transaction->amount * 100,
            'email'=>$email,
            'reference'=>$reference,
        ]);

        return $paystackTransaction->data->authorization_url;
    }

    /**
     * Calls PayStack API to verify payment status and updates transaction in our side accordingly.
     * @param $reference
     * @return \Illuminate\Database\Eloquent\Builder|\Illuminate\Database\Eloquent\Model|object|null
     */
    public function verifyPaystackTransaction($reference) {
        $transaction = null;
        if($reference){
            $transaction = Transaction::query()->where('paystack_payment_token', $reference)->first();
            if($transaction && $transaction->status !== Transaction::APPROVED_STATUS) {
                $paystack = new Paystack(getSetting('payments.paystack_secret_key'));
                try
                {
                    // @phpstan-ignore property.notFound (Paystack exposes API resources through magic properties.)
                    $paystackTransaction = $paystack->transaction->verify([
                        'reference'=>$reference,
                    ]);

                    if ('success' === $paystackTransaction->data->status) {
                        $transaction->status = Transaction::APPROVED_STATUS;
                        $transaction->save();

                        $this->creditReceiverForTransaction($transaction);
                        app(PurchaseConversionService::class)->complete($transaction);
                        NotificationServiceProvider::createTipNotificationByTransaction($transaction);
                        NotificationServiceProvider::createPPVNotificationByTransaction($transaction);
                    }
                } catch(ApiException $e){
                    Log::channel('payments')->error("Failed verifying paystack transaction: ".$e->getMessage());
                }
            }
        }

        return $transaction;
    }

    /**
     * Cancels a subscription.
     * @param $subscription
     * @return bool|RedirectResponse
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function cancelSubscription($subscription) {
        $cancelSubscription = false;

        if ($subscription->provider != null) {
            if ($subscription->provider === Transaction::PAYPAL_PROVIDER && $subscription->paypal_agreement_id != null) {
                $cancelSubscription = $this->cancelPaypalSubscription($subscription->paypal_agreement_id);
            } elseif ($subscription->provider === Transaction::STRIPE_PROVIDER && $subscription->stripe_subscription_id != null) {
                $cancelSubscription = $this->cancelStripeSubscription($subscription->stripe_subscription_id);
            } elseif ($subscription->provider === Transaction::VEROTEL_PROVIDER && $subscription->verotel_sale_id != null) {
                $cancelSubscription = VerotelServiceProvider::cancelSubscriptionSale($subscription->verotel_sale_id);
            } elseif ($subscription->provider === Transaction::CCBILL_PROVIDER && $subscription->ccbill_subscription_id != null) {
                if($this->cancelCCBillSubscription($subscription->ccbill_subscription_id)){
                    $cancelSubscription = true;
                }
            } elseif($subscription->provider === Transaction::CREDIT_PROVIDER) {
                $cancelSubscription = true;
            }

            // handle cancel subscription
            if($cancelSubscription) {
                $subscription->status = Subscription::CANCELED_STATUS;
                $subscription->canceled_at = Carbon::now();

                $subscription->save();
            }
        }

        return $cancelSubscription;
    }

    /**
     * Generate Mercado transaction.
     * @param $transaction
     * @return string|void
     */
    public function generateMercadoTransaction($transaction) {
        try {
            $this->initiateMercadoPagoSdk();
            $reference = self::generateMercadoUniqueTransactionToken($transaction);

            $preference = new Preference();
            // @phpstan-ignore-next-line property.protected (MercadoPago SDK exposes model fields through magic access.)
            $preference->external_reference = $reference;
            // @phpstan-ignore-next-line property.protected (MercadoPago SDK exposes model fields through magic access.)
            $preference->notification_url = route('mercado.payment.update');

            $item = new \MercadoPago\Item();
            // @phpstan-ignore-next-line property.protected (MercadoPago SDK exposes model fields through magic access.)
            $item->title = self::getPaymentDescriptionByTransaction($transaction);
            // @phpstan-ignore-next-line property.protected (MercadoPago SDK exposes model fields through magic access.)
            $item->quantity = 1;
            // @phpstan-ignore-next-line property.protected (MercadoPago SDK exposes model fields through magic access.)
            $item->unit_price = $transaction->amount;

            // @phpstan-ignore-next-line property.protected (MercadoPago SDK exposes model fields through magic access.)
            $preference->items = [$item];

            // @phpstan-ignore-next-line property.protected, assign.propertyType (MercadoPago SDK exposes model fields through magic access.)
            $preference->back_urls = [
                "success" => route('payment.checkMercadoPaymentStatus'),
            ];
            // @phpstan-ignore-next-line property.protected (MercadoPago SDK exposes model fields through magic access.)
            $preference->auto_return = "approved";

            $preference->save();

            // @phpstan-ignore-next-line property.protected (MercadoPago SDK exposes model fields through magic access.)
            return $preference->init_point;
        } catch (\Exception $exception) {
            $this->redirectByTransaction($transaction);
        }
    }

    /**
     * Verify Mercado transaction and update transaction accordingly.
     * @param $reference
     * @return \Illuminate\Database\Eloquent\Builder|\Illuminate\Database\Eloquent\Model|object|null
     */
    public function verifyMercadoTransaction($paymentId) {
        $transaction = null;
        try {
            $this->initiateMercadoPagoSdk();
            $mercadoPayment = \MercadoPago\Payment::get($paymentId);
            if($mercadoPayment) {
                $transaction = Transaction::query()->where('mercado_payment_token', $mercadoPayment->external_reference)->first();
                if($transaction && $transaction->status !== Transaction::APPROVED_STATUS) {
                    $success = $mercadoPayment->status === 'approved';
                    if($success) {
                        $transaction->status = Transaction::APPROVED_STATUS;
                        $transaction->mercado_payment_id = $paymentId;
                        $transaction->save();

                        $this->creditReceiverForTransaction($transaction);
                        app(PurchaseConversionService::class)->complete($transaction);
                        NotificationServiceProvider::createTipNotificationByTransaction($transaction);
                        NotificationServiceProvider::createPPVNotificationByTransaction($transaction);
                    }
                }
            }
        } catch (\Exception $exception) {
            Log::channel('payments')->error("Failed verifying Mercado transaction: ".$exception->getMessage());
        }

        return $transaction;
    }

    /**
     * Generates MercadoPago unique transaction token.
     * @param $transaction
     * @return \Ramsey\Uuid\Type\Hexadecimal
     */
    private function generateMercadoUniqueTransactionToken($transaction)
    {
        // generate unique token for transaction
        do {
            $id = Uuid::uuid4()->getHex();
        } while (Transaction::query()->where('paystack_payment_token', $id)->first() != null);
        $transaction->mercado_payment_token = $id;

        return $id;
    }

    /**
     * Initiates MercadoPago SDK.
     * @return void
     */
    private function initiateMercadoPagoSdk() {
        SDK::setAccessToken(getSetting('payments.mercado_access_token'));
    }

    public function getOriginalPaymentIdFromResourceForRefundedTransaction(array $resource): ?string {
        // Check if the resource contains the "links" array
        if (isset($resource['links'])) {
            foreach ($resource['links'] as $link) {
                if (isset($link['rel']) && $link['rel'] === 'up') {
                    // Extract the original payment ID from the "href" URL
                    $urlParts = explode('/', rtrim($link['href'], '/'));
                    return end($urlParts); // Return the last part of the URL (the ID)
                }
            }
        }

        // Return null if no payment ID is found
        return null;
    }

    public function handlePaypalTransactionRefund(string $transactionId): void {
        $transaction = Transaction::query()->where('paypal_transaction_id', $transactionId)->with('subscription')->first();
        if ($transaction) {
            if($transaction->status === Transaction::APPROVED_STATUS){
                $transaction->status = Transaction::REFUNDED_STATUS;
                $transaction->save();

                if($transaction->subscription != null){
                    $transaction->subscription->status = Subscription::SUSPENDED_STATUS;
                    $transaction->subscription->expires_at = Carbon::now('UTC');
                    $transaction->subscription->save();
                }

                $this->deductMoneyFromUserForRefundedTransaction($transaction);
            }
        }
    }

    private function generateVerotelSubscriptionByTransaction($transaction): Subscription
    {
        $existingSubscription = $this->getSubscriptionBySenderAndReceiverAndProvider(
            $transaction['sender_user_id'],
            $transaction['recipient_user_id'],
            Transaction::VEROTEL_PROVIDER
        );

        if ($existingSubscription != null) {
            $subscription = $existingSubscription;
        } else {
            $subscription = $this->createSubscriptionFromTransaction($transaction);
            $subscription['amount'] = $transaction['amount'];
            $subscription['verotel_sale_id'] = $transaction['verotel_sale_id'];

            $subscription->save();
        }
        $transaction['subscription_id'] = $subscription['id'];

        return $subscription;
    }

    public function generateVerotelSubscriptionPayment($transaction): string {
        $token = self::generateVerotelUniqueTransactionToken($transaction);
        $this->generateVerotelSubscriptionByTransaction($transaction);

        return VerotelServiceProvider::generateSubscriptionPaymentUrl($transaction, $token);
    }

    public function generateVerotelTransaction($transaction): string {
        $token = self::generateVerotelUniqueTransactionToken($transaction);

        return VerotelServiceProvider::generateOneTimePaymentUrl($transaction, $token);
    }

    private function generateVerotelUniqueTransactionToken($transaction): string
    {
        // generate unique token for transaction
        do {
            $id = Uuid::uuid4()->getHex();
        } while (Transaction::query()->where('verotel_payment_token', $id)->first() != null);
        $transaction->verotel_payment_token = $id;

        return $id;
    }

    public function verifyVerotelOneTimePayment(string $paymentToken, string $saleId): void {
        try {
            /** @var Transaction|null $transaction */
            $transaction = Transaction::query()->where('verotel_payment_token', $paymentToken)->first();
            if($transaction) {
                $verotelPaymentData = VerotelServiceProvider::getPaymentData($saleId);
                Log::channel('payments')->debug("Verotel payment data", [$verotelPaymentData]);

                if($transaction->status !== Transaction::APPROVED_STATUS
                    && isset($verotelPaymentData['saleResult'])
                    && $verotelPaymentData['saleResult'] === 'APPROVED'
                ) {
                    $transaction->status = Transaction::APPROVED_STATUS;
                    $transaction->verotel_sale_id = $saleId;
                    $transaction->save();

                    $this->creditReceiverForTransaction($transaction);
                    app(PurchaseConversionService::class)->complete($transaction);
                    NotificationServiceProvider::createTipNotificationByTransaction($transaction);
                    NotificationServiceProvider::createPPVNotificationByTransaction($transaction);
                }
            }
        } catch (\Exception $exception) {
            Log::channel('payments')->error("Failed verifying Verotel transaction: ".$exception->getMessage());
        }
    }

    public function verifyVerotelInitialRecurringPayment(string $paymentToken, string $saleId, ?string $nextChargeOn = null): void {
        try {
            /** @var Transaction|null $transaction */
            $transaction = Transaction::query()->where('verotel_payment_token', $paymentToken)->first();
            if($transaction) {
                $verotelPaymentData = VerotelServiceProvider::getPaymentData($saleId);
                Log::channel('payments')->debug("Verotel payment data", [$verotelPaymentData]);

                if($transaction->status !== Transaction::APPROVED_STATUS
                    && isset($verotelPaymentData['saleResult'])
                    && $verotelPaymentData['saleResult'] === 'APPROVED'
                ) {
                    // handle initial recurring payment
                    $transaction->verotel_sale_id = $saleId;
                    $transaction->status = Transaction::APPROVED_STATUS;
                    $transaction->save();

                    $subscription = $transaction->subscription;
                    if($subscription) {
                        if($nextChargeOn) {
                            $expiryDate = Carbon::parse($nextChargeOn, 'UTC');
                        } else {
                            $expiryDate = Carbon::now('UTC')->addDays((int) self::getCCBillRecurringPeriodInDaysByTransaction($transaction));
                        }
                        $subscription->status = Subscription::ACTIVE_STATUS;
                        $subscription->expires_at = $expiryDate;
                        $subscription->verotel_sale_id = $saleId;
                        $subscription->save();

                        self::creditReceiverForTransaction($transaction);
                        app(PurchaseConversionService::class)->complete($transaction);
                        NotificationServiceProvider::createNewSubscriptionNotification($subscription);
                    }
                }
            }
        } catch (\Exception $exception) {
            Log::channel('payments')->error("Failed verifying Verotel subscription transaction: ".$exception->getMessage());
        }
    }

    public function verifyVerotelRenewalRecurringPayment(string $saleId, string $nextChargeOn): void {
        try {
            /** @var Subscription|null $subscription */
            $subscription = Subscription::query()->where('verotel_sale_id', $saleId)->first();
            if($subscription) {
                $verotelPaymentData = VerotelServiceProvider::getPaymentData($saleId);
                Log::channel('payments')->debug("Verotel payment data", [$verotelPaymentData]);

                if(isset($verotelPaymentData['saleResult']) && $verotelPaymentData['saleResult'] === 'APPROVED') {
                    $renewalTransaction = self::createSubscriptionRenewalTransaction($subscription, $paymentSucceeded = true, $saleId);
                    $expiryDate = Carbon::parse($nextChargeOn, 'UTC');
                    $subscription->expires_at = $expiryDate;
                    if ($subscription->status != Subscription::ACTIVE_STATUS) {
                        $subscription->status = Subscription::ACTIVE_STATUS;

                        NotificationServiceProvider::createNewSubscriptionNotification($subscription);
                    }
                    $subscription->save();
                    app(PurchaseConversionService::class)->complete($renewalTransaction, $saleId.':'.$expiryDate->toIso8601String());
                }
            }
        } catch (\Exception $exception) {
            Log::channel('payments')->error("Failed verifying Verotel subscription renewal transaction: ".$exception->getMessage());
        }
    }

    public function handleVerotelTransactionRefund(string $saleId): void {
        $transaction = Transaction::where('verotel_sale_id', $saleId)->with('subscription')->first();
        $wasApproved = $transaction->status === Transaction::APPROVED_STATUS;
        $transaction->status = Transaction::REFUNDED_STATUS;
        $transaction->save();

        if ($wasApproved) {
            self::deductMoneyFromUserForRefundedTransaction($transaction);
        }

        if ($transaction->subscription != null) {
            $transaction->subscription->status = Subscription::SUSPENDED_STATUS;
            $transaction->subscription->expires_at = Carbon::now('UTC');
            $transaction->subscription->save();
        }
    }

    public function handleVerotelSubscriptionCancelation(string $saleId): void {
        $subscription = Subscription::where('verotel_sale_id', $saleId)->first();

        if($subscription) {
            $subscription->status = Subscription::CANCELED_STATUS;
            $subscription->canceled_at = Carbon::now();

            $subscription->save();
        }
    }

    public function generateRazorPayTransaction($transaction): string {
        $token = self::generateRazorPayUniqueTransactionToken($transaction);

        return RazorPayServiceProvider::createPaymentLinkByTransaction($transaction, $token);
    }

    private function generateRazorPayUniqueTransactionToken($transaction): string
    {
        // generate unique token for transaction
        do {
            $id = Uuid::uuid4()->getHex();
        } while (Transaction::query()->where('razorpay_payment_token', $id)->first() != null);
        $transaction->razorpay_payment_token = $id;

        return $id;
    }

    public function verifyRazorpayPayment(string $paymentToken, string $paymentId): Transaction {
        $transaction = null;
        try {
            /** @var Transaction|null $transaction */
            $transaction = Transaction::query()->where('razorpay_payment_token', $paymentToken)->first();
            if($transaction && $transaction->status === Transaction::INITIATED_STATUS) {
                $razorpayPaymentData = RazorPayServiceProvider::getPaymentData($paymentId);
                Log::channel('payments')->info("Razorpay payment data", [$razorpayPaymentData->toArray()]);
                if(isset($razorpayPaymentData['status'])) {
                    if($razorpayPaymentData['status'] === 'captured') {
                        $transaction->status = Transaction::APPROVED_STATUS;
                        $transaction->razorpay_payment_id = $paymentId;

                        $transaction->save();

                        $this->creditReceiverForTransaction($transaction);
                        app(PurchaseConversionService::class)->complete($transaction);
                        NotificationServiceProvider::createTipNotificationByTransaction($transaction);
                        NotificationServiceProvider::createPPVNotificationByTransaction($transaction);
                    }

                    if($razorpayPaymentData['status'] === 'failed') {
                        $transaction->status = Transaction::DECLINED_STATUS;
                        $transaction->razorpay_payment_id = $paymentId;

                        $transaction->save();
                    }
                }
            }
        } catch (\Exception $exception) {
            Log::channel('payments')->error("Failed verifying RazorPay transaction: ".$exception->getMessage());
        }

        return $transaction;
    }

    public function handleRazorpayTransactionRefund(string $paymentId): void
    {
        $transaction = Transaction::where('razorpay_payment_id', $paymentId)->first();
        if ($transaction && $transaction->status === Transaction::APPROVED_STATUS) {
            $razorpayPaymentData = RazorPayServiceProvider::getPaymentData($paymentId);
            Log::channel('payments')->debug("Razorpay payment data", [$razorpayPaymentData->toArray()]);

            if (isset($razorpayPaymentData['status']) && $razorpayPaymentData['status'] === 'refunded') {
                $transaction->status = Transaction::REFUNDED_STATUS;
                $transaction->save();

                self::deductMoneyFromUserForRefundedTransaction($transaction);
            }
        }
    }

    public function getTaxesForCountry(?string $countryName): Collection
    {
        // "All" taxes
        $allTaxes = Tax::query()
            ->join('country_taxes', 'taxes.id', '=', 'country_taxes.tax_id')
            ->join('countries', 'country_taxes.country_id', '=', 'countries.id')
            ->where('countries.name', 'All')
            ->select('taxes.*')
            ->get()
            ->keyBy('id');

        if (!$countryName) {
            return $allTaxes->values();
        }

        /** @var Country|null $country */
        $country = Country::query()
            ->where('name', $countryName)
            ->with('taxes')
            ->first();
        if (!$country) {
            return collect($allTaxes->values());
        }

        // merge + de-dupe by id
        $countryTaxes = $country->taxes->keyBy('id');

        return $countryTaxes->union($allTaxes)->values();
    }

    /**
     * Calculate taxes using cents math
     * - Inclusive taxes define a "net base" (tax-exclusive) derived from subtotal and inclusive rates.
     * - Exclusive taxes are computed as a GROUP total (sum of rates), rounded once,
     *   then allocated across lines by largest remainder (so two 10% lines can become 1.31 + 1.30).
     * - Fixed taxes are added as cents amounts.
     *
     * Notes:
     * - Our schema stores fixed amount in `percentage` column
     */
    public function calculateTaxesQuote(float $subtotal, Collection $taxes): array
    {
        $toCents = static fn ($v) => (int) round(((float) $v) * 100);
        $fromCents = static fn (int $c) => number_format($c / 100, 2, '.', '');

        // Allocate a rounded "group total" across lines by largest remainder.
        // Inputs are numerators for each line over a common denominator (10000 for basis points).
        $allocateGroup = static function (array $numerators, int $denominator): array {
            // floors + remainders
            $floors = [];
            $remainders = [];
            $sumFloors = 0;
            $sumNumerators = 0;

            foreach ($numerators as $i => $num) {
                $sumNumerators += $num;
                $floor = intdiv($num, $denominator);
                $rem = $num % $denominator;

                $floors[$i] = $floor;
                $remainders[$i] = $rem;
                $sumFloors += $floor;
            }

            // Round the group total once (HALF_UP)
            // total = round(sumNumerators / denominator)
            $totalRounded = intdiv($sumNumerators + intdiv($denominator, 2), $denominator);

            $delta = $totalRounded - $sumFloors; // cents to distribute (can be negative)

            if ($delta === 0) {
                return $floors;
            }

            // Sort indices by remainder (desc for +delta, asc for -delta)
            $indices = array_keys($remainders);
            usort($indices, function ($a, $b) use ($remainders, $delta) {
                if ($remainders[$a] === $remainders[$b]) {
                    return $a <=> $b; // stable tie-break
                }
                return $delta > 0
                    ? ($remainders[$b] <=> $remainders[$a]) // biggest remainder first
                    : ($remainders[$a] <=> $remainders[$b]); // smallest remainder first
            });

            $sign = $delta > 0 ? 1 : -1;
            $deltaAbs = abs($delta);
            $n = count($indices);

            for ($k = 0; $k < $deltaAbs; $k++) {
                $idx = $indices[$k % $n];
                $floors[$idx] += $sign;
            }

            return $floors;
        };

        $subtotalCents = $toCents($subtotal);
        if ($subtotalCents <= 0) {
            return [
                'subtotal' => $fromCents(0),
                'taxesTotalAmount' => $fromCents(0),
                'total' => $fromCents(0),
                'netSubtotal' => $fromCents(0),
                'data' => [],
            ];
        }

        // Normalize + keep order stable
        $normalized = $taxes->map(function ($t) {
            return [
                'name' => (string) $t->name,
                'type' => (string) $t->type, // inclusive|exclusive|fixed
                'percentage' => (float) $t->percentage,
                'hidden' => $t->hidden ?? false,
            ];
        })->values()->all();

        // Convert % to basis points to avoid float drift (10% => 1000 bp)
        $toBp = static fn (float $pct): int => (int) round($pct * 100, 0, PHP_ROUND_HALF_UP);

        $inclusive = [];
        $exclusive = [];
        $fixed = [];

        foreach ($normalized as $t) {
            if ($t['type'] === 'inclusive') $inclusive[] = $t;
            elseif ($t['type'] === 'exclusive') $exclusive[] = $t;
            elseif ($t['type'] === 'fixed') $fixed[] = $t;
        }

        // 1) Compute net base (tax-exclusive) from inclusive rates
        $inclusiveBpTotal = array_sum(array_map(fn ($t) => $toBp($t['percentage']), $inclusive)); // bp
        $netBaseCents = $subtotalCents;

        if ($inclusiveBpTotal > 0) {
            // subtotal = netBase * (1 + inclusiveRate)
            // netBase = round(subtotal / (1 + rate))
            // rate = inclusiveBpTotal / 10000
            // netBase = round(subtotalCents * 10000 / (10000 + inclusiveBpTotal))
            $den = 10000 + $inclusiveBpTotal;
            $netBaseCents = intdiv(($subtotalCents * 10000) + intdiv($den, 2), $den);
        }

        $inclusiveTotalCents = $subtotalCents - $netBaseCents;

        // 2) Compute inclusive line allocation (if multiple)
        $inclusiveLineCents = [];
        if (count($inclusive) === 1) {
            $inclusiveLineCents[0] = $inclusiveTotalCents;
        } elseif (count($inclusive) > 1) {
            // Allocate inclusiveTotalCents proportional to each inclusive rate (bp)
            $nums = [];
            foreach ($inclusive as $i => $t) {
                $bp = $toBp($t['percentage']);
                // numerator in "cents * bp"
                $nums[$i] = $inclusiveTotalCents * $bp;
            }
            // denominator is sum(bp)
            $inclusiveLineCents = $allocateGroup($nums, array_sum(array_map(fn ($t) => $toBp($t['percentage']), $inclusive)));
        }

        // 3) Compute exclusive tax GROUP total then allocate across lines
        $exclusiveTotalCents = 0;
        $exclusiveLineCents = [];

        if (count($exclusive) > 0) {
            $exclusiveBpTotal = array_sum(array_map(fn ($t) => $toBp($t['percentage']), $exclusive)); // bp

            // group numerator = netBaseCents * exclusiveBpTotal
            // group total cents = round(groupNumerator / 10000)
            $groupNumerator = $netBaseCents * $exclusiveBpTotal;
            $exclusiveTotalCents = intdiv($groupNumerator + 5000, 10000);

            if (count($exclusive) === 1) {
                $exclusiveLineCents[0] = $exclusiveTotalCents;
            } else {
                // Allocate the GROUP total across lines by remainder:
                // Each line numerator = netBaseCents * lineBp (over 10000)
                $nums = [];
                foreach ($exclusive as $i => $t) {
                    $nums[$i] = $netBaseCents * $toBp($t['percentage']); // over 10000
                }
                $exclusiveLineCents = $allocateGroup($nums, 10000);
                // This allocation will sum to round(sum(nums)/10000) which equals $exclusiveTotalCents
            }
        }

        // 4) Fixed taxes (currency amounts stored in percentage column)
        $fixedTotalCents = 0;
        $fixedLineCents = [];
        foreach ($fixed as $i => $t) {
            $c = $toCents($t['percentage']);
            $fixedLineCents[$i] = $c;
            $fixedTotalCents += $c;
        }

        // 5) Build lines in original order (inclusive/exclusive/fixed interleaved)
        $lines = [];
        $taxesTotalCents = 0;

        $incIdx = 0;
        $excIdx = 0;
        $fixIdx = 0;

        foreach ($normalized as $t) {
            $taxCents = 0;
            $taxPct = null;

            if ($t['type'] === 'inclusive') {
                $taxCents = $inclusiveLineCents[$incIdx] ?? 0;
                $taxPct = $t['percentage'];
                $incIdx++;
            } elseif ($t['type'] === 'exclusive') {
                $taxCents = $exclusiveLineCents[$excIdx] ?? 0;
                $taxPct = $t['percentage'];
                $excIdx++;
            } elseif ($t['type'] === 'fixed') {
                $taxCents = $fixedLineCents[$fixIdx] ?? 0;
                $fixIdx++;
            }

            $taxesTotalCents += $taxCents;

            $lines[] = [
                'taxName' => $t['name'],
                'taxAmount' => $fromCents($taxCents),
                'taxPercentage' => $taxPct,
                'taxType' => $t['type'],
                'hidden' => $t['hidden'],
            ];
        }

        // Total charged = subtotal + exclusive + fixed
        $totalCents = $subtotalCents + $exclusiveTotalCents + $fixedTotalCents;

        return [
            'subtotal' => $fromCents($subtotalCents),
            'taxesTotalAmount' => $fromCents($taxesTotalCents),
            'total' => $fromCents($totalCents),
            'data' => $lines,
            'netSubtotal' => $fromCents($netBaseCents), // "Total excluding tax"
        ];
    }

    /**
     * Fetch taxes for country and compute quote for a subtotal.
     */
    public function quoteTaxesForCountry(?string $countryName, float $subtotal): array
    {
        $taxes = $this->getTaxesForCountry($countryName);

        return $this->calculateTaxesQuote($subtotal, $taxes);
    }

    public function quoteTaxesForTransaction(string $transactionType, ?string $countryName, float $subtotal): array
    {
        // Funding a wallet is not a purchase: taxes apply when its balance is spent.
        if ($transactionType === Transaction::DEPOSIT_TYPE) {
            return $this->calculateTaxesQuote($subtotal, collect());
        }

        return $this->quoteTaxesForCountry($countryName, $subtotal);
    }

    public function ensurePaymentIsAllowed(Transaction $transaction): void
    {
        if ($transaction->type === Transaction::POST_UNLOCK || ($transaction->type === Transaction::TIP_TYPE && $transaction->post_id)) {
            $post = Post::query()->find($transaction->post_id);
            if (!$post || !PublicationReviewService::isApproved($post) || $post->is_scheduled || ($post->expire_date && $post->is_expired)) {
                throw ValidationException::withMessages(['post_id' => __('This content is not available for payment.')]);
            }
        }

        $users = User::query()->whereIn('id', array_filter([
            $transaction->sender_user_id,
            $transaction->type === Transaction::DEPOSIT_TYPE ? null : $transaction->recipient_user_id,
        ]))->get();
        foreach ($users as $user) {
            if ($user->isBanned()) {
                throw ValidationException::withMessages(['account' => __('Payments are unavailable while an account is suspended.')]);
            }
        }

        $ownerId = match ($transaction->type) {
            Transaction::POST_UNLOCK => Post::query()->whereKey($transaction->post_id)->value('user_id'),
            Transaction::MESSAGE_UNLOCK => UserMessage::query()->whereKey($transaction->user_message_id)->value('sender_id'),
            Transaction::STREAM_ACCESS => Stream::query()->whereKey($transaction->stream_id)->value('user_id'),
            default => null,
        };
        if ($ownerId !== null && (int) $ownerId !== (int) $transaction->recipient_user_id) {
            throw ValidationException::withMessages(['recipient_user_id' => __('The payment recipient does not own this content.')]);
        }
    }

    public function resolveBaseAmountFromRequest(QuoteTaxesRequest|CreateTransactionRequest $request): float
    {
        $type = $request->get('transaction_type');

        // Tips and deposits: user chooses amount (BASE)
        if (in_array($type, [Transaction::TIP_TYPE, Transaction::CHAT_TIP_TYPE, Transaction::DEPOSIT_TYPE], true)) {
            return (float) $request->get('amount');
        }

        // Subscriptions: derive from recipient
        if (in_array($type, [
            Transaction::ONE_MONTH_SUBSCRIPTION,
            Transaction::THREE_MONTHS_SUBSCRIPTION,
            Transaction::SIX_MONTHS_SUBSCRIPTION,
            Transaction::YEARLY_SUBSCRIPTION,
        ], true)) {
            $recipientUser = User::query()->find($request->get('recipient_user_id'));
            if (!$recipientUser) return 0.0;

            return match ($type) {
                Transaction::ONE_MONTH_SUBSCRIPTION => (float) $recipientUser->profile_access_price,
                Transaction::THREE_MONTHS_SUBSCRIPTION => (float) ($recipientUser->profile_access_price_3_months * 3),
                Transaction::SIX_MONTHS_SUBSCRIPTION => (float) ($recipientUser->profile_access_price_6_months * 6),
                Transaction::YEARLY_SUBSCRIPTION => (float) ($recipientUser->profile_access_price_12_months * 12),
            };
        }

        // PPV types: derive from DB
        if ($type === Transaction::POST_UNLOCK) {
            $post = Post::query()->find($request->get('post_id'));
            return $post ? (float) $post->price : 0.0;
        }

        if ($type === Transaction::STREAM_ACCESS) {
            $stream = Stream::query()->find($request->get('stream'));
            return $stream ? (float) $stream->price : 0.0;
        }

        if ($type === Transaction::MESSAGE_UNLOCK) {
            $msg = UserMessage::query()->find($request->get('user_message_id'));
            return $msg ? (float) $msg->price : 0.0;
        }

        return 0.0;
    }

    /**
     * Validates transaction base amount against what the FE sends.
     * @param string $type
     * @param float $baseAmount
     * @param User|null $recipientUser
     * @param Transaction|null $transaction
     * @return bool
     */
    public function validateTransactionBaseAmount(
        string $type,
        float $baseAmount,
        ?User $recipientUser,
        ?Transaction $transaction = null
    ): bool
    {
        // tips/deposits
        if (in_array($type, [Transaction::TIP_TYPE, Transaction::CHAT_TIP_TYPE, Transaction::DEPOSIT_TYPE], true)) {
            return $baseAmount > 0;
        }

        if (!$recipientUser) return false;

        // Compare with DB-derived prices
        switch ($type) {
            case Transaction::ONE_MONTH_SUBSCRIPTION:
                if (!ProfileMonetizationServiceProvider::canReceiveProfileSubscriptions($recipientUser)) {
                    return false;
                }
                return (string)($baseAmount + 0) === (string)($recipientUser->profile_access_price + 0);

            case Transaction::THREE_MONTHS_SUBSCRIPTION:
                if (!ProfileMonetizationServiceProvider::canReceiveProfileSubscriptions($recipientUser)) {
                    return false;
                }
                return (string)($baseAmount + 0) === (string)(($recipientUser->profile_access_price_3_months * 3) + 0);

            case Transaction::SIX_MONTHS_SUBSCRIPTION:
                if (!ProfileMonetizationServiceProvider::canReceiveProfileSubscriptions($recipientUser)) {
                    return false;
                }
                return (string)($baseAmount + 0) === (string)(($recipientUser->profile_access_price_6_months * 6) + 0);

            case Transaction::YEARLY_SUBSCRIPTION:
                if (!ProfileMonetizationServiceProvider::canReceiveProfileSubscriptions($recipientUser)) {
                    return false;
                }
                return (string)($baseAmount + 0) === (string)(($recipientUser->profile_access_price_12_months * 12) + 0);

            case Transaction::POST_UNLOCK:
                if (!$transaction?->post_id) return false;
                $post = Post::query()->find($transaction->post_id);
                return $post && (string)($baseAmount + 0) === (string)($post->price + 0);

            case Transaction::STREAM_ACCESS:
                if (!$transaction?->stream_id) return false;
                $stream = Stream::query()->find($transaction->stream_id);
                return $stream && (string)($baseAmount + 0) === (string)($stream->price + 0);

            case Transaction::MESSAGE_UNLOCK:
                if (!$transaction?->user_message_id) return false;
                $msg = UserMessage::query()->find($transaction->user_message_id);
                return $msg && (string)($baseAmount + 0) === (string)($msg->price + 0);
        }

        return false;
    }
}
