<?php

namespace App\Http\Requests;

use App\Rules\AllowedEmailDomains;
use App\Rules\IsEmailDelivrable;
use App\Rules\MatchOldPassword;
use App\Services\UserEmailChangeService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;

class UpdateUserEmailRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        app(UserEmailChangeService::class)->expireStalePendingChanges();

        $this->merge([
            'new_email' => mb_strtolower(trim((string) $this->input('new_email'))),
        ]);
    }

    public function rules(): array
    {
        $emailRules = [
            'required',
            'string',
            'email',
            'max:191',
            new AllowedEmailDomains,
            Rule::unique('users', 'email')->ignore($this->user()->id),
            function ($attribute, $value, $fail) {
                if (mb_strtolower((string) $value) === mb_strtolower((string) $this->user()->email)) {
                    $fail(__('Please enter a different email address.'));
                }
            },
        ];

        if (Schema::hasTable('pending_user_email_changes')) {
            $emailRules[] = Rule::unique('pending_user_email_changes', 'new_email')
                ->where(fn ($query) => $query->where('user_id', '!=', $this->user()->id));
        }

        if (getSetting('security.enforce_email_valid_check') && getSetting('security.email_abstract_api_key')) {
            $emailRules[] = new IsEmailDelivrable;
        }

        return [
            'new_email' => $emailRules,
            'email_change_password' => ['required', 'min:8', new MatchOldPassword],
        ];
    }

    public function attributes(): array
    {
        return [
            'new_email' => __('new email'),
            'email_change_password' => __('current password'),
        ];
    }
}
