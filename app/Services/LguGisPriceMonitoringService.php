<?php

namespace App\Services;

use App\Models\PriceReference;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class LguGisPriceMonitoringService
{
    public function officialPrices(
        ?string $municipalityId = null,
        ?string $speciesId = null,
        ?string $salePurpose = null
    ): Collection {
        $today = Carbon::today()->toDateString();

        return PriceReference::query()
            ->where('status', 'official')
            ->whereDate('effective_from', '<=', $today)
            ->where(function ($query) use ($today) {
                $query->whereNull('effective_to')
                    ->orWhereDate('effective_to', '>=', $today);
            })
            ->whereHas('municipality', function ($query) {
                $query->where('is_active', true);
            })
            ->when(
                $municipalityId,
                fn($query) => $query->where(
                    'municipality_id',
                    $municipalityId
                )
            )
            ->when(
                $speciesId,
                fn($query) => $query->where(
                    'species_id',
                    $speciesId
                )
            )
            ->when(
                $salePurpose,
                fn($query) => $query->where(
                    'sale_purpose',
                    $salePurpose
                )
            )
            ->with([
                'municipality:id,name,province',
                'species:id,name',
                'breed:id,name',
                'barangay:id,name',
            ])
            ->orderBy('municipality_id')
            ->orderBy('species_id')
            ->orderByDesc('effective_from')
            ->get()
            ->map(fn(PriceReference $price) => [
                'id' => $price->id,
                'municipality_id' => $price->municipality_id,
                'municipality_name' => $price->municipality?->name,
                'province_name' => $price->municipality?->province,
                'species_id' => $price->species_id,
                'species_name' => $price->species?->name,
                'breed_name' => $price->breed?->name,
                'barangay_name' => $price->barangay?->name,
                'sale_purpose' => $price->sale_purpose,
                'price_per_kg' => $price->price_per_kg,
                'effective_from' =>
                    $price->effective_from?->toDateString(),
                'effective_to' =>
                    $price->effective_to?->toDateString(),
                'status' => $price->status,
            ]);
    }


    public function barangayPrices(
        string $municipalityId,
        string $barangayId,
        ?string $speciesId = null
    ): Collection {
        $today = Carbon::today()->toDateString();

        return PriceReference::query()
            ->where('status', 'official')
            ->where('municipality_id', $municipalityId)
            ->where('barangay_id', $barangayId)
            ->whereDate('effective_from', '<=', $today)
            ->where(function ($query) use ($today) {
                $query->whereNull('effective_to')
                    ->orWhereDate('effective_to', '>=', $today);
            })
            ->whereHas(
                'municipality',
                fn($query) =>
                    $query->where('is_active', true)
            )
            ->when(
                $speciesId,
                fn($query) => $query->where(
                    'species_id',
                    $speciesId
                )
            )
            ->with([
                'species:id,name',
                'breed:id,name',
            ])
            ->orderBy('species_id')
            ->orderBy('sale_purpose')
            ->orderByDesc('effective_from')
            ->get()
            ->map(fn(PriceReference $price) => [
                'species_id' => $price->species_id,
                'species_name' => $price->species?->name,
                'breed_name' => $price->breed?->name,
                'sale_purpose' => $price->sale_purpose,
                'price_per_kg' => $price->price_per_kg,
                'effective_from' =>
                    $price->effective_from?->toDateString(),
                'effective_to' =>
                    $price->effective_to?->toDateString(),
            ]);
    }

}
