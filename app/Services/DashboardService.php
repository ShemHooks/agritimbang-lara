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
        $monthStart = now()->startOfMonth();
        $nextMonth = now()->copy()->startOfMonth()->addMonth();
        $verified = $scope(Transaction::query())->where('status', 'verified')
            ->whereNotNull('transaction_date')
            ->where('transaction_date', '>=', $monthStart)
            ->where('transaction_date', '<', $nextMonth);

        $yearStart = now()->startOfYear();
        $nextYear = now()->copy()->startOfYear()->addYear();
        $verifiedYearlySales = (float) $scope(Transaction::query())
            ->where('status', 'verified')
            ->whereNotNull('transaction_date')
            ->where('transaction_date', '>=', $yearStart)
            ->where('transaction_date', '<', $nextYear)
            ->sum('total_selling_price');

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
            // Keep the all-time total for analytics, and expose a separate
            // current-month registration count for the dashboard card.
            'total_livestock' => (int) $status->sum(),
            'monthly_livestock_count' => (int) LivestockRecord::query()
                ->when(
                    $municipalityId,
                    fn ($query) => $query->where('municipality_id', $municipalityId)
                )
                // Count every record created in this calendar month, regardless
                // of current livestock status; do not use the all-time status sum.
                ->where('created_at', '>=', $monthStart->toDateTimeString())
                ->where('created_at', '<', $nextMonth->toDateTimeString())
                ->count(),
            'livestock_by_status' => collect(['available', 'in_transaction', 'sold', 'archived'])
                ->mapWithKeys(fn($key) => [$key => (int) ($status[$key] ?? 0)])->all(),
            'verified_transactions' => (clone $verified)->count(),
            'verified_sales_total' => round((float) (clone $verified)->sum('total_selling_price'), 2),
            'yearly_verified_sales_total' => round($verifiedYearlySales, 2),
            'monthly_period_label' => now()->translatedFormat('F Y'),
            'yearly_period_label' => now()->format('Y'),
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
            'by_status_year' => (clone $base)
                ->whereNotNull('transaction_date')
                ->selectRaw('YEAR(transaction_date) AS year')
                ->addSelect('status')
                ->selectRaw('COUNT(*) AS total')
                ->groupBy('year', 'status')->orderBy('year')->get(),
            'verified_monthly' => (clone $base)->where('status', 'verified')
                ->whereNotNull('transaction_date')
                ->selectRaw("DATE_FORMAT(transaction_date, '%Y-%m') AS month")
                ->selectRaw('COUNT(*) AS transaction_count')
                ->selectRaw('SUM(total_selling_price) AS selling_total')
                ->groupBy('month')->orderBy('month')->get(),
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

        // Year-based summaries drive the Reporting Year filter across the page.
        $byYear = (clone $base)
            ->selectRaw('YEAR(valuations.created_at) AS year')
            ->selectRaw('COUNT(*) AS calculation_count')
            ->selectRaw('AVG(valuations.estimated_value) AS average_value')
            ->selectRaw("SUM(CASE WHEN valuations.weight_type = 'actual' THEN 1 ELSE 0 END) AS actual_count")
            ->groupBy('year')->orderBy('year')->get();

        $byWeightTypeYear = (clone $base)
            ->selectRaw('YEAR(valuations.created_at) AS year')
            ->addSelect('valuations.weight_type')
            ->selectRaw('COUNT(*) AS total')
            ->groupBy('year', 'valuations.weight_type')->orderBy('year')->get();

        $bySpeciesYear = (clone $base)
            ->join('species', 'species.id', '=', 'livestock_records.species_id')
            ->selectRaw('YEAR(valuations.created_at) AS year')
            ->addSelect('species.id', 'species.name')
            ->selectRaw('COUNT(*) AS calculation_count')
            ->selectRaw('AVG(valuations.estimated_value) AS average_value')
            ->groupBy('year', 'species.id', 'species.name')->orderBy('year')->orderBy('species.name')->get();

        $adjustmentsByYear = (clone $base)
            ->selectRaw('YEAR(valuations.created_at) AS year')
            ->selectRaw('AVG(valuations.breed_factor) AS breed_factor')
            ->selectRaw('AVG(valuations.age_factor) AS age_factor')
            ->selectRaw('AVG(valuations.reproductive_factor) AS reproductive_factor')
            ->selectRaw('AVG(valuations.frame_factor) AS frame_factor')
            ->selectRaw('AVG(valuations.condition_factor) AS condition_factor')
            ->groupBy('year')->orderBy('year')->get();

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
            'by_year' => $byYear,
            'by_weight_type_year' => $byWeightTypeYear,
            'by_species_year' => $bySpeciesYear,
            'adjustments_by_year' => $adjustmentsByYear,

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
