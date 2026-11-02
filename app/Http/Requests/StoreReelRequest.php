<?php

namespace App\Http\Requests;

use App\Rules\AllowedHyperlinks;
use Illuminate\Foundation\Http\FormRequest;

class StoreReelRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'caption' => ['nullable', 'string', 'max:2200', new AllowedHyperlinks()],
            'is_public' => ['nullable', 'boolean'],
            'video_attachment_id' => ['required', 'string', 'exists:attachments,id'],
            'cover_attachment_id' => ['nullable', 'string', 'different:video_attachment_id', 'exists:attachments,id'],
            'sound_id' => ['nullable', 'integer', 'exists:sounds,id'],
            'overlay' => ['nullable', 'array'],
        ];
    }
}
