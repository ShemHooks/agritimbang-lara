<?php

namespace App\Services;

use App\Models\Barangay;
use App\Models\LivestockRecord;
use App\Models\Municipality;
use Illuminate\Support\Facades\DB;

class GisService
{
    public function municipalities(): array
    {
        return Municipality::query()
            ->where('is_active', true)
            ->orderBy('province')
            ->orderBy('name')
            ->get(['id', 'name', 'province'])
            ->toArray();
    }

    public function barangays(?string $municipalityId = null): array
    {
        return Barangay::query()
            ->where('is_active', true)
            ->whereHas(
                'municipality',
                fn($query) =>
                    $query->where('is_active', true)
            )
            ->when(
                $municipalityId,
                fn($query) => $query->where(
                    'municipality_id',
                    $municipalityId
                )
            )
            ->orderBy('name')
            ->get(['id', 'municipality_id', 'name', 'psgc_code'])
            ->toArray();
    }


    public function geojson(
        ?string $municipalityId = null,
        ?string $speciesId = null,
        bool $includeLivestock = false
    ): array {
        $barangays = Barangay::query()
            ->where('is_active', true)

            // Always restrict to active municipalities,
            // including livestock monitoring requests.
            ->whereHas('municipality', function ($query) {
                $query->where('is_active', true);
            })

            ->whereHas('geometry')

            // Eager-load municipality information
            // to prevent N+1 queries.
            ->with([
                'geometry',
                'municipality:id,name,province,is_active',
            ])

            ->when(
                $municipalityId,
                fn($query) => $query->where(
                    'municipality_id',
                    $municipalityId
                )
            )

            ->orderBy('name')
            ->get();

        $counts = $includeLivestock
            ? $this->countsByBarangay(
                $municipalityId,
                $speciesId
            )
            : [];

        $features = [];

        foreach ($barangays as $barangay) {
            $geometry = $this->normalizeGeometry(
                $barangay->geometry?->geometry,
                $barangay->geometry?->geometry_type
            );

            if ($geometry === null) {
                continue;
            }

            $municipality = $barangay->municipality;

            $properties = [
                'barangay_id' => $barangay->id,
                'municipality_id' => $barangay->municipality_id,

                // New municipality metadata.
                'municipality_name' => $municipality?->name,
                'municipality_is_active' => (bool) $municipality?->is_active,
                'province_name' => $municipality?->province,

                'name' => $barangay->name,
                'psgc_code' => $barangay->psgc_code,
            ];

            if ($includeLivestock) {
                $count = $counts[$barangay->id] ?? null;

                $properties += [
                    'total_livestock' => (int) ($count->total ?? 0),
                    'available' => (int) ($count->available ?? 0),
                    'in_transaction' => (int) ($count->in_transaction ?? 0),
                    'sold' => (int) ($count->sold ?? 0),
                    'archived' => (int) ($count->archived ?? 0),
                ];
            }

            $features[] = [
                'type' => 'Feature',
                'id' => $barangay->id,
                'geometry' => $geometry,
                'properties' => $properties,
            ];
        }

        return [
            'type' => 'FeatureCollection',
            'features' => $features,
        ];
    }


    public function livestockSummary(?string $municipalityId = null, ?string $barangayId = null, ?string $speciesId = null): array
    {
        $base = LivestockRecord::query()
            ->when($municipalityId, fn($query) => $query->where('municipality_id', $municipalityId))
            ->when($barangayId, fn($query) => $query->where('barangay_id', $barangayId))
            ->when($speciesId, fn($query) => $query->where('species_id', $speciesId));

        $statusCounts = (clone $base)
            ->select('status', DB::raw('COUNT(*) as total'))
            ->groupBy('status')
            ->pluck('total', 'status');

        $bySpecies = (clone $base)
            ->join('species', 'species.id', '=', 'livestock_records.species_id')
            ->select('species.id', 'species.name', DB::raw('COUNT(*) as total'))
            ->groupBy('species.id', 'species.name')
            ->orderBy('species.name')
            ->get()
            ->map(fn($row) => [
                'species_id' => $row->id,
                'species_name' => $row->name,
                'total' => (int) $row->total,
            ])
            ->all();

        return [
            'total_livestock' => (int) $statusCounts->sum(),
            'by_status' => [
                'available' => (int) ($statusCounts['available'] ?? 0),
                'in_transaction' => (int) ($statusCounts['in_transaction'] ?? 0),
                'sold' => (int) ($statusCounts['sold'] ?? 0),
                'archived' => (int) ($statusCounts['archived'] ?? 0),
            ],
            'by_species' => $bySpecies,
        ];
    }

    private function countsByBarangay(?string $municipalityId, ?string $speciesId): array
    {
        return LivestockRecord::query()
            ->select('barangay_id')
            ->selectRaw('COUNT(*) as total')
            ->selectRaw("SUM(CASE WHEN status = 'available' THEN 1 ELSE 0 END) as available")
            ->selectRaw("SUM(CASE WHEN status = 'in_transaction' THEN 1 ELSE 0 END) as in_transaction")
            ->selectRaw("SUM(CASE WHEN status = 'sold' THEN 1 ELSE 0 END) as sold")
            ->selectRaw("SUM(CASE WHEN status = 'archived' THEN 1 ELSE 0 END) as archived")
            ->when($municipalityId, fn($query) => $query->where('municipality_id', $municipalityId))
            ->when($speciesId, fn($query) => $query->where('species_id', $speciesId))
            ->groupBy('barangay_id')
            ->get()
            ->keyBy('barangay_id')
            ->all();
    }

    private function normalizeGeometry(?array $geometry, ?string $geometryType): ?array
    {
        if ($geometry === null) {
            return null;
        }

        if (($geometry['type'] ?? null) === 'Feature') {
            $geometry = $geometry['geometry'] ?? null;
        }

        if (!is_array($geometry)) {
            return null;
        }

        if (($geometry['type'] ?? null) === 'FeatureCollection') {
            return null;
        }

        if (!isset($geometry['type'], $geometry['coordinates'])) {
            $geometry = [
                'type' => $geometryType,
                'coordinates' => $geometry['coordinates'] ?? $geometry,
            ];
        }

        if (
            !in_array($geometry['type'] ?? null, ['Polygon', 'MultiPolygon'], true)
            || !is_array($geometry['coordinates'] ?? null)
            || $geometry['coordinates'] === []
        ) {
            return null;
        }

        return $geometry;
    }
}
