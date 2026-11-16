<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreAuctionScheduleRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:180'],
            'auction_date' => ['required', 'date', 'after_or_equal:today'],
            'start_time' => ['required', 'date_format:H:i'],
            'expected_end_time' => ['required', 'date_format:H:i'],
            'auction_house_id' => ['required', 'uuid', 'exists:auction_houses,id'],
            'registration_opens_at' => ['required', 'date'],
            'registration_closes_at' => ['required', 'date', 'after:registration_opens_at'],
            'description' => ['nullable', 'string', 'max:3000'],
            'status' => ['sometimes', 'in:draft,published'],
        ];
    }
}
