<?php

namespace App\Http\Controllers;

use App\Models\Breed;
use App\Models\Municipality;
use App\Models\Species;
use App\Services\GisAccessService;
use App\Services\PriceAnalyticsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PriceAnalyticsController extends Controller
{
    public function __construct(private readonly PriceAnalyticsService $prices, private readonly GisAccessService $access) {}

    public function index(Request $request): JsonResponse
    {
        $data = $request->validate([
            'municipality_id' => ['nullable', 'uuid', Rule::exists(Municipality::class, 'id')],
            'species_id' => ['nullable', 'uuid', Rule::exists(Species::class, 'id')],
            'breed_id' => ['nullable', 'uuid', Rule::exists(Breed::class, 'id')],
        ]);
        $municipality = $this->access->resolveMunicipality($request->user(), $data['municipality_id'] ?? null);
        return response()->json(['data' => $this->prices->history($municipality, $data['species_id'] ?? null, $data['breed_id'] ?? null)]);
    }
}
