<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateAuctionScheduleRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'required', 'string', 'max:180'],
            'auction_date' => ['sometimes', 'required', 'date', 'after_or_equal:today'],
            'start_time' => ['sometimes', 'required', 'date_format:H:i'],
            'expected_end_time' => ['sometimes', 'required', 'date_format:H:i'],
            'auction_house_id' => ['sometimes', 'required', 'uuid', 'exists:auction_houses,id'],
            'registration_opens_at' => ['sometimes', 'required', 'date'],
            'registration_closes_at' => ['sometimes', 'required', 'date', 'after:registration_opens_at'],
            'description' => ['nullable', 'string', 'max:3000'],
        ];
    }
}
