<?php

namespace App\Http\Controllers;

use App\Http\Requests\CreateWithdrawalRequest;
use App\Services\ManualPayments\ManualWithdrawalRequests;
use App\Model\User;
use App\Model\Withdrawal;
use App\Providers\GenericHelperServiceProvider;
use App\Providers\PaymentsServiceProvider;
use App\Providers\SettingsServiceProvider;
use App\Providers\StripeServiceProvider;
use App\Providers\WithdrawalsServiceProvider;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Validation\ValidationException;

class WithdrawalsController extends Controller
{
    /**
     * Method used for requesting an withdrawal request from the admin.
     *
     * @param CreateWithdrawalRequest $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function requestWithdrawal(CreateWithdrawalRequest $request)
    {
        try {
            $amount = (float) $request->request->get('amount');
            $manualMethodId = $request->integer('manual_method_id');
            $methodKey = $manualMethodId ? PaymentsServiceProvider::WITHDRAWAL_METHOD_MANUAL : $request->input('method');
            if (($manualMethodId && $request->filled('method'))
                || (!$manualMethodId && ($methodKey !== PaymentsServiceProvider::WITHDRAWAL_METHOD_STRIPE_CONNECT || !getSetting('payments.withdrawal_enable_stripe_connect')))) {
                throw ValidationException::withMessages(['manual_method_id' => __('Please select an available withdrawal method.')]);
            }
            $methodLabel = PaymentsServiceProvider::getWithdrawalMethodLabel($methodKey);
            $payoutAccountId = $request->integer('payout_account_id');

            $user = Auth::user();
            if (!$user || !SettingsServiceProvider::allowWithdrawals($user)) {
                throw ValidationException::withMessages(['method' => __('Please complete verification before requesting a withdrawal.')]);
            }
            if (WithdrawalsServiceProvider::isTaxInfoRequiredForWithdrawals($user)) {
                throw ValidationException::withMessages(['method' => __('Please complete your tax information before requesting a withdrawal.')]);
            }
            if (PaymentsServiceProvider::isStripeConnectWithdrawalMethod($methodKey)
                && (!$user->country_id || !$user->stripe_onboarding_verified || !$user->stripe_account_id)) {
                throw ValidationException::withMessages(['method' => __('Please complete Stripe onboarding before requesting a withdrawal.')]);
            }
            if ($amount != null && $user != null) {
                if ($manualMethodId) {
                    app(ManualWithdrawalRequests::class)->create(
                        (int) $user->id,
                        $manualMethodId,
                        $payoutAccountId,
                        $amount,
                        $request->input('submitted_fields', []) ?? [],
                    );
                    $wallet = $user->wallet()->firstOrFail();
                    $totalAmount = number_format((float) $wallet->total, 2, '.', '');
                    $pendingBalance = number_format((float) $wallet->pendingBalance, 2, '.', '');
                } else {
                    [$wallet, $totalAmount, $pendingBalance] = DB::transaction(function () use ($user, $amount, $methodKey, $methodLabel) {
                        $lockedUser = User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();
                        if ($lockedUser->isBanned()) {
                            throw ValidationException::withMessages(['account' => __('Withdrawals are unavailable while this account is suspended.')]);
                        }
                        $wallet = $user->wallet()->lockForUpdate()->first();

                        if ($wallet == null) {
                            GenericHelperServiceProvider::createUserWallet($user);
                            $wallet = $user->wallet()->lockForUpdate()->first();
                        }

                        if ($wallet == null) {
                            throw ValidationException::withMessages([
                                'amount' => __('Unable to create wallet, please try again'),
                            ]);
                        }

                        if ($amount === (float) PaymentsServiceProvider::getWithdrawalMinimumAmount() && $amount > (float) $wallet->total) {
                            throw ValidationException::withMessages([
                                'amount' => __("You don't have enough credit to withdraw. Minimum amount is: :minAmount", ['minAmount' => PaymentsServiceProvider::getWithdrawalMinimumAmount()]),
                            ]);
                        }

                        if ($amount > (float) $wallet->total) {
                            throw ValidationException::withMessages([
                                'amount' => __('You cannot withdraw this amount, try with a lower one'),
                            ]);
                        }

                        $fee = Withdrawal::calculateFee($amount);

                        // Wallet requests reserve funds here; the admin creation observer must not reserve them again.
                        Withdrawal::withoutEvents(fn () => Withdrawal::create([
                            'user_id' => $user->id,
                            'amount' => $amount,
                            'status' => Withdrawal::REQUESTED_STATUS,
                            'message' => null,
                            'payment_method' => $methodLabel,
                            'payment_identifier' => null,
                            'payout_account_id' => null,
                            'payout_method_key' => $methodKey,
                            'payout_snapshot' => null,
                            'fee' => $fee,
                        ]));

                        $wallet->update([
                            'total' => (float) $wallet->total - $amount,
                        ]);

                        $wallet->refresh();

                        return [
                            $wallet,
                            number_format((float) $wallet->total, 2, '.', ''),
                            number_format((float) $wallet->pendingBalance, 2, '.', ''),
                        ];
                    });

                    WithdrawalsServiceProvider::processNewWithdrawalEmailNotification();
                }

                return response()->json([
                    'success' => true,
                    'message' => __('Successfully requested withdrawal'),
                    'totalAmount' => SettingsServiceProvider::getWebsiteFormattedAmount($totalAmount),
                    'pendingBalance' => SettingsServiceProvider::getWebsiteFormattedAmount($pendingBalance),
                ]);
            }
        } catch (ValidationException $exception) {
            $errors = $exception->errors();

            return response()->json([
                'success' => false,
                'message' => collect($errors)->flatten()->first() ?: __('The given data was invalid.'),
                'errors' => $errors,
            ], 422);
        } catch (\Exception $exception) {
            return response()->json(['success' => false, 'message' => $exception->getMessage()]);
        }

        return response()->json(['success' => false, 'message' => __('Something went wrong, please try again')], 500);
    }

    public function onboarding() {
        $user = Auth::user();

        try {
            // redirect user to the form where he must add his details for the first time
            $onboardingType = "account_onboarding";
            // check if user have a stripe account created
            if(!$user->stripe_account_id) {
                WithdrawalsServiceProvider::createStripeAccountForUser($user);
            }

            // check if user done onboarding and if so just redirect him to only update his details
            if(WithdrawalsServiceProvider::userDoneStripeOnboarding($user)) {
                $onboardingType = "account_update";
            }

            // create account link (Stripe hosted UI to complete verification / onboarding process)
            $accountLink = StripeServiceProvider::createStripeAccountLink($user->stripe_account_id, $onboardingType);
        } catch (\Exception $exception) {
            Log::channel('withdrawals')->error(
                'StripeConnect onboarding failed being initiated',
                ['error' => $exception->getMessage(), 'userId' => $user->id]
            );
            return back()->with('error', __('Onboarding initiation failed, please retry or contact support'));
        }

        // redirect on Stripe hosted UI
        return Redirect::away($accountLink->url);
    }

    public function approveWithdrawal($withdrawalId) {
        $approvalResponse = WithdrawalsServiceProvider::approve($withdrawalId);
        $statusCode = $approvalResponse['success'] ? 200 : ($approvalResponse['error'] === __('Withdrawal not found') ? 404 : 400);

        return response()->json($approvalResponse, $statusCode);
    }

    public function rejectWithdrawal($withdrawalId) {
        $rejectionResponse = WithdrawalsServiceProvider::reject($withdrawalId);
        $statusCode = $rejectionResponse['success'] ? 200 : ($rejectionResponse['error'] === __('Withdrawal not found') ? 404 : 400);

        return response()->json($rejectionResponse, $statusCode);
    }
}
