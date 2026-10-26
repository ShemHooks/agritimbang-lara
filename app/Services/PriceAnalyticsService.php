<?php

namespace App\Services;

use App\Models\PriceReference;

class PriceAnalyticsService
{
    public function history(?string $municipalityId, ?string $speciesId = null, ?string $breedId = null): array
    {
        $rows = PriceReference::query()
            ->where('status', 'official')
            ->when($municipalityId, fn ($q) => $q->where('municipality_id', $municipalityId))
            ->when($speciesId, fn ($q) => $q->where('species_id', $speciesId))
            ->when($breedId, fn ($q) => $q->where('breed_id', $breedId))
            ->with(['species:id,name', 'breed:id,name'])
            ->orderBy('effective_from')->orderBy('id')
            ->get(['id', 'municipality_id', 'barangay_id', 'species_id', 'breed_id', 'sale_purpose', 'price_per_kg', 'effective_from', 'effective_to']);

        return [
            'points' => $rows->map(fn ($p) => [
                'id' => $p->id,
                'municipality_id' => $p->municipality_id,
                'barangay_id' => $p->barangay_id,
                'species_id' => $p->species_id,
                'species_name' => $p->species?->name,
                'breed_id' => $p->breed_id,
                'breed_name' => $p->breed?->name,
                'sale_purpose' => $p->sale_purpose,
                'effective_from' => $p->effective_from?->toDateString(),
                'effective_to' => $p->effective_to?->toDateString(),
                'price_per_kg' => (float) $p->price_per_kg,
            ])->all(),
            'count' => $rows->count(),
        ];
    }
}
