<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\Rule;

class ProfileController extends BaseController
{
    public function show(Request $request): JsonResponse
    {
        return $this->success(
            $this->profileData($request->user()),
            'Profile retrieved successfully.'
        );
    }

    public function update(Request $request): JsonResponse
    {
        $user = $request->user();

        $data = $request->validate([
            'first_name' => [
                'required',
                'string',
                'max:100'
            ],
            'middle_name' => [
                'nullable',
                'string',
                'max:100'
            ],
            'last_name' => [
                'required',
                'string',
                'max:100'
            ],
            'email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($user->id),
            ],
            'phone_number' => [
                'nullable',
                'string',
                'max:30'
            ],
        ]);

        $user->update($data);

        return $this->success(
            $this->profileData($user->fresh()),
            'Profile updated successfully.'
        );
    }

    public function changePassword(Request $request): JsonResponse
    {
        $data = $request->validate([
            'current_password' => [
                'required',
                'string'
            ],
            'password' => [
                'required',
                'confirmed',
                Password::min(8)->letters()->numbers(),
            ],
        ]);

        $user = $request->user();

        if (
            !Hash::check(
                $data['current_password'],
                $user->password
            )
        ) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'current_password' => [
                    'The current password is incorrect.'
                ],
            ]);
        }

        if (Hash::check($data['password'], $user->password)) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'password' => [
                    'Your new password must be different from your current password.'
                ],
            ]);
        }

        DB::transaction(function () use ($user, $data) {
            // User model already has a hashed password cast.
            $user->update([
                'password' => $data['password'],
            ]);

            // Revoke other personal access tokens.
            // Current token stays valid so the user can continue
            // using the application after changing the password.
            $currentToken = $user->currentAccessToken();

            $tokens = $user->tokens();

            if (
                $currentToken instanceof
                \Laravel\Sanctum\PersonalAccessToken
            ) {
                $tokens->where(
                    'id',
                    '!=',
                    $currentToken->getKey()
                )->delete();
            } else {
                $tokens->delete();
            }
        });

        return $this->success(
            null,
            'Password changed successfully.'
        );
    }

    private function profileData(User $user): User
    {
        return $user->load([
            'roles',
            'farmerProfile.municipality',
            'farmerProfile.barangay',
            'lguOfficerProfile.municipality',
        ]);
    }
}
