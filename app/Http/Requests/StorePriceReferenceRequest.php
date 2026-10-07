<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StorePriceReferenceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'barangay_id' => ['nullable', 'uuid', 'exists:barangays,id'],

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
            'sale_purpose' => [
                'required',
                'in:slaughter,breeding,fattening,work',
            ],

            'price_per_kg' => [
                'required',
                'numeric',
                'gt:0',
                'decimal:0,2',
            ],

            'effective_from' => [
                'required',
                'date',
            ],

            'effective_to' => [
                'nullable',
                'date',
                'after_or_equal:effective_from',
            ],

            'source' => [
                'required',
                'string',
                'max:255',
            ],

            'remarks' => [
                'nullable',
                'string',
                'max:1000',
            ],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                $user = $this->user();

                if (!$user) {
                    return;
                }

                $officerProfile = $user->lguOfficerProfile;

                if (!$officerProfile) {
                    $validator->errors()->add(
                        'user',
                        'The authenticated user does not have an LGU officer profile.'
                    );

                    return;
                }

                if ($this->filled('barangay_id')) {
                    $belongsToMunicipality = \App\Models\Barangay::query()
                        ->whereKey($this->barangay_id)
                        ->where('municipality_id', $officerProfile->municipality_id)
                        ->where('is_active', true)
                        ->exists();

                    if (!$belongsToMunicipality) {
                        $validator->errors()->add(
                            'barangay_id',
                            'The selected barangay does not belong to your municipality or is inactive.'
                        );
                    }
                }

                $species = \App\Models\Species::query()
                    ->whereKey($this->species_id)
                    ->where('is_active', true)
                    ->exists();

                if (!$species) {
                    $validator->errors()->add(
                        'species_id',
                        'The selected species is inactive.'
                    );
                }

                if ($this->filled('breed_id')) {
                    $breedMatchesSpecies = \App\Models\Breed::query()
                        ->whereKey($this->breed_id)
                        ->where('species_id', $this->species_id)
                        ->where('is_active', true)
                        ->exists();

                    if (!$breedMatchesSpecies) {
                        $validator->errors()->add(
                            'breed_id',
                            'The selected breed does not belong to the selected species or is inactive.'
                        );
                    }
                }
            },
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'source' => $this->filled('source')
                ? trim($this->source)
                : null,

            'remarks' => $this->filled('remarks')
                ? trim($this->remarks)
                : null,
        ]);
    }
}