<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SaveReelCommentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'reel_id' => ['required', 'integer', 'exists:reels,id'],
            'parent_id' => ['nullable', 'integer', 'exists:reel_comments,id'],
            'reply_to_id' => ['nullable', 'integer', 'exists:reel_comments,id'],
            'message' => ['required_without:gif', 'nullable', 'string', 'min:1', 'max:1000'],
            'gif' => ['nullable', 'array:provider,id'],
        ];
    }
}
