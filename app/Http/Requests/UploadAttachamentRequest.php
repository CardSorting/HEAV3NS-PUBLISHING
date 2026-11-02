<?php

namespace App\Http\Requests;

use App\Providers\AttachmentServiceProvider;
use Illuminate\Foundation\Http\FormRequest;
use App\Services\Media\FileAttachmentService;
use Illuminate\Support\Facades\Validator;

class UploadAttachamentRequest extends FormRequest
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
        $type = $this->route('type') ?: request()->route('type');
        $allowedExtensions = AttachmentServiceProvider::getAllowedUploadExtensions($type);

        return [
            'file' => ['bail', 'required', 'file', 'max:'.(string) ((int) getSetting('media.max_file_upload_size') * 1024),
                function ($attribute, $file, $fail) use ($type, $allowedExtensions) {
                    if (array_key_exists(strtolower($file->getClientOriginalExtension()), FileAttachmentService::supportedFormats())) {
                        app(FileAttachmentService::class)->validate($file, $type);
                    } elseif (Validator::make(['file' => $file], ['file' => 'mimes:'.$allowedExtensions])->fails()) {
                        $fail(__('This file format is not allowed here.'));
                    }
                },
            ],
        ];
    }
}
