<?php

namespace App\Http\Requests;

use App\Rules\MatchOldPassword;
use Illuminate\Foundation\Http\FormRequest;

class DeleteAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'delete_password' => ['required', 'min:8', new MatchOldPassword],
            'delete_confirmation' => ['accepted'],
            'reason' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function attributes(): array
    {
        return [
            'delete_password' => __('password'),
            'delete_confirmation' => __('confirmation'),
        ];
    }
}
