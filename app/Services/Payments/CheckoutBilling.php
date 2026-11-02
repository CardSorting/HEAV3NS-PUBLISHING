<?php

namespace App\Services\Payments;

use App\Model\Tax;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class CheckoutBilling
{
    public const FULL = 'full';
    public const NONE = 'none';
    public const FIELDS = [
        'first_name', 'last_name', 'billing_address', 'country', 'state', 'postcode', 'city',
    ];

    public static function collectsDetails(): bool
    {
        return getSetting('payments.billing_details_collection', self::FULL) !== self::NONE;
    }

    public static function hasCountrySpecificTaxes(): bool
    {
        return Tax::query()->whereHas('countries', fn ($query) => $query->where('countries.name', '!=', 'All'))->exists();
    }

    public static function configurationErrors(string $mode, bool $invoicesEnabled): array
    {
        if (!in_array($mode, [self::FULL, self::NONE], true)) {
            return ['billing_details_collection' => __('Choose a valid billing details collection mode.')];
        }

        if ($mode !== self::NONE) {
            return [];
        }

        $errors = [];
        if ($invoicesEnabled) {
            $errors['invoices_enabled'] = __('Disable site-generated invoices on the Invoices tab before choosing Don\'t collect billing details.');
        }
        if (self::hasCountrySpecificTaxes()) {
            $errors['billing_details_collection'] = __('Don\'t collect billing details supports only All countries taxes. Remove country-specific tax assignments or use Full details.');
        }

        return $errors;
    }

    public static function validateConfiguration(Validator $validator): void
    {
        if (self::collectsDetails()) {
            return;
        }

        foreach (self::configurationErrors(self::NONE, (bool) getSetting('payments.invoices_enabled')) as $field => $message) {
            $validator->errors()->add($field, __('Checkout billing is not configured correctly. Please contact support.'));
        }
    }

    public static function stripUncollectedDetails(Request $request): void
    {
        if (self::collectsDetails()) {
            return;
        }

        foreach (self::FIELDS as $field) {
            $request->request->remove($field);
            $request->query->remove($field);
            if ($request->isJson()) {
                $request->json()->remove($field);
            }
        }
    }

    public static function validationRules(bool $forPayment): array
    {
        if (!self::collectsDetails()) {
            return array_fill_keys(self::FIELDS, ['exclude']);
        }

        $rules = [];
        foreach (self::FIELDS as $field) {
            $rules[$field] = [
                $forPayment || $field === 'country' ? 'required' : 'nullable',
                'string',
                $field === 'billing_address' ? 'min:3' : 'min:1',
                'max:255',
            ];
        }
        $rules['country'][] = Rule::exists('countries', 'name')->where(fn ($query) => $query->where('name', '!=', 'All'));

        return $rules;
    }
}
