<?php

namespace App\Providers;

use DateTime;
use DateTimeZone;
use GuzzleHttp\Client;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\ServiceProvider;

class PaypalAPIServiceProvider extends ServiceProvider
{
    public const PAYPAL_SANDBOX_API_BASE_URL = 'https://api-m.sandbox.paypal.com';
    public const PAYPAL_LIVE_API_BASE_URL = 'https://api-m.paypal.com';
    public const SUBSCRIPTION_PRODUCT_ID = 'JF-SUB';

    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
    }

    private static function getBasePath(): string
    {
        return getSetting('payments.paypal_live_mode')
            ? self::PAYPAL_LIVE_API_BASE_URL
            : self::PAYPAL_SANDBOX_API_BASE_URL;
    }

    private static function getBasicCredentials(): string
    {
        return 'Basic '.base64_encode(getSetting('payments.paypal_client_id').':'.getSetting('payments.paypal_secret'));
    }

    public static function createPlan($transaction): array
    {
        $httpClient = new Client();
        $planName = PaymentsServiceProvider::getPaymentDescriptionByTransaction($transaction);

        $response = $httpClient->post(self::getBasePath().'/v1/billing/plans', [
            'headers' => [
                'Content-Type' => 'application/json',
                'Authorization' => self::getBasicCredentials(),
            ],
            'json' => [
                'name' => $planName,
                'status' => 'ACTIVE',
                'product_id' => self::getProduct(self::SUBSCRIPTION_PRODUCT_ID),
                'description' => $planName,
                'billing_cycles' => [
                    [
                        'tenure_type' => 'REGULAR',
                        'sequence' => 1,
                        'total_cycles' => 0, // infinite cycles
                        'pricing_scheme' => [
                            'fixed_price' => [
                                'currency_code' => $transaction['currency'],
                                'value' => $transaction['amount'],
                            ],
                        ],
                        'frequency' => [
                            'interval_unit' => 'MONTH',
                            'interval_count' => PaymentsServiceProvider::getSubscriptionMonthlyIntervalByTransactionType($transaction->type),
                        ],
                    ],
                ],
                'payment_preferences' => [
                    'auto_bill_outstanding' => true,
                    'setup_fee_failure_action' => 'CANCEL',
                    'payment_failure_threshold' => 0,
                    'setup_fee' => [
                        'currency_code' => $transaction['currency'],
                        'value' => $transaction['amount'],
                    ],
                ],

            ],
        ]);

        return json_decode($response->getBody(), true);
    }

    public static function createSubscriptionByPlanAndTransaction(string $planId, $transaction): array
    {
        $httpClient = new Client();
        $startTime = new DateTime(
            '+'.PaymentsServiceProvider::getSubscriptionMonthlyIntervalByTransactionType($transaction->type).' month',
            new DateTimeZone('UTC')
        );

        $response = $httpClient->post(self::getBasePath().'/v1/billing/subscriptions', [
            'headers' => [
                'Content-Type' => 'application/json',
                'Authorization' => self::getBasicCredentials(),
            ],
            'json' => [
                'plan_id' => $planId,
                'start_time' => $startTime->format('Y-m-d\TH:i:s\Z'),
                'application_context' => [
                    'brand_name' => getSetting('site.name'),
                    // After you redirect the customer to the PayPal subscription consent page, a Subscribe Now button appears.
                    // Use this option when you want PayPal to activate the subscription.
                    'user_action' => 'SUBSCRIBE_NOW',
                    'return_url' => route('payment.executePaypalPayment'),
                    'cancel_url' => route('payment.executePaypalPayment'),
                    'payment_method' => [
                        'payee_preferred' => 'IMMEDIATE_PAYMENT_REQUIRED',
                    ],
                ],
            ],
        ]);

        return json_decode($response->getBody(), true);
    }

    public static function getSubscription(string $subscriptionId): array
    {
        $httpClient = new Client(['connect_timeout' => 5, 'timeout' => 15]);

        $response = $httpClient->get(self::getBasePath().'/v1/billing/subscriptions/'.$subscriptionId, [
            'headers' => [
                'Content-Type' => 'application/json',
                'Authorization' => self::getBasicCredentials(),
            ],
            'json' => [
                'reason' => __("Subscription starts"),
            ],
        ]);

        return json_decode($response->getBody(), true);
    }

    public static function activateSubscription(string $subscriptionId): void
    {
        $httpClient = new Client();

        $httpClient->post(self::getBasePath().'/v1/billing/subscriptions/'.$subscriptionId.'/activate', [
            'headers' => [
                'Content-Type' => 'application/json',
                'Authorization' => self::getBasicCredentials(),
            ],
            'json' => [
                'reason' => __("Subscription starts"),
            ],
        ]);
    }

    public static function cancelSubscription(string $subscriptionId): bool
    {
        $subscription = self::getSubscription($subscriptionId);
        if (in_array($subscription['status'] ?? null, ['CANCELLED', 'EXPIRED'], true)) {
            return true;
        }
        $httpClient = new Client(['connect_timeout' => 5, 'timeout' => 15]);

        $response = $httpClient->post(self::getBasePath().'/v1/billing/subscriptions/'.$subscriptionId.'/cancel', [
            'headers' => [
                'Content-Type' => 'application/json',
                'Authorization' => self::getBasicCredentials(),
            ],
            'json' => [
                'reason' => __('Cancel by the client.'),
            ],
        ]);

        return $response->getStatusCode() === 204;
    }

    public static function createProduct(string $productId): string
    {
        $httpClient = new Client();
        $productName = $productId == self::SUBSCRIPTION_PRODUCT_ID ? 'Subscription' : 'OneTimePayment';

        $response = $httpClient->post(self::getBasePath().'/v1/catalogs/products', [
            'headers' => [
                'Content-Type' => 'application/json',
                'Authorization' => self::getBasicCredentials(),
            ],
            'json' => [
                'name' => $productName,
                'type' => 'DIGITAL',
                'id' => $productId,
            ],
        ]);

        $body = json_decode($response->getBody(), true);

        return $body['id'];
    }

    public static function getProduct(string $productId): ?string
    {
        $product = null;
        try {
            $httpClient = new Client();

            $response = $httpClient->get(self::getBasePath().'/v1/catalogs/products/'.$productId, [
                'headers' => [
                    'Content-Type' => 'application/json',
                    'Authorization' => self::getBasicCredentials(),
                ],
            ]);

            $body = json_decode($response->getBody(), true);

            $product = $body['id'];
        } catch (\Exception $exception) {
            // product does not exist so create it
            if($exception->getCode() === 404) {
                $product = self::createProduct($productId);
            }
        }

        return $product;
    }

    public static function getApprovalUrlByResource(array $data, string $rel): ?string
    {
        if (is_array($data['links'])) {
            foreach ($data['links'] as $link) {
                if ($link['rel'] === $rel) {
                    return $link['href'];
                }
            }
        }

        return null;
    }

    public static function getPayPalTransactionTokenFromApprovalLink(string $approvalUrl): ?string
    {
        $token = explode('_token=', $approvalUrl);
        if (array_key_exists(1, $token)) {
            return $token[1];
        }

        return null;
    }

    public static function createOrderByTransaction($transaction): array {
        $httpClient = new Client();
        $paymentDescription = PaymentsServiceProvider::getPaymentDescriptionByTransaction($transaction);
        $amount = number_format((float) $transaction['amount'], 2, '.', '');

        $response = $httpClient->post(self::getBasePath().'/v2/checkout/orders', [
            'headers' => [
                'Content-Type' => 'application/json',
                'Authorization' => self::getBasicCredentials(),
            ],
            'json' => [
                'purchase_units' => [
                    [
                        'description' => $paymentDescription,
                        'items' => [
                            [
                                'name' => $paymentDescription,
                                'quantity' => 1,
                                'unit_amount' => [
                                    'currency_code' => config('app.site.currency_code'),
                                    'value' => $amount,
                                ],
                            ],
                        ],
                        'amount' => [
                            'currency_code' => config('app.site.currency_code'),
                            'value' => $amount,
                            'breakdown' => [
                                'item_total' => [
                                    'currency_code' => config('app.site.currency_code'),
                                    'value' => $amount,
                                ],
                            ],
                        ],
                    ],
                ],
                'intent' => 'CAPTURE',
                'payment_source' => [
                    'paypal' => [
                        'experience_context' => [
                            'brand_name' => getSetting('site.name'),
                            'user_action' => 'PAY_NOW',
                            'return_url' => route('payment.executePaypalPayment'),
                            'cancel_url' => route('payment.executePaypalPayment'),
                            'payment_method' => [
                                'payee_preferred' => 'IMMEDIATE_PAYMENT_REQUIRED',
                            ],
                        ],
                    ],
                ],
            ],
        ]);

        return json_decode($response->getBody(), true);
    }

    public static function capturePaymentForOrder($transaction): array
    {
        $httpClient = new Client();

        $response = $httpClient->post(
            self::getBasePath().'/v2/checkout/orders/'.$transaction['paypal_transaction_token'].'/capture',
            [
                'headers' => [
                    'Content-Type' => 'application/json',
                    'Authorization' => self::getBasicCredentials(),
                ],
            ]
        );

        return json_decode($response->getBody(), true);
    }

    public static function getTransactionsBySubscription(string $subscriptionId, string $startTime, string $endTime): array
    {
        $httpClient = new Client();

        $queryParams = 'start_time='.$startTime.'&end_time='.$endTime;
        $response = $httpClient->get(
            self::getBasePath().'/v1/billing/subscriptions/'.$subscriptionId.'/transactions?'.$queryParams,
            [
                'headers' => [
                    'Content-Type' => 'application/json',
                    'Authorization' => self::getBasicCredentials(),
                ],
            ]
        );

        return json_decode($response->getBody(), true);
    }

    public static function verifyWebhookSignature(Request $request): bool {
        try {
            $headers = $request->headers;
            $transmissionId = $headers->get('paypal-transmission-id');
            $timestamp = $headers->get('paypal-transmission-time');
            $signature = $headers->get('paypal-transmission-sig');
            $certUrl = $headers->get('paypal-cert-url');
            $authAlgo = $headers->get('paypal-auth-algo');
            $webhookId = getSetting('payments.paypal_webhook_id');

            $payload = [
                'auth_algo' => $authAlgo,
                'cert_url' => $certUrl,
                'transmission_id' => $transmissionId,
                'transmission_sig' => $signature,
                'transmission_time' => $timestamp,
                'webhook_id' => $webhookId,
                'webhook_event' => json_decode($request->getContent(), true),
            ];

            $httpClient = new Client();

            $response = $httpClient->post(
                self::getBasePath().'/v1/notifications/verify-webhook-signature',
                [
                    'headers' => [
                        'Content-Type' => 'application/json',
                        'Authorization' => self::getBasicCredentials(),
                    ],
                    'json' => $payload,
                ]
            );

            $responseData = json_decode($response->getBody(), true);

            if(isset($responseData['verification_status'])) {
                return $responseData['verification_status'] === 'SUCCESS';
            }
        } catch (\Exception $e) {
            Log::channel('payments')->error("Failed verifying PayPal hook signature: ".$e->getMessage());
        }

        return false;
    }
}
