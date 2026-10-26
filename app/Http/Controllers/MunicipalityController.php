<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreMunicipalityRequest;
use App\Http\Requests\UpdateMunicipalityRequest;
use App\Models\Municipality;
use Illuminate\Http\JsonResponse;

class MunicipalityController extends BaseController
{
    public function index(): JsonResponse
    {
        $municipalities = Municipality::query()
            ->orderBy('province')
            ->orderBy('name')
            ->get();

        return $this->success(
            $municipalities,
            'Municipalities retrieved successfully.'
        );
    }

    public function store(
        StoreMunicipalityRequest $request
    ): JsonResponse {
        $municipality = Municipality::create(
            $request->validated()
        );

        return $this->success(
            $municipality,
            'Municipality created successfully.',
            201
        );
    }

    public function show(
        Municipality $municipality
    ): JsonResponse {
        return $this->success(
            $municipality,
            'Municipality retrieved successfully.'
        );
    }

    public function update(
        UpdateMunicipalityRequest $request,
        Municipality $municipality
    ): JsonResponse {
        $municipality->update(
            $request->validated()
        );

        return $this->success(
            $municipality->fresh(),
            'Municipality updated successfully.'
        );
    }

    public function destroy(
        Municipality $municipality
    ): JsonResponse {
        $municipality->update([
            'is_active' => false,
        ]);

        return $this->success(
            $municipality->fresh(),
            'Municipality deactivated successfully.'
        );
    }

    public function activate(Municipality $municipality): JsonResponse
    {
        if ($municipality->is_active) {
            return $this->success(
                $municipality,
                'Municipality is already active.'
            );
        }

        $municipality->update([
            'is_active' => true,
        ]);

        return $this->success(
            $municipality->fresh(),
            'Municipality activated successfully.'
        );
    }

    public function active(): JsonResponse
    {
        $municipalities = Municipality::query()
            ->where('is_active', true)
            ->orderBy('province')
            ->orderBy('name')
            ->get();

        return $this->success(
            $municipalities,
            'Active municipalities retrieved successfully.'
        );
    }
}