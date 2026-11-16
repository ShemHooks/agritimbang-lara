<?php

namespace App\Http\Requests;

use App\Models\Breed;
use App\Models\Species;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class PreviewManualWeightEstimationRequest extends FormRequest
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
            'age_group' => ['nullable', 'string', 'max:50'],
            'heart_girth_cm' => ['nullable', 'numeric', 'gt:0', 'max:500'],
            'body_length_cm' => ['nullable', 'numeric', 'gt:0', 'max:500'],
            'rump_height_cm' => ['nullable', 'numeric', 'gt:0', 'max:300'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $species = Species::query()->find($this->input('species_id'));

                if (!$species) {
                    return;
                }

                if (!$species->is_active) {
                    $validator->errors()->add('species_id', 'The selected species is inactive.');
                }

                $breed = null;
                if ($this->filled('breed_id')) {
                    $breed = Breed::query()->find($this->input('breed_id'));

                    if ($breed && (string) $breed->species_id !== (string) $species->id) {
                        $validator->errors()->add('breed_id', 'The selected breed does not belong to the selected species.');
                    }

                    if ($breed && !$breed->is_active) {
                        $validator->errors()->add('breed_id', 'The selected breed is inactive.');
                    }
                }

                $code = strtoupper((string) $species->code);

                if ($code === 'GOAT' && !$this->filled('age_months') && !$this->filled('age_group')) {
                    $validator->errors()->add('age_months', 'This goat record needs an age or age group before its weight can be estimated.');
                }

                // Hog breed is optional: FormulaProfileResolver selects the
                // HOG_COMMERCIAL_FALLBACK when breed is missing/unspecified.
                if (
                    !$this->filled('heart_girth_cm') &&
                    !$this->filled('body_length_cm') &&
                    !$this->filled('rump_height_cm')
                ) {
                    $validator->errors()->add('measurements', 'Enter the required livestock measurements.');
                }
            },
        ];
    }
}
