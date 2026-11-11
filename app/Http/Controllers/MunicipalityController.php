<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreMunicipalityRequest;
use App\Http\Requests\UpdateMunicipalityRequest;
use App\Models\Municipality;
use Illuminate\Http\JsonResponse;
use App\Services\AuditLogService;
use Illuminate\Support\Facades\DB;

class MunicipalityController extends BaseController
{

    /**
     * Public municipality lookup for registration
     * and farmer profile forms.
     */
    public function referenceIndex(): JsonResponse
    {
        return $this->active();
    }
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
        Municipality $municipality,
        AuditLogService $auditLogs
    ): JsonResponse {
        $changed = false;

        $municipality = DB::transaction(function () use ($municipality, $auditLogs, &$changed) {
            $municipality = Municipality::query()
                ->lockForUpdate()
                ->findOrFail($municipality->getKey());

            if (!$municipality->is_active) {
                return $municipality;
            }

            $municipality->update([
                'is_active' => false,
            ]);

            $auditLogs->record(
                actor: request()->user(),
                action: 'municipality.deactivated',
                entity: $municipality,
                municipalityId: $municipality->id,
                oldValues: ['is_active' => true],
                newValues: ['is_active' => false],
            );

            $changed = true;

            return $municipality->fresh();
        });

        return $this->success(
            $municipality,
            $changed
            ? 'Municipality deactivated successfully.'
            : 'Municipality is already inactive.'
        );
    }



    public function activate(
        Municipality $municipality,
        AuditLogService $auditLogs
    ): JsonResponse {
        $changed = false;

        $municipality = DB::transaction(function () use ($municipality, $auditLogs, &$changed) {
            $municipality = Municipality::query()
                ->lockForUpdate()
                ->findOrFail($municipality->getKey());

            if ($municipality->is_active) {
                return $municipality;
            }

            $oldValues = [
                'is_active' => false,
            ];

            $municipality->update([
                'is_active' => true,
            ]);

            $auditLogs->record(
                actor: request()->user(),
                action: 'municipality.activated',
                entity: $municipality,
                municipalityId: $municipality->id,
                oldValues: $oldValues,
                newValues: ['is_active' => true],
            );

            $changed = true;

            return $municipality->fresh();
        });

        return $this->success(
            $municipality,
            $changed
            ? 'Municipality activated successfully.'
            : 'Municipality is already active.'
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