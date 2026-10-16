<?php

namespace App\Services;

use App\Models\LivestockRecord;
use App\Models\PricingAdjustmentRule;
use App\Models\WeightEstimation;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;

class PricingAdjustmentResolver
{
    private const CONDITION_FACTORS = [
        1 => '0.6500',
        2 => '0.8000',
        3 => '1.0000',
        4 => '1.1200',
        5 => '1.2000',
    ];

    /**
     * Resolve all FPC adjustment factors.
     */
    public function resolve(
        LivestockRecord $livestock,
        CarbonInterface $valuationDate,
        ?WeightEstimation $weightEstimation = null
    ): array {
        $date = $valuationDate->toDateString();

        $livestock->loadMissing(['species', 'breed']);

        $breedKey = $livestock->breed?->code;
        $ageKey = $livestock->age_group;

        $reproductiveKey = $this->resolveReproductiveKey($livestock);

        // Frame is sourced from the WET classification snapshot.
        $frameKey = $weightEstimation?->frame_category;

        $breed = $this->resolveRule(
            $livestock,
            'breed',
            $breedKey,
            $date
        );

        $age = $this->resolveRule(
            $livestock,
            'age_stage',
            $ageKey,
            $date
        );

        $reproductive = $this->resolveRule(
            $livestock,
            'reproductive_status',
            $reproductiveKey,
            $date
        );

        $frame = $this->resolveRule(
            $livestock,
            'frame',
            $frameKey,
            $date
        );

        $condition = $this->resolveConditionFactor(
            $livestock->condition_score
        );

        return [
            'breed_factor' => $breed['factor'],
            'age_factor' => $age['factor'],
            'reproductive_factor' => $reproductive['factor'],
            'frame_factor' => $frame['factor'],
            'condition_factor' => $condition['factor'],

            'breakdown' => [
                'breed' => $breed,
                'age_stage' => $age,
                'reproductive_status' => $reproductive,
                'frame' => $frame,
                'condition' => $condition,
            ],
        ];
    }

    /**
     * Resolve the most specific approved rule.
     */
    private function resolveRule(
        LivestockRecord $livestock,
        string $dimension,
        ?string $categoryKey,
        string $date
    ): array {
        $default = [
            'factor' => '1.0000',
            'rule_id' => null,
            'category_key' => $categoryKey,
            'source' => 'default_neutral',
            'source_note' => null,
        ];

        if (!$categoryKey || $categoryKey === 'unknown') {
            return $default;
        }

        $baseQuery = PricingAdjustmentRule::query()
            ->where('species_id', $livestock->species_id)
            ->where('dimension', $dimension)
            ->where('category_key', $categoryKey)
            ->where('status', 'approved')
            ->whereNotNull('approved_by')
            ->where('factor', '>', 0)
            ->whereDate('effective_from', '<=', $date)
            ->where(function (Builder $query) use ($date) {
                $query->whereNull('effective_to')
                    ->orWhereDate('effective_to', '>=', $date);
            });

        /*
         * Precedence:
         * 1. Exact breed + exact sale purpose
         * 2. Exact breed + any sale purpose
         * 3. All breeds + exact sale purpose
         * 4. All breeds + any sale purpose
         */
        $scopes = [];

        if ($livestock->breed_id) {
            $scopes[] = [
                'breed_id' => $livestock->breed_id,
                'sale_purpose' => $livestock->sale_purpose,
            ];

            $scopes[] = [
                'breed_id' => $livestock->breed_id,
                'sale_purpose' => null,
            ];
        }

        $scopes[] = [
            'breed_id' => null,
            'sale_purpose' => $livestock->sale_purpose,
        ];

        $scopes[] = [
            'breed_id' => null,
            'sale_purpose' => null,
        ];

        foreach ($scopes as $scope) {
            $query = clone $baseQuery;

            if ($scope['breed_id'] === null) {
                $query->whereNull('breed_id');
            } else {
                $query->where('breed_id', $scope['breed_id']);
            }

            if ($scope['sale_purpose'] === null) {
                $query->whereNull('sale_purpose');
            } else {
                $query->where(
                    'sale_purpose',
                    $scope['sale_purpose']
                );
            }

            $rule = $query
                ->orderByDesc('effective_from')
                ->orderByDesc('created_at')
                ->orderBy('id')
                ->first();

            if ($rule) {
                return [
                    'factor' => $rule->factor,
                    'rule_id' => $rule->id,
                    'category_key' => $categoryKey,
                    'source' => 'approved_rule',
                    'source_note' => $rule->source_note,
                    'effective_from' =>
                        $rule->effective_from->toDateString(),
                    'effective_to' =>
                        $rule->effective_to?->toDateString(),
                ];
            }
        }

        return $default;
    }


    private function resolveReproductiveKey(
        LivestockRecord $livestock
    ): ?string {
        if ($livestock->sex !== 'female') {
            return null;
        }

        $status = $livestock->reproductive_status;

        if (!$status || $status === 'unknown') {
            return null;
        }

        // Parity-based eligibility for animals that have given birth.
        if (
            $status === 'has_given_birth' &&
            ($livestock->parity === null || $livestock->parity < 1)
        ) {
            return null;
        }

        return $status;
    }

    /**
     * Condition factors are fixed by the FPC specification.
     */
    private function resolveConditionFactor(
        ?int $score
    ): array {
        if ($score === null) {
            return [
                'factor' => '1.0000',
                'condition_score' => null,
                'source' => 'default_neutral',
            ];
        }

        if (!array_key_exists($score, self::CONDITION_FACTORS)) {
            throw ValidationException::withMessages([
                'condition_score' =>
                    'Condition score must be between 1 and 5.',
            ]);
        }

        return [
            'factor' => self::CONDITION_FACTORS[$score],
            'condition_score' => $score,
            'source' => 'fpc_specification',
        ];
    }
}