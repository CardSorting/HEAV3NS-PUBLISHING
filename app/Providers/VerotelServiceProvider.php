<?php

namespace App\Providers;

use App\Helpers\PaymentHelper;
use App\Model\Transaction;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Yaml\Yaml;
use Verotel\FlexPay\Brand;
use Verotel\FlexPay\Client;
use Illuminate\Support\ServiceProvider;
use Verotel\FlexPay\Exception;

class VerotelServiceProvider extends ServiceProvider
{
    private const VEROTEL_CONTROL_CENTER_BASE_API_PATH = 'https://controlcenter.verotel.com/api';

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

    /**
     * @throws Exception
     */
    private static function getClient(): Client {
        $brand = Brand::create_from_merchant_id(getSetting('payments.verotel_merchant_id'));

        return new Client(
            (int)getSetting('payments.verotel_shop_id'),
            getSetting('payments.verotel_signature_key'),
            $brand
        );
    }

    public static function generateOneTimePaymentUrl(Transaction $transaction, string $token): string {
        $paymentHelper = new PaymentHelper();

        return self::getClient()->get_purchase_URL([
            "priceAmount" => $transaction->amount,
            "priceCurrency" => $transaction->currency,
            "description" => $paymentHelper->getPaymentDescriptionByTransaction($transaction),
            "custom1" => $token,
            "successURL" => route('payment.checkVerotelPaymentStatus', ['ref' => $token]),
            "declineURL" => route('payment.checkVerotelPaymentStatus', ['ref' => $token]),
        ]);
    }

    public static function generateSubscriptionPaymentUrl(Transaction $transaction, string $token): string {
        $paymentHelper = new PaymentHelper();

        return self::getClient()->get_subscription_URL([
            "subscriptionType" => "recurring",
            "name" => $paymentHelper->getPaymentDescriptionByTransaction($transaction),
            "priceAmount" => $transaction->amount,
            "priceCurrency" => $transaction->currency,
            "period" => 'P'.self::getRecurringPeriodInDaysByTransaction($transaction).'D',
            "custom1" => $token,
            "successURL" => route('payment.checkVerotelPaymentStatus', ['ref' => $token]),
            "declineURL" => route('payment.checkVerotelPaymentStatus', ['ref' => $token]),
        ]);
    }

    public static function getPaymentData(string $saleId): array {
        $transactionStatusUrl = self::getClient()->get_status_URL(['saleID' => $saleId]);

        return Yaml::parse(file_get_contents($transactionStatusUrl));
    }

    private static function getRecurringPeriodInDaysByTransaction($transaction): int {
        return PaymentsServiceProvider::getSubscriptionMonthlyIntervalByTransactionType($transaction->type) * 30;
    }

    public static function validWebhookSignature(array $queryParams): bool {
        return self::getClient()->validate_signature($queryParams);
    }

    public static function cancelSubscriptionSale(string $saleId): bool
    {
        $username = getSetting('payments.verotel_control_center_api_user');
        $password = getSetting('payments.verotel_control_center_api_password');

        if (!$username || !$password) {
            return false;
        }

        try {
            $authToken = base64_encode("$username:$password");

            $client = new \GuzzleHttp\Client(['connect_timeout' => 5, 'timeout' => 15]);
            $response = $client->request(
                'POST',
                self::VEROTEL_CONTROL_CENTER_BASE_API_PATH."/sale/$saleId/cancel",
                [
                    'headers' => [
                        'Authorization' => 'Basic '.$authToken,
                        'Accept' => 'application/json; version=2.0.0',
                    ],
                ]
            );

            $responseBody = json_decode($response->getBody()->getContents(), true);

            if (isset($responseBody['is_success']) && is_bool($responseBody['is_success'])) {
                return $responseBody['is_success'];
            }
        } catch (\Exception $exception) {
            Log::channel('payments')->error('Failed cancelling Verotel sale.', ['sale_id' => $saleId]);
            return false;
        }

        return false;
    }
}
