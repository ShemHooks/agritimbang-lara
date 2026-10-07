<?php

namespace App\Services;

use App\Models\PriceReference;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PriceReferenceService
{
    public function getPriceReferences(
        User $user,
        array $filters = []
    ): LengthAwarePaginator {
        $municipalityId = $this->municipalityId($user);

        return PriceReference::query()
            ->where('municipality_id', $municipalityId)
            ->when(
                $filters['status'] ?? null,
                fn($query, $status) =>
                    $query->where('status', $status)
            )
            ->when(
                $filters['species_id'] ?? null,
                fn($query, $speciesId) =>
                    $query->where('species_id', $speciesId)
            )
            ->when(
                $filters['breed_id'] ?? null,
                fn($query, $breedId) =>
                    $query->where('breed_id', $breedId)
            )
            ->when(
                $filters['sale_purpose'] ?? null,
                fn($query, $salePurpose) =>
                    $query->where('sale_purpose', $salePurpose)
            )
            ->when(
                $filters['barangay_id'] ?? null,
                fn($query, $barangayId) =>
                    $query->where('barangay_id', $barangayId)
            )
            ->with([
                'municipality',
                'barangay',
                'species',
                'breed',
                'submitter',
            ])
            ->latest('effective_from')
            ->paginate(20);
    }

    public function getPriceReference(
        User $user,
        PriceReference $priceReference
    ): PriceReference {
        $this->ensureSameMunicipality($user, $priceReference);

        return $priceReference->load([
            'municipality',
            'barangay',
            'species',
            'breed',
            'submitter',
            'reviewer',
            'approver',
            'validationLogs.performer',
        ]);
    }

    public function create(
        User $user,
        array $data
    ): PriceReference {
        $municipalityId = $this->municipalityId($user);

        return DB::transaction(function () use ($user, $data, $municipalityId) {
            $priceReference = PriceReference::create([
                'municipality_id' => $municipalityId,
                'barangay_id' => $data['barangay_id'] ?? null,
                'species_id' => $data['species_id'],
                'breed_id' => $data['breed_id'] ?? null,

                'sale_purpose' => $data['sale_purpose'],

                'price_per_kg' => $data['price_per_kg'],

                'effective_from' => $data['effective_from'],
                'effective_to' => $data['effective_to'] ?? null,

                'source' => $data['source'],
                'remarks' => $data['remarks'] ?? null,

                'status' => 'draft',
            ]);

            return $priceReference->load([
                'municipality',
                'barangay',
                'species',
                'breed',
            ]);
        });
    }

    public function update(
        User $user,
        PriceReference $priceReference,
        array $data
    ): PriceReference {
        $this->ensureSameMunicipality($user, $priceReference);

        if ($priceReference->status !== 'draft') {
            throw ValidationException::withMessages([
                'status' =>
                    'Only draft price references can be edited.',
            ]);
        }

        $priceReference->update($data);

        return $priceReference->fresh()->load([
            'municipality',
            'barangay',
            'species',
            'breed',
        ]);
    }

    public function submit(
        User $user,
        PriceReference $priceReference
    ): PriceReference {
        $this->ensureSameMunicipality($user, $priceReference);

        if ($priceReference->status !== 'draft') {
            throw ValidationException::withMessages([
                'status' =>
                    'Only draft price references can be submitted.',
            ]);
        }

        return DB::transaction(function () use ($user, $priceReference) {
            $priceReference->update([
                'status' => 'pending_review',
                'submitted_by' => $user->id,
                'submitted_at' => now(),
            ]);

            $priceReference->validationLogs()->create([
                'from_status' => 'draft',
                'to_status' => 'pending_review',
                'action' => 'submitted',
                'performed_by' => $user->id,
            ]);

            return $priceReference->fresh()->load([
                'municipality',
                'barangay',
                'species',
                'breed',
                'submitter',
                'validationLogs.performer',
            ]);
        });
    }

    private function municipalityId(User $user): string
    {
        $municipalityId = $user->lguOfficerProfile?->municipality_id;

        if (!$municipalityId) {
            throw ValidationException::withMessages([
                'user' =>
                    'The authenticated user does not have an assigned municipality.',
            ]);
        }

        return $municipalityId;
    }

    private function ensureSameMunicipality(
        User $user,
        PriceReference $priceReference
    ): void {
        if (
            $priceReference->municipality_id !==
            $this->municipalityId($user)
        ) {
            abort(404);
        }
    }
}