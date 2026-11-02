<?php

namespace App\Providers;

use App\Model\PaymentRequest;
use App\Model\User;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;

class PaymentRequestServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        //
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        //        UserVerify::observe(UserVerifyObserver::class);
        Schema::defaultStringLength(191);
    }

    /**
     * Creates a deposit request for admins by a transaction.
     * @param $transaction
     */
    public static function createDepositPaymentRequestByTransaction($transaction, ?array $submittedFields = null, ?array $detailsSnapshot = null)
    {
        $paymentRequest = PaymentRequest::create([
            'type' => PaymentRequest::DEPOSIT_TYPE,
            'user_id' => $transaction['recipient_user_id'],
            'transaction_id' => $transaction['id'],
            'amount' => $transaction['amount'],
            'submitted_fields' => $submittedFields,
            'details_snapshot' => $detailsSnapshot,
        ]);
        // Delivery runs after the enclosing payment transaction commits, so mail failures cannot lose a deposit.
        \Illuminate\Support\Facades\DB::afterCommit(function () {
            try {
                $adminEmails = User::where('role_id', 1)->select(['email', 'name'])->get();
                foreach ($adminEmails as $user) {
                    EmailsServiceProvider::sendGenericEmail([
                        'email' => $user->email,
                        'subject' => __('Action required | New deposit request'),
                        'title' => __('Hello, :name,', ['name' => $user->name]),
                        'content' => __('There is a new deposit request on :siteName that requires your attention.', ['siteName' => getSetting('site.name')]),
                        'button' => [
                            'text' => __('Go to admin'),
                            'url' => url()->route('filament.admin.resources.payment-requests.index'),
                        ],
                    ]);
                }
            } catch (\Throwable $exception) {
                \Illuminate\Support\Facades\Log::warning('Failed to notify admins of a deposit request.', ['error' => $exception->getMessage()]);
            }
        });

        return $paymentRequest;
    }
}
