<?php

namespace App\Services;

use App\Models\PriceReference;
use App\Models\User;
use App\Notifications\SystemAlertNotification;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PriceValidationService
{

    private function supersedePriorOfficialReferences(PriceReference $priceReference): void
    {
        $references = PriceReference::query()
            ->where('id', '!=', $priceReference->id)
            ->where('status', 'official')
            ->where('municipality_id', $priceReference->municipality_id)
            ->where('species_id', $priceReference->species_id)
            ->where('sale_purpose', $priceReference->sale_purpose)
            ->where('reference_category', $priceReference->reference_category)
            ->whereNull('barangay_id')
            ->when(
                $priceReference->breed_id === null,
                fn ($query) => $query->whereNull('breed_id'),
                fn ($query) => $query->where('breed_id', $priceReference->breed_id)
            )
            ->lockForUpdate()
            ->get();

        $newStart = $priceReference->effective_from->toDateString();
        $newEnd = $priceReference->effective_to?->toDateString();

        foreach ($references as $existing) {
            $existingStart = $existing->effective_from->toDateString();
            $existingEnd = $existing->effective_to?->toDateString();

            $overlaps = ($newEnd === null || $existingStart <= $newEnd)
                && ($existingEnd === null || $existingEnd >= $newStart);

            if (!$overlaps) {
                continue;
            }

            if ($existingStart < $newStart) {
                // Preserve the old approved price up to the day before the new
                // one becomes effective. This is the weekly carry-forward boundary.
                $existing->update([
                    'effective_to' => \Illuminate\Support\Carbon::parse($newStart)->subDay()->toDateString(),
                ]);
                continue;
            }

            throw ValidationException::withMessages([
                'price_reference' =>
                    'A future or same-date official price already overlaps this effective period. Adjust its effective date before approving this reference.',
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
            ->where('sale_purpose', '!=', 'piglet')
            ->where(function ($query) {
                $query->whereNull('reference_category')
                    ->orWhere('reference_category', 'not like', 'Piglet%');
            })
            ->whereNull('barangay_id')

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
        $this->ensureSupportedSalePurpose($priceReference);

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
            $this->ensureSupportedSalePurpose($lockedPriceReference);

            if ($lockedPriceReference->status !== 'pending_review') {
                throw ValidationException::withMessages([
                    'status' =>
                        'Only pending price references can be approved.',
                ]);
            }

            // All official price references are municipality-wide.
            $lockedPriceReference->update(['barangay_id' => null]);

            $this->supersedePriorOfficialReferences(
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

            $speciesName = $lockedPriceReference->species()->value('name') ?? 'livestock';
            $farmers = User::query()
                ->whereHas('farmerProfile', fn ($q) => $q->where('municipality_id', $lockedPriceReference->municipality_id))
                ->get();
            foreach ($farmers as $farmerUser) {
                $farmerUser->notify(new SystemAlertNotification(
                    'price.approved',
                    'Bag-ong opisyal nga presyo',
                    'An official ' . $lockedPriceReference->sale_purpose . ' price reference for ' . $speciesName . ' has been approved. Open Official Prices to view the details.',
                    (string) $lockedPriceReference->municipality_id,
                    'price_reference',
                    (string) $lockedPriceReference->id
                ));
            }

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
            $this->ensureSupportedSalePurpose($lockedPriceReference);

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

    private function ensureSupportedSalePurpose(PriceReference $priceReference): void
    {
        $legacyPigletCategory = str_starts_with(
            strtolower((string) $priceReference->reference_category),
            'piglet'
        );

        if ($priceReference->sale_purpose === 'piglet' || $legacyPigletCategory) {
            throw ValidationException::withMessages([
                'sale_purpose' => 'Piglet is no longer a supported sale purpose. Use Slaughter, Breeding, Fattening, or Work.',
            ]);
        }
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