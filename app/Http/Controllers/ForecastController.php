<?php

namespace App\Http\Controllers;

use App\Models\Municipality;
use App\Models\Species;
use App\Services\ForecastService;
use App\Services\GisAccessService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Throwable;

class ForecastController extends Controller
{
    public function __invoke(Request $request, GisAccessService $access, ForecastService $forecasts): JsonResponse
    {
        $validated = $request->validate([
            'municipality_id' => ['nullable', 'uuid', 'exists:municipalities,id'],
            'species_id' => ['required', 'uuid', 'exists:species,id'],
            'weeks' => ['sometimes', 'integer', 'between:1,12'],
            'sale_purpose' => ['nullable', 'string', 'max:100'],
        ]);

        $municipalityId = $access->resolveMunicipality($request->user(), $validated['municipality_id'] ?? null);
        if ($municipalityId === null) {
            throw ValidationException::withMessages(['municipality_id' => 'Choose a municipality for forecasting.']);
        }
        if (!Municipality::query()->whereKey($municipalityId)->where('is_active', true)->exists()) {
            throw ValidationException::withMessages(['municipality_id' => 'An active municipality is required.']);
        }
        if (!Species::query()->whereKey($validated['species_id'])->where('is_active', true)->exists()) {
            throw ValidationException::withMessages(['species_id' => 'An active species is required.']);
        }

        try {
            $result = $forecasts->forecast(
                $municipalityId,
                $validated['species_id'],
                (int) ($validated['weeks'] ?? 4),
                $validated['sale_purpose'] ?? null,
            );
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            Log::error('ARIMA forecast failed', ['exception' => $exception]);
            return response()->json(['message' => 'Forecast service is temporarily unavailable.'], 503);
        }

        return response()->json(['data' => $result]);
    }
}
