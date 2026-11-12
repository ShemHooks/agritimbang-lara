<?php

namespace App\Services;

use App\Models\PriceReference;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;
use App\Models\TransactionItem;
use Illuminate\Support\Facades\DB;

class PriceAnalyticsService
{



    public function weeklyRecommendation(
        User $user,
        array $filters
    ): array {
        $municipalityId = $user->lguOfficerProfile?->municipality_id;

        if (!$municipalityId) {
            throw ValidationException::withMessages([
                'user' => 'Your LGU account has no assigned municipality.',
            ]);
        }

        $effectiveDate = CarbonImmutable::parse(
            $filters['effective_from'],
            config('app.timezone')
        );

        $weekStart = $effectiveDate
            ->startOfWeek(CarbonImmutable::MONDAY)
            ->subWeek();

        $weekEnd = $weekStart->addDays(6);

        $query = TransactionItem::query()
            ->join(
                'transactions',
                'transactions.id',
                '=',
                'transaction_items.transaction_id'
            )
            ->join(
                'livestock_records',
                'livestock_records.id',
                '=',
                'transaction_items.livestock_id'
            )
            ->where('transactions.status', 'verified')
            ->whereNotNull('transactions.verified_at')
            ->where('transactions.municipality_id', $municipalityId)
            ->where('livestock_records.species_id', $filters['species_id'])
            ->whereDate(
                'transactions.verified_at',
                '>=',
                $weekStart->toDateString()
            )
            ->whereDate(
                'transactions.verified_at',
                '<=',
                $weekEnd->toDateString()
            )
            ->where('transaction_items.weight_used_kg', '>', 0)
            ->where('transaction_items.actual_selling_price', '>', 0);

        if (!empty($filters['breed_id'])) {
            $query->where(
                'livestock_records.breed_id',
                $filters['breed_id']
            );
        }

        if (!empty($filters['barangay_id'])) {
            $query->where(
                'transactions.barangay_id',
                $filters['barangay_id']
            );
        }

        // A sale-purpose filter is intentionally not applied here.
        // Confirm whether LivestockRecord stores a sale_purpose
        // field before enabling purpose-specific recommendations.

        $stats = $query->selectRaw(
            'COUNT(*) as livestock_count'
        )->selectRaw(
                'COUNT(DISTINCT transactions.id) as transaction_count'
            )->selectRaw(
                'SUM(transaction_items.actual_selling_price) as total_sales'
            )->selectRaw(
                'SUM(transaction_items.weight_used_kg) as total_weight'
            )->first();

        $livestockCount = (int) ($stats?->livestock_count ?? 0);
        $totalSales = (float) ($stats?->total_sales ?? 0);
        $totalWeight = (float) ($stats?->total_weight ?? 0);

        $available = $livestockCount > 0 && $totalWeight > 0;

        return [
            'available' => $available,
            'source' => 'verified_transactions',
            'method' => 'weight_adjusted_average',
            'municipality_id' => $municipalityId,
            'species_id' => $filters['species_id'],
            'breed_id' => $filters['breed_id'] ?? null,
            'barangay_id' => $filters['barangay_id'] ?? null,
            'sale_purpose' => $filters['sale_purpose'] ?? null,
            'sale_purpose_applied' => false,
            'week_start' => $weekStart->toDateString(),
            'week_end' => $weekEnd->toDateString(),
            'record_count' => $livestockCount,
            'transaction_count' => (int) ($stats?->transaction_count ?? 0),
            'total_selling_price' => round($totalSales, 2),
            'total_weight_kg' => round($totalWeight, 4),
            'average_price_per_kg' => $available
                ? round($totalSales / $totalWeight, 2)
                : null,
        ];
    }


    public function history(?string $municipalityId, ?string $speciesId = null, ?string $breedId = null): array
    {
        $rows = PriceReference::query()
            ->where('status', 'official')
            ->when($municipalityId, fn($q) => $q->where('municipality_id', $municipalityId))
            ->when($speciesId, fn($q) => $q->where('species_id', $speciesId))
            ->when($breedId, fn($q) => $q->where('breed_id', $breedId))
            ->with(['species:id,name', 'breed:id,name'])
            ->orderBy('effective_from')->orderBy('id')
            ->get(['id', 'municipality_id', 'barangay_id', 'species_id', 'breed_id', 'sale_purpose', 'price_per_kg', 'effective_from', 'effective_to']);

        return [
            'points' => $rows->map(fn($p) => [
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
