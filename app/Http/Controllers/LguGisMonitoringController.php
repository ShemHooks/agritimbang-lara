<?php

namespace App\Http\Controllers;

use App\Models\Municipality;
use App\Models\Species;
use App\Services\GisAccessService;
use App\Services\GisService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class LguGisMonitoringController extends Controller
{
    public function __construct(
        private readonly GisService $gis,
        private readonly GisAccessService $access
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
            'dataset' => ['nullable', 'in:livestock,verified_sales'],
            'status' => ['nullable', 'in:available,in_transaction,sold'],
            'sale_purpose' => ['nullable', 'in:slaughter,breeding,fattening,work'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
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
                true,
                $data['dataset'] ?? 'livestock',
                $data['status'] ?? null,
                $data['sale_purpose'] ?? null,
                $data['date_from'] ?? null,
                $data['date_to'] ?? null
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


}
