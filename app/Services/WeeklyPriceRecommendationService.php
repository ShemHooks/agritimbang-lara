<?php

namespace App\Services;

use App\Models\Species;
use App\Models\TransactionItem;
use App\Models\WeeklyPriceRecommendation;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class WeeklyPriceRecommendationService
{
    private const SALE_PURPOSES = ['slaughter', 'breeding', 'fattening', 'work'];

    /**
     * Generate one recommendation for every active species/purpose pair.
     * The supplied date identifies the completed sales week (Monday-Sunday).
     */
    public function generate(string $municipalityId, string $weekDate, string $userId): array
    {
        $selectedDate = CarbonImmutable::parse($weekDate);
        $start = $selectedDate->startOfWeek(CarbonImmutable::MONDAY);
        $end = $start->addDays(6);

        $species = Species::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name']);

        $sales = TransactionItem::query()
            ->join('transactions', 'transactions.id', '=', 'transaction_items.transaction_id')
            ->join('livestock_records', 'livestock_records.id', '=', 'transaction_items.livestock_id')
            ->join('species', 'species.id', '=', 'livestock_records.species_id')
            ->join('farmer_profiles', 'farmer_profiles.id', '=', 'transactions.farmer_id')
            ->join('users', 'users.id', '=', 'farmer_profiles.user_id')
            ->where('transactions.municipality_id', $municipalityId)
            ->where('transactions.status', 'verified')
            ->whereNotNull('transactions.verified_at')
            // The reporting period is based on when the sale happened, not
            // when an LGU officer later completed its verification.
            ->whereBetween(DB::raw('DATE(transactions.transaction_date)'), [$start->toDateString(), $end->toDateString()])
            ->whereIn('livestock_records.sale_purpose', self::SALE_PURPOSES)
            ->where('transaction_items.actual_selling_price', '>', 0)
            ->select([
                'transactions.id as transaction_id',
                'transactions.transaction_date',
                'transactions.verified_at',
                'transactions.farmer_id',
                'users.first_name',
                'users.middle_name',
                'users.last_name',
                'livestock_records.id as livestock_id',
                'livestock_records.species_id',
                'livestock_records.sale_purpose',
                'species.name as species_name',
                'transaction_items.weight_used_kg',
                'transaction_items.actual_selling_price',
                'transaction_items.actual_selling_price_per_kg',
            ])
            ->get()
            ->filter(fn ($row) => (float) $row->weight_used_kg > 0)
            ->groupBy(fn ($row) => $row->species_id . '|' . strtolower((string) $row->sale_purpose));

        $saved = [];

        foreach ($species as $animal) {
            foreach (self::SALE_PURPOSES as $purpose) {
                $key = $animal->id . '|' . $purpose;
                /** @var Collection $rows */
                $rows = $sales->get($key, collect());

                $observations = $rows->map(function ($row) {
                    $weight = (float) $row->weight_used_kg;
                    $sellingPrice = (float) $row->actual_selling_price;
                    $recordedPerKg = (float) ($row->actual_selling_price_per_kg ?? 0);
                    $unitPrice = $recordedPerKg > 0
                        ? $recordedPerKg
                        : ($weight > 0 ? $sellingPrice / $weight : null);

                    return [
                        'transaction_id' => $row->transaction_id,
                        'date' => (string) $row->transaction_date,
                        'verified_at' => (string) $row->verified_at,
                        'farmer_id' => $row->farmer_id,
                        'farmer_name' => trim(implode(' ', array_filter([
                            $row->first_name,
                            $row->middle_name,
                            $row->last_name,
                        ]))),
                        'livestock_id' => $row->livestock_id,
                        'species_name' => $row->species_name,
                        'sale_purpose' => strtolower((string) $row->sale_purpose),
                        'weight_kg' => $weight,
                        'selling_price' => $sellingPrice,
                        'price_per_kg' => $unitPrice,
                        'price_unit' => 'per kg',
                        'observed_price' => $unitPrice,
                    ];
                })->filter(fn ($row) => $row['observed_price'] !== null && $row['observed_price'] > 0)->values()->all();

                $prices = array_column($observations, 'observed_price');
                sort($prices, SORT_NUMERIC);
                $q1 = $this->quantile($prices, 0.25);
                $q3 = $this->quantile($prices, 0.75);
                $iqr = $q3 - $q1;
                $lower = $q1 - (1.5 * $iqr);
                $upper = $q3 + (1.5 * $iqr);

                foreach ($observations as &$observation) {
                    $observation['included'] = $observation['observed_price'] >= $lower
                        && $observation['observed_price'] <= $upper;
                    $observation['reason'] = $observation['included'] ? 'Within IQR bounds' : 'IQR outlier';
                }
                unset($observation);

                $included = array_values(array_filter($observations, fn ($item) => $item['included']));
                $includedCount = count($included);
                $average = $includedCount > 0
                    ? array_sum(array_column($included, 'observed_price')) / $includedCount
                    : null;

                $existing = WeeklyPriceRecommendation::query()
                    ->where('municipality_id', $municipalityId)
                    ->where('species_id', $animal->id)
                    ->where('sale_purpose', $purpose)
                    ->whereDate('week_start', $start->toDateString())
                    ->first();

                // Never overwrite a recommendation already reserved by an Encoder.
                if ($existing && $existing->used_at) {
                    $saved[] = $existing->load('species');
                    continue;
                }

                $record = WeeklyPriceRecommendation::updateOrCreate(
                    [
                        'municipality_id' => $municipalityId,
                        'species_id' => $animal->id,
                        'sale_purpose' => $purpose,
                        'week_start' => $start->toDateString(),
                    ],
                    [
                        'week_end' => $end->toDateString(),
                        'recommended_price' => $includedCount >= 4 ? round($average, 2) : null,
                        'transaction_count' => count(array_unique(array_column($observations, 'transaction_id'))),
                        'observation_count' => count($observations),
                        'included_count' => $includedCount,
                        'excluded_count' => count($observations) - $includedCount,
                        'q1' => $q1,
                        'q3' => $q3,
                        'iqr' => $iqr,
                        'lower_bound' => $lower,
                        'upper_bound' => $upper,
                        'observations' => $observations,
                        'status' => $includedCount >= 4 ? 'available' : 'insufficient_data',
                        'generated_by' => $userId,
                        'used_by' => null,
                        'used_at' => null,
                        'price_reference_id' => null,
                    ]
                );

                $saved[] = $record->load('species');
            }
        }

        return $saved;
    }

    private function quantile(array $values, float $quantile): float
    {
        if (!$values) {
            return 0.0;
        }

        $position = (count($values) - 1) * $quantile;
        $base = (int) floor($position);
        $fraction = $position - $base;

        return isset($values[$base + 1])
            ? $values[$base] + $fraction * ($values[$base + 1] - $values[$base])
            : $values[$base];
    }

    public function use(string $id, string $municipalityId, string $userId): WeeklyPriceRecommendation
    {
        return DB::transaction(function () use ($id, $municipalityId, $userId) {
            $recommendation = WeeklyPriceRecommendation::query()
                ->whereKey($id)
                ->where('municipality_id', $municipalityId)
                ->lockForUpdate()
                ->firstOrFail();

            if (!in_array($recommendation->sale_purpose, self::SALE_PURPOSES, true)) {
                throw ValidationException::withMessages([
                    'recommendation' => 'This sale purpose is not supported by the weekly price-reference process.',
                ]);
            }

            if ($recommendation->status !== 'available'
                || $recommendation->used_at
                || $recommendation->price_reference_id
                || !$recommendation->recommended_price) {
                throw ValidationException::withMessages([
                    'recommendation' => 'This recommendation is unavailable or has already been used.',
                ]);
            }

            $recommendation->update([
                'used_by' => $userId,
                'used_at' => now(),
                'status' => 'used',
            ]);

            return $recommendation->fresh()->load('species');
        });
    }
}
