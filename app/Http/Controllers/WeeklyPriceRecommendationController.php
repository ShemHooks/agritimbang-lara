<?php

namespace App\Http\Controllers;

use App\Models\WeeklyPriceRecommendation;
use App\Services\WeeklyPriceRecommendationService;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;

class WeeklyPriceRecommendationController extends BaseController
{
    private function scope(Request $request): string
    {
        $user = $request->user();
        abort_unless($user && $user->lguOfficerProfile?->municipality_id, 403, 'LGU municipality assignment required.');

        return $user->lguOfficerProfile->municipality_id;
    }

    private function authority(Request $request): void
    {
        abort_unless($request->user()->roles()->whereIn('slug', ['lgu_authority', 'LGU_AUTHORITY'])->exists(), 403);
    }

    public function index(Request $request)
    {
        $municipalityId = $this->scope($request);
        $request->validate(['week_start' => ['nullable', 'date']]);
        $weekStart = $request->filled('week_start')
            ? CarbonImmutable::parse($request->input('week_start'))->startOfWeek(CarbonImmutable::MONDAY)->toDateString()
            : CarbonImmutable::now()->startOfWeek(CarbonImmutable::MONDAY)->subWeek()->toDateString();

        $rows = WeeklyPriceRecommendation::query()
            ->with('species:id,name')
            ->where('municipality_id', $municipalityId)
            ->whereDate('week_start', $weekStart)
            ->whereIn('sale_purpose', ['slaughter', 'breeding', 'fattening', 'work'])
            ->whereHas('species', fn ($query) => $query->where('is_active', true))
            ->orderBy('species_id')
            ->orderByRaw("FIELD(sale_purpose, 'slaughter', 'breeding', 'fattening', 'work')")
            ->get();

        return $this->success($rows);
    }

    public function generate(Request $request, WeeklyPriceRecommendationService $service)
    {
        $this->authority($request);
        $municipalityId = $this->scope($request);
        $data = $request->validate([
            'week_date' => ['required', 'date', 'before:today'],
        ]);

        $selectedWeekStart = CarbonImmutable::parse($data['week_date'])->startOfWeek(CarbonImmutable::MONDAY);
        $currentWeekStart = CarbonImmutable::now()->startOfWeek(CarbonImmutable::MONDAY);
        abort_if($selectedWeekStart->greaterThanOrEqualTo($currentWeekStart), 422, 'Recommendations can only be generated from a completed Monday–Sunday sales week.');

        $rows = $service->generate($municipalityId, $data['week_date'], $request->user()->id);

        return $this->success($rows, 'Weekly recommendations processed for all active species and sale purposes.');
    }

    public function show(Request $request, WeeklyPriceRecommendation $recommendation)
    {
        $municipalityId = $this->scope($request);
        abort_unless($recommendation->municipality_id === $municipalityId, 404);

        return $this->success($recommendation->load(
            'species:id,name',
            'generator:id,first_name,last_name',
            'user:id,first_name,last_name'
        ));
    }

    public function use(Request $request, WeeklyPriceRecommendation $recommendation, WeeklyPriceRecommendationService $service)
    {
        $municipalityId = $this->scope($request);
        abort_unless($request->user()->roles()->whereIn('slug', ['lgu_encoder', 'LGU_ENCODER'])->exists(), 403);

        $used = $service->use($recommendation->id, $municipalityId, $request->user()->id);

        return $this->success($used, 'Recommendation reserved for your price-reference draft.');
    }
}
