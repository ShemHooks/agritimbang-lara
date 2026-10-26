<?php

namespace App\Http\Controllers;

use App\Models\Barangay;
use App\Models\Municipality;
use App\Models\Species;
use App\Services\GisAccessService;
use App\Services\GisService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class GisController extends Controller
{
    public function __construct(
        private readonly GisService $gis,
        private readonly GisAccessService $access
    ) {
    }

    // ---------------------------------------------------------
    // Public geographic reference data
    // ---------------------------------------------------------

    public function municipalities(): JsonResponse
    {
        return response()->json([
            'data' => $this->gis->municipalities(),
        ]);
    }

    public function barangays(Request $request): JsonResponse
    {
        $data = $request->validate([
            'municipality_id' => [
                'nullable',
                'uuid',
                Rule::exists(Municipality::class, 'id'),
            ],
        ]);

        return response()->json([
            'data' => $this->gis->barangays(
                $data['municipality_id'] ?? null
            ),
        ]);
    }

    public function geojson(Request $request): JsonResponse
    {
        $data = $request->validate([
            'municipality_id' => [
                'nullable',
                'uuid',
                Rule::exists(Municipality::class, 'id'),
            ],
        ]);

        return response()->json(
            $this->gis->geojson(
                $data['municipality_id'] ?? null,
                null,
                false
            )
        );
    }

    // ---------------------------------------------------------
    // Authenticated GIS livestock monitoring
    // ---------------------------------------------------------

    public function monitoringGeojson(Request $request): JsonResponse
    {
        $data = $request->validate([
            'municipality_id' => [
                'nullable',
                'uuid',
                Rule::exists(Municipality::class, 'id'),
            ],
            'species_id' => [
                'nullable',
                'uuid',
                Rule::exists(Species::class, 'id'),
            ],
        ]);

        $municipalityId = $this->access->resolveMunicipality(
            $request->user(),
            $data['municipality_id'] ?? null
        );

        return response()->json(
            $this->gis->geojson(
                $municipalityId,
                $data['species_id'] ?? null,
                true
            )
        );
    }

    public function livestockSummary(Request $request): JsonResponse
    {
        $data = $request->validate([
            'municipality_id' => [
                'nullable',
                'uuid',
                Rule::exists(Municipality::class, 'id'),
            ],
            'barangay_id' => [
                'nullable',
                'uuid',
                Rule::exists(Barangay::class, 'id'),
            ],
            'species_id' => [
                'nullable',
                'uuid',
                Rule::exists(Species::class, 'id'),
            ],
        ]);

        $municipalityId = $this->access->resolveMunicipality(
            $request->user(),
            $data['municipality_id'] ?? null
        );

        if (isset($data['barangay_id'])) {
            $matches = Barangay::query()
                ->whereKey($data['barangay_id'])
                ->when(
                    $municipalityId,
                    fn($query) => $query->where(
                        'municipality_id',
                        $municipalityId
                    )
                )
                ->exists();

            if (!$matches) {
                throw ValidationException::withMessages([
                    'barangay_id' =>
                        'Barangay does not belong to the permitted municipality.',
                ]);
            }
        }

        return response()->json([
            'data' => $this->gis->livestockSummary(
                $municipalityId,
                $data['barangay_id'] ?? null,
                $data['species_id'] ?? null
            ),
        ]);
    }
}