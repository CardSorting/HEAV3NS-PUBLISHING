<?php

namespace App\Http\Requests;

use App\Rules\AllowedHyperlinks;
use App\Rules\MaxLengthMarkdown;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class UpdateUserProfileSettingsRequest extends FormRequest
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
            'name' => 'required|max:100',
            'username' => 'required|string|alpha_dash|max:255|unique:users,username,'.Auth::user()->id,
            'location' => 'max:500',
            'social_links' => ['nullable', 'array'],
            'social_links.*.platform' => ['nullable', 'string', 'max:50'],
            'social_links.*.value' => ['nullable', 'string', 'max:2048'],
            'website' => ['nullable', 'string', 'max:2048', new AllowedHyperlinks()],

            // AI Settings
            'ai' => ['nullable', 'array'],
            'ai.tone' => ['nullable', Rule::in(['neutral', 'playful', 'flirty', 'classy', 'bold', 'mysterious'])],
            'ai.length' => ['nullable', Rule::in(['short', 'medium'])],
            'ai.share_profile' => ['nullable', 'boolean'],
            'ai.traits' => ['nullable', 'array', 'max:5'],
            'ai.traits.*' => ['string', 'max:24'],

        ];

        $maxProfileBioLength = (int) getSetting('profiles.max_profile_bio_length');

        if ($maxProfileBioLength !== 0) {
            if (getSetting('profiles.allow_profile_bio_markdown')) {
                $rules['bio'] = [
                    new MaxLengthMarkdown,
                    new AllowedHyperlinks(),
                ];
            } else {
                $rules['bio'] = [
                    'max:'.$maxProfileBioLength,
                    new AllowedHyperlinks(),
                ];
            }
        } else {
            $rules['bio'] = ['nullable', 'string', new AllowedHyperlinks()];
        }

        return $rules;
    }
}
