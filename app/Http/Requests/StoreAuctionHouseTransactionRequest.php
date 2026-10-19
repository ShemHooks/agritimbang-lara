<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreAuctionHouseTransactionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'auction_house_id' => ['required', 'uuid', 'exists:auction_houses,id'],
            'farmer_id' => ['required', 'uuid', 'exists:farmer_profiles,id'],
            'buyer_name' => ['required', 'string', 'max:255'],
            'buyer_phone' => ['nullable', 'string', 'max:30'],
            'transaction_date' => ['required', 'date', 'before_or_equal:now'],
            'acknowledgement_reference' => [
                'required',
                'string',
                'max:255',
            ],
            'acknowledgement_notes' => [
                'nullable',
                'string',
                'max:2000',
            ],
            'remarks' => ['nullable', 'string', 'max:2000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.livestock_id' => [
                'required',
                'uuid',
                'distinct',
                'exists:livestock_records,id',
            ],
            'items.*.valuation_id' => [
                'required',
                'uuid',
                'exists:valuations,id',
            ],
            'items.*.actual_selling_price' => [
                'required',
                'numeric',
                'gt:0',
                'decimal:0,2',
            ],
        ];
    }
}
