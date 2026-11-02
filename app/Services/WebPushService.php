<?php

namespace App\Services;

use App\Model\PushSubscription;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;
use RuntimeException;

class WebPushService
{
    protected WebPush $client;

    public function __construct()
    {
        if (!getSetting('profiles.push_notifications_enabled')) {
            throw new RuntimeException('Web push is disabled.');
        }

        $contactEmail = getSetting('profiles.webpush_contact_email');
        $publicKey = getSetting('profiles.webpush_public_key');
        $privateKey = getSetting('profiles.webpush_private_key');

        if (!$contactEmail || !$publicKey || !$privateKey) {
            throw new RuntimeException('Web push is not fully configured.');
        }

        $this->client = new WebPush([
            'VAPID' => [
                'subject' => 'mailto:'.$contactEmail,
                'publicKey' => $publicKey,
                'privateKey' => $privateKey,
            ],
        ]);
    }

    public function sendToUser(int $userId, array $payload): void
    {
        $subscriptions = PushSubscription::query()
            ->where('user_id', $userId)
            ->get();

        $this->sendToSubscriptions($subscriptions, $payload);
    }

    public function sendToUsers(array $userIds, array $payload): void
    {
        if (!count($userIds)) {
            return;
        }

        $subscriptions = PushSubscription::query()
            ->whereIn('user_id', $userIds)
            ->get();

        $this->sendToSubscriptions($subscriptions, $payload);
    }

    protected function sendToSubscriptions(Collection $subscriptions, array $payload): void
    {
        if ($subscriptions->isEmpty()) {
            return;
        }

        $message = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        foreach ($subscriptions as $row) {
            $subscription = Subscription::create([
                'endpoint' => $row->endpoint,
                'publicKey' => $row->public_key,
                'authToken' => $row->auth_token,
                'contentEncoding' => $row->content_encoding ?: 'aes128gcm',
            ]);

            $this->client->queueNotification($subscription, $message);
        }

        foreach ($this->client->flush() as $report) {
            if ($report->isSuccess()) {
                continue;
            }

            $endpoint = (string) $report->getRequest()->getUri();

            Log::warning('Web push notification failed', [
                'endpoint' => $endpoint,
                'reason' => $report->getReason(),
            ]);

            if ($report->isSubscriptionExpired()) {
                PushSubscription::query()
                    ->where('endpoint', $endpoint)
                    ->delete();
            }
        }
    }

    public function buildPayload(
        string $title,
        ?string $body = null,
        ?string $url = null,
        array $extra = []
    ): array {
        return array_merge([
            'title' => $title,
            'body' => $body ?: '',
            'url' => $url ?: url('/'),
            'icon' => $this->resolveNotificationIcon(),
            'badge' => $this->resolveNotificationBadge(),
        ], $extra);
    }

    protected function resolveNotificationIcon(): string
    {
        if ($pwaIcon = $this->resolvePwaGeneratedIcon()) {
            return $pwaIcon;
        }

        if ($logo = $this->resolveSettingAssetUrl(getSetting('site.light_logo'))) {
            return $logo;
        }

        return url('/favicon.ico');
    }

    protected function resolveNotificationBadge(): string
    {
        if ($pwaBadge = $this->resolvePwaGeneratedIcon()) {
            return $pwaBadge;
        }

        if ($logo = $this->resolveSettingAssetUrl(getSetting('site.light_logo'))) {
            return $logo;
        }

        return url('/favicon.ico');
    }

    protected function resolvePwaGeneratedIcon(): ?string
    {
        if (!getSetting('site.pwa_enabled')) {
            return null;
        }

        $generator = app(PwaAssetGenerator::class);

        return $generator->generatedUrl('manifest-icon-192.png')
            ?: $generator->generatedUrl('manifest-icon-512.png');
    }

    protected function resolveSettingAssetUrl(?string $path): ?string
    {
        if (!$path) {
            return null;
        }

        if (filter_var($path, FILTER_VALIDATE_URL)) {
            return $path;
        }

        $disk = config('filesystems.default');

        if (Storage::disk($disk)->exists($path)) {
            return Storage::disk($disk)->url($path);
        }

        return null;
    }
}
