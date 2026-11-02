<?php

namespace App\Services\Settings;

use App\Model\Subscription;

class SubscriptionPresenter
{
    public function present(Subscription $subscription, string $activeTab, int $viewerId): array
    {
        $profileUser = $activeTab === 'subscriptions'
            ? $subscription->creator
            : $subscription->subscriber;

        return [
            'id' => $subscription->id,
            'provider' => $subscription->provider,
            'profileName' => $profileUser->name ?? '—',
            'profileAvatar' => $profileUser?->avatar,
            'profileUrl' => $profileUser ? route('profile', ['username' => $profileUser->username]) : null,
            'statusClass' => $this->getStatusClass($subscription),
            'statusLabel' => $this->getStatusLabel($subscription),
            'providerLabel' => $subscription->provider ? ucfirst($subscription->provider) : '—',
            'renewsLabel' => $this->getRenewsLabel($subscription),
            'renewsIsPlaceholder' => $this->isRenewsPlaceholder($subscription),
            'expiresLabel' => $this->getExpiresLabel($subscription),
            'expiresIsPlaceholder' => $this->isExpiresPlaceholder($subscription),
            'canCancel' => $this->canCancel($subscription, $viewerId),
        ];
    }

    protected function getStatusClass(Subscription $subscription): string
    {
        return match ($subscription->status) {
            Subscription::ACTIVE_STATUS => 'success',
            Subscription::PENDING_STATUS,
            'update-needed',
            Subscription::CANCELED_STATUS => 'warning',
            Subscription::SUSPENDED_STATUS,
            Subscription::EXPIRED_STATUS,
            Subscription::FAILED_STATUS => 'danger',
            default => 'secondary',
        };
    }

    protected function getStatusLabel(Subscription $subscription): string
    {
        return match ($subscription->status) {
            Subscription::ACTIVE_STATUS => __('Active'),
            Subscription::PENDING_STATUS => __('Pending'),
            'update-needed' => __('Update needed'),
            Subscription::CANCELED_STATUS => __('Canceled'),
            Subscription::SUSPENDED_STATUS => __('Suspended'),
            Subscription::EXPIRED_STATUS => __('Expired'),
            Subscription::FAILED_STATUS => __('Failed'),
            default => ucfirst(__($subscription->status)),
        };
    }

    protected function getRenewsLabel(Subscription $subscription): string
    {
        if ($this->isRenewsPlaceholder($subscription)) {
            return '—';
        }

        return $subscription->expires_at->format('M d Y');
    }

    protected function getExpiresLabel(Subscription $subscription): string
    {
        if ($this->isExpiresPlaceholder($subscription)) {
            return '—';
        }

        return $subscription->expires_at->format('M d Y');
    }

    protected function isRenewsPlaceholder(Subscription $subscription): bool
    {
        return !$subscription->expires_at || $subscription->status === Subscription::CANCELED_STATUS;
    }

    protected function isExpiresPlaceholder(Subscription $subscription): bool
    {
        return !$subscription->expires_at || $subscription->status === Subscription::ACTIVE_STATUS;
    }

    protected function canCancel(Subscription $subscription, int $viewerId): bool
    {
        return $subscription->status === Subscription::ACTIVE_STATUS
            && (int) $subscription->sender_user_id === $viewerId;
    }
}
