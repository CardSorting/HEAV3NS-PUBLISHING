<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Rahul900day\Captcha\Facades\Captcha;
use Rahul900day\Captcha\Rules\Captcha as CaptchaRule;

class SaveNewContactMessageRequest extends FormRequest
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

        $additionalRules = [];
        if(getSetting('security.captcha_driver') !== 'none'){
            $additionalRules = [
                Captcha::getResponseName() => [
                    'required',
                    new CaptchaRule(),
                ],
            ];
        }

        return array_merge([
            'subject' => 'required|min:5',
            'email' => 'required|email',
            'message' => 'required|min:10',
        ], $additionalRules);
    }
}
