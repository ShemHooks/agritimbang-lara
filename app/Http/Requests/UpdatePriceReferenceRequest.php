<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdatePriceReferenceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'barangay_id' => [
                'sometimes',
                'nullable',
                'uuid',
                'exists:barangays,id',
            ],

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
            'sale_purpose' => [
                'sometimes',
                'in:slaughter,breeding,fattening,work',
            ],

            'price_per_kg' => [
                'sometimes',
                'numeric',
                'gt:0',
                'decimal:0,2',
            ],

            'effective_from' => [
                'sometimes',
                'date',
            ],

            'effective_to' => [
                'sometimes',
                'nullable',
                'date',
            ],

            'source' => [
                'sometimes',
                'string',
                'max:255',
            ],

            'remarks' => [
                'sometimes',
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
                $priceReference = $this->route('priceReference');

                if (!$priceReference) {
                    return;
                }

                $user = $this->user();
                $profile = $user?->lguOfficerProfile;

                if (!$profile) {
                    $validator->errors()->add(
                        'user',
                        'The authenticated user does not have an LGU officer profile.'
                    );

                    return;
                }

                $speciesId = $this->input(
                    'species_id',
                    $priceReference->species_id
                );

                $barangayId = $this->has('barangay_id')
                    ? $this->input('barangay_id')
                    : $priceReference->barangay_id;

                $breedId = $this->has('breed_id')
                    ? $this->input('breed_id')
                    : $priceReference->breed_id;

                if ($barangayId) {
                    $validBarangay = \App\Models\Barangay::query()
                        ->whereKey($barangayId)
                        ->where(
                            'municipality_id',
                            $profile->municipality_id
                        )
                        ->where('is_active', true)
                        ->exists();

                    if (!$validBarangay) {
                        $validator->errors()->add(
                            'barangay_id',
                            'The selected barangay is invalid for your municipality.'
                        );
                    }
                }

                $validSpecies = \App\Models\Species::query()
                    ->whereKey($speciesId)
                    ->where('is_active', true)
                    ->exists();

                if (!$validSpecies) {
                    $validator->errors()->add(
                        'species_id',
                        'The selected species is inactive.'
                    );
                }

                if ($breedId) {
                    $validBreed = \App\Models\Breed::query()
                        ->whereKey($breedId)
                        ->where('species_id', $speciesId)
                        ->where('is_active', true)
                        ->exists();

                    if (!$validBreed) {
                        $validator->errors()->add(
                            'breed_id',
                            'The selected breed does not belong to the selected species or is inactive.'
                        );
                    }
                }

                $from = $this->input(
                    'effective_from',
                    $priceReference->effective_from
                );

                $to = $this->has('effective_to')
                    ? $this->input('effective_to')
                    : $priceReference->effective_to;

                if ($from && $to && strtotime($to) < strtotime($from)) {
                    $validator->errors()->add(
                        'effective_to',
                        'The effective end date cannot be before the start date.'
                    );
                }
            },
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('source')) {
            $this->merge([
                'source' => trim((string) $this->source),
            ]);
        }

        if ($this->has('remarks')) {
            $this->merge([
                'remarks' => $this->remarks !== null
                    ? trim((string) $this->remarks)
                    : null,
            ]);
        }
    }
}