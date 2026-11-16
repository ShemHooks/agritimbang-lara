<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreBuyerInterestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'first_name' => ['required', 'string', 'max:80'],
            'last_name' => ['required', 'string', 'max:80'],
            'mobile_number' => ['required', 'string', 'max:20'],
            'buyer_type' => ['required', 'in:Individual,Business,individual,business'],
            'livestock_ids' => ['required', 'array', 'min:1'],
            'livestock_ids.*' => ['required', 'uuid', 'distinct'],
            'interest_status' => ['required', 'in:Active,Withdrawn,active,withdrawn'],
            'attendance_intention' => ['required', 'in:Yes — Will attend,No — Cannot attend,Tentative,yes,no,tentative'],
            'contact_consent' => ['required', 'accepted'],
        ];
    }

    public function attributes(): array
    {
        return [
            'contact_consent' => 'Contact Consent',
            'livestock_ids' => 'Livestock Interested',
        ];
    }
}
