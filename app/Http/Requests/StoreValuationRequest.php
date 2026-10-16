<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreValuationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'weight_estimation_id' => [
                'nullable',
                'uuid',
                Rule::exists('weight_estimations', 'id'),
            ],
        ];
    }
}
