<?php

namespace App\Http\Requests;

use App\Model\UserTax;
use Illuminate\Foundation\Http\FormRequest;

class AddUserTaxesRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'legalName' => 'required|max:191|min:2',
            'taxType' => 'required|in:'.implode(',', UserTax::ALLOWED_TAX_TYPES),
            'dateOfBirth' => 'required|date',
            'issuingCountry' => 'required',
            'taxIdentificationNumber' => 'required|max:191',
            'vatNumber' => 'max:191',
            'primaryAddress' => 'required|max:500',
        ];
    }
}
