<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class EditPostCommentRequest extends FormRequest
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
            'message' => ['nullable', 'string', 'min:1', 'max:1000'],
            'gif' => ['nullable', 'array:provider,id'],
            'post_id' => ['required', 'integer', 'exists:posts,id'],
            'comment_id' => ['required', 'integer', 'exists:post_comments,id'],
        ];
    }
}
