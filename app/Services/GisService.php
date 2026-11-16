<?php

namespace App\Services;

use App\Models\Barangay;
use App\Models\LivestockRecord;
use App\Models\Municipality;
use App\Models\TransactionItem;
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
        bool $includeLivestock = false,
        string $dataset = 'livestock',
        ?string $status = null,
        ?string $salePurpose = null,
        ?string $dateFrom = null,
        ?string $dateTo = null
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

        $counts = [];
        $speciesCounts = [];
        if ($includeLivestock && $dataset === 'verified_sales') {
            $counts = $this->verifiedSalesByBarangay($municipalityId, $speciesId, $salePurpose, $dateFrom, $dateTo);
            $speciesCounts = $this->verifiedSalesSpeciesByBarangay($municipalityId, $speciesId, $salePurpose, $dateFrom, $dateTo);
        } elseif ($includeLivestock) {
            $counts = $this->countsByBarangay($municipalityId, $speciesId, $status);
            $speciesCounts = $this->speciesCountsByBarangay($municipalityId, $speciesId, $status);
        }

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
                if ($dataset === 'verified_sales') {
                    $properties += [
                        'dataset' => 'verified_sales',
                        'verified_transactions' => (int) ($count->transaction_count ?? 0),
                        'verified_sales_total' => (float) ($count->sales_total ?? 0),
                        'verified_items' => (int) ($count->item_count ?? 0),
                        'by_species' => $speciesCounts[$barangay->id] ?? [],
                    ];
                } else {
                    $properties += [
                        'dataset' => 'livestock',
                        'total_livestock' => (int) ($count->total ?? 0),
                        'available' => (int) ($count->available ?? 0),
                        'in_transaction' => (int) ($count->in_transaction ?? 0),
                        'sold' => (int) ($count->sold ?? 0),
                        'by_species' => $speciesCounts[$barangay->id] ?? [],
                    ];
                }
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

    private function countsByBarangay(?string $municipalityId, ?string $speciesId, ?string $status = null): array
    {
        return LivestockRecord::query()
            ->select('barangay_id')
            ->selectRaw('COUNT(*) as total')
            ->selectRaw("SUM(CASE WHEN status = 'available' THEN 1 ELSE 0 END) as available")
            ->selectRaw("SUM(CASE WHEN status = 'in_transaction' THEN 1 ELSE 0 END) as in_transaction")
            ->selectRaw("SUM(CASE WHEN status = 'sold' THEN 1 ELSE 0 END) as sold")
            ->when($municipalityId, fn($query) => $query->where('municipality_id', $municipalityId))
            ->when($speciesId, fn($query) => $query->where('species_id', $speciesId))
            ->when($status, fn($query) => $query->where('status', $status))
            ->groupBy('barangay_id')
            ->get()
            ->keyBy('barangay_id')
            ->all();
    }

    private function verifiedSalesBaseQuery(?string $municipalityId, ?string $speciesId, ?string $salePurpose, ?string $dateFrom, ?string $dateTo)
    {
        return TransactionItem::query()
            ->join('transactions', 'transactions.id', '=', 'transaction_items.transaction_id')
            ->join('livestock_records', 'livestock_records.id', '=', 'transaction_items.livestock_id')
            ->where('transactions.status', 'verified')
            ->whereNotNull('transactions.verified_at')
            ->where('transaction_items.actual_selling_price', '>', 0)
            ->when($municipalityId, fn($q) => $q->where('transactions.municipality_id', $municipalityId))
            ->when($speciesId, fn($q) => $q->where('livestock_records.species_id', $speciesId))
            ->when($salePurpose, fn($q) => $q->where('livestock_records.sale_purpose', $salePurpose))
            ->when($dateFrom, fn($q) => $q->whereDate('transactions.transaction_date', '>=', $dateFrom))
            ->when($dateTo, fn($q) => $q->whereDate('transactions.transaction_date', '<=', $dateTo));
    }

    private function verifiedSalesByBarangay(?string $municipalityId, ?string $speciesId, ?string $salePurpose, ?string $dateFrom, ?string $dateTo): array
    {
        return $this->verifiedSalesBaseQuery($municipalityId, $speciesId, $salePurpose, $dateFrom, $dateTo)
            ->select('transactions.barangay_id')
            ->selectRaw('COUNT(transaction_items.id) as item_count')
            ->selectRaw('COUNT(DISTINCT transactions.id) as transaction_count')
            ->selectRaw('COALESCE(SUM(transaction_items.actual_selling_price), 0) as sales_total')
            ->groupBy('transactions.barangay_id')
            ->get()->keyBy('barangay_id')->all();
    }

    private function verifiedSalesSpeciesByBarangay(?string $municipalityId, ?string $speciesId, ?string $salePurpose, ?string $dateFrom, ?string $dateTo): array
    {
        return $this->verifiedSalesBaseQuery($municipalityId, $speciesId, $salePurpose, $dateFrom, $dateTo)
            ->join('species', 'species.id', '=', 'livestock_records.species_id')
            ->select('transactions.barangay_id', 'species.id as species_id', 'species.name as species_name')
            ->selectRaw('COUNT(transaction_items.id) as total')
            ->groupBy('transactions.barangay_id', 'species.id', 'species.name')
            ->orderBy('species.name')->get()
            ->groupBy('barangay_id')
            ->map(fn($rows) => $rows->map(fn($row) => [
                'species_id' => $row->species_id,
                'species_name' => $row->species_name,
                'total' => (int) $row->total,
            ])->values()->all())->all();
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

    private function speciesCountsByBarangay(
        ?string $municipalityId,
        ?string $speciesId,
        ?string $status = null
    ): array {
        return LivestockRecord::query()
            ->join(
                'species',
                'species.id',
                '=',
                'livestock_records.species_id'
            )
            ->select([
                'livestock_records.barangay_id',
                'species.id as species_id',
                'species.name as species_name',
            ])
            ->selectRaw('COUNT(*) as total')
            ->when(
                $municipalityId,
                fn($query) => $query->where(
                    'livestock_records.municipality_id',
                    $municipalityId
                )
            )
            ->when(
                $speciesId,
                fn($query) => $query->where(
                    'livestock_records.species_id',
                    $speciesId
                )
            )
            ->when($status, fn($query) => $query->where('livestock_records.status', $status))
            ->groupBy(
                'livestock_records.barangay_id',
                'species.id',
                'species.name'
            )
            ->orderBy('species.name')
            ->get()
            ->groupBy('barangay_id')
            ->map(fn($rows) => $rows->map(fn($row) => [
                'species_id' => $row->species_id,
                'species_name' => $row->species_name,
                'total' => (int) $row->total,
            ])->values()->all())
            ->all();
    }
}
