<?php

namespace App\Http\Controllers;

use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegisterFarmerRequest;
use App\Services\AuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;


class AuthController extends BaseController
{
    public function __construct(
        private readonly AuthService $authService
    ) {
    }

    public function registerFarmer(
        RegisterFarmerRequest $request
    ): JsonResponse {
        $result = $this->authService->registerFarmer(
            $request->validated()
        );

        return $this->success(
            $result,
            'Farmer registration successful.',
            201
        );
    }


    public function login(LoginRequest $request): JsonResponse
    {
        $stateful = $request->attributes->get('sanctum', false);

        $result = $this->authService->login(
            $request->validated(),
            !$stateful
        );

        if ($stateful) {
            Auth::guard('web')->login($result['user']);
            $request->session()->regenerate();
        }

        return $this->success($result, 'Login successful.');
    }

    public function me(Request $request): JsonResponse
    {
        return $this->success(
            $request->user()->load([
                'roles',
                'farmerProfile',
                'lguOfficerProfile',
            ]),
            'Authenticated user retrieved successfully.'
        );
    }

    public function logout(Request $request): JsonResponse
    {
        $token = $request->user()?->currentAccessToken();

        if ($token instanceof PersonalAccessToken) {
            $token->delete();
        } else {
            Auth::guard('web')->logout();

            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        return $this->success(null, 'Logout successful.');
    }
}