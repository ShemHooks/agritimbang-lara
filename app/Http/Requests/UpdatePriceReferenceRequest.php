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

                $breedId = $this->input('breed_id', $priceReference->breed_id);

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


            },
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['barangay_id' => null]);

        $effectiveFrom = $this->input(
            'effective_from',
            $this->route('priceReference')?->effective_from?->toDateString()
        );
        if ($effectiveFrom) {
            $this->merge([
                'effective_to' => \Carbon\CarbonImmutable::parse($effectiveFrom)
                    ->endOfWeek(\Carbon\Carbon::SUNDAY)
                    ->toDateString(),
            ]);
        }

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