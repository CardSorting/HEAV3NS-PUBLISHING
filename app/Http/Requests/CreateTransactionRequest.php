<?php

namespace App\Http\Requests;

use App\Model\ManualPaymentMethod;
use App\Model\Transaction;
use App\Model\User;
use App\Providers\ProfileMonetizationServiceProvider;
use App\Services\Payments\CheckoutBilling;
use Illuminate\Foundation\Http\FormRequest;
use App\Services\ManualPayments\ManualPaymentDocuments;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class CreateTransactionRequest extends FormRequest
{
    private ?ManualPaymentMethod $resolvedManualDepositMethod = null;

    protected function prepareForValidation(): void
    {
        CheckoutBilling::stripUncollectedDetails($this);
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
            'provider' => ['required', Rule::in(Transaction::NEW_PAYMENT_PROVIDERS)],
            'transaction_type' => 'required',
            'manual_method_id' => 'nullable|integer',
            'submitted_fields' => 'nullable|array',
        ] + CheckoutBilling::validationRules(true);
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            CheckoutBilling::validateConfiguration($validator);
            $this->validateManualDeposit($validator);
            $transactionType = $this->get('transaction_type');

            if (!in_array($transactionType, ProfileMonetizationServiceProvider::subscriptionTypes(), true)) {
                return;
            }

            $recipientUser = User::query()->find($this->get('recipient_user_id'));
            if (!ProfileMonetizationServiceProvider::canReceiveProfileSubscriptions($recipientUser)) {
                $validator->errors()->add(
                    'transaction_type',
                    __('Profile subscriptions are disabled for this profile.')
                );
            }
        });
    }

    public function manualDepositMethod(): ?ManualPaymentMethod
    {
        if ($this->resolvedManualDepositMethod) {
            return $this->resolvedManualDepositMethod;
        }
        if (!Schema::hasTable('manual_payment_methods') || !$this->input('manual_method_id')) {
            return null;
        }

        return $this->resolvedManualDepositMethod = ManualPaymentMethod::query()
            ->whereKey($this->input('manual_method_id'))
            ->where('direction', ManualPaymentMethod::DEPOSIT)
            ->where('enabled', true)
            ->first();
    }

    private function validateManualDeposit(Validator $validator): void
    {
        if ($this->input('provider') !== Transaction::MANUAL_PROVIDER) {
            return;
        }
        if ($this->input('transaction_type') !== Transaction::DEPOSIT_TYPE) {
            $validator->errors()->add('provider', __('Manual methods are available for deposits only.'));
            return;
        }
        if ($validator->errors()->isNotEmpty()) {
            return;
        }

        if (!getSetting('payments.allow_manual_payments')) {
            $validator->errors()->add('provider', __('This payment method is unavailable.'));
            return;
        }

        $method = $this->manualDepositMethod();
        if (!$method) {
            $validator->errors()->add('manual_method_id', __('Please select an available deposit method.'));
            return;
        }

        $fields = [];
        try {
            $fields = app(ManualPaymentDocuments::class)->validateSubmission($method, $this->input('submitted_fields', []) ?? [], (int) $this->user()->id);
            $this->merge(['submitted_fields' => $fields]);
        } catch (ValidationException $exception) {
            foreach ($exception->errors() as $key => $errors) {
                foreach ($errors as $error) {
                    $validator->errors()->add($key, $error);
                }
            }
        }

        if (ManualPaymentDocuments::countSubmittedFiles($method, $fields) < (int) ($method->submission_settings['minimum_attachments'] ?? 0)) {
            $validator->errors()->add('submitted_fields', __('Please attach the required payment proof.'));
        }
    }
}
