<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;
use App\Services\Payments\CheckoutBilling;

class QuoteTaxesRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        CheckoutBilling::stripUncollectedDetails($this);
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(fn (Validator $validator) => CheckoutBilling::validateConfiguration($validator));
    }

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
        return [
            'recipient_user_id' => '',
            'post_id' => '',
            'taxes' => '',
            'amount' => 'required',
            'transaction_type' => 'required',
        ] + CheckoutBilling::validationRules(false);
    }
}
