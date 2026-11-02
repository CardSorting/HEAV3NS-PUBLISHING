<?php

namespace App\Http\Requests;

use App\Rules\AllowedHyperlinks;
use App\Rules\PPVMinMax;
use Illuminate\Foundation\Http\FormRequest;

class SavePostRequest extends FormRequest
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
        return [
            'text' => ['nullable', 'string', new AllowedHyperlinks()],
            'attachments' => 'nullable|array',
            'price' => [new PPVMinMax('post')],
        ];
    }

    /**
     * Configure the validator instance.
     *
     * @param \Illuminate\Validation\Validator $validator
     */
    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            $text = $this->input('text');
            $attachments = $this->input('attachments');
            $minText = (int) getSetting('feed.min_post_description');
            $allowTextOnlyPPV = getSetting('compliance.allow_text_only_ppv');

            $hasText = is_string($text) && strlen(trim($text)) >= $minText;
            $hasAttachments = is_array($attachments) && count($attachments) > 0;

            // General rule: Must have either text or attachments
            if (!$hasText && !$hasAttachments) {
                $validator->errors()->add('post', __('post_must_have_text_or_attachments'));
            }

            // Compliance setting: enforce presence if text-only PPV is not allowed
            if (!$hasText && !$hasAttachments && !$allowTextOnlyPPV) {
                $validator->errors()->add('post', __('post_must_have_text_or_attachments_restricted'));
            }

            // Enforce minimum text length if no media is present
            if (!$hasAttachments && strlen(trim($text)) < $minText) {
                $validator->errors()->add('text', __('text_min_if_no_media', ['min' => $minText]));
            }
        });
    }
}
