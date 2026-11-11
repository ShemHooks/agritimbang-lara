<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreBreedRequest;
use App\Http\Requests\UpdateBreedRequest;
use App\Http\Requests\UpdateReferenceStatusRequest;
use App\Models\Breed;
use App\Models\Species;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use App\Services\AuditLogService;
use Illuminate\Support\Facades\DB;

class BreedController extends BaseController
{
    public function index(Request $request): JsonResponse
    {
        $breeds = Breed::query()
            ->with('species:id,name,code')

            ->when(
                $request->filled('species_id'),
                fn($query) =>
                    $query->where(
                        'species_id',
                        $request->species_id
                    )
            )

            ->when(
                $request->filled('search'),
                function ($query) use ($request) {
                    $search = $request->string('search');

                    $query->where(function ($query) use ($search) {
                        $query
                            ->where('name', 'like', "%{$search}%")
                            ->orWhere('code', 'like', "%{$search}%");
                    });
                }
            )

            ->orderBy('name')
            ->paginate(20);

        return $this->success(
            $breeds,
            'Breeds retrieved successfully.'
        );
    }

    public function store(
        StoreBreedRequest $request
    ): JsonResponse {
        $data = $request->validated();

        $species = Species::findOrFail(
            $data['species_id']
        );

        if (!$species->is_active) {
            throw ValidationException::withMessages([
                'species_id' => [
                    'A breed cannot be added to an inactive species.',
                ],
            ]);
        }

        $breed = Breed::create($data);

        return $this->success(
            $breed->load('species:id,name,code'),
            'Breed created successfully.',
            201
        );
    }

    public function show(
        Breed $breed
    ): JsonResponse {
        return $this->success(
            $breed->load('species:id,name,code'),
            'Breed retrieved successfully.'
        );
    }

    public function update(
        UpdateBreedRequest $request,
        Breed $breed
    ): JsonResponse {
        $data = $request->validated();

        if (isset($data['species_id'])) {
            $species = Species::findOrFail(
                $data['species_id']
            );

            if (!$species->is_active) {
                throw ValidationException::withMessages([
                    'species_id' => [
                        'A breed cannot be assigned to an inactive species.',
                    ],
                ]);
            }
        }

        $breed->update($data);

        return $this->success(
            $breed
                ->fresh()
                ->load('species:id,name,code'),
            'Breed updated successfully.'
        );
    }

    public function updateStatus(
        UpdateReferenceStatusRequest $request,
        Breed $breed,
        AuditLogService $auditLogService
    ): JsonResponse {
        $isActive = (bool) $request->validated()['is_active'];

        $breed = DB::transaction(function () use ($breed, $isActive, $request, $auditLogService) {
            // Lock the parent species before the breed.
            // This helps coordinate concurrent status changes.
            $speciesId = $breed->species_id;

            $species = Species::query()
                ->whereKey($speciesId)
                ->lockForUpdate()
                ->firstOrFail();

            $lockedBreed = Breed::query()
                ->whereKey($breed->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ((string) $lockedBreed->species_id !== (string) $species->getKey()) {
                throw ValidationException::withMessages([
                    'species_id' => [
                        'The breed species changed. Please retry.',
                    ],
                ]);
            }

            if ($isActive && !$species->is_active) {
                throw ValidationException::withMessages([
                    'is_active' => [
                        'A breed cannot be activated while its species is inactive.',
                    ],
                ]);
            }

            $previousStatus = (bool) $lockedBreed->is_active;

            if ($previousStatus === $isActive) {
                return $lockedBreed;
            }

            $lockedBreed->update([
                'is_active' => $isActive,
            ]);

            $auditLogService->record(
                $request->user(),
                $isActive
                ? 'breed.activated'
                : 'breed.deactivated',
                $lockedBreed,
                null,
                [
                    'is_active' => $previousStatus,
                ],
                [
                    'is_active' => $isActive,
                ]
            );

            return $lockedBreed;
        });

        return $this->success(
            $breed->fresh()->load('species:id,name,code'),
            'Breed status updated successfully.'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Reference Data
    |--------------------------------------------------------------------------
    */

    public function referenceBySpecies(
        Species $species
    ): JsonResponse {
        abort_unless(
            $species->is_active,
            404
        );

        $breeds = $species
            ->breeds()
            ->where('is_active', true)
            ->orderBy('name')
            ->get([
                'id',
                'species_id',
                'name',
                'code',
            ]);

        return $this->success(
            $breeds,
            'Active breeds retrieved successfully.'
        );
    }
}