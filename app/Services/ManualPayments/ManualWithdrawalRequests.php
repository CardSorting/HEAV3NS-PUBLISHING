<?php

namespace App\Services\ManualPayments;

use App\Model\ManualPaymentMethod;
use App\Model\User;
use App\Model\UserPayoutAccount;
use App\Model\Withdrawal;
use App\Providers\PaymentsServiceProvider;
use App\Providers\WithdrawalsServiceProvider;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class ManualWithdrawalRequests
{
    /** Admin file fields contain temporary uploads; wallet file fields contain pending document IDs. */
    public function create(int $userId, int $methodId, int $accountId, float $amount, array $fields = []): Withdrawal
    {
        Validator::make(['amount' => $amount], [
            'amount' => ['required', 'numeric', 'between:'.PaymentsServiceProvider::getWithdrawalMinimumAmount().','.PaymentsServiceProvider::getWithdrawalMaximumAmount()],
        ])->validate();

        $uploadedPaths = [];
        try {
            return DB::transaction(function () use ($userId, $methodId, $accountId, $amount, $fields, &$uploadedPaths): Withdrawal {
                $user = User::query()->whereKey($userId)->lockForUpdate()->firstOrFail();
                if ($user->isBanned()) {
                    throw ValidationException::withMessages(['user_id' => __('Withdrawals are unavailable while this account is suspended.')]);
                }
                $wallet = $user->wallet()->lockForUpdate()->first();
                if (!$wallet || $amount > (float) $wallet->total) {
                    throw ValidationException::withMessages(['amount' => $amount === (float) PaymentsServiceProvider::getWithdrawalMinimumAmount()
                        ? __("You don't have enough credit to withdraw. Minimum amount is: :minAmount", ['minAmount' => PaymentsServiceProvider::getWithdrawalMinimumAmount()])
                        : __('You cannot withdraw this amount, try with a lower one')]);
                }

                $method = ManualPaymentMethod::query()->whereKey($methodId)
                    ->where('direction', ManualPaymentMethod::WITHDRAWAL)->where('enabled', true)->lockForUpdate()->first();
                if (!$method) {
                    throw ValidationException::withMessages(['manual_method_id' => __('This withdrawal method is no longer available.')]);
                }
                $account = UserPayoutAccount::query()->whereKey($accountId)->where('user_id', $userId)
                    ->where('manual_method_id', $method->id)->where('is_active', true)->lockForUpdate()->first();
                if (!$account) {
                    throw ValidationException::withMessages(['payout_account_id' => __('Please select a valid saved payout account for this method.')]);
                }
                try {
                    $accountValues = ManualPaymentFields::validate($method->account_fields ?? [], $account->account_details ?? [], 'account_details');
                } catch (ValidationException $exception) {
                    throw ValidationException::withMessages(['payout_account_id' => __('Please update this saved payout account to match the current method requirements.')]);
                }

                $documents = app(ManualPaymentDocuments::class);
                foreach ($method->request_fields ?? [] as $field) {
                    if ($field['type'] !== 'file' || !is_array($fields[$field['key']] ?? null)) {
                        continue;
                    }
                    if (count($fields[$field['key']]) > $field['max_files']) {
                        throw ValidationException::withMessages(['submitted_fields.'.$field['key'] => __('Too many files for this field.')]);
                    }
                    foreach ($fields[$field['key']] as $index => $file) {
                        if ($file instanceof UploadedFile) {
                            try {
                                $document = $documents->upload($userId, $method->id, $field['key'], $file);
                            } catch (ValidationException $exception) {
                                throw ValidationException::withMessages(['submitted_fields.'.$field['key'] => collect($exception->errors())->flatten()->all()]);
                            }
                            $uploadedPaths[] = $document->path;
                            $fields[$field['key']][$index] = $document->id;
                        }
                    }
                }
                $fields = $documents->validateSubmission($method, $fields, $userId, true);

                // Both callers reserve the balance here, so the admin observer must not reserve it again.
                $withdrawal = Withdrawal::withoutEvents(fn () => Withdrawal::create([
                    'user_id' => $userId,
                    'manual_method_id' => $method->id,
                    'payout_account_id' => $account->id,
                    'payout_method_key' => PaymentsServiceProvider::WITHDRAWAL_METHOD_MANUAL,
                    'payment_method' => $method->name,
                    'payment_identifier' => null,
                    'message' => null,
                    'payout_snapshot' => null,
                    'amount' => $amount,
                    'fee' => Withdrawal::calculateFee($amount),
                    'status' => Withdrawal::REQUESTED_STATUS,
                    'processed' => false,
                    'submitted_fields' => $fields,
                    'details_snapshot' => [
                        'method_name' => $method->name,
                        'manual_method_id' => $method->id,
                        'instructions' => $method->instructions,
                        'account_label' => $account->display_label,
                        'account_items' => ManualPaymentFields::snapshotItems($method->account_fields ?? [], $accountValues),
                        'request_items' => ManualPaymentFields::snapshotItems($method->request_fields ?? [], $fields),
                    ],
                ]));
                $documents->bind($method, $fields, $userId, $withdrawal);
                $wallet->update(['total' => (float) $wallet->total - $amount]);
                $account->update(['last_used_at' => now()]);
                WithdrawalsServiceProvider::processNewWithdrawalEmailNotification();

                return $withdrawal;
            });
        } catch (\Throwable $exception) {
            // Database rollback cannot undo file writes made while preparing admin evidence.
            foreach ($uploadedPaths as $path) {
                Storage::disk(ManualPaymentDocuments::DISK)->delete($path);
            }
            throw $exception;
        }
    }
}
