<?php

namespace App\Services;

use App\Models\PriceReference;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PriceValidationService
{

    private function ensureNoOfficialConflict(
        PriceReference $priceReference
    ): void {
        $query = PriceReference::query()
            ->where('id', '!=', $priceReference->id)
            ->where('status', 'official')
            ->where(
                'municipality_id',
                $priceReference->municipality_id
            )
            ->where(
                'species_id',
                $priceReference->species_id
            )
            ->where(
                'sale_purpose',
                $priceReference->sale_purpose
            );

        if ($priceReference->barangay_id === null) {
            $query->whereNull('barangay_id');
        } else {
            $query->where(
                'barangay_id',
                $priceReference->barangay_id
            );
        }

        if ($priceReference->breed_id === null) {
            $query->whereNull('breed_id');
        } else {
            $query->where(
                'breed_id',
                $priceReference->breed_id
            );
        }

        $query
            ->where(function ($query) use ($priceReference) {
                if ($priceReference->effective_to !== null) {
                    $query->where(
                        'effective_from',
                        '<=',
                        $priceReference->effective_to
                    );
                }
            })
            ->where(function ($query) use ($priceReference) {
                $query
                    ->whereNull('effective_to')
                    ->orWhere(
                        'effective_to',
                        '>=',
                        $priceReference->effective_from
                    );
            });

        if ($query->exists()) {
            throw ValidationException::withMessages([
                'price_reference' =>
                    'An official price reference already exists for the same locality, species, breed, sale purpose, and overlapping effective period.',
            ]);
        }
    }
    public function getPendingPriceReferences(
        User $authority,
        array $filters = []
    ): LengthAwarePaginator {
        $municipalityId = $this->municipalityId($authority);

        return PriceReference::query()
            ->where('municipality_id', $municipalityId)
            ->where('status', 'pending_review')

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

            ->orderBy('submitted_at')
            ->paginate(20);
    }

    public function getPriceReferenceForReview(
        User $authority,
        PriceReference $priceReference
    ): PriceReference {
        $this->ensureSameMunicipality(
            $authority,
            $priceReference
        );

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

    public function approve(
        User $authority,
        PriceReference $priceReference
    ): PriceReference {
        $this->ensureSameMunicipality(
            $authority,
            $priceReference
        );

        return DB::transaction(function () use ($authority, $priceReference) {
            $lockedPriceReference = PriceReference::query()
                ->whereKey($priceReference->id)
                ->lockForUpdate()
                ->firstOrFail();

            $this->ensureSameMunicipality(
                $authority,
                $lockedPriceReference
            );

            if ($lockedPriceReference->status !== 'pending_review') {
                throw ValidationException::withMessages([
                    'status' =>
                        'Only pending price references can be approved.',
                ]);
            }

            $this->ensureNoOfficialConflict(
                $lockedPriceReference
            );

            $now = now();

            $lockedPriceReference->update([
                'status' => 'official',

                'reviewed_by' => $authority->id,
                'reviewed_at' => $now,

                'approved_by' => $authority->id,
                'approved_at' => $now,
            ]);

            $lockedPriceReference
                ->validationLogs()
                ->create([
                    'from_status' => 'pending_review',
                    'to_status' => 'official',
                    'action' => 'approved',
                    'remarks' => null,
                    'performed_by' => $authority->id,
                ]);

            return $lockedPriceReference
                ->fresh()
                ->load([
                    'municipality',
                    'barangay',
                    'species',
                    'breed',
                    'submitter',
                    'reviewer',
                    'approver',
                    'validationLogs.performer',
                ]);
        });
    }

    public function reject(
        User $authority,
        PriceReference $priceReference,
        string $remarks
    ): PriceReference {
        $this->ensureSameMunicipality(
            $authority,
            $priceReference
        );

        return DB::transaction(function () use ($authority, $priceReference, $remarks) {
            $lockedPriceReference = PriceReference::query()
                ->whereKey($priceReference->id)
                ->lockForUpdate()
                ->firstOrFail();

            $this->ensureSameMunicipality(
                $authority,
                $lockedPriceReference
            );

            if ($lockedPriceReference->status !== 'pending_review') {
                throw ValidationException::withMessages([
                    'status' =>
                        'Only pending price references can be rejected.',
                ]);
            }

            $lockedPriceReference->update([
                'status' => 'rejected',

                'reviewed_by' => $authority->id,
                'reviewed_at' => now(),

                'approved_by' => null,
                'approved_at' => null,
            ]);

            $lockedPriceReference
                ->validationLogs()
                ->create([
                    'from_status' => 'pending_review',
                    'to_status' => 'rejected',
                    'action' => 'rejected',
                    'remarks' => $remarks,
                    'performed_by' => $authority->id,
                ]);

            return $lockedPriceReference
                ->fresh()
                ->load([
                    'municipality',
                    'barangay',
                    'species',
                    'breed',
                    'submitter',
                    'reviewer',
                    'approver',
                    'validationLogs.performer',
                ]);
        });
    }

    private function municipalityId(User $user): string
    {
        $municipalityId =
            $user->lguOfficerProfile?->municipality_id;

        if (!$municipalityId) {
            throw ValidationException::withMessages([
                'user' =>
                    'The authenticated LGU Authority does not have an assigned municipality.',
            ]);
        }

        return $municipalityId;
    }

    private function ensureSameMunicipality(
        User $authority,
        PriceReference $priceReference
    ): void {
        if (
            $priceReference->municipality_id !==
            $this->municipalityId($authority)
        ) {
            abort(404);
        }
    }
}