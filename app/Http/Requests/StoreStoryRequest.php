<?php

namespace App\Http\Requests;

use App\Rules\AllowedHyperlinks;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreStoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $maxText = (int) (getSetting('stories.max_text_length') ?: 2000);

        return [
            'mode'         => ['required', Rule::in(['media', 'text'])],
            'text'         => ['nullable', 'string', 'max:'.$maxText],

            'overlay_x'    => ['nullable', 'numeric', 'min:0', 'max:1'],
            'overlay_y'    => ['nullable', 'numeric', 'min:0', 'max:1'],

            'bg_preset'    => ['nullable', 'string', 'max:32'],
            'is_public'    => ['nullable', 'boolean'],

            'attachmentID' => ['nullable', 'string', 'exists:attachments,id'],

            'link_url'     => ['nullable', 'url', 'max:2048', new AllowedHyperlinks()],
            'link_text'    => ['nullable', 'string', 'max:80'],

            'sound_id'     => ['nullable', 'integer', 'exists:sounds,id'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $mode = $this->input('mode');

            if ($mode === 'media' && empty($this->input('attachmentID'))) {
                $validator->errors()->add('attachmentID', __('Please upload a photo or video first.'));
            }

            if ($mode === 'text' && empty(trim((string) $this->input('text', '')))) {
                $validator->errors()->add('text', __('Please write something first.'));
            }

            // If link_text provided without link_url -> error (or you can silently ignore in controller)
            $linkUrl = trim((string) $this->input('link_url', ''));
            $linkText = trim((string) $this->input('link_text', ''));

            if ($linkUrl === '' && $linkText !== '') {
                $validator->errors()->add('link_url', __('Invalid link URL'));
            }
        });
    }
}
