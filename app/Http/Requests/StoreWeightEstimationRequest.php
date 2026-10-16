<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreWeightEstimationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'heart_girth_cm' => [
                'nullable',
                'numeric',
                'gt:0',
                'max:500',
            ],

            'body_length_cm' => [
                'nullable',
                'numeric',
                'gt:0',
                'max:500',
            ],

            'rump_height_cm' => [
                'nullable',
                'numeric',
                'gt:0',
                'max:300',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'heart_girth_cm.gt' =>
                'Heart girth must be greater than zero.',

            'body_length_cm.gt' =>
                'Body length must be greater than zero.',

            'rump_height_cm.gt' =>
                'Rump height must be greater than zero.',
        ];
    }
}