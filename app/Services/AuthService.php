<?php

namespace App\Services;

use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthService
{
    /**
     * Public registration is exclusively for farmers.
     */
    public function registerFarmer(array $data): array
    {
        return DB::transaction(function () use ($data) {
            $farmerRole = Role::where('slug', 'farmer')->first();

            if (!$farmerRole) {
                throw new \RuntimeException(
                    'Farmer role is not configured.'
                );
            }

            $user = User::create([
                'first_name' => $data['first_name'],
                'middle_name' => $data['middle_name'] ?? null,
                'last_name' => $data['last_name'],
                'email' => $data['email'],
                'phone_number' => $data['phone_number'] ?? null,
                'password' => Hash::make($data['password']),
                'status' => 'active',
                'verification_status' => 'unverified',
            ]);

            $user->roles()->attach($farmerRole->id);

            $token = $user
                ->createToken('auth_token')
                ->plainTextToken;

            return [
                'user' => $user->load('roles'),
                'token' => $token,
            ];
        });
    }

    public function login(
        array $credentials,
        bool $issueToken = true
    ): array {
        $user = User::where('email', $credentials['email'])->first();

        if (!$user || !Hash::check($credentials['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        if ($user->status !== 'active') {
            throw ValidationException::withMessages([
                'email' => ['This account is currently unavailable.'],
            ]);
        }

        $user->forceFill(['last_login_at' => now()])->save();

        $result = ['user' => $user->load('roles')];

        if ($issueToken) {
            $result['token'] = $user->createToken('auth_token')->plainTextToken;
        }

        return $result;
    }

    public function logout(User $user): void
    {
        $user->currentAccessToken()?->delete();
    }
}