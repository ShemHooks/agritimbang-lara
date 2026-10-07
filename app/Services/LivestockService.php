<?php

namespace App\Services;

use App\Models\FarmerProfile;
use App\Models\LivestockRecord;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class LivestockService
{


    public function __construct(
        private readonly AgeGroupService $ageGroupService
    ) {
    }

    private function prepareLivestockUpdate(
        LivestockRecord $livestock,
        array $data
    ): array {
        $speciesId =
            $data['species_id'] ?? $livestock->species_id;

        $species = \App\Models\Species::findOrFail(
            $speciesId
        );

        $ageMonths = array_key_exists(
            'age_months',
            $data
        )
            ? $data['age_months']
            : $livestock->age_months;

        $weightKg = array_key_exists(
            'actual_weight_kg',
            $data
        )
            ? $data['actual_weight_kg']
            : $livestock->actual_weight_kg;

        $data['age_group'] =
            $this->ageGroupService->resolve(
                $species,
                $ageMonths !== null
                ? (int) $ageMonths
                : null,
                $weightKg !== null
                ? (float) $weightKg
                : null
            );

        return $data;
    }

    /*
    |--------------------------------------------------------------------------
    | Farmer Operations
    |--------------------------------------------------------------------------
    */

    public function getFarmerLivestock(
        User $user,
        array $filters = []
    ): LengthAwarePaginator {
        $farmer = $this->farmerProfile($user);

        return LivestockRecord::query()
            ->where('farmer_id', $farmer->id)

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

            ->with([
                'species',
                'breed',
                'municipality',
                'barangay',
            ])

            ->latest()
            ->paginate(20);
    }

    public function getFarmerLivestockRecord(
        User $user,
        LivestockRecord $livestock
    ): LivestockRecord {
        $this->ensureFarmerOwnsLivestock(
            $user,
            $livestock
        );

        return $livestock->load([
            'farmer.user',
            'species',
            'breed',
            'municipality',
            'barangay',
            'creator',
        ]);
    }

    public function createForFarmer(
        User $user,
        array $data
    ): LivestockRecord {
        $farmer = $this->farmerProfile($user);

        $this->ensureFarmerIsVerified($user);

        return DB::transaction(function () use ($user, $farmer, $data) {
            $species = \App\Models\Species::findOrFail(
                $data['species_id']
            );

            $ageGroup = $this->ageGroupService->resolve(
                $species,
                $data['age_months'] ?? null,
                isset($data['actual_weight_kg'])
                ? (float) $data['actual_weight_kg']
                : null
            );

            $livestock = LivestockRecord::create([
                'farmer_id' => $farmer->id,

                'species_id' => $data['species_id'],
                'breed_id' => $data['breed_id'] ?? null,

                'sex' => $data['sex'],

                'age_months' =>
                    $data['age_months'] ?? null,

                'age_group' => $ageGroup,

                'reproductive_status' =>
                    $data['reproductive_status'] ?? 'unknown',

                'parity' =>
                    $data['parity'] ?? null,

                'sale_purpose' =>
                    $data['sale_purpose'],

                'condition_score' =>
                    $data['condition_score'],

                'actual_weight_kg' =>
                    $data['actual_weight_kg'] ?? null,

                'municipality_id' =>
                    $farmer->municipality_id,

                'barangay_id' =>
                    $farmer->barangay_id,

                'status' => 'available',

                'created_by' => $user->id,
            ]);

            return $livestock->load([
                'farmer.user',
                'species',
                'breed',
                'municipality',
                'barangay',
                'creator',
            ]);
        });
    }

    public function updateForFarmer(
        User $user,
        LivestockRecord $livestock,
        array $data
    ): LivestockRecord {
        $this->ensureFarmerOwnsLivestock(
            $user,
            $livestock
        );

        $this->ensureEditable($livestock);

        $data = $this->prepareLivestockUpdate(
            $livestock,
            $data
        );

        $livestock->update($data);

        return $livestock
            ->fresh()
            ->load([
                'farmer.user',
                'species',
                'breed',
                'municipality',
                'barangay',
                'creator',
            ]);
    }

    public function updateFarmerStatus(
        User $user,
        LivestockRecord $livestock,
        string $status
    ): LivestockRecord {
        $this->ensureFarmerOwnsLivestock(
            $user,
            $livestock
        );

        if (
            !in_array(
                $status,
                ['available', 'archived'],
                true
            )
        ) {
            throw ValidationException::withMessages([
                'status' =>
                    'Farmers may only mark livestock as available or archived.',
            ]);
        }

        if ($livestock->status === 'in_transaction') {
            throw ValidationException::withMessages([
                'status' =>
                    'Livestock involved in a transaction cannot have its status changed manually.',
            ]);
        }

        if ($livestock->status === 'sold') {
            throw ValidationException::withMessages([
                'status' =>
                    'Sold livestock cannot have its status changed manually.',
            ]);
        }

        $livestock->update([
            'status' => $status,
        ]);

        return $livestock->fresh();
    }


    /*
    |--------------------------------------------------------------------------
    | LGU Operations
    |--------------------------------------------------------------------------
    */

    public function getMunicipalityLivestock(
        User $encoder,
        array $filters = []
    ): LengthAwarePaginator {
        $municipalityId =
            $this->municipalityId($encoder);

        return LivestockRecord::query()
            ->where(
                'municipality_id',
                $municipalityId
            )

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
                $filters['farmer_id'] ?? null,
                fn($query, $farmerId) =>
                    $query->where('farmer_id', $farmerId)
            )

            ->with([
                'farmer.user',
                'species',
                'breed',
                'municipality',
                'barangay',
            ])

            ->latest()
            ->paginate(20);
    }

    public function getMunicipalityLivestockRecord(
        User $encoder,
        LivestockRecord $livestock
    ): LivestockRecord {
        $this->ensureSameMunicipality(
            $encoder,
            $livestock
        );

        return $livestock->load([
            'farmer.user',
            'species',
            'breed',
            'municipality',
            'barangay',
            'creator',
        ]);
    }

    public function createForLgu(
        User $encoder,
        FarmerProfile $farmer,
        array $data
    ): LivestockRecord {
        $municipalityId =
            $this->municipalityId($encoder);

        if (
            $farmer->municipality_id !==
            $municipalityId
        ) {
            abort(404);
        }

        if (
            $farmer->user?->verification_status !==
            'verified'
        ) {
            throw ValidationException::withMessages([
                'farmer_id' =>
                    'Livestock can only be registered for a verified farmer.',
            ]);
        }

        return DB::transaction(function () use ($encoder, $farmer, $data) {
            $species = \App\Models\Species::findOrFail(
                $data['species_id']
            );

            $ageGroup = $this->ageGroupService->resolve(
                $species,
                $data['age_months'] ?? null,
                isset($data['actual_weight_kg'])
                ? (float) $data['actual_weight_kg']
                : null
            );

            $livestock = LivestockRecord::create([
                'farmer_id' => $farmer->id,

                'species_id' => $data['species_id'],
                'breed_id' => $data['breed_id'] ?? null,

                'sex' => $data['sex'],

                'age_months' =>
                    $data['age_months'] ?? null,

                'age_group' => $ageGroup,

                'reproductive_status' =>
                    $data['reproductive_status'] ?? 'unknown',

                'parity' =>
                    $data['parity'] ?? null,

                'sale_purpose' =>
                    $data['sale_purpose'],

                'condition_score' =>
                    $data['condition_score'],

                'actual_weight_kg' =>
                    $data['actual_weight_kg'] ?? null,

                'municipality_id' =>
                    $farmer->municipality_id,

                'barangay_id' =>
                    $farmer->barangay_id,

                'status' => 'available',

                'created_by' => $encoder->id,
            ]);

            return $livestock->load([
                'farmer.user',
                'species',
                'breed',
                'municipality',
                'barangay',
                'creator',
            ]);
        });
    }

    public function updateForLgu(
        User $encoder,
        LivestockRecord $livestock,
        array $data
    ): LivestockRecord {
        $this->ensureSameMunicipality(
            $encoder,
            $livestock
        );

        $this->ensureEditable($livestock);

        $data = $this->prepareLivestockUpdate(
            $livestock,
            $data
        );

        $livestock->update($data);

        return $livestock
            ->fresh()
            ->load([
                'farmer.user',
                'species',
                'breed',
                'municipality',
                'barangay',
                'creator',
            ]);
    }


    /*
    |--------------------------------------------------------------------------
    | Internal Rules
    |--------------------------------------------------------------------------
    */

    private function farmerProfile(
        User $user
    ): FarmerProfile {
        $farmer = $user->farmerProfile;

        if (!$farmer) {
            throw ValidationException::withMessages([
                'profile' =>
                    'Complete your farmer profile before registering livestock.',
            ]);
        }

        return $farmer;
    }

    private function ensureFarmerIsVerified(
        User $user
    ): void {
        if ($user->verification_status !== 'verified') {
            throw ValidationException::withMessages([
                'verification' =>
                    'Your account must be verified before registering livestock.',
            ]);
        }
    }

    private function ensureFarmerOwnsLivestock(
        User $user,
        LivestockRecord $livestock
    ): void {
        $farmer = $this->farmerProfile($user);

        if ($livestock->farmer_id !== $farmer->id) {
            abort(404);
        }
    }

    private function municipalityId(
        User $user
    ): string {
        $municipalityId =
            $user->lguOfficerProfile?->municipality_id;

        if (!$municipalityId) {
            throw ValidationException::withMessages([
                'user' =>
                    'The authenticated LGU user does not have an assigned municipality.',
            ]);
        }

        return $municipalityId;
    }

    private function ensureSameMunicipality(
        User $user,
        LivestockRecord $livestock
    ): void {
        if (
            $livestock->municipality_id !==
            $this->municipalityId($user)
        ) {
            abort(404);
        }
    }

    private function ensureEditable(
        LivestockRecord $livestock
    ): void {
        if ($livestock->status === 'in_transaction') {
            throw ValidationException::withMessages([
                'livestock' =>
                    'Livestock involved in a transaction cannot be edited.',
            ]);
        }

        if ($livestock->status === 'sold') {
            throw ValidationException::withMessages([
                'livestock' =>
                    'Sold livestock cannot be edited.',
            ]);
        }

        if ($livestock->status === 'archived') {
            throw ValidationException::withMessages([
                'livestock' =>
                    'Archived livestock must be restored before it can be edited.',
            ]);
        }
    }
}