<?php

namespace App\Http\Requests;

use App\Model\ManualPaymentMethod;
use App\Providers\PaymentsServiceProvider;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateWithdrawalRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        $rules = [
            'amount' => ['required', 'numeric', 'between:'.PaymentsServiceProvider::getWithdrawalMinimumAmount().','.PaymentsServiceProvider::getWithdrawalMaximumAmount()],
            'submitted_fields' => ['nullable', 'array'],
            'method' => [
                'required_without:manual_method_id', 'nullable', 'string', Rule::prohibitedIf($this->filled('manual_method_id')),
                Rule::in(getSetting('payments.withdrawal_enable_stripe_connect') ? [PaymentsServiceProvider::WITHDRAWAL_METHOD_STRIPE_CONNECT] : []),
            ],
            'manual_method_id' => [
                'required_without:method', 'nullable', 'integer', Rule::prohibitedIf($this->filled('method')),
                Rule::exists('manual_payment_methods', 'id')->where(fn ($query) => $query
                    ->where('direction', ManualPaymentMethod::WITHDRAWAL)->where('enabled', true)),
            ],
            'payout_account_id' => [
                'required_with:manual_method_id', 'nullable',
                'integer',
                Rule::exists('user_payout_accounts', 'id')->where(fn ($query) => $query
                    ->where('user_id', $this->user()?->id)
                    ->where('is_active', true)),
            ],
        ];

        return $rules;
    }

    public function messages()
    {
        return [
            'payout_account_id.required' => __('Please select a saved payout account for this withdrawal method.'),
            'payout_account_id.exists' => __('The selected payout account is no longer available.'),
        ];
    }
}
