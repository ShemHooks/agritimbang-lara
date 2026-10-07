<?php

namespace App\Http\Requests;

use App\Models\Breed;
use App\Models\Species;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreLivestockRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'species_id' => [
                'required',
                'uuid',
                'exists:species,id',
            ],

            'breed_id' => [
                'nullable',
                'uuid',
                'exists:breeds,id',
            ],

            'sex' => [
                'required',
                'in:male,female,unknown',
            ],

            'age_months' => [
                'nullable',
                'integer',
                'min:0',
                'max:600',
            ],

            /*
             * age_group is deliberately not accepted here.
             * It will be derived by the backend.
             */

            'reproductive_status' => [
                'nullable',
                'in:has_given_birth,never_given_birth,not_applicable,unknown',
            ],

            'parity' => [
                'nullable',
                'integer',
                'min:0',
            ],

            'sale_purpose' => [
                'required',
                'in:slaughter,breeding,fattening,work',
            ],

            'condition_score' => [
                'required',
                'integer',
                'between:1,5',
            ],

            'actual_weight_kg' => [
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
                $this->validateSpeciesAndBreed($validator);
                $this->validateReproductiveData($validator);
            },
        ];
    }

    private function validateSpeciesAndBreed(
        Validator $validator
    ): void {
        $species = Species::query()
            ->whereKey($this->input('species_id'))
            ->first();

        if ($species && !$species->is_active) {
            $validator->errors()->add(
                'species_id',
                'The selected species is inactive.'
            );
        }

        if (!$this->filled('breed_id')) {
            return;
        }

        $breed = Breed::query()
            ->whereKey($this->input('breed_id'))
            ->first();

        if (
            $breed &&
            $breed->species_id !== $this->input('species_id')
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

    private function validateReproductiveData(
        Validator $validator
    ): void {
        $sex = $this->input('sex');
        $status = $this->input('reproductive_status');
        $parity = $this->input('parity');

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
    }

    protected function prepareForValidation(): void
    {
        $sex = $this->input('sex');

        $data = [];

        /*
         * Resolve sensible biological defaults.
         */
        if ($sex === 'male') {
            $data['reproductive_status'] = 'not_applicable';
            $data['parity'] = null;
        }

        if (
            $sex === 'female' &&
            !$this->filled('reproductive_status')
        ) {
            $data['reproductive_status'] = 'unknown';
        }

        if (
            $sex === 'unknown' &&
            !$this->filled('reproductive_status')
        ) {
            $data['reproductive_status'] = 'unknown';
        }

        $this->merge($data);
    }
}