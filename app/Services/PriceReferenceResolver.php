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
            ->where('status', 'official')
            ->whereNotNull('approved_by')
            ->whereNotNull('approved_at')
            ->where('price_per_kg', '>', 0)
            ->whereDate('effective_from', '<=', $date)
            ->where(function (Builder $query) use ($date) {
                $query->whereNull('effective_to')
                    ->orWhereDate('effective_to', '>=', $date);
            });


        $candidates = [];

        if ($livestock->barangay_id) {
            if ($livestock->breed_id) {
                $candidates[] = [
                    'barangay_id' => $livestock->barangay_id,
                    'breed_id' => $livestock->breed_id,
                    'source_level' => 'barangay',
                    'match_type' => 'exact_breed',
                ];
            }

            $candidates[] = [
                'barangay_id' => $livestock->barangay_id,
                'breed_id' => null,
                'source_level' => 'barangay',
                'match_type' => 'species_only',
            ];
        }

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

        foreach ($candidates as $candidate) {
            $candidateQuery = clone $query;

            if ($candidate['barangay_id'] === null) {
                $candidateQuery->whereNull('barangay_id');
            } else {
                $candidateQuery->where(
                    'barangay_id',
                    $candidate['barangay_id']
                );
            }

            if ($candidate['breed_id'] === null) {
                $candidateQuery->whereNull('breed_id');
            } else {
                $candidateQuery->where(
                    'breed_id',
                    $candidate['breed_id']
                );
            }

            $reference = $candidateQuery
                ->orderByDesc('effective_from')
                ->orderByDesc('approved_at')
                ->orderBy('id')
                ->first();

            if ($reference) {
                return $this->formatResult(
                    $reference,
                    $candidate,
                    $date
                );
            }
        }

        throw ValidationException::withMessages([
            'price_reference' =>
                'No applicable MAO-approved official price reference is available for this livestock, locality, sale purpose, and valuation date.',
        ]);
    }

    private function formatResult(
        PriceReference $reference,
        array $candidate,
        string $date
    ): array {
        return [
            'price_reference_id' => $reference->id,

            'base_price_per_kg' => $reference->price_per_kg,

            'source_type' => 'mao_official',
            'source_level' => $candidate['source_level'],
            'source_name' => $reference->source,

            'match_type' => $candidate['match_type'],
            'is_fallback' =>
                $candidate['match_type'] !== 'exact_breed',

            'municipality_id' => $reference->municipality_id,
            'barangay_id' => $reference->barangay_id,
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