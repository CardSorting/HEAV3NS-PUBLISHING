<?php

namespace App\Model;

use App\Services\ManualPayments\ManualPaymentFields;
use App\Services\ManualPayments\ManualPaymentReceivingImages;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/** @phpstan-consistent-constructor */
class ManualPaymentMethod extends Model
{
    public const DEPOSIT = 'deposit';
    public const WITHDRAWAL = 'withdrawal';

    protected $fillable = [
        'direction', 'key', 'name', 'enabled', 'instructions', 'receiving_details',
        'account_fields', 'request_fields', 'submission_settings', 'sort_order',
    ];

    protected $casts = [
        'enabled' => 'boolean',
        'receiving_details' => 'array',
        'account_fields' => 'array',
        'request_fields' => 'array',
        'submission_settings' => 'array',
        'sort_order' => 'integer',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $method): void {
            $errors = [];
            if (!is_string($method->name) || trim($method->name) === '' || mb_strlen($method->name) > 150) {
                $errors['name'] = 'Enter a method name of at most 150 characters.';
            }
            if (!in_array($method->direction, [self::DEPOSIT, self::WITHDRAWAL], true)) {
                $errors['direction'] = 'Select deposits or withdrawals.';
            }
            if ($method->instructions !== null && (!is_string($method->instructions) || mb_strlen($method->instructions) > 10000)) {
                $errors['instructions'] = 'Instructions must contain at most 10000 characters.';
            }
            foreach (['receiving_details', 'account_fields', 'request_fields', 'submission_settings'] as $column) {
                if ($method->{$column} !== null && !is_array($method->{$column})) {
                    $errors[$column] = 'Enter a valid configuration list.';
                }
            }
            if ($errors !== []) {
                throw ValidationException::withMessages($errors);
            }

            $method->name = trim($method->name);

            // Keys identify legacy templates; submitted forms select the immutable record ID.
            if (!$method->key) {
                $method->key = substr(Str::slug($method->name, '_') ?: 'method', 0, 80).'_'.Str::lower(Str::random(8));
            }
            if (!is_string($method->key) || !preg_match('/^[a-z][a-z0-9_]{0,99}$/D', $method->key)) {
                throw ValidationException::withMessages(['key' => 'Enter a valid method key of at most 100 characters.']);
            }

            if ($method->exists && $method->isDirty('direction')) {
                throw ValidationException::withMessages(['direction' => 'An existing method cannot move between deposits and withdrawals.']);
            }
            if ($method->exists && $method->isDirty('key')) {
                throw ValidationException::withMessages(['key' => 'The method key cannot change after creation.']);
            }

            $method->account_fields = ManualPaymentFields::validateDefinitions($method->account_fields ?? [], 'account_fields');
            $method->request_fields = ManualPaymentFields::validateDefinitions($method->request_fields ?? [], 'request_fields', true);

            $receiving = [];
            $keys = [];
            if (count($method->receiving_details ?? []) > 50) {
                $errors['receiving_details'] = 'A method can contain at most 50 receiving details.';
            }
            foreach (array_values($method->receiving_details ?? []) as $index => $row) {
                $base = 'receiving_details.'.$index;
                if (!is_array($row)) {
                    $errors[$base] = 'Enter a valid receiving detail.';
                    continue;
                }
                $key = $row['key'] ?? '';
                $label = $row['label'] ?? '';
                $value = $row['value'] ?? '';
                $type = $row['type'] ?? 'text';
                if (!in_array($type, ['text', 'image'], true) || ($type === 'image' && $method->direction !== self::DEPOSIT)) {
                    $errors[$base.'.type'] = __('Select text or an image for deposit receiving details.');
                }
                if (!is_string($key) || !preg_match('/^[a-z][a-z0-9_]{0,63}$/D', $key)) {
                    $errors[$base.'.key'] = 'Enter a valid receiving detail key.';
                } elseif (isset($keys[$key])) {
                    $errors[$base.'.key'] = 'Each receiving detail must have a different key.';
                } else {
                    $keys[$key] = true;
                }
                if (!is_string($label) || trim($label) === '' || mb_strlen($label) > 150) {
                    $errors[$base.'.label'] = 'Enter a label of at most 150 characters.';
                }
                if (!is_string($value) || ($method->enabled && trim($value) === '') || mb_strlen($value) > 2000) {
                    $errors[$base.'.value'] = 'Enter a receiving detail of at most 2000 characters.';
                }
                if ($type === 'image' && is_string($value) && trim($value) !== '' && !ManualPaymentReceivingImages::metadata($value)) {
                    $errors[$base.'.value'] = __('Choose an uploaded receiving image.');
                }
                if (is_string($key) && is_string($label) && is_string($value)) {
                    $receiving[] = ['key' => $key, 'label' => trim($label), 'value' => trim($value)]
                        + (array_key_exists('type', $row) ? ['type' => $type] : []);
                }
            }

            $settings = $method->submission_settings ?? [];
            if (array_key_exists('minimum_attachments', $settings)) {
                $minimum = filter_var($settings['minimum_attachments'], FILTER_VALIDATE_INT);
                if ($minimum === false || $minimum < 0) {
                    $errors['submission_settings.minimum_attachments'] = 'Enter a nonnegative whole number of required receipts.';
                }
                $settings['minimum_attachments'] = (int) $minimum;
            }
            if ($errors !== []) {
                throw ValidationException::withMessages($errors);
            }
            $method->receiving_details = $receiving;
            $method->submission_settings = array_intersect_key($settings, ['minimum_attachments' => true]);
        });
    }

    public function hasLinkedRecords(): bool
    {
        if (!$this->exists) {
            return false;
        }

        foreach (['user_payout_accounts', 'withdrawals', 'transactions'] as $table) {
            if (DB::table($table)->where('manual_method_id', $this->getKey())->exists()) {
                return true;
            }
        }

        return false;
    }

    /** @return Collection<int, static> */
    public static function enabledFor(string $direction): Collection
    {
        if (!Schema::hasTable((new static)->getTable())) {
            return new Collection;
        }

        return static::query()->where('direction', $direction)->where('enabled', true)
            ->orderBy('sort_order')->orderBy('id')->get();
    }

    public static function findForDirection(int|string|null $id, string $direction, bool $enabledOnly = true): ?self
    {
        if (!preg_match('/^[1-9][0-9]*$/D', (string) $id)
            || !Schema::hasTable((new static)->getTable())) {
            return null;
        }

        return static::query()->where('direction', $direction)
            ->when($enabledOnly, fn ($query) => $query->where('enabled', true))
            ->find($id);
    }
}
