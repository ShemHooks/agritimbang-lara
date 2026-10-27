<?php

namespace App\Services;

use App\Models\FarmerProfile;
use App\Models\LivestockRecord;
use App\Models\Transaction;
use App\Models\Valuation;
use Illuminate\Support\Facades\DB;

class DashboardService
{
    public function overview(?string $municipalityId): array
    {
        $scope = fn($query) => $query->when($municipalityId, fn($q) => $q->where('municipality_id', $municipalityId));
        $livestock = $scope(LivestockRecord::query());
        $status = (clone $livestock)->select('status')->selectRaw('COUNT(*) AS total')->groupBy('status')->pluck('total', 'status');
        $verified = $scope(Transaction::query())->where('status', 'verified');

        // Select only the newest valuation for each livestock record to avoid double-counting.
        $latestValuationIds = Valuation::query()->select('id')
            ->whereColumn('livestock_id', 'livestock_records.id')
            ->orderByDesc('created_at')->orderByDesc('id')->limit(1);
        $inventoryValue = (float) (clone $livestock)
            ->whereIn('status', ['available', 'in_transaction'])
            ->join('valuations', function ($join) use ($latestValuationIds) {
                $join->on('valuations.livestock_id', '=', 'livestock_records.id')
                    ->where('valuations.id', '=', $latestValuationIds);
            })->sum('valuations.estimated_value');

        return [
            'registered_farmers' => $scope(FarmerProfile::query())->count(),
            'total_livestock' => (int) $status->sum(),
            'livestock_by_status' => collect(['available', 'in_transaction', 'sold', 'archived'])
                ->mapWithKeys(fn($key) => [$key => (int) ($status[$key] ?? 0)])->all(),
            'verified_transactions' => (clone $verified)->count(),
            'verified_sales_total' => round((float) (clone $verified)->sum('total_selling_price'), 2),
            'current_estimated_inventory_value' => round($inventoryValue, 2),
        ];
    }

    public function livestock(?string $municipalityId): array
    {
        $base = LivestockRecord::query()->when($municipalityId, fn($q) => $q->where('livestock_records.municipality_id', $municipalityId));
        return [
            'by_species' => (clone $base)->join('species', 'species.id', '=', 'livestock_records.species_id')
                ->select('species.id', 'species.name')->selectRaw('COUNT(*) AS total')
                ->groupBy('species.id', 'species.name')->orderBy('species.name')->get(),
            'by_barangay' => (clone $base)->join('barangays', 'barangays.id', '=', 'livestock_records.barangay_id')
                ->select('barangays.id', 'barangays.name')->selectRaw('COUNT(*) AS total')
                ->groupBy('barangays.id', 'barangays.name')->orderBy('barangays.name')->get(),
        ];
    }

    public function transactions(?string $municipalityId): array
    {
        $base = Transaction::query()->when($municipalityId, fn($q) => $q->where('municipality_id', $municipalityId));
        return [
            'by_status' => (clone $base)->select('status')->selectRaw('COUNT(*) AS total')->groupBy('status')->get(),
            'verified_monthly' => (clone $base)->where('status', 'verified')
                ->selectRaw("DATE_FORMAT(verified_at, '%Y-%m') AS month")
                ->selectRaw('COUNT(*) AS transaction_count')
                ->selectRaw('SUM(total_selling_price) AS selling_total')
                ->whereNotNull('verified_at')->groupBy('month')->orderBy('month')->get(),
        ];
    }


    public function valuations(?string $municipalityId): array
    {
        $base = Valuation::query()
            ->join(
                'livestock_records',
                'livestock_records.id',
                '=',
                'valuations.livestock_id'
            )
            ->when(
                $municipalityId,
                fn($q) => $q->where(
                    'livestock_records.municipality_id',
                    $municipalityId
                )
            );

        // Historical valuation calculations.
        $calculationCount = (clone $base)->count();

        $averageValue = (float) (clone $base)
            ->avg('valuations.estimated_value');

        // Newest valuation for each livestock record.
        $latestValuationIds = Valuation::query()
            ->select('id')
            ->whereColumn(
                'livestock_id',
                'livestock_records.id'
            )
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit(1);

        // Current eligible inventory only.
        $inventoryValue = (float) LivestockRecord::query()
            ->when(
                $municipalityId,
                fn($q) => $q->where(
                    'livestock_records.municipality_id',
                    $municipalityId
                )
            )
            ->whereIn('livestock_records.status', [
                'available',
                'in_transaction',
            ])
            ->join('valuations', function ($join) use ($latestValuationIds) {
                $join->on(
                    'valuations.livestock_id',
                    '=',
                    'livestock_records.id'
                )->where(
                        'valuations.id',
                        '=',
                        $latestValuationIds
                    );
            })
            ->sum('valuations.estimated_value');

        // Weight method distribution.
        $byWeightType = (clone $base)
            ->select('valuations.weight_type')
            ->selectRaw('COUNT(*) AS total')
            ->groupBy('valuations.weight_type')
            ->get();

        // Historical calculation activity by month.
        $monthly = (clone $base)
            ->selectRaw(
                "DATE_FORMAT(valuations.created_at, '%Y-%m') AS month"
            )
            ->selectRaw('COUNT(*) AS calculation_count')
            ->selectRaw(
                'AVG(valuations.estimated_value) AS average_value'
            )
            ->groupBy('month')
            ->orderBy('month')
            ->get();

        // Historical valuations grouped by species.
        $bySpecies = (clone $base)
            ->join(
                'species',
                'species.id',
                '=',
                'livestock_records.species_id'
            )
            ->select(
                'species.id',
                'species.name'
            )
            ->selectRaw('COUNT(*) AS calculation_count')
            ->selectRaw(
                'AVG(valuations.estimated_value) AS average_value'
            )
            ->groupBy(
                'species.id',
                'species.name'
            )
            ->orderBy('species.name')
            ->get();

        // Average FPC adjustment factors.
        $adjustments = (clone $base)
            ->selectRaw(
                'AVG(valuations.breed_factor) AS breed_factor'
            )
            ->selectRaw(
                'AVG(valuations.age_factor) AS age_factor'
            )
            ->selectRaw(
                'AVG(valuations.reproductive_factor) AS reproductive_factor'
            )
            ->selectRaw(
                'AVG(valuations.frame_factor) AS frame_factor'
            )
            ->selectRaw(
                'AVG(valuations.condition_factor) AS condition_factor'
            )
            ->first();

        return [
            'calculation_count' => $calculationCount,

            'average_estimated_value' => round(
                $averageValue,
                2
            ),

            'latest_inventory_value' => round(
                (float) $inventoryValue,
                2
            ),

            'by_weight_type' => $byWeightType,

            'monthly' => $monthly,

            'by_species' => $bySpecies,

            'average_adjustments' => [
                'breed_factor' => round(
                    (float) ($adjustments?->breed_factor ?? 0),
                    4
                ),
                'age_factor' => round(
                    (float) ($adjustments?->age_factor ?? 0),
                    4
                ),
                'reproductive_factor' => round(
                    (float) ($adjustments?->reproductive_factor ?? 0),
                    4
                ),
                'frame_factor' => round(
                    (float) ($adjustments?->frame_factor ?? 0),
                    4
                ),
                'condition_factor' => round(
                    (float) ($adjustments?->condition_factor ?? 0),
                    4
                ),
            ],
        ];
    }

}
