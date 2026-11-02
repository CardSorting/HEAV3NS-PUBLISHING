<?php

namespace App\Http\Requests;

use App\Model\ManualPaymentMethod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveUserPayoutAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'payout_account_mode' => ['nullable', Rule::in(['create', 'edit'])],
            'payout_account_id' => [
                'required_if:payout_account_mode,edit', 'nullable', 'integer',
                Rule::exists('user_payout_accounts', 'id')->where(fn ($query) => $query->where('user_id', $this->user()?->id)),
            ],
            'manual_method_id' => [
                'required', 'integer',
                Rule::exists('manual_payment_methods', 'id')->where(fn ($query) => $query
                    ->where('direction', ManualPaymentMethod::WITHDRAWAL)->where('enabled', true)),
            ],
            'label' => 'nullable|string|max:191',
            'account_details' => ['nullable', 'array'],
            'isDefault' => 'nullable|boolean',
        ];
    }
}
