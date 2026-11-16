<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RegisterAuctionLivestockRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'livestock_ids' => ['required', 'array', 'min:1', 'max:50'],
            'livestock_ids.*' => ['required', 'uuid', 'distinct', 'exists:livestock_records,id'],
        ];
    }
}
