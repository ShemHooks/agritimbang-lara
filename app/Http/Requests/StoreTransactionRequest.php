<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreTransactionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'buyer_name' => [
                'required',
                'string',
                'max:255',
            ],

            'buyer_phone' => [
                'nullable',
                'string',
                'max:30',
            ],

            'transaction_date' => [
                'required',
                'date',
            ],

            'remarks' => [
                'nullable',
                'string',
                'max:2000',
            ],

            'items' => [
                'required',
                'array',
                'min:1',
                'max:50',
            ],

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
                'max:999999999999.99',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'items.min' =>
                'At least one livestock must be included.',

            'items.*.livestock_id.distinct' =>
                'The same livestock cannot appear twice.',

            'items.*.valuation_id.exists' =>
                'The selected valuation does not exist.',

            'items.*.actual_selling_price.gt' =>
                'The selling price must be greater than zero.',
        ];
    }
}
