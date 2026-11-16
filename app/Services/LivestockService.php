<?php

namespace App\Services;

use App\Models\FarmerProfile;
use App\Models\LivestockRecord;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use App\Notifications\SystemAlertNotification;
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
                'latestWeightEstimation',
            ])
            ->withCount(['weightEstimations', 'valuations'])

            ->latest()
            ->paginate(min(max((int) ($filters['per_page'] ?? 20), 1), 100));
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
            'latestWeightEstimation',
            'latestValuation',
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
                'photo_path' => isset($data['photo']) ? $data['photo']->store('livestock', 'public') : null,

                'municipality_id' =>
                    $farmer->municipality_id,

                'barangay_id' =>
                    $farmer->barangay_id,

                'status' => 'available',

                'created_by' => $user->id,
            ]);

            $this->notifyLivestockCreated($livestock->load('species'), $user);

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

        $data = $this->prepareLivestockUpdate($livestock, $data);
        if (isset($data['photo'])) {
            if ($livestock->photo_path) Storage::disk('public')->delete($livestock->photo_path);
            $data['photo_path'] = $data['photo']->store('livestock', 'public');
            unset($data['photo']);
        }
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

        if ($status === 'archived' && $livestock->activeAuctionRegistration()->exists()) {
            throw ValidationException::withMessages([
                'status' => 'Withdraw this livestock from its active auction registration before archiving it.',
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



    public function deleteForFarmer(User $user, LivestockRecord $livestock): void
    {
        $this->ensureFarmerOwnsLivestock($user, $livestock);
        $this->ensureEditable($livestock);
        if ($livestock->status !== 'available') {
            throw ValidationException::withMessages(['status' => 'Only available livestock can be deleted.']);
        }
        if ($livestock->transactionItems()->exists()) {
            throw ValidationException::withMessages(['livestock' => 'This livestock has transaction history and cannot be deleted. Archive it instead.']);
        }
        if ($livestock->auctionRegistrations()->exists()) {
            throw ValidationException::withMessages(['livestock' => 'This livestock has auction registration history and cannot be deleted. Archive it instead.']);
        }
        DB::transaction(function () use ($livestock) {
            // Preserve referential integrity: valuations reference weight estimations.
            $livestock->valuations()->delete();
            $livestock->weightEstimations()->delete();
            if ($livestock->photo_path) Storage::disk('public')->delete($livestock->photo_path);
            $livestock->delete();
        });
    }

    private function notifyLivestockCreated(LivestockRecord $livestock, User $farmerUser): void
    {
        $notification = new SystemAlertNotification(
            'livestock.created',
            'Bag-ong hayop nairehistro',
            'A new livestock record was added to your AgriTimbang account.',
            (string) $livestock->municipality_id,
            'livestock',
            (string) $livestock->id
        );
        $farmerUser->notify($notification);

        $officers = User::query()
            ->where('status', 'active')
            ->whereHas('roles', fn ($q) => $q->whereIn('slug', ['lgu_encoder', 'lgu_authority']))
            ->whereHas('lguOfficerProfile', fn ($q) => $q->where('municipality_id', $livestock->municipality_id))
            ->get();
        foreach ($officers as $officer) {
            $officer->notify(new SystemAlertNotification(
                'livestock.created',
                'Bag-ong livestock record',
                'A farmer registered a new ' . ($livestock->species?->name ?? 'livestock') . ' record. Please review it when needed.',
                (string) $livestock->municipality_id,
                'livestock',
                (string) $livestock->id
            ));
        }
    }

    public function getMunicipalityLivestock(
        User $user,
        array $filters = []
    ): LengthAwarePaginator {

        $isAdmin = $user->roles()
            ->where('slug', 'admin')
            ->exists();

        $query = LivestockRecord::query()
            ->with([
                'farmer.user',
                'species',
                'breed',
                'municipality',
                'barangay',
                'latestWeightEstimation',
            ])
            ->withCount(['weightEstimations', 'valuations']);

        // LGU users are always restricted to their municipality.
        if (!$isAdmin) {
            $municipalityId = $this->municipalityId($user);

            $query->where('municipality_id', $municipalityId);
        }

        // Admin can optionally filter by municipality.
        if ($isAdmin && !empty($filters['municipality_id'])) {
            $query->where(
                'municipality_id',
                $filters['municipality_id']
            );
        }

        if (!empty($filters['search'])) {
            $search = trim($filters['search']);

            $query->where(function ($q) use ($search) {
                $q->where('id', 'like', "%{$search}%")
                    ->orWhereHas('farmer', function ($farmerQuery) use ($search) {
                        $farmerQuery
                            ->where('farmer_code', 'like', "%{$search}%")
                            ->orWhereHas('user', function ($userQuery) use ($search) {
                                $userQuery
                                    ->where('first_name', 'like', "%{$search}%")
                                    ->orWhere('middle_name', 'like', "%{$search}%")
                                    ->orWhere('last_name', 'like', "%{$search}%");
                            });
                    });
            });
        }

        $filterColumns = [
            'status',
            'species_id',
            'breed_id',
            'farmer_id',
            'barangay_id',
        ];

        foreach ($filterColumns as $column) {
            if (isset($filters[$column]) && $filters[$column] !== '') {
                $query->where($column, $filters[$column]);
            }
        }

        $perPage = min(
            max((int) ($filters['per_page'] ?? 20), 1),
            100
        );

        return $query
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate($perPage);
    }



    public function getMunicipalityLivestockRecord(
        User $encoder,
        LivestockRecord $livestock
    ): LivestockRecord {
        $isAdmin = $encoder->roles()
            ->where('slug', 'admin')
            ->exists();

        if (!$isAdmin) {
            $this->ensureSameMunicipality($encoder, $livestock);
        }

        return $livestock->load([
            'farmer.user',
            'species',
            'breed',
            'municipality',
            'barangay',
            'creator',
            'latestWeightEstimation',
            'latestValuation',
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

        if (isset($data['photo'])) {
            $newPhotoPath = $data['photo']->store('livestock', 'public');
            if ($livestock->photo_path) {
                Storage::disk('public')->delete($livestock->photo_path);
            }
            $data['photo_path'] = $newPhotoPath;
            unset($data['photo']);
        }

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



    /**
     * Authorize farmer access to livestock for weight estimation.
     */
    public function authorizeFarmerWeightEstimation(
        User $user,
        LivestockRecord $livestock
    ): LivestockRecord {
        $this->ensureFarmerOwnsLivestock(
            $user,
            $livestock
        );

        $this->ensureFarmerIsVerified($user);

        $this->ensureEditable($livestock);

        return $livestock;
    }

    /**
     * Authorize LGU access to livestock for weight estimation.
     */
    public function authorizeLguWeightEstimation(
        User $user,
        LivestockRecord $livestock
    ): LivestockRecord {
        $this->ensureSameMunicipality(
            $user,
            $livestock
        );

        $this->ensureEditable($livestock);

        return $livestock;
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