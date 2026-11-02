<?php

namespace App\Http\Requests;

use App\Rules\AllowedHyperlinks;
use App\Rules\PPVMinMax;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class SaveNewMessageRequest extends FormRequest
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
            'price' => [new PPVMinMax('message')],
            'receiverIDs' => ['required', 'array', 'min:1', 'max:60'],
            'receiverIDs.*' => ['required', 'integer', 'distinct', 'exists:users,id'],
            'story_id' => ['nullable', 'integer', 'exists:stories,id'],
            'gif' => ['nullable', 'array:provider,id'],
        ];

        // Build message rules
        if (getSetting('websockets.driver') === 'pusher') {
            $rules['message'] = ['nullable', 'string', 'max:800', new AllowedHyperlinks()];
        } else {
            $rules['message'] = ['nullable', 'string', new AllowedHyperlinks()];
        }

        return $rules;
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            if (trim((string) $this->input('message')) === ''
                && !$this->filled('gif')
                && empty($this->input('attachments'))) {
                $validator->errors()->add('message', __('Please write a message.'));
            }
        });
    }
}
