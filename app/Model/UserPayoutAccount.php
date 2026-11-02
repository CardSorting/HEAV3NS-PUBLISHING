<?php

namespace App\Model;

use App\Services\ManualPayments\ManualPaymentFields;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserPayoutAccount extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'manual_method_id',
        'account_details',
        'label',
        'is_default',
        'is_active',
        'extra_data',
        'last_used_at',
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'account_details' => 'encrypted:array',
        'is_default' => 'boolean',
        'is_active' => 'boolean',
        'extra_data' => 'array',
        'last_used_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /** @return BelongsTo<ManualPaymentMethod, $this> */
    public function manualMethod(): BelongsTo
    {
        return $this->belongsTo(ManualPaymentMethod::class, 'manual_method_id');
    }

    public function getMaskedDetailsAttribute(): string
    {
        $fields = collect($this->manualMethod->account_fields ?? [])->keyBy('key');

        return collect($this->account_details ?? [])
            ->filter(fn ($value) => is_scalar($value) && (string) $value !== '')
            ->map(function ($value, $key) use ($fields) {
                $value = (string) $value;
                $field = $fields->get($key);
                if (($field['type'] ?? null) === 'select') {
                    foreach ($field['options'] ?? [] as $option) {
                        if ((string) ($option['value'] ?? '') === $value && isset($option['label'])) {
                            return (string) $option['label'];
                        }
                    }
                }
                if (filter_var($value, FILTER_VALIDATE_EMAIL)) {
                    [$name, $domain] = explode('@', $value, 2);

                    return mb_substr($name, 0, 1).'••••@'.$domain;
                }

                return '••••'.mb_substr($value, -4);
            })
            ->take(2)
            ->implode(' • ');
    }

    public function getDisplayLabelAttribute(): string
    {
        return $this->label ?: $this->manualMethod?->name ?: __('Payout account');
    }

    public function getSummaryLinesAttribute(): array
    {
        return collect(ManualPaymentFields::snapshotItems($this->manualMethod->account_fields ?? [], $this->account_details ?? []))
            ->map(fn ($item) => $item['label'].': '.$item['value'])
            ->all();
    }
}
