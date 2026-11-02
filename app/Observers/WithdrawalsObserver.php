<?php

namespace App\Observers;

use App\Model\Withdrawal;
use App\Providers\EmailsServiceProvider;
use App\Providers\GenericHelperServiceProvider;
use App\Providers\NotificationServiceProvider;
use App\Providers\PaymentsServiceProvider;
use App\Providers\SettingsServiceProvider;
use App\Providers\UsersServiceProvider;
use App\Providers\WithdrawalsServiceProvider;
use App\Model\User;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class WithdrawalsObserver
{
    /**
     * Reserve new admin requests and process status transitions once.
     *
     * @param Withdrawal $withdrawal
     * @return void
     */
    public function saving(Withdrawal $withdrawal)
    {
        // Observer logic only appies to admin, user side code is hosted in the controller
        if(!UsersServiceProvider::loggedAsAdmin()) {
            return;
        }

        if (!$withdrawal->exists) {
            if ($withdrawal->status !== Withdrawal::REQUESTED_STATUS) {
                throw ValidationException::withMessages(['status' => __('admin.resources.withdrawal.helpers.status_creation_rule')]);
            }
            $withdrawal->processed = false;
            self::handleWithdrawalCreation($withdrawal);

            return;
        }

        if ($withdrawal->isDirty('processed')) {
            throw ValidationException::withMessages(['processed' => __('admin.resources.withdrawal.helpers.processed_flag')]);
        }

        foreach (['amount', 'user_id', 'fee'] as $field) {
            if ($withdrawal->isDirty($field)) {
                throw ValidationException::withMessages([$field => __('admin.resources.withdrawal.helpers.reserved_details_locked')]);
            }
        }

        if (!$withdrawal->isDirty('status')) {
            return;
        }

        if ($withdrawal->getOriginal('status') !== Withdrawal::REQUESTED_STATUS
            || $withdrawal->getOriginal('processed')
            || !in_array($withdrawal->status, [Withdrawal::REJECTED_STATUS, Withdrawal::APPROVED_STATUS], true)) {
            throw ValidationException::withMessages(['status' => __('admin.resources.withdrawal.helpers.processed_warning')]);
        }

        if ($withdrawal->status === Withdrawal::REJECTED_STATUS) {
            self::handleWithdrawalRejection($withdrawal);
        }

        if ($withdrawal->status === Withdrawal::APPROVED_STATUS) {
            self::handleWithdrawalApproval($withdrawal);
        }
    }

    public function created(Withdrawal $withdrawal): void
    {
        if (UsersServiceProvider::loggedAsAdmin()) {
            WithdrawalsServiceProvider::processNewWithdrawalEmailNotification();
        }
    }

    /**
     * Handles the Withdrawal deletion event.
     *
     * @param Withdrawal $withdrawal
     * @return void
     */
    public function deleted(Withdrawal $withdrawal)
    {
        // we only care about admin handling here
        if(!UsersServiceProvider::loggedAsAdmin()) {
            return;
        }

        if (!$withdrawal->processed && $withdrawal->status === Withdrawal::REQUESTED_STATUS) {
            self::handleWithdrawalRejection($withdrawal, true);
        }
    }

    /**
     * Returns money to the user and send notifications for a rejected/deleted withdrawal.
     * @param $withdrawal
     * @param bool $skipNotficationEntry
     */
    private function handleWithdrawalRejection($withdrawal, bool $skipNotficationEntry = false): void {
        WithdrawalsServiceProvider::creditUserForRejectedWithdrawal($withdrawal);
        $emailSubject = __('Your withdrawal request has been denied.');
        $button = [
            'text' => __('Try again'),
            'url' => route('my.settings', ['type'=>'wallet']),
        ];

        self::processWithdrawalNotifications($withdrawal, $emailSubject, $button, $skipNotficationEntry);
        // mark withdrawal as processed
        $withdrawal->processed = true;
    }

    private function handleWithdrawalApproval($withdrawal): void {
        PaymentsServiceProvider::createTransactionForWithdrawal($withdrawal);

        $emailSubject = __('Your withdrawal request has been approved.');
        $button = [
            'text' => __('My payments'),
            'url' => route('my.settings', ['type'=>'payments']),
        ];

        self::processWithdrawalNotifications($withdrawal, $emailSubject, $button);
        // mark withdrawal as processed
        $withdrawal->processed = true;
    }

    private function handleWithdrawalCreation($withdrawal) {
        $user = User::query()->whereKey($withdrawal->user_id)->lockForUpdate()->firstOrFail();
        if ($user->isBanned()) {
            throw ValidationException::withMessages(['user_id' => __('Withdrawals are unavailable while this account is suspended.')]);
        }
        $userWallet = $withdrawal->user->wallet()->lockForUpdate()->first();
        if(!$userWallet) {
            $userWallet = GenericHelperServiceProvider::createUserWallet($withdrawal->user);
        }

        $amount = $withdrawal->amount;
        $withdrawal->fee = Withdrawal::calculateFee($amount);

        $userWallet->update([
            'total' => $userWallet->total - floatval($amount),
        ]);
    }

    /**
     * Creates email / user notifications.
     * @param $withdrawal
     * @param $emailSubject
     * @param $button
     * @param $skipNotficationEntry
     */
    private function processWithdrawalNotifications($withdrawal, $emailSubject, $button, $skipNotficationEntry = false) {
        $withdrawal = clone $withdrawal;
        $withdrawal->getConnection()->afterCommit(function () use ($withdrawal, $emailSubject, $button, $skipNotficationEntry) {
            $locale = App::getLocale() ?: 'en';
            try {
                $user = User::findOrFail($withdrawal->user_id);
                App::setLocale(data_get($user->settings, 'locale', 'en'));
                EmailsServiceProvider::sendGenericEmail([
                    'email' => $user->email,
                    'subject' => $emailSubject,
                    'title' => __('Hello, :name,', ['name'=>$user->name]),
                    'content' => __('Email withdrawal processed', [
                            'siteName' => getSetting('site.name'),
                            'status' => __($withdrawal->status),
                        ]).($withdrawal->status == 'approved' ? ' '.SettingsServiceProvider::getWebsiteFormattedAmount($withdrawal->amount).((float) $withdrawal->fee > 0 ? '(-'.SettingsServiceProvider::getWebsiteFormattedAmount($withdrawal->fee).' taxes)' : '').' '.__('has been sent to your account.') : ''),
                    'button' => $button,
                ]);
            } catch (\Throwable $exception) {
                Log::warning('Failed to send withdrawal status email.', [
                    'withdrawal_id' => $withdrawal->id,
                    'error' => $exception->getMessage(),
                ]);
            } finally {
                App::setLocale($locale);
            }

            // If withdrawal is deleted - do not create notification entry
            if(!$skipNotficationEntry){
                try {
                    NotificationServiceProvider::createApprovedOrRejectedWithdrawalNotification($withdrawal);
                } catch (\Throwable $exception) {
                    Log::warning('Failed to publish withdrawal status notification.', [
                        'withdrawal_id' => $withdrawal->id,
                        'error' => $exception->getMessage(),
                    ]);
                }
            }
        });
    }
}
