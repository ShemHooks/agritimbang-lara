<?php

namespace App\Http\Requests;

use App\Models\Breed;
use App\Models\Species;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateLivestockRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'species_id' => [
                'sometimes',
                'uuid',
                'exists:species,id',
            ],

            'breed_id' => [
                'sometimes',
                'nullable',
                'uuid',
                'exists:breeds,id',
            ],

            'sex' => [
                'sometimes',
                'in:male,female,unknown',
            ],

            'age_months' => [
                'sometimes',
                'nullable',
                'integer',
                'min:0',
                'max:600',
            ],

            'reproductive_status' => [
                'sometimes',
                'in:has_given_birth,never_given_birth,not_applicable,unknown',
            ],

            'parity' => [
                'sometimes',
                'nullable',
                'integer',
                'min:0',
            ],

            'sale_purpose' => [
                'sometimes',
                'in:slaughter,breeding,fattening,work',
            ],

            'condition_score' => [
                'sometimes',
                'integer',
                'between:1,5',
            ],

            'actual_weight_kg' => [
                'sometimes',
                'nullable',
                'numeric',
                'gt:0',
                'decimal:0,2',
            ],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                $livestock = $this->route('livestock');

                if (!$livestock) {
                    return;
                }

                $speciesId = $this->input(
                    'species_id',
                    $livestock->species_id
                );

                $breedId = $this->exists('breed_id')
                    ? $this->input('breed_id')
                    : $livestock->breed_id;

                $species = Species::query()
                    ->whereKey($speciesId)
                    ->first();

                if ($species && !$species->is_active) {
                    $validator->errors()->add(
                        'species_id',
                        'The selected species is inactive.'
                    );
                }

                if ($breedId) {
                    $breed = Breed::query()
                        ->whereKey($breedId)
                        ->first();

                    if (
                        $breed &&
                        $breed->species_id !== $speciesId
                    ) {
                        $validator->errors()->add(
                            'breed_id',
                            'The selected breed does not belong to the selected species.'
                        );
                    }

                    if ($breed && !$breed->is_active) {
                        $validator->errors()->add(
                            'breed_id',
                            'The selected breed is inactive.'
                        );
                    }
                }

                $sex = $this->input(
                    'sex',
                    $livestock->sex
                );

                $status = $this->input(
                    'reproductive_status',
                    $livestock->reproductive_status
                );

                $parity = $this->exists('parity')
                    ? $this->input('parity')
                    : $livestock->parity;

                if (
                    $sex === 'male' &&
                    $status !== 'not_applicable'
                ) {
                    $validator->errors()->add(
                        'reproductive_status',
                        'Reproductive status must be not_applicable for male livestock.'
                    );
                }

                if (
                    $sex === 'male' &&
                    $parity !== null
                ) {
                    $validator->errors()->add(
                        'parity',
                        'Parity is not applicable to male livestock.'
                    );
                }

                if (
                    $sex === 'female' &&
                    $status === 'not_applicable'
                ) {
                    $validator->errors()->add(
                        'reproductive_status',
                        'Female livestock cannot use not_applicable as reproductive status.'
                    );
                }

                if (
                    $sex === 'female' &&
                    $status === 'has_given_birth' &&
                    ($parity === null || (int) $parity < 1)
                ) {
                    $validator->errors()->add(
                        'parity',
                        'Parity must be at least 1 when the livestock has given birth.'
                    );
                }

                if (
                    $sex === 'female' &&
                    $status === 'never_given_birth' &&
                    $parity !== null &&
                    (int) $parity !== 0
                ) {
                    $validator->errors()->add(
                        'parity',
                        'Parity must be 0 when the livestock has never given birth.'
                    );
                }
            },
        ];
    }

    protected function prepareForValidation(): void
    {
        /*
         * If an update explicitly changes the animal to male,
         * normalize reproductive fields automatically.
         */
        if (
            $this->exists('sex') &&
            $this->input('sex') === 'male'
        ) {
            $this->merge([
                'reproductive_status' => 'not_applicable',
                'parity' => null,
            ]);
        }
    }
}