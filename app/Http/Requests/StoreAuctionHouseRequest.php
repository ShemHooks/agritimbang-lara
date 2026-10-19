<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreAuctionHouseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'name' => [
                'required',
                'string',
                'max:255',
            ],
            'barangay_id' => [
                'nullable',
                'uuid',
                'exists:barangays,id',
            ],
            'address' => [
                'nullable',
                'string',
                'max:255',
            ],
        ];
    }
}
