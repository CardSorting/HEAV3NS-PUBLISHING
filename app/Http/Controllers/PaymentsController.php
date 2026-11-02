<?php

namespace App\Http\Controllers;

use App\Helpers\PaymentHelper;
use App\Http\Requests\CreateTransactionRequest;
use App\Http\Requests\QuoteTaxesRequest;
use App\Model\ManualPaymentMethod;
use App\Model\Subscription;
use App\Model\Transaction;
use App\Model\Withdrawal;
use App\Providers\InvoiceServiceProvider;
use App\Providers\NotificationServiceProvider;
use App\Providers\PaymentRequestServiceProvider;
use App\Providers\PaymentsServiceProvider;
use App\Providers\PaypalAPIServiceProvider;
use App\Providers\PostsHelperServiceProvider;
use App\Providers\RazorPayServiceProvider;
use App\Providers\VerotelServiceProvider;
use App\Providers\WithdrawalsServiceProvider;
use App\Services\ConversionTracking\PurchaseConversionService;
use App\Services\ManualPayments\ManualPaymentFields;
use App\Services\ManualPayments\ManualPaymentDocuments;
use Illuminate\Validation\ValidationException;
use App\Services\Payments\CheckoutBilling;
use App\Model\User;
use Carbon\Carbon;
use DateTime;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\Routing\ResponseFactory;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redirect;
use Stripe\StripeClient;
use Yabacon\Paystack;

class PaymentsController extends Controller
{
    protected $paymentHandler;

    /**
     * PaymentsController constructor.
     * @param PaymentHelper $paymentHandler
     */
    public function __construct(PaymentHelper $paymentHandler)
    {
        $this->paymentHandler = $paymentHandler;
    }

    public function paymentInitiateValidator(CreateTransactionRequest $request) {
        return response()->json([
            'status' => 200,
        ], 200);
    }

    /**
     * Initiates the payment based on the required provider.
     * @param CreateTransactionRequest $request
     * @return \Illuminate\Http\RedirectResponse
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function initiatePayment(CreateTransactionRequest $request)
    {
        $transactionType = $request->get('transaction_type');
        $redirectLink = null;
        // generate one time transaction
        try {
            $this->updateUserBillingDetails($request);

            $transaction = new Transaction();
            $transaction['sender_user_id'] = Auth::user()->id;
            $transaction['recipient_user_id'] = $request->get('recipient_user_id');
            $transaction['post_id'] = $request->get('post_id');
            $transaction['user_message_id'] = $request->get('user_message_id');
            $transaction['type'] = $transactionType;
            $transaction['status'] = Transaction::INITIATED_STATUS;
            $transaction['currency'] = config('app.site.currency_code');
            $transaction['payment_provider'] = $request->get('provider');
            $transaction['stream_id'] = $request->get('stream');
            $errorMessage = __('Something went wrong with this transaction. Please try again');

            $baseAmount = $this->paymentHandler->resolveBaseAmountFromRequest($request);
            $country = CheckoutBilling::collectsDetails() ? $request->input('country') : null;
            $taxQuote = $this->paymentHandler->quoteTaxesForTransaction($transactionType, $country, $baseAmount);
            // Store taxes computed by BE instead of relying on the FE
            $transaction['taxes'] = $taxQuote;
            // Set transaction amount to TOTAL (the amount being charged)
            $transaction['amount'] = (float) $taxQuote['total'];

            $recipientUser = User::query()->where('id', $transaction['recipient_user_id'])->first();
            $this->paymentHandler->ensurePaymentIsAllowed($transaction);
            if ($transaction['amount'] <= 0 || (!$recipientUser && $transactionType !== Transaction::DEPOSIT_TYPE)) {
                return $this->paymentHandler->redirectByTransaction($transaction, $errorMessage);
            }

            if (!$this->paymentHandler->validateTransactionBaseAmount($transactionType, $baseAmount, $recipientUser, $transaction)) {
                return $this->paymentHandler->redirectByTransaction($transaction, $errorMessage);
            }

            if ($transaction['payment_provider'] === Transaction::MANUAL_PROVIDER && ($method = $request->manualDepositMethod())) {
                $transaction->manual_method_id = $method->id;
            }

            app(PurchaseConversionService::class)->capture($transaction, $request);

            if (in_array($transaction['payment_provider'], [Transaction::STRIPE_PROVIDER, Transaction::OXXO_PROVIDER])) {
                $redirectLink = $this->paymentHandler->generateStripeSessionByTransaction($transaction);
                // if we cannot fetch a redirect link it means stripe session generation process failed
                if ($redirectLink == null) {
                    $transaction['status'] = Transaction::DECLINED_STATUS;
                    $transaction->save();
                    return $this->paymentHandler->redirectByTransaction($transaction, $errorMessage = __('Failed generating stripe session'));
                }
            }

            if ($transaction['payment_provider'] == Transaction::CREDIT_PROVIDER) {
                $userAvailableAmount = $this->paymentHandler->getLoggedUserAvailableAmount();
                // check if user have enough money to pay with credit for this transaction
                if ($userAvailableAmount < $transaction['amount']) {
                    $errorMessage = __("You don't have enough money to pay with credit for this transaction. Please try with another payment method");

                    return $this->paymentHandler->redirectByTransaction($transaction, $errorMessage);
                }
            }

            switch ($transactionType) {
                case Transaction::TIP_TYPE:
                case Transaction::CHAT_TIP_TYPE:
                case Transaction::STREAM_ACCESS:
                case Transaction::POST_UNLOCK:
                case Transaction::MESSAGE_UNLOCK:
                    $userId = Auth::user()->id;
                    $postId = $transaction['post_id'];
                    $streamId = $transaction['stream_id'];
                    $messageId = $transaction['user_message_id'];
                    if($recipientUser->id === $transaction['sender_user_id']) {
                        return $this->paymentHandler->redirectByTransaction(
                            $transaction,
                            $errorMessage = __('Cannot pay to yourself.')
                        );
                    }

                    if($transactionType === Transaction::POST_UNLOCK && PostsHelperServiceProvider::userPaidForPost($userId, $postId)){
                        return $this->paymentHandler->redirectByTransaction(
                            $transaction,
                            $errorMessage = __('You already unlocked this post.')
                        );
                    } elseif($transactionType === Transaction::STREAM_ACCESS && PostsHelperServiceProvider::userPaidForStream($userId, $streamId)){
                        return $this->paymentHandler->redirectByTransaction(
                            $transaction,
                            $errorMessage = __('You already paid for this streaming')
                        );
                    } elseif($transactionType === Transaction::MESSAGE_UNLOCK && PostsHelperServiceProvider::userPaidForMessage($userId, $messageId)){
                        return $this->paymentHandler->redirectByTransaction(
                            $transaction,
                            $errorMessage = __('You already paid access for this message')
                        );
                    }

                    if ($transaction['payment_provider'] == Transaction::PAYPAL_PROVIDER) {
                        $redirectLink = $this->paymentHandler->initiateOneTimePaypalTransaction($transaction);
                    } elseif ($transaction['payment_provider'] == Transaction::CREDIT_PROVIDER) {
                        $this->paymentHandler->generateOneTimeCreditTransaction($transaction);
                    } elseif ($transaction['payment_provider'] == Transaction::YOOKASSA_PROVIDER) {
                        $redirectLink = $this->paymentHandler->generateYooKassaTransaction($transaction);
                    } elseif ($transaction['payment_provider'] == Transaction::MOLLIE_PROVIDER) {
                        $redirectLink = $this->paymentHandler->generateMollieTransaction($transaction);
                    } elseif ($transaction['payment_provider'] == Transaction::FLUTTERWAVE_PROVIDER) {
                        $redirectLink = $this->paymentHandler->generateFlutterwaveTransaction($transaction);
                    } elseif ($transaction['payment_provider'] == Transaction::COINGATE_PROVIDER) {
                        $redirectLink = $this->paymentHandler->generateCoinGateTransaction($transaction);
                    } elseif ($transaction['payment_provider'] == Transaction::XENDIT_PROVIDER) {
                        $redirectLink = $this->paymentHandler->generateXenditTransaction($transaction);
                    } elseif ($transaction['payment_provider'] == Transaction::PADDLE_PROVIDER) {
                        $redirectLink = $this->paymentHandler->generatePaddleTransaction($transaction);
                    } elseif ($transaction['payment_provider'] == Transaction::CRYPTOCOM_PROVIDER) {
                        $redirectLink = $this->paymentHandler->generateCryptoComTransaction($transaction);
                    } elseif($transaction['payment_provider'] == Transaction::NOWPAYMENTS_PROVIDER) {
                        $redirectLink = $this->paymentHandler->generateNowPaymentsTransaction($transaction);
                    } elseif($transaction['payment_provider'] == Transaction::CCBILL_PROVIDER) {
                        $redirectLink = $this->paymentHandler->generateCCBillOneTimePaymentTransaction($transaction);
                    } elseif($transaction['payment_provider'] == Transaction::PAYSTACK_PROVIDER) {
                        $redirectLink = $this->paymentHandler->generatePaystackTransaction($transaction, Auth::user()->email);
                    } elseif($transaction['payment_provider'] == Transaction::MERCADO_PROVIDER) {
                        $redirectLink = $this->paymentHandler->generateMercadoTransaction($transaction);
                    } elseif($transaction['payment_provider'] == Transaction::VEROTEL_PROVIDER) {
                        $redirectLink = $this->paymentHandler->generateVerotelTransaction($transaction);
                    } elseif($transaction['payment_provider'] == Transaction::RAZORPAY_PROVIDER) {
                        $redirectLink = $this->paymentHandler->generateRazorPayTransaction($transaction);
                    }
                    break;
                case Transaction::DEPOSIT_TYPE:
                    $transaction['recipient_user_id'] = Auth::user()->id;
                    if ($transaction['payment_provider'] == Transaction::PAYPAL_PROVIDER) {
                        $redirectLink = $this->paymentHandler->initiateOneTimePaypalTransaction($transaction);
                    } elseif ($transaction['payment_provider'] == Transaction::YOOKASSA_PROVIDER) {
                        $redirectLink = $this->paymentHandler->generateYooKassaTransaction($transaction);
                    } elseif ($transaction['payment_provider'] == Transaction::MOLLIE_PROVIDER) {
                        $redirectLink = $this->paymentHandler->generateMollieTransaction($transaction);
                    } elseif ($transaction['payment_provider'] == Transaction::FLUTTERWAVE_PROVIDER) {
                        $redirectLink = $this->paymentHandler->generateFlutterwaveTransaction($transaction);
                    } elseif ($transaction['payment_provider'] == Transaction::COINGATE_PROVIDER) {
                        $redirectLink = $this->paymentHandler->generateCoinGateTransaction($transaction);
                    } elseif ($transaction['payment_provider'] == Transaction::XENDIT_PROVIDER) {
                        $redirectLink = $this->paymentHandler->generateXenditTransaction($transaction);
                    } elseif ($transaction['payment_provider'] == Transaction::PADDLE_PROVIDER) {
                        $redirectLink = $this->paymentHandler->generatePaddleTransaction($transaction);
                    } elseif ($transaction['payment_provider'] == Transaction::CRYPTOCOM_PROVIDER) {
                        $redirectLink = $this->paymentHandler->generateCryptoComTransaction($transaction);
                    } elseif($transaction['payment_provider'] == Transaction::NOWPAYMENTS_PROVIDER) {
                        $redirectLink = $this->paymentHandler->generateNowPaymentsTransaction($transaction);
                    } elseif($transaction['payment_provider'] == Transaction::CCBILL_PROVIDER) {
                        $redirectLink = $this->paymentHandler->generateCCBillOneTimePaymentTransaction($transaction);
                    } elseif($transaction['payment_provider'] == Transaction::PAYSTACK_PROVIDER) {
                        $redirectLink = $this->paymentHandler->generatePaystackTransaction($transaction, Auth::user()->email);
                    } elseif($transaction['payment_provider'] == Transaction::MERCADO_PROVIDER) {
                        $redirectLink = $this->paymentHandler->generateMercadoTransaction($transaction);
                    } elseif($transaction['payment_provider'] == Transaction::VEROTEL_PROVIDER) {
                        $redirectLink = $this->paymentHandler->generateVerotelTransaction($transaction);
                    } elseif($transaction['payment_provider'] == Transaction::RAZORPAY_PROVIDER) {
                        $redirectLink = $this->paymentHandler->generateRazorPayTransaction($transaction);
                    }
                    break;
                case Transaction::ONE_MONTH_SUBSCRIPTION:
                case Transaction::THREE_MONTHS_SUBSCRIPTION:
                case Transaction::SIX_MONTHS_SUBSCRIPTION:
                case Transaction::YEARLY_SUBSCRIPTION:
                    if($recipientUser->id === $transaction['sender_user_id']) {
                        return $this->paymentHandler->redirectByTransaction(
                            $transaction,
                            $errorMessage = __('Cannot subscribe to yourself.')
                        );
                    }

                    if (PostsHelperServiceProvider::hasActiveSub($transaction['sender_user_id'], $transaction['recipient_user_id'])) {
                        $errorMessage = __('You already have an active subscription for this user.');

                        return $this->paymentHandler->redirectByTransaction($transaction, $errorMessage);
                    }

                    if ($transaction['payment_provider'] == Transaction::PAYPAL_PROVIDER) {
                        $redirectLink = $this->paymentHandler->generatePaypalSubscriptionByTransaction($transaction);
                    } elseif ($transaction['payment_provider'] == Transaction::STRIPE_PROVIDER) {
                        $this->paymentHandler->generateStripeSubscriptionByTransaction($transaction);
                    } elseif ($transaction['payment_provider'] == Transaction::CREDIT_PROVIDER) {
                        $this->paymentHandler->generateCreditSubscriptionByTransaction($transaction);
                    } elseif ($transaction['payment_provider'] == Transaction::CCBILL_PROVIDER) {
                        $redirectLink = $this->paymentHandler->generateCCBillSubscriptionPayment($transaction);
                    } elseif($transaction['payment_provider'] == Transaction::VEROTEL_PROVIDER) {
                        $redirectLink = $this->paymentHandler->generateVerotelSubscriptionPayment($transaction);
                    }
                    break;
                default:
                    return $this->paymentHandler->redirectByTransaction($transaction);
            }
            if ($transaction['payment_provider'] === Transaction::MANUAL_PROVIDER) {
                DB::transaction(function () use ($transaction, $request) {
                    User::query()->whereKey(Auth::user()->id)->lockForUpdate()->firstOrFail();
                    $method = $transaction->manual_method_id
                        ? ManualPaymentMethod::query()->whereKey($transaction->manual_method_id)
                            ->where('direction', ManualPaymentMethod::DEPOSIT)->where('enabled', true)->lockForUpdate()->first()
                        : null;
                    if (!getSetting('payments.allow_manual_payments') || !$method) {
                        throw ValidationException::withMessages(['manual_method_id' => __('This deposit method is unavailable.')]);
                    }
                    $settings = $method->submission_settings ?? [];
                    $submittedFields = app(ManualPaymentDocuments::class)->validateSubmission(
                        $method,
                        $request->input('submitted_fields', []) ?? [],
                        (int) Auth::user()->id,
                        true
                    );
                    if (ManualPaymentDocuments::countSubmittedFiles($method, $submittedFields) < (int) ($settings['minimum_attachments'] ?? 0)) {
                        throw ValidationException::withMessages(['submitted_fields' => __('Please attach the required payment proof.')]);
                    }
                    $detailsSnapshot = [
                        'method_name' => $method->name,
                        'instructions' => $method->instructions,
                        'receiving_details' => $method->receiving_details ?? [],
                        'request_fields' => ManualPaymentFields::snapshotItems($method->request_fields ?? [], $submittedFields),
                    ];
                    $transaction->save();
                    $paymentRequest = PaymentRequestServiceProvider::createDepositPaymentRequestByTransaction(
                        $transaction,
                        $submittedFields,
                        $detailsSnapshot
                    );
                    app(ManualPaymentDocuments::class)->bind($method, $submittedFields, (int) Auth::user()->id, $paymentRequest);
                }, 3);
            } else {
                $transaction->save();
            }

            if ($transaction->getAttribute('payment_provider') === Transaction::CREDIT_PROVIDER
                && $transaction->getAttribute('status') === Transaction::APPROVED_STATUS) {
                $this->paymentHandler->creditReceiverForTransaction($transaction);
                $this->paymentHandler->deductMoneyFromUserWalletForCreditTransaction($transaction, Auth::user()->wallet);
                app(PurchaseConversionService::class)->complete($transaction);
                $this->paymentHandler->createNewTipNotificationForCreditTransaction($transaction);
                NotificationServiceProvider::createPPVNotificationByTransaction($transaction);
            }

            if ($transaction != null) {
                try {
                    $invoice = InvoiceServiceProvider::createInvoiceByTransaction($transaction);
                    if ($invoice != null) {
                        $transaction->invoice_id = $invoice->id;
                        $transaction->save();
                    }
                } catch (\Exception $exception) {
                    Log::channel('payments')->error("Failed generating invoice for transaction: ".$transaction->id." error: ".$exception->getMessage());
                }
            }
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (\Exception $exception) {
            Log::channel('payments')->error("Payment failed -> error message: ".$exception->getMessage());
            Log::channel('payments')->error("Payment failed", [$exception->getTraceAsString()]);

            return Redirect::route('feed')
                ->with('error', __('Payment failed.'));
        }

        // Url generated successfully
        if (!empty($redirectLink) && in_array($transaction['payment_provider'], Transaction::ALLOWED_PAYMENT_PROVIDERS)) {
            // redirect on payment provider checkout page
            return Redirect::away($redirectLink);
        }
        return $this->paymentHandler->redirectByTransaction($transaction);
    }

    /**
     * Handles the deposit request response.
     *
     * @param Request $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function executePaypalPayment(Request $request)
    {
        // Checking for valid request
        $paypalOneTimePaymentToken = $request->get('token');
        $paypalSubscriptionPaymentToken = $request->get('ba_token');
        if (empty($paypalOneTimePaymentToken) && empty($paypalSubscriptionPaymentToken)) {
            return Redirect::route('my.settings', ['type' => 'wallet'])
                ->with('error', __('Looks like the payment process has been cancelled.')); // warning
        }

        // fetch the token in case we have a ba_token available (subs payments), fallback to token (one time payments)
        $token = $paypalSubscriptionPaymentToken ?: $paypalOneTimePaymentToken;

        // find PayPal transaction and update it
        $transaction = Transaction::query()->where(['paypal_transaction_token' => $token])->first();
        if ($transaction != null && $transaction->type != null) {
            if ($this->paymentHandler->isSubscriptionPayment($transaction->type)) {
                if ($transaction->subscription_id != null) {
                    $transaction = $this->paymentHandler->executePaypalSubscriptionPayment($transaction);
                }
            } else {
                $transaction = $this->paymentHandler->capturePaymentForOrder($transaction);
            }
        }

        return $this->paymentHandler->redirectByTransaction($transaction);
    }

    /**
     * Stripe payment confirmation endpoint / webhook.
     */
    public function stripePaymentsHook()
    {
        if (app()->bound('debugbar')) {
            app('debugbar')->disable();
        }

        $endpoint_secret = getSetting('payments.stripe_webhooks_secret');
        $payload = @file_get_contents('php://input');
        if (isset($_SERVER['HTTP_STRIPE_SIGNATURE'])) {
            $sig_header = $_SERVER['HTTP_STRIPE_SIGNATURE'];
        } else {
            // Invalid payload
            http_response_code(400);
            exit();
        }

        $event = null;
        try {
            $event = \Stripe\Webhook::constructEvent(
                $payload,
                $sig_header,
                $endpoint_secret
            );
        } catch (\UnexpectedValueException $e) {
            // Invalid payload
            http_response_code(400);
            exit();
        } catch (\Stripe\Exception\SignatureVerificationException $e) {
            // Invalid signature
            http_response_code(400);
            exit();
        }
        Log::channel('payments')->info('Stripe payload received. Proceeding with completing the payment & fulfill the order.');
        Log::channel('payments')->debug($event);
        $eventObject = data_get($event->toArray(), 'data.object', []);
        $eventObject = is_array($eventObject) ? $eventObject : [];
        $eventObjectId = data_get($eventObject, 'id');
        $eventObjectId = is_string($eventObjectId) ? $eventObjectId : null;
        $paymentIntent = data_get($eventObject, 'payment_intent');
        $paymentIntent = is_string($paymentIntent) ? $paymentIntent : null;

        try {
            if ($event->type === 'checkout.session.completed') {
            // Payment is successful and the subscription is created.
            $sessionId = $eventObjectId;
            if ($sessionId != null) {
                // don't update oxxo transactions here
                $oxxoTransaction = Transaction::query()->where(['stripe_session_id' => $sessionId, 'payment_provider' => Transaction::OXXO_PROVIDER])->first();
                if(!$oxxoTransaction) {
                    $this->paymentHandler->updateTransactionByStripeSessionId($sessionId);
                }
            }
            // Occurs whenever a customer's subscription ends.
            } elseif ($event->type === 'customer.subscription.deleted' && $eventObjectId != null) {
                $subscription = Subscription::query()->where('stripe_subscription_id', $eventObjectId)->first();
                if ($subscription != null) {
                    $subscription->status = Subscription::CANCELED_STATUS;

                    $subscription->update();
                }
            } elseif (($event->type === 'invoice.paid' || $event->type === 'invoice.payment_failed') && $eventObjectId != null) {
                $paymentSucceeded = $event->type === 'invoice.paid' ? true : false;
                $stripe = new StripeClient(getSetting('payments.stripe_secret_key'));
                $stripeInvoice = $stripe->invoices->retrieve($eventObjectId);
                if ($stripeInvoice != null && $stripeInvoice->subscription) {
                    $stripeSub = $stripe->subscriptions->retrieve($stripeInvoice->subscription);
                    if ($stripeSub != null && $stripeSub->id != null) {
                        $subscription = Subscription::query()->where('stripe_subscription_id', $stripeSub->id)->first();
                        if ($subscription != null && isset($subscription->expires_at) && $subscription->expires_at < new DateTime()) {
                            $renewalTransaction = $this->paymentHandler->createSubscriptionRenewalTransaction($subscription, $paymentSucceeded, $eventObjectId);
                            // update subscription expire date
                            if ($paymentSucceeded) {
                                $subscription->status = Subscription::ACTIVE_STATUS;
                                $subscription->expires_at = Carbon::createFromTimestamp((int) $stripeSub->current_period_end, date_default_timezone_get());
                            } else {
                                if ($subscription->expires_at <= new DateTime()) {
                                    $subscription->status = Subscription::EXPIRED_STATUS;
                                } else {
                                    $subscription->status = Subscription::FAILED_STATUS;
                                }
                            }
                            $subscription->save();
                            if ($paymentSucceeded) {
                                app(PurchaseConversionService::class)->complete($renewalTransaction);
                            }
                        }
                    }
                }
            } elseif ($event->type === 'charge.refunded' && $paymentIntent != null) {
                $transaction = Transaction::query()->where('stripe_transaction_id', $paymentIntent)->with('subscription')->first();
                if ($transaction) {
                    $wasApproved = $transaction->status === Transaction::APPROVED_STATUS;
                    $transaction->status = Transaction::REFUNDED_STATUS;
                    $transaction->save();

                    if($wasApproved){
                        $this->paymentHandler->deductMoneyFromUserForRefundedTransaction($transaction);
                    }

                    if($transaction->subscription != null){
                        $transaction->subscription->status = Subscription::SUSPENDED_STATUS;
                        $transaction->subscription->expires_at = Carbon::now('UTC');
                        $transaction->subscription->save();
                    }
                }
            // handles oxxo (or other stripe payment providers) related hooks
            } elseif(($event->type === 'checkout.session.async_payment_succeeded' || $event->type === 'checkout.session.async_payment_failed') && $eventObjectId != null) {
                $this->paymentHandler->updateTransactionByStripeSessionId($eventObjectId);
            }
        } catch (\Exception $exception) {
            Log::channel('payments')->error($exception->getMessage());
        }

        http_response_code(200);
    }

    /**
     * Gets stripe transaction status and redirects.
     *
     * @param Request $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function getStripePaymentStatus(Request $request)
    {
        $transaction = $this->paymentHandler->updateTransactionByStripeSessionId($request->get('session_id'));
        NotificationServiceProvider::createTipNotificationByTransaction($transaction);
        NotificationServiceProvider::createPPVNotificationByTransaction($transaction);

        return $this->paymentHandler->redirectByTransaction($transaction);
    }

    /**
     * PayPal handling webhook method.
     *
     * @param Request $request
     */
    public function paypalPaymentsHook(Request $request)
    {
        try {
            $webhookContent = json_decode($request->getContent(), true);
            $eventType = $webhookContent['event_type'];
            $resourceContent = $webhookContent['resource'];

            Log::channel('payments')->info('Paypal payload received. Proceeding with completing the payment & fulfill the order.');
            Log::channel('payments')->debug($webhookContent);

            // if webhooks id is provided by the admin
            // we'll verify the PayPal signature to make sure this call is made from their side
            if(getSetting('payments.paypal_webhook_id')) {
                if(!PaypalAPIServiceProvider::verifyWebhookSignature($request)) {
                    Log::channel('payments')->error("PayPal webhook signature verification failed!");

                    http_response_code(400);

                    return;
                }
            }

            switch ($eventType) {
                case 'PAYMENT.SALE.COMPLETED':
                    // handle recurring payments (one month subscriptions)
                    if (array_key_exists('billing_agreement_id', $resourceContent) && !empty($resourceContent['billing_agreement_id'])) {
                        $agreementId = $resourceContent['billing_agreement_id'];
                        $this->paymentHandler->verifyPaypalSubscriptionPayment($agreementId, $resourceContent['id']);
                    }
                    break;
                case 'BILLING.SUBSCRIPTION.EXPIRED':
                case 'BILLING.SUBSCRIPTION.CANCELLED':
                case 'BILLING.SUBSCRIPTION.SUSPENDED':
                    if (isset($resourceContent['id']) && !empty($resourceContent['id']) && isset($resourceContent['status']) && !empty($resourceContent['status'])) {
                        Log::channel('payments')->debug($webhookContent);

                        $subStatus = $resourceContent['status'];
                        $agreementId = $resourceContent['id'];
                        // find a subscription by this paypal agreement id
                        $subscription = Subscription::query()->where('paypal_agreement_id', $agreementId)->first();
                        if ($subscription != null) {
                            if ($subStatus == 'CANCELLED') {
                                $subscription->status = Subscription::CANCELED_STATUS;
                                $subscription->canceled_at = Carbon::now();
                            } elseif ($subStatus == 'SUSPENDED') {
                                $subscription->status = Subscription::SUSPENDED_STATUS;
                            } elseif ($subStatus == 'EXPIRED') {
                                $subscription->status = Subscription::EXPIRED_STATUS;
                            }

                            $subscription->save();
                        }
                    }
                    break;
                case 'PAYMENT.SALE.REFUNDED':
                    if (array_key_exists('parent_payment', $resourceContent) && !empty($resourceContent['parent_payment'])) {
                        $this->paymentHandler->handlePaypalTransactionRefund($resourceContent['parent_payment']);
                    }
                    break;
                case 'PAYMENT.CAPTURE.REFUNDED':
                    $paypalTransactionId = $this->paymentHandler
                        ->getOriginalPaymentIdFromResourceForRefundedTransaction($resourceContent);
                    if($paypalTransactionId) {
                        $this->paymentHandler->handlePaypalTransactionRefund($paypalTransactionId);
                    }
                    break;
                case 'CHECKOUT.ORDER.APPROVED':
                    if (isset($resourceContent['id'])) {
                        $paypalOrderId = $resourceContent['id'];
                        $transaction = Transaction::query()
                            ->where([
                                'paypal_transaction_token' => $paypalOrderId,
                                'status' => Transaction::INITIATED_STATUS,
                            ])
                            ->first();
                        if ($transaction) {
                            $this->paymentHandler->capturePaymentForOrder($transaction);
                        }
                    }
                    break;
            }
        } catch (\Exception $exception) {
            Log::channel('payments')->error($exception->getMessage());
        }

        http_response_code(200);
    }

    /**
     * Method used for saving user billing details.
     *
     * @param $request
     */
    public function updateUserBillingDetails($request)
    {
        if (!CheckoutBilling::collectsDetails()) {
            return;
        }

        $firstName = $request->input('first_name');
        $lastName = $request->input('last_name');
        $billingAddress = $request->input('billing_address');
        $country = $request->input('country');
        $city = $request->input('city');
        $state = $request->input('state');
        $postcode = $request->input('postcode');

        // update user billing details if they changed
        if ($firstName != null || $lastName != null || $billingAddress != null) {
            $loggedUser = Auth::user();

            if ($loggedUser != null) {
                $updateData = [];
                if ($firstName != null && $firstName != $loggedUser->first_name) {
                    $updateData['first_name'] = $firstName;
                }

                if ($lastName != null && $lastName != $loggedUser->last_name) {
                    $updateData['last_name'] = $lastName;
                }

                if ($billingAddress != null && $billingAddress != $loggedUser->billing_address) {
                    $updateData['billing_address'] = $billingAddress;
                }

                if ($country != null && $country != $loggedUser->country) {
                    $updateData['country'] = $country;
                }

                if ($state != null && $state != $loggedUser->state) {
                    $updateData['state'] = $state;
                }

                if ($city != null && $city != $loggedUser->city) {
                    $updateData['city'] = $city;

                }

                if ($postcode != null && $postcode != $loggedUser->postcode) {
                    $updateData['postcode'] = $postcode;

                }
                if(!empty($updateData)) {
                    $loggedUser->update($updateData);
                }
            }
        }
    }

    /**
     * Handles NowPayments payment redirect.
     * @param Request $request
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function checkAndUpdateNowPaymentsTransaction(Request $request)
    {
        $nowPaymentsTransactionToken = $request->get('orderId');
        $transaction = null;
        if($nowPaymentsTransactionToken) {
            $transaction = Transaction::query()->where('nowpayments_order_id', $nowPaymentsTransactionToken)->first();
        }

        return $this->paymentHandler->redirectByTransaction($transaction);
    }

    /**
     * Process NowPayments IPN hooks.
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function nowPaymentsHook(Request $request) {
        if(!getSetting('payments.nowpayments_ipn_secret_key')){
            Log::channel('payments')->info("NowPayments hook error: missing IPN secret key");
            return response()->json([
                'status' => 400,
            ], 400);
        }

        try{
            if (isset($_SERVER['HTTP_X_NOWPAYMENTS_SIG']) && !empty($_SERVER['HTTP_X_NOWPAYMENTS_SIG'])) {
                $received_hmac = $_SERVER['HTTP_X_NOWPAYMENTS_SIG'];
                $request_json = $request->getContent();
                Log::channel('payments')->info("NowPayments hook received: ", [$request_json]);
                Log::channel('payments')->info("NowPayments hook signature received: ", [$received_hmac]);

                if (!empty($request_json)) {
                    $hmac = hash_hmac("sha512", $request_json, trim(getSetting('payments.nowpayments_ipn_secret_key')));
                    Log::channel('payments')->info("Calculated signature: ", [$received_hmac]);
                    if ($hmac == $received_hmac) {
                        $payload = json_decode($request_json, true);
                        Log::channel('payments')->info("NowPayments hook payload: ", [$payload]);
                        if(isset($payload['order_id']) && isset($payload['payment_status']) && isset($payload['payment_id'])) {
                            $transaction = Transaction::query()->where('nowpayments_order_id', $payload['order_id'])->with('receiver')->first();
                            if($transaction){
                                $paymentStatus = (string) $payload['payment_status'];
                                $paymentApproved = false;

                                if(in_array($transaction->status, [Transaction::INITIATED_STATUS, Transaction::PENDING_STATUS, Transaction::PARTIALLY_PAID_STATUS], true)){
                                    // payment approved
                                    if($paymentStatus === 'finished') {
                                        $transaction->status = Transaction::APPROVED_STATUS;
                                        $paymentApproved = true;
                                        $this->paymentHandler->creditReceiverForTransaction($transaction);
                                        NotificationServiceProvider::createTipNotificationByTransaction($transaction);
                                        NotificationServiceProvider::sendApprovedDepositTransactionEmailNotification($transaction);
                                        NotificationServiceProvider::createPPVNotificationByTransaction($transaction);
                                        // payment pending
                                    } elseif ($transaction->status !== Transaction::PENDING_STATUS && in_array($paymentStatus, ['waiting', 'confirming', 'sending'], true)) {
                                        $transaction->nowpayments_payment_id = $payload['payment_id'];
                                        $transaction->status = Transaction::PENDING_STATUS;
                                        // payment partially paid
                                    } elseif ($paymentStatus === 'partially_paid') {
                                        $transaction->status = Transaction::PARTIALLY_PAID_STATUS;
                                        if ($transaction->isDirty('status')) {
                                            NotificationServiceProvider::sendNowPaymentsPartiallyPaidTransactionEmailNotification($transaction);
                                        }
                                        // payment expired or failed
                                    } elseif (in_array($paymentStatus, ['expired', 'failed'], true)) {
                                        $transaction->status = Transaction::DECLINED_STATUS;
                                    }
                                    $transaction->save();
                                    if ($paymentApproved) {
                                        app(PurchaseConversionService::class)->complete($transaction, (string) $payload['payment_id']);
                                    }
                                    // handle refund
                                } elseif($transaction->status === Transaction::APPROVED_STATUS && $paymentStatus === 'refunded') {
                                    $transaction->status = Transaction::REFUNDED_STATUS;
                                    $transaction->save();
                                    $this->paymentHandler->deductMoneyFromUserForRefundedTransaction($transaction);
                                }
                            }
                        }

                        return response()->json([
                            'status' => 200,
                        ]);
                    } else {
                        Log::channel('payments')->info('NowPayments HMAC signature does not match');
                    }
                } else {
                    Log::channel('payments')->info('NowPayments Error reading POST data');
                }
            } else {
                Log::channel('payments')->info('NowPayments No HMAC signature sent.');
            }
        } catch (\Exception $exception){
            Log::channel('payments')->error("NowPayments hook error: ", [$exception->getMessage()]);
        }

        return response()->json([
            'status' => 400,
        ], 400);
    }

    /**
     * @param Request $request
     * @return \Illuminate\Http\RedirectResponse
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function processCCBillTransaction(Request $request)
    {
        $paymentToken = $request->get('token');
        $transaction = null;
        if($paymentToken) {
            $transaction = Transaction::query()->where('ccbill_payment_token', $paymentToken)->first();
        }

        return $this->paymentHandler->redirectByTransaction($transaction);
    }

    /**
     * @param Request $request
     * @throws \Exception
     */
    public function ccBillHook(Request $request)
    {
        $ccBillAccountNumber = $request->get('clientAccnum');
        $ccBillSubAccountNumber = $request->get('clientSubacc');
        $eventType = $request->get('eventType');

        try {
            // check if this webhook comes with the right ccbill account numbers
            if ($ccBillAccountNumber === getSetting('payments.ccbill_account_number')
                && ($ccBillSubAccountNumber === getSetting('payments.ccbill_subaccount_number_recurring')
                    || $ccBillSubAccountNumber === getSetting('payments.ccbill_subaccount_number_one_time'))) {
                $content = $request->getContent();
                // handles possible UTF8 incorrectly encoded characters coming from CCBill
                $utfEncodedContent = mb_convert_encoding($content, 'UTF-8', 'UTF-8');
                $eventBody = json_decode($utfEncodedContent, true, 512, JSON_THROW_ON_ERROR);
                Log::channel('payments')->info('CCBill hook received eventType: '.$eventType);
                Log::channel('payments')->info('CCBill hook received: ', [$eventBody]);

                // handle payment success or failure
                if (isset($eventBody['X-token']) && in_array($eventType, ['NewSaleSuccess', 'NewSaleFailure'], true)) {
                    $transaction = Transaction::where('ccbill_payment_token', $eventBody['X-token'])->with('subscription')->first();
                    if ($transaction) {
                        $subscriptionId = isset($eventBody['subscriptionId']) ? $eventBody['subscriptionId'] : null;
                        $saleSuccess = $eventType === 'NewSaleSuccess' ? true : false;
                        $transaction->ccbill_transaction_id = isset($eventBody['transactionId']) ? $eventBody['transactionId'] : null;
                        $transaction->ccbill_subscription_id = $subscriptionId;
                        $transaction->status = $saleSuccess ? Transaction::APPROVED_STATUS : Transaction::DECLINED_STATUS;
                        $transaction->save();

                        if($this->paymentHandler->isSubscriptionPayment($transaction->type) && $transaction->subscription) {
                            $subscription = $transaction->subscription;
                            $subscription->ccbill_subscription_id = $subscriptionId;
                            if($saleSuccess) {
                                $expiresDate = Carbon::now('UTC')->addDays((int) $this->paymentHandler->getCCBillRecurringPeriodInDaysByTransaction($transaction));
                                if ($subscription->status != Subscription::ACTIVE_STATUS) {
                                    $subscription->status = Subscription::ACTIVE_STATUS;
                                    $subscription->expires_at = $expiresDate;

                                    NotificationServiceProvider::createNewSubscriptionNotification($subscription);
                                } else {
                                    $subscription->expires_at = $expiresDate;
                                }

                            } else {
                                $subscription->status = Subscription::FAILED_STATUS;
                            }
                            $subscription->save();
                        }

                        if ($transaction->status == Transaction::APPROVED_STATUS) {
                            $this->paymentHandler->creditReceiverForTransaction($transaction);
                            app(PurchaseConversionService::class)->complete($transaction);
                            NotificationServiceProvider::createTipNotificationByTransaction($transaction);
                            NotificationServiceProvider::createPPVNotificationByTransaction($transaction);
                        }
                    }
                    // handle payment reversals
                } elseif(isset($eventBody['transactionId']) && in_array($eventType, ['Refund', 'Chargeback', 'Return', 'Void'], true)) {
                    $this->processCCBillReversal((string) $eventBody['transactionId']);
                    // handle renewal success / failure, cancellation or expiration
                } elseif (isset($eventBody['subscriptionId']) && in_array($eventType, ['RenewalSuccess', 'RenewalFailure', 'Cancellation', 'Expiration'], true)) {
                    $subscription = Subscription::where('ccbill_subscription_id', $eventBody['subscriptionId'])->first();
                    if ($subscription) {
                        if ($eventType === 'RenewalSuccess') {
                            $this->processCCBillRenewalSuccess($subscription, $eventBody);
                        } elseif ($eventType === 'RenewalFailure') {
                            $subscription->status = Subscription::SUSPENDED_STATUS;
                        } elseif ($eventType === 'Cancellation') {
                            $subscription->status = Subscription::CANCELED_STATUS;
                            $subscription->canceled_at = Carbon::now();
                        } elseif ($eventType === 'Expiration') {
                            $subscription->status = Subscription::EXPIRED_STATUS;
                        }
                        if ($eventType !== 'RenewalSuccess') {
                            $subscription->save();
                        }
                    }
                }
            }
        } catch (\Exception $exception) {
            Log::channel('payments')->error('CCBill hook error:', [$exception->getMessage()]);
        }
    }

    /**
     * Reverse a CCBill transaction once, even when CCBill retries the webhook.
     */
    private function processCCBillReversal(string $transactionId): void
    {
        $transactionId = trim($transactionId);
        if ($transactionId === '') {
            return;
        }

        DB::transaction(function () use ($transactionId) {
            $transaction = Transaction::query()
                ->where('payment_provider', Transaction::CCBILL_PROVIDER)
                ->where('ccbill_transaction_id', $transactionId)
                ->lockForUpdate()
                ->first();

            if (!$transaction) {
                return;
            }

            $wasApproved = $transaction->status === Transaction::APPROVED_STATUS;
            if ($transaction->status !== Transaction::REFUNDED_STATUS) {
                $transaction->status = Transaction::REFUNDED_STATUS;
                $transaction->save();
            }

            if ($wasApproved) {
                $this->paymentHandler->deductMoneyFromUserForRefundedTransaction($transaction);
            }

            if ($transaction->subscription_id != null) {
                $subscription = Subscription::query()
                    ->whereKey($transaction->subscription_id)
                    ->lockForUpdate()
                    ->first();

                if ($subscription) {
                    $subscription->status = Subscription::SUSPENDED_STATUS;
                    $subscription->expires_at = Carbon::now('UTC');
                    $subscription->save();
                }
            }
        });
    }

    /**
     * Store the individual CCBill renewal payment and make webhook retries idempotent.
     */
    private function processCCBillRenewalSuccess(Subscription $subscription, array $eventBody): void
    {
        $transactionId = isset($eventBody['transactionId']) && is_scalar($eventBody['transactionId'])
            ? trim((string) $eventBody['transactionId'])
            : '';

        if ($transactionId === '') {
            Log::channel('payments')->warning('CCBill renewal success missing transactionId', [$eventBody]);
            return;
        }

        $nextRenewalDate = $eventBody['nextRenewalDate'] ?? null;
        $reactivated = false;

        $renewalTransaction = DB::transaction(function () use ($subscription, $transactionId, $nextRenewalDate, &$reactivated) {
            $subscription = Subscription::query()->whereKey($subscription->id)->lockForUpdate()->first();
            if (!$subscription) {
                return null;
            }

            $renewalTransaction = Transaction::query()
                ->where('payment_provider', Transaction::CCBILL_PROVIDER)
                ->where('subscription_id', $subscription->id)
                ->where('ccbill_transaction_id', $transactionId)
                ->first();

            if (!$renewalTransaction) {
                $renewalTransaction = $this->paymentHandler->createSubscriptionRenewalTransaction(
                    $subscription,
                    true,
                    $transactionId
                );
            }

            if ($nextRenewalDate) {
                $subscription->expires_at = Carbon::parse((string) $nextRenewalDate, 'UTC');
            } else {
                Log::channel('payments')->warning('CCBill renewal success missing nextRenewalDate');
            }

            $reactivated = $subscription->status !== Subscription::ACTIVE_STATUS;
            $subscription->status = Subscription::ACTIVE_STATUS;
            $subscription->save();

            return $renewalTransaction;
        });

        if (!$renewalTransaction) {
            return;
        }

        if ($reactivated) {
            NotificationServiceProvider::createNewSubscriptionNotification($subscription->fresh());
        }

        app(PurchaseConversionService::class)->complete($renewalTransaction, $transactionId);
    }

    /**
     * Verifies paystack payment by calling their API and updating transaction in our side.
     * @param Request $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function verifyPaystackTransaction(Request $request) {
        $reference = $request->get('reference');
        $transaction = $this->paymentHandler->verifyPaystackTransaction($reference);

        return $this->paymentHandler->redirectByTransaction($transaction);
    }

    /**
     * @param Request $request
     * @return void
     */
    public function paystackHook(Request $request) {
        // Retrieve the request's body and parse it as JSON
        $event = Paystack\Event::capture();

        /* Verify that the signature matches one of your keys*/
        $my_keys = [
            'live'=>getSetting('payments.paystack_secret_key'),
            'test'=>getSetting('payments.paystack_secret_key'),
        ];
        $owner = $event->discoverOwner($my_keys);
        if(!$owner){
            return;
        }
        Log::channel('payments')->debug('Paystack hook received: ', [$event]);

        switch($event->obj->event){
            // charge.success
            case 'charge.success':
                if('success' === $event->obj->data->status){
                    $this->paymentHandler->verifyPaystackTransaction($event->obj->data->reference);
                }
                break;
            case 'refund.processed':
                if($event->obj->data->transaction_reference) {
                    $transaction = Transaction::where('paystack_payment_token', $event->obj->data->transaction_reference)->first();
                    if($transaction->status === Transaction::APPROVED_STATUS){
                        $transaction->status = Transaction::REFUNDED_STATUS;
                        $transaction->save();
                        $this->paymentHandler->deductMoneyFromUserForRefundedTransaction($transaction);
                    }
                }

                break;
        }

        http_response_code(200);
    }

    /**
     * Verifies MercadoPago transaction.
     * @param Request $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function verifyMercadoTransaction(Request $request) {
        $paymentId = $request->query->get('payment_id');
        $transaction = $this->paymentHandler->verifyMercadoTransaction($paymentId);

        return $this->paymentHandler->redirectByTransaction($transaction);
    }

    /**
     * Handles MercadoPago hooks.
     * @param Request $request
     * @return void
     */
    public function mercadoHook(Request $request) {
        $content = json_decode($request->getContent(), true);
        Log::channel('payments')->debug("MercadoPago hook received: ", [$content]);

        if(isset($content['data']) && isset($content['data']['id']) && isset($content['action'])) {
            switch ($content['action']) {
                case 'payment.created':
                case 'payment.updated':
                    $this->paymentHandler->verifyMercadoTransaction($content['data']['id']);
                    break;
            }
        }

        http_response_code(200);
    }

    public function stripeConnectHook() {
        if (app()->bound('debugbar')) {
            app('debugbar')->disable();
        }

        $endpoint_secret = getSetting('payments.withdrawal_stripe_connect_webhooks_secret');
        $payload = @file_get_contents('php://input');
        if (isset($_SERVER['HTTP_STRIPE_SIGNATURE'])) {
            $sig_header = $_SERVER['HTTP_STRIPE_SIGNATURE'];
        } else {
            // Invalid payload
            http_response_code(400);
            exit();
        }

        try {
            $event = \Stripe\Webhook::constructEvent(
                $payload,
                $sig_header,
                $endpoint_secret
            );
        } catch (\UnexpectedValueException $e) {
            // Invalid payload
            http_response_code(400);
            exit();
        } catch (\Stripe\Exception\SignatureVerificationException $e) {
            // Invalid signature
            http_response_code(400);
            exit();
        }
        Log::channel('withdrawals')->info('StripeConnect payload received.');
        Log::channel('withdrawals')->debug($event);

        try {
            if(isset($event->data->object)) {
                if ($event->type === 'account.updated') {
                    $connectedAccountId = $event->data->object->id;
                    $user = User::query()->where('stripe_account_id', $connectedAccountId)->first();
                    if ($user) {
                        $verified = WithdrawalsServiceProvider::userDoneStripeOnboarding($user);
                        if($verified) {
                            $user->stripe_onboarding_verified = true;
                            $user->save();
                        }
                    }
                } elseif(in_array($event->type, ['payout.failed', 'payout.canceled'])) {
                    $payoutId = $event->data->object->id;
                    $withdrawal = Withdrawal::query()->where('stripe_payout_id', $payoutId)->first();
                    if($withdrawal) {
                        $oldWithdrawalStatus = $withdrawal->status;
                        $withdrawal->status = Withdrawal::REJECTED_STATUS;
                        // if withdrawal was already processed and approved before we'll have to send
                        // the money back to the user as the observer won't do any processing in this case
                        if($withdrawal->processed && $oldWithdrawalStatus === Withdrawal::APPROVED_STATUS) {
                            WithdrawalsServiceProvider::creditUserForRejectedWithdrawal($withdrawal);
                        }
                        $withdrawal->save();
                    }
                } elseif($event->type === 'payout.paid') {
                    $payoutId = $event->data->object->id;
                    $withdrawal = Withdrawal::query()->where('stripe_payout_id', $payoutId)->first();
                    if($withdrawal) {
                        $withdrawal->status = Withdrawal::APPROVED_STATUS;
                        $withdrawal->save();
                    }
                }
            }
        } catch (\Exception $exception) {
            Log::channel('withdrawals')->error($exception->getMessage());
        }

        http_response_code(200);
    }

    /**
     * Verifies Verotel transaction.
     * @param Request $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function verifyVerotelTransaction(Request $request) {
        $paymentId = $request->query->get('ref');
        $transaction = null;
        if($paymentId) {
            $transaction = Transaction::query()->where('verotel_payment_token', $paymentId)->first();
        }

        return $this->paymentHandler->redirectByTransaction($transaction);
    }

    /**
     * Handles Verotel hooks.
     * @param Request $request
     * @return Application|ResponseFactory|\Illuminate\Foundation\Application|Response|void
     */
    public function verotelHook(Request $request) {
        try {
            if(!VerotelServiceProvider::validWebhookSignature($request->query())) {
                Log::channel('payments')->warning("Invalid hook received", [$request->query()]);
                return response('Invalid signature', 400);
            }

            Log::channel('payments')->info("Verotel hook received", [$request->query()]);

            $saleId = $request->get('saleID');
            $type = $request->get('type');
            $event = $request->get('event');
            $paymentToken = $request->get('custom1');
            $nextChargeOn = $request->get('nextChargeOn');

            if($saleId) {
                // Handles one-time payments
                if($paymentToken && $type === 'purchase') {
                    $this->paymentHandler->verifyVerotelOneTimePayment($paymentToken, $saleId);
                }

                // Handles recurring payments
                if($paymentToken && $event === 'initial' && $type === 'subscription') {
                    $this->paymentHandler->verifyVerotelInitialRecurringPayment($paymentToken, $saleId, $nextChargeOn);
                }

                // Handles renewal payments
                if($paymentToken && $event === 'rebill' && $type === 'subscription') {
                    $this->paymentHandler->verifyVerotelRenewalRecurringPayment($saleId, $nextChargeOn);
                }

                // Handles subscription cancellation
                if($event === 'cancel' && $type === 'subscription') {
                    $this->paymentHandler->handleVerotelSubscriptionCancelation($saleId);
                }

                // Handles subscription chargeback
                if($event === 'chargeback' && in_array($type, ['purchase', 'subscription'])) {
                    $this->paymentHandler->handleVerotelTransactionRefund($saleId);
                }
            }
        } catch (\Exception $exception) {
            Log::channel('payments')->error('Verotel hook error:', [$exception->getMessage()]);
        }

        return response('OK', 200)
            ->header('Content-Type', 'text/plain');
    }

    /**
     * Verifies RazorPay transaction.
     * @param Request $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function verifyRazorPayTransaction(Request $request) {
        $paymentId = $request->query->get('razorpay_payment_id');
        $paymentToken = $request->query->get('razorpay_payment_link_reference_id');
        $transaction = null;
        if($paymentToken) {
            $transaction = Transaction::query()->where('razorpay_payment_token', $paymentToken)->first();
            if($transaction && empty($transaction->razorpay_payment_id)) {
                $transaction = $this->paymentHandler->verifyRazorpayPayment($paymentToken, $paymentId);
            }
        }

        return $this->paymentHandler->redirectByTransaction($transaction);
    }

    /**
     * Handles RazorPay hooks.
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function razorPayHook(Request $request)
    {
        try {
            $payload = $request->getContent();
            Log::channel('payments')->info("Razorpay raw hook received", [$payload]);

            $signature = $request->header('X-Razorpay-Signature');
            $secret = getSetting('payments.razorpay_webhooks_secret');

            // only do the webhook signature verification if we have the secret & header present
            if ($secret && $signature) {
                try {
                    RazorPayServiceProvider::verifyWebhookSignature($payload, $signature, $secret);
                } catch (\Exception $e) {
                    Log::channel('payments')->info("RazorPay hook signature verification failed", [$e->getMessage()]);
                    return response()->json(['status' => 'invalid'], 400);
                }
            }

            $data = json_decode($payload, true);
            Log::channel('payments')->info("RazorPay decoded hook data received", [$data]);

            switch ($data['event']) {
                case 'payment_link.paid':
                case 'payment_link.payment_failed':
                    $paymentId = $data['payload']['payment']['entity']['id'];
                    $reference = $data['payload']['payment_link']['entity']['reference_id'];
                    $this->paymentHandler->verifyRazorpayPayment($reference, $paymentId);

                    break;
                case 'refund.processed':
                    $paymentId = $data['payload']['refund']['entity']['payment_id'];
                    $this->paymentHandler->handleRazorpayTransactionRefund($paymentId);

                    break;
            }
        } catch (\Exception $exception) {
            Log::channel('payments')->error('RazorPay hook error:', [$exception->getMessage()]);
        }

        return response()->json(['status' => 'OK']);
    }

    /**
     * Handles taxes quote.
     * @param QuoteTaxesRequest $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function quoteTaxes(QuoteTaxesRequest $request)
    {
        // No-collection checkout uses global taxes only, regardless of saved or submitted details.
        $countryName = CheckoutBilling::collectsDetails() ? $request->input('country') : null;

        // Determine the BASE subtotal (without taxes)
        $baseAmount = $this->paymentHandler->resolveBaseAmountFromRequest($request);
        $quote = $this->paymentHandler->quoteTaxesForTransaction($request->get('transaction_type'), $countryName, $baseAmount);

        return response()->json([
            'status' => 200,
            'quote' => $quote,
        ], 200);
    }

    /**
     * Handles YooKassa payment redirect.
     * @param Request $request
     * @return \Illuminate\Http\RedirectResponse
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function checkAndUpdateYooKassaTransaction(Request $request)
    {
        $transaction = $this->paymentHandler->verifyYooKassaTransactionByToken($request->get('token'));

        return $this->paymentHandler->redirectByTransaction($transaction);
    }

    /**
     * Handles Mollie payment redirect.
     * @param Request $request
     * @return \Illuminate\Http\RedirectResponse
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function checkAndUpdateMollieTransaction(Request $request)
    {
        $transaction = $this->paymentHandler->verifyMollieTransactionByToken($request->get('token'));

        return $this->paymentHandler->redirectByTransaction($transaction);
    }

    /**
     * Handles Flutterwave payment redirect.
     * @param Request $request
     * @return \Illuminate\Http\RedirectResponse
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function checkAndUpdateFlutterwaveTransaction(Request $request)
    {
        $transaction = $this->paymentHandler->verifyFlutterwaveTransactionByToken(
            $request->get('tx_ref') ?: $request->get('token'),
            $request->get('transaction_id'),
            $request->get('status')
        );

        return $this->paymentHandler->redirectByTransaction($transaction);
    }

    /**
     * Handles CoinGate payment redirect.
     * @param Request $request
     * @return \Illuminate\Http\RedirectResponse
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function checkAndUpdateCoinGateTransaction(Request $request)
    {
        $transaction = $this->paymentHandler->verifyCoinGateTransactionByToken($request->get('token'));

        return $this->paymentHandler->redirectByTransaction($transaction);
    }

    /**
     * Process YooKassa hooks.
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function yooKassaHook(Request $request)
    {
        try {
            $payload = json_decode($request->getContent(), true);
            Log::channel('payments')->info('YooKassa hook received: ', [$payload]);
            $eventType = data_get($payload, 'event');

            if ($eventType !== 'payment.succeeded') {
                Log::channel('payments')->info('YooKassa hook ignored unsupported event.', [
                    'event' => $eventType,
                    'payload' => $payload,
                ]);

                return response()->json([
                    'status' => 200,
                ], 200);
            }

            $paymentId = data_get($payload, 'object.id');

            if (!$paymentId) {
                Log::channel('payments')->warning('YooKassa hook missing payment ID.', [
                    'payload' => $payload,
                    'raw_payload' => $request->getContent(),
                ]);

                return response()->json([
                    'status' => 400,
                    'message' => 'Missing YooKassa payment ID.',
                ], 400);
            }

            $this->paymentHandler->verifyYooKassaTransactionByPaymentId($paymentId);
        } catch (\Exception $exception) {
            Log::channel('payments')->error('YooKassa hook error: ', [$exception->getMessage()]);

            return response()->json([
                'status' => 400,
            ], 400);
        }

        return response()->json([
            'status' => 200,
        ], 200);
    }

    /**
     * Process CoinGate hooks.
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function coingateHook(Request $request)
    {
        try {
            $payload = json_decode($request->getContent(), true);
            $requestData = is_array($payload) && count($payload) ? $payload : $request->all();

            Log::channel('payments')->info('CoinGate hook received.', [
                'payload' => $requestData,
                'raw_payload' => $request->getContent(),
            ]);

            $paymentToken = data_get($requestData, 'token') ?? data_get($requestData, 'order_id');
            $orderId = data_get($requestData, 'id');

            if ($paymentToken) {
                $transaction = Transaction::query()->where('coingate_payment_token', (string) $paymentToken)->first();

                if (!$transaction) {
                    Log::channel('payments')->warning('CoinGate hook token did not match a local transaction.', [
                        'payload' => $requestData,
                    ]);

                    return response()->json([
                        'status' => 400,
                        'message' => 'Invalid CoinGate callback token.',
                    ], 400);
                }

                if ($orderId) {
                    $this->paymentHandler->verifyCoinGateTransactionById((string) $orderId);
                } else {
                    $this->paymentHandler->verifyCoinGateTransactionByToken((string) $paymentToken);
                }
            } elseif ($orderId) {
                $this->paymentHandler->verifyCoinGateTransactionById((string) $orderId);
            } else {
                Log::channel('payments')->warning('CoinGate hook missing order reference.', [
                    'payload' => $requestData,
                ]);

                return response()->json([
                    'status' => 400,
                    'message' => 'Missing CoinGate order reference.',
                ], 400);
            }
        } catch (\Exception $exception) {
            Log::channel('payments')->error('CoinGate hook error: ', [$exception->getMessage()]);

            return response()->json([
                'status' => 400,
            ], 400);
        }

        return response()->json([
            'status' => 200,
        ], 200);
    }

    /**
     * Process Flutterwave hooks.
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function flutterwaveHook(Request $request)
    {
        $secretHash = (string) getSetting('payments.flutterwave_webhook_secret_hash');
        $rawPayload = $request->getContent();
        $signature = $request->header('flutterwave-signature')
            ?: $request->header('verif-hash')
            ?: $request->header('verifi-hash');

        if (!$signature) {
            Log::channel('payments')->warning('Flutterwave hook missing signature header.', [
                'headers' => $request->headers->all(),
                'raw_payload' => $rawPayload,
            ]);

            return response()->json([
                'status' => 400,
                'message' => 'Missing Flutterwave signature header.',
            ], 400);
        }

        $hmacSignature = base64_encode(hash_hmac('sha256', $rawPayload, $secretHash, true));
        $isValidSignature = $secretHash !== '' && ($signature === $secretHash || hash_equals($hmacSignature, $signature));

        if (!$isValidSignature) {
            Log::channel('payments')->warning('Flutterwave hook invalid signature.', [
                'headers' => $request->headers->all(),
                'raw_payload' => $rawPayload,
            ]);

            return response()->json([
                'status' => 403,
                'message' => 'Invalid Flutterwave signature.',
            ], 403);
        }

        try {
            $payload = json_decode($rawPayload, true);
            Log::channel('payments')->info('Flutterwave hook received: ', [$payload]);

            $eventType = data_get($payload, 'event') ?? data_get($payload, 'type');

            if ($eventType !== 'charge.completed') {
                Log::channel('payments')->info('Flutterwave hook ignored unsupported event.', [
                    'event' => $eventType,
                    'payload' => $payload,
                ]);

                return response()->json([
                    'status' => 200,
                ], 200);
            }

            $transactionId = data_get($payload, 'data.id');
            if ($transactionId) {
                $this->paymentHandler->verifyFlutterwaveTransactionById((string) $transactionId);
            } else {
                $paymentToken = data_get($payload, 'data.tx_ref') ?? data_get($payload, 'data.meta.payment_token');

                if ($paymentToken) {
                    $this->paymentHandler->verifyFlutterwaveTransactionByToken((string) $paymentToken);
                } else {
                    Log::channel('payments')->warning('Flutterwave hook missing transaction reference.', [
                        'payload' => $payload,
                    ]);

                    return response()->json([
                        'status' => 400,
                        'message' => 'Missing Flutterwave transaction reference.',
                    ], 400);
                }
            }
        } catch (\Exception $exception) {
            Log::channel('payments')->error('Flutterwave hook error: ', [$exception->getMessage()]);

            return response()->json([
                'status' => 400,
            ], 400);
        }

        return response()->json([
            'status' => 200,
        ], 200);
    }

    /**
     * Process Mollie hooks.
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function mollieHook(Request $request)
    {
        try {
            $paymentId = $request->get('id');

            if (!$paymentId) {
                $payload = json_decode($request->getContent(), true);
                $paymentId = data_get($payload, 'id');
            }

            Log::channel('payments')->info('Mollie hook received.', [
                'payment_id' => $paymentId,
                'payload' => $request->request->all(),
                'raw_payload' => $request->getContent(),
            ]);

            if (!$paymentId) {
                Log::channel('payments')->warning('Mollie hook missing payment ID.', [
                    'payload' => $request->request->all(),
                    'raw_payload' => $request->getContent(),
                ]);

                return response()->json([
                    'status' => 400,
                    'message' => 'Missing Mollie payment ID.',
                ], 400);
            }

            $this->paymentHandler->verifyMollieTransactionByPaymentId($paymentId);
        } catch (\Exception $exception) {
            Log::channel('payments')->error('Mollie hook error: ', [$exception->getMessage()]);

            return response()->json([
                'status' => 400,
            ], 400);
        }

        return response()->json([
            'status' => 200,
        ], 200);
    }

    /**
     * Handles Xendit payment redirect.
     * @param Request $request
     * @return \Illuminate\Http\RedirectResponse
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function checkAndUpdateXenditTransaction(Request $request)
    {
        $transaction = $this->paymentHandler->verifyXenditTransactionByToken($request->get('token'));

        return $this->paymentHandler->redirectByTransaction($transaction);
    }

    /**
     * Process Xendit hooks.
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function xenditHook(Request $request)
    {
        $callbackToken = $request->header('x-callback-token');

        if (!$callbackToken) {
            Log::channel('payments')->warning('Xendit hook missing callback token.', [
                'headers' => $request->headers->all(),
                'raw_payload' => $request->getContent(),
            ]);

            return response()->json([
                'status' => 400,
                'message' => 'Missing Xendit callback token.',
            ], 400);
        }

        if ($callbackToken !== getSetting('payments.xendit_webhook_token')) {
            Log::channel('payments')->warning('Xendit hook invalid callback token.', [
                'headers' => $request->headers->all(),
                'raw_payload' => $request->getContent(),
            ]);

            return response()->json([
                'status' => 403,
                'message' => 'Invalid Xendit callback token.',
            ], 403);
        }

        try {
            $payload = json_decode($request->getContent(), true);
            Log::channel('payments')->info('Xendit hook received: ', [$payload]);

            $event = data_get($payload, 'event');
            $sessionId = data_get($payload, 'data.payment_session_id');
            $paymentToken = data_get($payload, 'data.reference_id') ?? data_get($payload, 'reference_id');

            if (!in_array($event, ['payment_session.completed', 'payment_session.expired'], true)) {
                Log::channel('payments')->info('Xendit hook ignored unsupported event.', [
                    'event' => $event,
                    'payload' => $payload,
                ]);

                return response()->json([
                    'status' => 200,
                ], 200);
            }

            if ($sessionId) {
                $this->paymentHandler->verifyXenditTransactionBySessionId($sessionId);
            } elseif ($paymentToken) {
                $this->paymentHandler->verifyXenditTransactionByToken($paymentToken);
            } else {
                Log::channel('payments')->warning('Xendit hook missing session reference.', [
                    'payload' => $payload,
                ]);

                return response()->json([
                    'status' => 400,
                    'message' => 'Missing Xendit session reference.',
                ], 400);
            }
        } catch (\Exception $exception) {
            Log::channel('payments')->error('Xendit hook error: ', [$exception->getMessage()]);

            return response()->json([
                'status' => 400,
            ], 400);
        }

        return response()->json([
            'status' => 200,
        ], 200);
    }

    /**
     * Handles Paddle payment redirect.
     * @param Request $request
     * @return \Illuminate\Http\RedirectResponse
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function checkAndUpdatePaddleTransaction(Request $request)
    {
        $transaction = $this->paymentHandler->verifyPaddleTransactionByToken($request->get('token'));

        return $this->paymentHandler->redirectByTransaction($transaction);
    }

    /**
     * Handles Crypto.com payment redirect.
     * @param Request $request
     * @return \Illuminate\Http\RedirectResponse
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function checkAndUpdateCryptoComTransaction(Request $request)
    {
        $transaction = $this->paymentHandler->verifyCryptoComTransactionByToken($request->get('token'));

        return $this->paymentHandler->redirectByTransaction($transaction);
    }

    /**
     * Process Paddle hooks.
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function paddleHook(Request $request)
    {
        $signatureHeader = $request->header('Paddle-Signature');

        if (!$signatureHeader) {
            Log::channel('payments')->warning('Paddle hook missing signature header.', [
                'headers' => $request->headers->all(),
                'raw_payload' => $request->getContent(),
            ]);

            return response()->json([
                'status' => 400,
                'message' => 'Missing Paddle signature header.',
            ], 400);
        }

        $signatureSegments = [];
        foreach (explode(';', $signatureHeader) as $segment) {
            $keyValue = explode('=', $segment, 2);
            if (count($keyValue) === 2) {
                $signatureSegments[$keyValue[0]] = $keyValue[1];
            }
        }

        if (!isset($signatureSegments['ts']) || !isset($signatureSegments['h1'])) {
            Log::channel('payments')->warning('Paddle hook missing signature segments.', [
                'signature_header' => $signatureHeader,
                'headers' => $request->headers->all(),
                'raw_payload' => $request->getContent(),
            ]);

            return response()->json([
                'status' => 400,
                'message' => 'Missing Paddle signature segments.',
            ], 400);
        }

        $payload = $request->getContent();
        $signedPayload = $signatureSegments['ts'].':'.$payload;
        $computedSignature = hash_hmac('sha256', $signedPayload, getSetting('payments.paddle_webhooks_secret'));

        if (!hash_equals($computedSignature, $signatureSegments['h1'])) {
            Log::channel('payments')->warning('Paddle hook invalid signature.', [
                'signature_header' => $signatureHeader,
                'raw_payload' => $payload,
            ]);

            return response()->json([
                'status' => 400,
                'message' => 'Invalid Paddle signature.',
            ], 400);
        }

        try {
            $event = json_decode($payload, true);
            Log::channel('payments')->info('Paddle hook received: ', [$event]);

            $eventType = data_get($event, 'event_type');
            $transactionId = data_get($event, 'data.id');

            if ($eventType !== 'transaction.completed') {
                Log::channel('payments')->info('Paddle hook ignored unsupported event.', [
                    'event_type' => $eventType,
                    'payload' => $event,
                ]);

                return response()->json([
                    'status' => 200,
                ], 200);
            }

            if ($transactionId) {
                $this->paymentHandler->verifyPaddleTransactionById($transactionId);
            } else {
                $paymentToken = data_get($event, 'data.custom_data.payment_token');

                if ($paymentToken) {
                    $this->paymentHandler->verifyPaddleTransactionByToken($paymentToken);
                } else {
                    Log::channel('payments')->warning('Paddle hook missing transaction reference.', [
                        'payload' => $event,
                    ]);

                    return response()->json([
                        'status' => 400,
                        'message' => 'Missing Paddle transaction reference.',
                    ], 400);
                }
            }
        } catch (\Exception $exception) {
            Log::channel('payments')->error('Paddle hook error: ', [$exception->getMessage()]);

            return response()->json([
                'status' => 400,
            ], 400);
        }

        return response()->json([
            'status' => 200,
        ], 200);
    }

    /**
     * Process Crypto.com hooks.
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function cryptocomHook(Request $request)
    {
        $signatureHeader = $request->header('Pay-Signature');

        if (!$signatureHeader) {
            Log::channel('payments')->warning('Crypto.com hook missing signature header.', [
                'headers' => $request->headers->all(),
                'raw_payload' => $request->getContent(),
            ]);

            return response()->json([
                'status' => 400,
                'message' => 'Missing Crypto.com signature header.',
            ], 400);
        }

        $signatureSegments = [];
        foreach (explode(',', $signatureHeader) as $segment) {
            $keyValue = explode('=', $segment, 2);
            if (count($keyValue) === 2) {
                $signatureSegments[$keyValue[0]] = $keyValue[1];
            }
        }

        if (!isset($signatureSegments['t']) || !isset($signatureSegments['v1'])) {
            Log::channel('payments')->warning('Crypto.com hook missing signature segments.', [
                'signature_header' => $signatureHeader,
                'headers' => $request->headers->all(),
                'raw_payload' => $request->getContent(),
            ]);

            return response()->json([
                'status' => 400,
                'message' => 'Missing Crypto.com signature segments.',
            ], 400);
        }

        $payload = $request->getContent();
        $signedPayload = $signatureSegments['t'].'.'.$payload;
        $computedSignature = hash_hmac('sha256', $signedPayload, getSetting('payments.cryptocom_webhooks_secret'));

        if (!hash_equals($computedSignature, $signatureSegments['v1'])) {
            Log::channel('payments')->warning('Crypto.com hook invalid signature.', [
                'signature_header' => $signatureHeader,
                'raw_payload' => $payload,
            ]);

            return response()->json([
                'status' => 400,
                'message' => 'Invalid Crypto.com signature.',
            ], 400);
        }

        try {
            $event = json_decode($payload, true);
            Log::channel('payments')->info('Crypto.com hook received: ', [$event]);

            $eventType = data_get($event, 'type');
            if (!in_array($eventType, ['payment.captured', 'payment.cancelled'], true)) {
                Log::channel('payments')->info('Crypto.com hook ignored unsupported event.', [
                    'type' => $eventType,
                    'payload' => $event,
                ]);

                return response()->json([
                    'status' => 200,
                ], 200);
            }

            $paymentId = data_get($event, 'data.object.id') ?? data_get($event, 'data.id');
            if (!$paymentId) {
                Log::channel('payments')->warning('Crypto.com hook missing payment ID.', [
                    'payload' => $event,
                ]);

                return response()->json([
                    'status' => 400,
                    'message' => 'Missing Crypto.com payment ID.',
                ], 400);
            }

            $this->paymentHandler->verifyCryptoComTransactionByPaymentId($paymentId);
        } catch (\Exception $exception) {
            Log::channel('payments')->error('Crypto.com hook error: ', [$exception->getMessage()]);

            return response()->json([
                'status' => 400,
            ], 400);
        }

        return response()->json([
            'status' => 200,
        ], 200);
    }
}
