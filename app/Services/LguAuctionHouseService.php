<?php

namespace App\Services;

use App\Models\AuctionHouse;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use App\Models\Barangay;
use Illuminate\Support\Str;

class LguAuctionHouseService
{

    public function __construct(
        private AuditLogService $auditLogs
    ) {
    }
    private function officerMunicipality(User $user): string
    {
        $profile = $user->lguOfficerProfile;

        abort_unless(
            $profile &&
            $user->roles()->where('slug', 'lgu_authority')->exists() &&
            $user->status === 'active' &&
            $user->verification_status === 'verified',
            403,
            'An active, verified LGU officer account is required.'
        );

        return $profile->municipality_id;
    }

    private function scopedHouse(User $officer, string $id): AuctionHouse
    {
        return AuctionHouse::query()
            ->where('municipality_id', $this->officerMunicipality($officer))
            ->findOrFail($id);
    }

    private function validateBarangay(
        string $municipalityId,
        ?string $barangayId
    ): void {
        if ($barangayId === null) {
            return;
        }

        $valid = Barangay::query()
            ->whereKey($barangayId)
            ->where('municipality_id', $municipalityId)
            ->exists();

        if (!$valid) {
            throw ValidationException::withMessages([
                'barangay_id' =>
                    'Barangay must belong to your municipality.',
            ]);
        }
    }

    public function index(User $officer)
    {
        return AuctionHouse::query()
            ->where('municipality_id', $this->officerMunicipality($officer))
            ->orderBy('name')
            ->paginate(15);
    }

    public function create(User $officer, array $data): AuctionHouse
    {
        $municipalityId = $this->officerMunicipality($officer);

        $this->validateBarangay(
            $municipalityId,
            $data['barangay_id'] ?? null
        );

        return AuctionHouse::create([
            'name' => $data['name'],
            'municipality_id' => $municipalityId,
            'barangay_id' => $data['barangay_id'] ?? null,
            'address' => $data['address'] ?? null,
            'is_active' => true,
        ]);
    }

    public function update(
        User $officer,
        string $id,
        array $data
    ): AuctionHouse {
        $house = $this->scopedHouse($officer, $id);

        if (array_key_exists('barangay_id', $data)) {
            $this->validateBarangay(
                $house->municipality_id,
                $data['barangay_id']
            );
        }

        $house->update($data);

        return $house->refresh();
    }

    public function setStatus(
        User $officer,
        string $id,
        bool $active
    ): AuctionHouse {
        $house = $this->scopedHouse($officer, $id);

        $house->update(['is_active' => $active]);

        return $house->refresh();
    }


    public function assignEncoder(
        User $officer,
        string $houseId,
        string $encoderId
    ): void {
        $house = $this->scopedHouse($officer, $houseId);

        if (!$house->is_active) {
            throw ValidationException::withMessages([
                'auction_house' => 'Auction house is inactive.',
            ]);
        }

        $encoder = User::with([
            'roles',
            'lguOfficerProfile',
        ])->findOrFail($encoderId);

        if (
            $encoder->status !== 'active' ||
            $encoder->verification_status !== 'verified' ||
            !$encoder->lguOfficerProfile ||
            (string) $encoder->lguOfficerProfile->municipality_id !==
            (string) $house->municipality_id ||
            !$encoder->roles->contains('slug', 'lgu_encoder')
        ) {
            throw ValidationException::withMessages([
                'user_id' =>
                    'Encoder must be an active, verified LGU Encoder from the same municipality.',
            ]);
        }

        DB::transaction(function () use ($house, $encoder, $officer) {
            $assignment = DB::table('auction_house_encoder_assignments')
                ->where('auction_house_id', $house->id)
                ->where('user_id', $encoder->id)
                ->lockForUpdate()
                ->first();

            if ($assignment) {
                DB::table('auction_house_encoder_assignments')
                    ->where('id', $assignment->id)
                    ->update([
                        'is_active' => true,
                        'assigned_by' => $officer->id,
                        'updated_at' => now(),
                    ]);

                return;
            }

            DB::table('auction_house_encoder_assignments')->insert([
                'id' => (string) Str::uuid(),
                'auction_house_id' => $house->id,
                'user_id' => $encoder->id,
                'assigned_by' => $officer->id,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });
    }


    public function removeEncoder(
        User $officer,
        string $houseId,
        string $encoderId
    ): void {
        $house = $this->scopedHouse($officer, $houseId);

        DB::table('auction_house_encoder_assignments')
            ->where('auction_house_id', $house->id)
            ->where('user_id', $encoderId)
            ->update([
                'is_active' => false,
                'updated_at' => now(),
            ]);
    }

    public function assignedEncoders(
        User $officer,
        string $houseId
    ) {
        $house = $this->scopedHouse($officer, $houseId);

        return DB::table('auction_house_encoder_assignments as assignments')
            ->join('users', 'users.id', '=', 'assignments.user_id')
            ->where('assignments.auction_house_id', $house->id)
            ->where('assignments.is_active', true)
            ->select([
                'assignments.id',
                'assignments.user_id',
                'assignments.assigned_by',
                'assignments.created_at',
                'users.email',
                'users.status',
            ])
            ->selectRaw("
            TRIM(CONCAT_WS(' ',
                users.first_name,
                users.middle_name,
                users.last_name
            )) AS name
        ")
            ->orderBy('users.last_name')
            ->orderBy('users.first_name')
            ->get();
    }

    public function eligibleEncoders(User $officer)
    {
        $municipalityId = $this->officerMunicipality($officer);

        return User::query()
            ->where('status', 'active')
            ->where('verification_status', 'verified')
            ->whereHas('roles', function ($query) {
                $query->where('slug', 'lgu_encoder');
            })
            ->whereHas('lguOfficerProfile', function ($query) use ($municipalityId) {
                $query->where('municipality_id', $municipalityId);
            })
            ->select([
                'id',
                'first_name',
                'middle_name',
                'last_name',
                'email',
            ])
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get()
            ->map(function ($encoder) {
                return [
                    'id' => $encoder->id,
                    'name' => trim(implode(' ', array_filter([
                        $encoder->first_name,
                        $encoder->middle_name,
                        $encoder->last_name,
                    ]))),
                    'email' => $encoder->email,
                ];
            });
    }
}
