<?php

namespace App\Http\Controllers;

use App\Models\Municipality;
use App\Models\Species;
use App\Services\GisAccessService;
use App\Services\GisService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use App\Services\LguGisPriceMonitoringService;
use App\Models\Barangay;

class LguGisMonitoringController extends Controller
{
    public function __construct(
        private readonly GisService $gis,
        private readonly GisAccessService $access,
        private readonly LguGisPriceMonitoringService $prices
    ) {
    }

    public function geojson(Request $request): JsonResponse
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

        $municipalityId =
            $this->access->resolveMonitoringMunicipality(
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

    public function summary(Request $request): JsonResponse
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

        $municipalityId =
            $this->access->resolveMonitoringMunicipality(
                $request->user(),
                $data['municipality_id'] ?? null
            );

        return response()->json([
            'data' => $this->gis->livestockSummary(
                $municipalityId,
                null,
                $data['species_id'] ?? null
            ),
        ]);
    }


    public function prices(Request $request): JsonResponse
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
            'sale_purpose' => [
                'nullable',
                'string',
                'max:100',
            ],
        ]);

        $municipalityId =
            $this->access->resolveMonitoringMunicipality(
                $request->user(),
                $data['municipality_id'] ?? null
            );

        return response()->json([
            'data' => $this->prices->officialPrices(
                $municipalityId,
                $data['species_id'] ?? null,
                $data['sale_purpose'] ?? null
            ),
        ]);
    }


    /**
     * Get currently effective official prices
     * for a selected barangay.
     *
     * LGU Authority cross-municipality read-only access.
     */
    public function barangayPrices(
        Request $request,
        Barangay $barangay
    ): JsonResponse {
        // Verify cross-municipality monitoring permission.
        $this->access->resolveMonitoringMunicipality(
            $request->user(),
            $barangay->municipality_id
        );

        // Only active barangays can be monitored.
        abort_unless(
            $barangay->is_active,
            404,
            'Barangay is not available for monitoring.'
        );

        $prices = $this->prices->barangayPrices(
            $barangay->municipality_id,
            $barangay->id
        );

        return response()->json([
            'data' => [
                'barangay_id' => $barangay->id,
                'barangay_name' => $barangay->name,
                'prices' => $prices,
            ],
        ]);
    }


}
