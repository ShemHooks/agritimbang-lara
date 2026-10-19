<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateAuctionHouseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'name' => [
                'sometimes',
                'required',
                'string',
                'max:255',
            ],
            'barangay_id' => [
                'sometimes',
                'nullable',
                'uuid',
                'exists:barangays,id',
            ],
            'address' => [
                'sometimes',
                'nullable',
                'string',
                'max:255',
            ],
        ];
    }
}
