<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PreviewManualValuationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'species_id' => ['required', 'uuid', 'exists:species,id'],
            'breed_id' => ['nullable', 'uuid', 'exists:breeds,id'],
            'sex' => ['required', 'in:male,female,unknown'],
            'age_months' => ['nullable', 'integer', 'min:0', 'max:600'],
            'sale_purpose' => ['required', 'in:slaughter,breeding,fattening,work'],
            'condition_score' => ['required', 'integer', 'between:1,5'],
            'reproductive_status' => ['nullable', 'in:unknown,has_given_birth,never_given_birth,not_applicable'],
            'parity' => ['required_if:reproductive_status,has_given_birth', 'nullable', 'integer', 'min:1', 'max:50'],
            'estimated_weight_kg' => ['required', 'numeric', 'gt:0', 'max:10000'],
            'weight_source' => ['nullable', 'in:estimated,manual'],
            'frame_category' => ['nullable', 'string', 'max:50'],
        ];
    }
}
