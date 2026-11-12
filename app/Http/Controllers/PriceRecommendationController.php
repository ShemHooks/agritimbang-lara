<?php

namespace App\Http\Controllers;

use App\Models\Species;
use App\Services\PriceAnalyticsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PriceRecommendationController extends BaseController
{
    public function __construct(
        private readonly PriceAnalyticsService $analyticsService
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $data = $request->validate([
            'effective_from' => [
                'nullable',
                'date_format:Y-m-d',
            ],
        ]);

        $effectiveFrom = $data['effective_from']
            ?? now()->toDateString();

        $species = Species::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name']);

        $recommendations = [];

        foreach ($species as $item) {
            $result = $this->analyticsService->weeklyRecommendation(
                $request->user(),
                [
                    'species_id' => $item->id,
                    'effective_from' => $effectiveFrom,
                ]
            );

            $recommendations[] = [
                'species_id' => $item->id,
                'species_name' => $item->name,
                'available' => (bool) $result['available'],
                'average_price_per_kg' =>
                    $result['average_price_per_kg'],
                'record_count' => (int) $result['record_count'],
                'transaction_count' =>
                    (int) ($result['transaction_count'] ?? 0),
                'week_start' => $result['week_start'],
                'week_end' => $result['week_end'],
            ];
        }

        return $this->success([
            'effective_from' => $effectiveFrom,
            'week_start' => $recommendations[0]['week_start'] ?? null,
            'week_end' => $recommendations[0]['week_end'] ?? null,
            'recommendations' => $recommendations,
        ], 'Recommended livestock prices retrieved.');
    }

    public function show(Request $request): JsonResponse
    {
        $data = $request->validate([
            'species_id' => [
                'required',
                'uuid',
                'exists:species,id',
            ],
            'breed_id' => [
                'nullable',
                'uuid',
                Rule::exists('breeds', 'id')
                    ->where('species_id', $request->input('species_id')),
            ],
            'barangay_id' => [
                'nullable',
                'uuid',
                'exists:barangays,id',
            ],
            'sale_purpose' => [
                'required',
                Rule::in([
                    'slaughter',
                    'breeding',
                    'fattening',
                    'work',
                ]),
            ],
            'effective_from' => [
                'required',
                'date_format:Y-m-d',
            ],
            'exclude_id' => [
                'nullable',
                'uuid',
                'exists:price_references,id',
            ],
        ]);

        $result = $this->analyticsService
            ->weeklyRecommendation($request->user(), $data);

        return $this->success(
            $result,
            'Weekly price recommendation retrieved successfully.'
        );
    }
}
