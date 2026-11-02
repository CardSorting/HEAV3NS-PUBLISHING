<?php

namespace App\Services\ConversionTracking;

class ConversionConfiguration
{
    public const DEFAULTS = [
        'enabled' => false,
        'method' => 'browser',
        'counting_mode' => 'first_site',
        'purchase_types' => ['subscription', 'post_unlock', 'message_unlock', 'stream_access'],
        'creator_scope' => 'all',
        'creator_ids' => [],
        'include_renewals' => false,
        'browser_code' => '',
        'postback_url' => '',
        'click_id_parameter' => 'click_id',
        'attribution_days' => 30,
        'test_mode' => true,
        'history_cutoff' => '',
        'purchase_history_cutoff' => '',
    ];

    public function __construct(private ?array $overrides = null) {}

    public function get(string $key): mixed
    {
        $default = self::DEFAULTS[$key] ?? null;

        return $this->overrides !== null
            ? ($this->overrides[$key] ?? $default)
            : getSetting('payments.conversion_tracking_'.$key, $default);
    }

    public function refresh(): void
    {
        if ($this->overrides === null && config('settings.admin_version') === 'v2') {
            app(\App\Settings\PaymentsSettings::class)->refresh();
            app(\App\Settings\ComplianceSettings::class)->refresh();
        }
    }

    public function enabled(): bool
    {
        if (!$this->get('enabled')) {
            return false;
        }
        // Marketing consent must be obtainable, including for server reporting.
        $consentAvailable = $this->overrides !== null
            ? ($this->overrides['consent_available'] ?? true)
            : getSetting('compliance.enable_cookies_box', false);

        return (bool) $consentAvailable;
    }

    public function method(): string
    {
        return in_array($this->get('method'), ['browser', 'postback'], true) ? $this->get('method') : '';
    }

    public function countingMode(): string
    {
        return (string) $this->get('counting_mode');
    }

    public function includesRenewals(): bool
    {
        return $this->countingMode() === 'every_purchase' && (bool) $this->get('include_renewals')
            && in_array('subscription', $this->selectedPurchaseTypes(), true);
    }

    public function selectedPurchaseTypes(): array
    {
        return array_values(array_intersect(
            ['subscription', 'post_unlock', 'message_unlock', 'stream_access', 'tip'],
            array_filter((array) $this->get('purchase_types'), 'is_string'),
        ));
    }

    /** History uses the selected categories, including prior renewals of a selected subscription. */
    public function eligibleTransactionTypes(): array
    {
        return array_values(array_filter(
            [...ConversionEligibility::PURCHASE_TYPES, \App\Model\Transaction::SUBSCRIPTION_RENEWAL],
            fn (string $type) => in_array(ConversionEligibility::purchaseType($type), $this->selectedPurchaseTypes(), true),
        ));
    }

    public function creatorAllowed(int $creatorId): bool
    {
        if ($this->get('creator_scope') === 'all') {
            return true;
        }

        return $this->get('creator_scope') === 'selected'
            && in_array($creatorId, array_map('intval', (array) $this->get('creator_ids')), true);
    }

    public function postbackUrl(): string
    {
        return (string) $this->get('postback_url');
    }

    public function browserCode(): string
    {
        return (string) $this->get('browser_code');
    }

    public function clickIdParameter(): string
    {
        $parameter = (string) $this->get('click_id_parameter');

        return preg_match('/\A[A-Za-z][A-Za-z0-9_]{0,63}\z/', $parameter) ? $parameter : 'click_id';
    }

    public function attributionDays(): int
    {
        return max(1, min(90, (int) $this->get('attribution_days')));
    }

    public function testMode(): bool
    {
        return (bool) $this->get('test_mode');
    }

    public function fingerprint(): string
    {
        // Do not deliver an old purchase to a newly configured destination.
        return hash('sha256', json_encode([
            $this->method(), $this->browserCode(), $this->postbackUrl(), $this->clickIdParameter(),
        ], JSON_THROW_ON_ERROR));
    }
}
