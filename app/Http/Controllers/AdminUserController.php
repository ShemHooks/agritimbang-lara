<?php

namespace App\Http\Controllers;

use App\Services\AdminUserService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AdminUserController extends BaseController
{
    public function __construct(
        private readonly AdminUserService $userService
    ) {
    }

    /*
    |--------------------------------------------------------------------------
    | User Management Summary
    |--------------------------------------------------------------------------
    */

    public function summary(): JsonResponse
    {
        return $this->success(
            $this->userService->summary(),
            'User management summary retrieved successfully.'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | All Users
    |--------------------------------------------------------------------------
    */

    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],

            'role' => [
                'nullable',
                Rule::in([
                    'admin',
                    'lgu_authority',
                    'lgu_encoder',
                    'farmer',
                ]),
            ],

            'status' => [
                'nullable',
                Rule::in([
                    'active',
                    'inactive',
                    'suspended',
                ]),
            ],

            'verification_status' => [
                'nullable',
                Rule::in([
                    'unverified',
                    'pending',
                    'verified',
                    'rejected',
                ]),
            ],

            'municipality_id' => [
                'nullable',
                'uuid',
                'exists:municipalities,id',
            ],
        ]);

        return $this->success(
            $this->userService->getUsers($filters),
            'Users retrieved successfully.'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | User Details
    |--------------------------------------------------------------------------
    */

    public function show(string $user): JsonResponse
    {
        return $this->success(
            $this->userService->getUser($user),
            'User details retrieved successfully.'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Update Account Status
    |--------------------------------------------------------------------------
    */

    public function updateStatus(
        Request $request,
        string $user
    ): JsonResponse {
        $data = $request->validate([
            'status' => [
                'required',
                Rule::in([
                    'active',
                    'inactive',
                    'suspended',
                ]),
            ],
        ]);

        $updatedUser = $this->userService->updateStatus(
            $request->user(),
            $user,
            $data['status']
        );

        return $this->success(
            $updatedUser,
            'User account status updated successfully.'
        );
    }
}
