<?php

namespace App\Services;

use App\Models\LivestockRecord;
use App\Models\PriceReference;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;

class PriceReferenceResolver
{
    public function resolve(
        LivestockRecord $livestock,
        CarbonInterface $valuationDate
    ): array {
        $date = $valuationDate->toDateString();

        if (!$livestock->species_id || !$livestock->municipality_id) {
            throw ValidationException::withMessages([
                'price_reference' =>
                    'Livestock species and municipality are required for price resolution.',
            ]);
        }

        $query = PriceReference::query()
            ->where('municipality_id', $livestock->municipality_id)
            ->where('species_id', $livestock->species_id)
            ->where('sale_purpose', $livestock->sale_purpose)
            ->where(function (Builder $categoryQuery) {
                $categoryQuery->whereNull('reference_category')
                    ->orWhere('reference_category', 'not like', 'Piglet%');
            })
            ->where('status', 'official')
            ->whereNotNull('approved_by')
            ->whereNotNull('approved_at')
            ->where('price_per_kg', '>', 0);


        // Official prices are municipality-wide. Barangay-specific references
        // are intentionally not considered by valuation calculations.
        $candidates = [];

        if ($livestock->breed_id) {
            $candidates[] = [
                'barangay_id' => null,
                'breed_id' => $livestock->breed_id,
                'source_level' => 'municipality',
                'match_type' => 'exact_breed',
            ];
        }

        $candidates[] = [
            'barangay_id' => null,
            'breed_id' => null,
            'source_level' => 'municipality',
            'match_type' => 'species_only',
        ];

        // First prefer a reference that is explicitly effective on the valuation date.
        foreach ($candidates as $candidate) {
            $candidateQuery = $this->applyCandidate(clone $query, $candidate)
                ->whereDate('effective_from', '<=', $date)
                ->where(function (Builder $activeQuery) use ($date) {
                    $activeQuery->whereNull('effective_to')
                        ->orWhereDate('effective_to', '>=', $date);
                });
            $reference = $candidateQuery
                ->orderByDesc('effective_from')
                ->orderByDesc('approved_at')
                ->orderBy('id')
                ->first();

            if ($reference) {
                return $this->formatResult($reference, $candidate, $date, false);
            }
        }

        // Carry forward the latest previously approved reference when no newer
        // reference is effective yet. Future-dated prices are never applied early.
        foreach ($candidates as $candidate) {
            $candidateQuery = $this->applyCandidate(clone $query, $candidate)
                ->whereDate('effective_from', '<=', $date);

            $reference = $candidateQuery
                ->orderByDesc('effective_from')
                ->orderByDesc('approved_at')
                ->orderBy('id')
                ->first();

            if ($reference) {
                return $this->formatResult($reference, $candidate, $date, true);
            }
        }

        throw ValidationException::withMessages([
            'price_reference' =>
                'No applicable MAO-approved official price reference is available for this livestock, locality, sale purpose, and valuation date.',
        ]);
    }

    private function applyCandidate(Builder $candidateQuery, array $candidate): Builder
    {
        if ($candidate['barangay_id'] === null) {
            $candidateQuery->whereNull('barangay_id');
        } else {
            $candidateQuery->where('barangay_id', $candidate['barangay_id']);
        }

        if ($candidate['breed_id'] === null) {
            $candidateQuery->whereNull('breed_id');
        } else {
            $candidateQuery->where('breed_id', $candidate['breed_id']);
        }

        return $candidateQuery;
    }

    private function formatResult(
        PriceReference $reference,
        array $candidate,
        string $date,
        bool $carriedForward = false
    ): array {
        return [
            'price_reference_id' => $reference->id,

            'base_price_per_kg' => $reference->price_per_kg,

            'source_type' => 'mao_official',
            'source_level' => $candidate['source_level'],
            'source_name' => $reference->source,

            'match_type' => $candidate['match_type'],
            'is_fallback' =>
                $carriedForward || $candidate['match_type'] !== 'exact_breed',
            'fallback_reason' => $carriedForward
                ? 'No newer approved price is effective for this date; the latest prior approved price was carried forward.'
                : null,

            'municipality_id' => $reference->municipality_id,
            'barangay_id' => null,
            'species_id' => $reference->species_id,
            'breed_id' => $reference->breed_id,
            'sale_purpose' => $reference->sale_purpose,

            'effective_from' =>
                $reference->effective_from->toDateString(),

            'effective_to' =>
                $reference->effective_to?->toDateString(),

            'valuation_date' => $date,

            'approved_by' => $reference->approved_by,
            'approved_at' =>
                $reference->approved_at?->toIso8601String(),
        ];
    }
}