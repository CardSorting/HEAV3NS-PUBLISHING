<?php

namespace App\Observers;

use App\Helpers\PaymentHelper;
use App\Model\MessageTemplate;
use App\Model\Subscription;
use App\Model\SubscriptionCancellationRequest;
use App\Services\MessageTemplateDispatchService;
use App\Services\UserBanService;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class SubscriptionsObserver
{
    /**
     * Listen to the Subscription deleting event.
     *
     * @param Subscription $subscription
     * @return void
     */
    public function deleting(Subscription $subscription)
    {
        if (SubscriptionCancellationRequest::query()->where('subscription_id', $subscription->id)
            ->where('status', '!=', SubscriptionCancellationRequest::COMPLETED)->exists()) {
            throw ValidationException::withMessages(['subscription' => __('Subscription cancellation is still pending. Confirm billing has stopped before deleting this record.')]);
        }

        try{
            $paymentHelper = new PaymentHelper();
            $cancelSubscription = $paymentHelper->cancelSubscription($subscription);
            if(!$cancelSubscription) {
                Log::error("Failed cancelling subscription for id: ".$subscription->id);
            }
        } catch (\Exception $exception) {
            Log::error("Failed cancelling subscription for id: ".$subscription->id." error: ".$exception->getMessage());
        }
    }

    /**
     * Listen to the Subscription created event.
     *
     * @param Subscription $subscription
     * @return void
     */
    public function created(Subscription $subscription) {
        $this->dispatchSubscriptionTemplateIfActive($subscription);
    }

    /**
     * Listen to the Subscription updating event.
     *
     * @param Subscription $subscription
     * @return void
     */
    public function updating(Subscription $subscription) {
        //
    }

    public function saved(Subscription $subscription): void
    {
        app(UserBanService::class)->inheritRenewalStop($subscription);
    }

    public function updated(Subscription $subscription): void
    {
        if ($subscription->wasChanged('status')) {
            $this->dispatchSubscriptionTemplateIfActive($subscription);
        }
    }

    protected function dispatchSubscriptionTemplateIfActive(Subscription $subscription): void
    {
        if ($subscription->status !== Subscription::ACTIVE_STATUS) {
            return;
        }

        $creatorId = (int) $subscription->recipient_user_id;
        $subscriberId = (int) $subscription->sender_user_id;

        dispatch(function () use ($creatorId, $subscriberId) {
            app(MessageTemplateDispatchService::class)->dispatchForTrigger(
                $creatorId,
                $subscriberId,
                MessageTemplate::TRIGGER_SUBSCRIPTION_CREATED
            );
        })->afterResponse();
    }
}
