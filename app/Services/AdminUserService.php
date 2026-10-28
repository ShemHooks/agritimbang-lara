<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AdminUserService
{
    private const ROLES = [
        'admin',
        'lgu_authority',
        'lgu_encoder',
        'farmer',
    ];

    private const STATUSES = [
        'active',
        'inactive',
        'suspended',
    ];

    private const VERIFICATION_STATUSES = [
        'unverified',
        'pending',
        'verified',
        'rejected',
    ];

    /*
    |--------------------------------------------------------------------------
    | User Management Summary
    |--------------------------------------------------------------------------
    */

    public function summary(): array
    {
        return [
            'total_users' => User::count(),

            'active_users' => User::where(
                'status',
                'active'
            )->count(),

            'inactive_users' => User::where(
                'status',
                'inactive'
            )->count(),

            'suspended_users' => User::where(
                'status',
                'suspended'
            )->count(),

            'pending_verifications' => User::query()
                ->where('verification_status', 'pending')
                ->whereHas('roles', function ($query) {
                    $query->where('slug', 'farmer');
                })
                ->count(),

            'roles' => [
                'admin' => $this->countByRole('admin'),
                'lgu_authority' => $this->countByRole('lgu_authority'),
                'lgu_encoder' => $this->countByRole('lgu_encoder'),
                'farmer' => $this->countByRole('farmer'),
            ],
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | All Users Directory
    |--------------------------------------------------------------------------
    */

    public function getUsers(array $filters): LengthAwarePaginator
    {
        $query = User::query()
            ->with([
                'roles',
                'farmerProfile.municipality',
                'farmerProfile.barangay',
                'lguOfficerProfile.municipality',
            ]);

        if (!empty($filters['search'])) {
            $search = trim($filters['search']);

            $query->where(function (Builder $query) use ($search) {
                $query
                    ->where('first_name', 'like', "%{$search}%")
                    ->orWhere('middle_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhereHas('farmerProfile', function ($profile) use ($search) {
                        $profile->where('farmer_code', 'like', "%{$search}%");
                    });
            });
        }

        if (!empty($filters['role'])) {
            $query->whereHas('roles', function ($query) use ($filters) {
                $query->where('slug', $filters['role']);
            });
        }

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['verification_status'])) {
            $query->where(
                'verification_status',
                $filters['verification_status']
            );
        }

        if (!empty($filters['municipality_id'])) {
            $municipalityId = $filters['municipality_id'];

            $query->where(function (Builder $query) use ($municipalityId) {
                $query
                    ->whereHas('farmerProfile', function ($profile) use ($municipalityId) {
                        $profile->where('municipality_id', $municipalityId);
                    })
                    ->orWhereHas('lguOfficerProfile', function ($profile) use ($municipalityId) {
                        $profile->where('municipality_id', $municipalityId);
                    });
            });
        }

        return $query
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->paginate(15);
    }

    /*
    |--------------------------------------------------------------------------
    | User Details
    |--------------------------------------------------------------------------
    */

    public function getUser(string $userId): User
    {
        return User::query()
            ->with([
                'roles',
                'farmerProfile.municipality',
                'farmerProfile.barangay',
                'lguOfficerProfile.municipality',
                'verificationDocuments.reviewer',
            ])
            ->findOrFail($userId);
    }

    /*
    |--------------------------------------------------------------------------
    | Account Status Management
    |--------------------------------------------------------------------------
    */

    public function updateStatus(
        User $admin,
        string $userId,
        string $status
    ): User {
        return DB::transaction(function () use ($admin, $userId, $status) {
            $user = User::query()
                ->whereKey($userId)
                ->lockForUpdate()
                ->firstOrFail();

            if ($admin->id === $user->id) {
                throw ValidationException::withMessages([
                    'user' => [
                        'You cannot change the status of your own account.',
                    ],
                ]);
            }

            // Protect other System Admin accounts.
            if (
                $user->roles()
                    ->where('slug', 'admin')
                    ->exists()
            ) {
                abort(403, 'System Admin accounts cannot be modified here.');
            }

            $user->update([
                'status' => $status,
            ]);

            if ($status !== 'active') {
                $user->tokens()->delete();
            }

            return $this->getUser($user->id);
        });
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers
    |--------------------------------------------------------------------------
    */

    private function countByRole(string $role): int
    {
        return User::whereHas('roles', function ($query) use ($role) {
            $query->where('slug', $role);
        })->count();
    }
}
