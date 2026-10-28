<?php

namespace App\Services;

use App\Models\LguOfficerProfile;
use App\Models\Municipality;
use App\Models\Role;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use App\Notifications\TemporaryAccountCredentials;
use RuntimeException;

class LguAccountService
{
    /*
    |--------------------------------------------------------------------------
    | LGU Authority
    |--------------------------------------------------------------------------
    */

    public function getAuthorities(
        ?string $search = null,
        ?string $status = null,
        ?string $municipalityId = null
    ): LengthAwarePaginator {
        return User::query()
            ->whereHas('roles', function ($query) {
                $query->where('slug', 'lgu_authority');
            })
            ->when($search, function ($query, $search) {
                $query->where(function ($query) use ($search) {
                    $query
                        ->where('first_name', 'like', "%{$search}%")
                        ->orWhere('middle_name', 'like', "%{$search}%")
                        ->orWhere('last_name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->when($status, function ($query, $status) {
                $query->where('status', $status);
            })
            ->when($municipalityId, function ($query, $municipalityId) {
                $query->whereHas(
                    'lguOfficerProfile',
                    fn($profile) =>
                        $profile->where(
                            'municipality_id',
                            $municipalityId
                        )
                );
            })
            ->with([
                'roles',
                'lguOfficerProfile.municipality',
            ])
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->paginate(15);
    }


    public function getAuthority(string $id): User
    {
        return User::query()
            ->whereKey($id)
            ->whereHas(
                'roles',
                fn($query) =>
                    $query->where('slug', 'lgu_authority')
            )
            ->with([
                'roles',
                'lguOfficerProfile.municipality',
            ])
            ->firstOrFail();
    }



    public function createAuthority(
        array $data,
        Municipality $municipality
    ): User {
        if (!$municipality->is_active) {
            throw new RuntimeException(
                'Cannot create an LGU Authority for an inactive municipality.'
            );
        }

        $temporaryPassword = Str::password(10);

        $user = DB::transaction(function () use ($data, $municipality, $temporaryPassword): User {
            $role = Role::where('slug', 'lgu_authority')
                ->firstOrFail();

            $user = User::create([
                'first_name' => $data['first_name'],
                'middle_name' => $data['middle_name'] ?? null,
                'last_name' => $data['last_name'],
                'email' => $data['email'],
                'phone_number' => $data['phone_number'] ?? null,
                'password' => Hash::make($temporaryPassword),
                'status' => 'active',
                'verification_status' => 'verified',
            ]);

            $user->roles()->attach($role->id);

            LguOfficerProfile::create([
                'user_id' => $user->id,
                'municipality_id' => $municipality->id,
                'station' => $data['station'] ?? null,
                'designation' => $data['designation'] ?? null,
            ]);

            return $user;
        });

        try {
            $user->notify(
                new TemporaryAccountCredentials($temporaryPassword)
            );
        } catch (\Throwable $exception) {
            Log::error('Failed to send LGU Authority credentials.', [
                'user_id' => $user->id,
                'exception' => $exception->getMessage(),
            ]);


        }

        return $this->getAuthority($user->id);
    }

    public function updateAuthority(
        User $authority,
        array $data
    ): User {
        return DB::transaction(function () use ($authority, $data) {
            $profile = $authority->lguOfficerProfile;

            if (!$profile) {
                throw new RuntimeException(
                    'LGU Authority profile not found.'
                );
            }

            if (array_key_exists('municipality_id', $data)) {
                $municipality = Municipality::findOrFail(
                    $data['municipality_id']
                );

                if (!$municipality->is_active) {
                    throw new RuntimeException(
                        'Cannot assign an inactive municipality.'
                    );
                }

                $profile->municipality_id = $municipality->id;
            }

            $userFields = [
                'first_name',
                'middle_name',
                'last_name',
                'email',
                'phone_number',
            ];

            foreach ($userFields as $field) {
                if (array_key_exists($field, $data)) {
                    $authority->{$field} = $data[$field];
                }
            }

            if (!empty($data['password'])) {
                $authority->password = Hash::make(
                    $data['password']
                );
            }

            $authority->save();

            if (array_key_exists('station', $data)) {
                $profile->station = $data['station'];
            }

            if (array_key_exists('designation', $data)) {
                $profile->designation = $data['designation'];
            }

            $profile->save();

            return $this->getAuthority($authority->id);
        });
    }


    /*
    |--------------------------------------------------------------------------
    | LGU Encoder
    |--------------------------------------------------------------------------
    */

    public function getEncoders(
        User $authority,
        ?string $search = null,
        ?string $status = null
    ): LengthAwarePaginator {
        $municipalityId = $this
            ->getAuthorityMunicipalityId($authority);

        return User::query()
            ->whereHas(
                'roles',
                fn($query) =>
                    $query->where('slug', 'lgu_encoder')
            )
            ->whereHas(
                'lguOfficerProfile',
                fn($query) =>
                    $query->where(
                        'municipality_id',
                        $municipalityId
                    )
            )
            ->when($search, function ($query, $search) {
                $query->where(function ($query) use ($search) {
                    $query
                        ->where('first_name', 'like', "%{$search}%")
                        ->orWhere('middle_name', 'like', "%{$search}%")
                        ->orWhere('last_name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->when($status, function ($query, $status) {
                $query->where('status', $status);
            })
            ->with([
                'roles',
                'lguOfficerProfile.municipality',
            ])
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->paginate(15);
    }


    public function getEncoder(
        User $authority,
        string $encoderId
    ): User {
        $municipalityId = $this
            ->getAuthorityMunicipalityId($authority);

        return User::query()
            ->whereKey($encoderId)
            ->whereHas(
                'roles',
                fn($query) =>
                    $query->where('slug', 'lgu_encoder')
            )
            ->whereHas(
                'lguOfficerProfile',
                fn($query) =>
                    $query->where(
                        'municipality_id',
                        $municipalityId
                    )
            )
            ->with([
                'roles',
                'lguOfficerProfile.municipality',
            ])
            ->firstOrFail();
    }



    public function createEncoder(
        array $data,
        User $authority
    ): User {
        $municipalityId = $this->getAuthorityMunicipalityId($authority);

        $municipality = Municipality::findOrFail($municipalityId);

        if (!$municipality->is_active) {
            throw new RuntimeException(
                'Cannot create an Encoder for an inactive municipality.'
            );
        }

        $temporaryPassword = Str::password(16);

        $user = DB::transaction(function () use ($data, $municipalityId, $temporaryPassword): User {
            $role = Role::where('slug', 'lgu_encoder')
                ->firstOrFail();

            $user = User::create([
                'first_name' => $data['first_name'],
                'middle_name' => $data['middle_name'] ?? null,
                'last_name' => $data['last_name'],
                'email' => $data['email'],
                'phone_number' => $data['phone_number'] ?? null,
                'password' => Hash::make($temporaryPassword),
                'status' => 'active',
                'verification_status' => 'verified',
            ]);

            $user->roles()->attach($role->id);

            LguOfficerProfile::create([
                'user_id' => $user->id,
                'municipality_id' => $municipalityId,
                'station' => $data['station'] ?? null,
                'designation' => $data['designation'] ?? null,
            ]);

            return $user;
        });

        try {
            $user->notify(
                new TemporaryAccountCredentials($temporaryPassword)
            );
        } catch (\Throwable $exception) {
            Log::error('Failed to send LGU Encoder credentials.', [
                'user_id' => $user->id,
                'exception' => $exception->getMessage(),
            ]);
        }

        return $this->getEncoder($authority, $user->id);
    }



    public function updateEncoder(
        User $authority,
        string $encoderId,
        array $data
    ): User {
        return DB::transaction(function () use ($authority, $encoderId, $data) {
            /*
             * Important:
             * getEncoder() enforces municipality ownership.
             */
            $encoder = $this->getEncoder(
                $authority,
                $encoderId
            );

            $profile = $encoder->lguOfficerProfile;

            $userFields = [
                'first_name',
                'middle_name',
                'last_name',
                'email',
                'phone_number',
            ];

            foreach ($userFields as $field) {
                if (array_key_exists($field, $data)) {
                    $encoder->{$field} = $data[$field];
                }
            }

            if (!empty($data['password'])) {
                $encoder->password = Hash::make(
                    $data['password']
                );
            }

            $encoder->save();

            if (array_key_exists('station', $data)) {
                $profile->station = $data['station'];
            }

            if (array_key_exists('designation', $data)) {
                $profile->designation = $data['designation'];
            }

            $profile->save();

            return $this->getEncoder(
                $authority,
                $encoder->id
            );
        });
    }


    /*
    |--------------------------------------------------------------------------
    | Account Status
    |--------------------------------------------------------------------------
    */

    public function updateAuthorityStatus(
        User $authority,
        string $status
    ): User {
        $authority->update([
            'status' => $status,
        ]);

        /*
         * Revoke existing sessions when access is removed.
         */
        if ($status !== 'active') {
            $authority->tokens()->delete();
        }

        return $this->getAuthority($authority->id);
    }


    public function updateEncoderStatus(
        User $authority,
        string $encoderId,
        string $status
    ): User {
        $encoder = $this->getEncoder(
            $authority,
            $encoderId
        );

        $encoder->update([
            'status' => $status,
        ]);

        if ($status !== 'active') {
            $encoder->tokens()->delete();
        }

        return $this->getEncoder(
            $authority,
            $encoderId
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Helpers
    |--------------------------------------------------------------------------
    */

    private function getAuthorityMunicipalityId(
        User $authority
    ): string {
        $authority->loadMissing(
            'lguOfficerProfile'
        );

        if (!$authority->lguOfficerProfile) {
            throw new RuntimeException(
                'LGU Authority profile not found.'
            );
        }

        return $authority
            ->lguOfficerProfile
            ->municipality_id;
    }
}