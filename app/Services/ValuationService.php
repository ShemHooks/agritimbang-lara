<?php

namespace App\Services;

use App\Models\LivestockRecord;
use App\Models\User;
use App\Models\Valuation;
use App\Models\WeightEstimation;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ValuationService
{
    private const CALCULATION_VERSION = 'FPC-1.1';

    public function __construct(
        private readonly PriceReferenceResolver $priceResolver,
        private readonly PricingAdjustmentResolver $adjustmentResolver,
    ) {
    }

    public function calculate(
        LivestockRecord $livestock,
        User $user,
        ?CarbonInterface $valuationDate = null,
        ?WeightEstimation $weightEstimation = null
    ): Valuation {
        $valuationDate ??= Carbon::now();

        $livestock->loadMissing(['species', 'breed']);

        /*
         * 1. Retrieve a WET record if one was not provided.
         *
         * Even when actual weight exists, a WET record
         * may provide frame classification evidence.
         */
        if ($weightEstimation !== null) {
            if ($weightEstimation->livestock_id !== $livestock->id) {
                throw ValidationException::withMessages([
                    'weight_estimation_id' =>
                        'The selected weight estimation does not belong to this livestock.',
                ]);
            }
        } else {
            $weightEstimation = WeightEstimation::query()
                ->where('livestock_id', $livestock->id)
                ->latest('created_at')
                ->first();
        }

        /*
         * 2. Resolve weight.
         *
         * Actual scale weight always takes priority.
         */
        if ($livestock->actual_weight_kg !== null) {
            $weightType = 'actual';
            $weightUsed = (float) $livestock->actual_weight_kg;
        } elseif ($weightEstimation !== null) {
            $weightType = 'estimated';
            $weightUsed = (float) $weightEstimation->estimated_weight_kg;
        } else {
            throw ValidationException::withMessages([
                'weight' =>
                    'A valid actual weight or weight estimation is required.',
            ]);
        }

        if (!is_finite($weightUsed) || $weightUsed <= 0) {
            throw ValidationException::withMessages([
                'weight' => 'Livestock weight must be greater than zero.',
            ]);
        }

        /*
         * 3. Resolve the official base price.
         */
        $price = $this->priceResolver->resolve(
            $livestock,
            $valuationDate
        );

        /*
         * 4. Resolve approved adjustment factors.
         */
        $adjustments = $this->adjustmentResolver->resolve(
            $livestock,
            $valuationDate,
            $weightEstimation
        );

        /*
         * 5. Calculate the adjusted price per kilogram.
         */
        $basePrice = (float) $price['base_price_per_kg'];

        $finalPrice = $basePrice
            * (float) $adjustments['breed_factor']
            * (float) $adjustments['age_factor']
            * (float) $adjustments['reproductive_factor']
            * (float) $adjustments['frame_factor']
            * (float) $adjustments['condition_factor'];

        $finalPrice = round($finalPrice, 2);

        /*
         * 6. Calculate fair market value.
         */
        $fairValue = round($weightUsed * $finalPrice, 2);

        if (
            !is_finite($basePrice) ||
            !is_finite($finalPrice) ||
            !is_finite($fairValue) ||
            $basePrice <= 0 ||
            $finalPrice <= 0 ||
            $fairValue <= 0
        ) {
            throw ValidationException::withMessages([
                'valuation' =>
                    'The valuation produced an invalid monetary result.',
            ]);
        }

        /*
         * 7. Build a reproducible snapshot.
         */
        $snapshot = [
            'calculation_version' => self::CALCULATION_VERSION,
            'valuation_date' => $valuationDate->toDateString(),

            'livestock' => [
                'id' => $livestock->id,
                'species_id' => $livestock->species_id,
                'breed_id' => $livestock->breed_id,
                'sex' => $livestock->sex,
                'age_months' => $livestock->age_months,
                'age_group' => $livestock->age_group,
                'reproductive_status' => $livestock->reproductive_status,
                'parity' => $livestock->parity,
                'sale_purpose' => $livestock->sale_purpose,
                'condition_score' => $livestock->condition_score,
                'municipality_id' => $livestock->municipality_id,
                'barangay_id' => $livestock->barangay_id,
            ],

            'weight' => [
                'type' => $weightType,
                'weight_used_kg' => number_format(
                    $weightUsed,
                    4,
                    '.',
                    ''
                ),
                'actual_weight_kg' => $livestock->actual_weight_kg,
                'weight_estimation_id' => $weightEstimation?->id,
                'estimated_weight_kg' =>
                    $weightEstimation?->estimated_weight_kg,
                'formula_code' => $weightEstimation?->formula_code,
                'formula_version' => $weightEstimation?->formula_version,
                'estimate_confidence' =>
                    $weightEstimation?->estimate_confidence,
                'frame_category' =>
                    $weightEstimation?->frame_category,
                'frame_z' => $weightEstimation?->frame_z,
                'frame_stats_version' =>
                    $weightEstimation?->frame_stats_version,
            ],

            'price_reference' => $price,

            'adjustments' => $adjustments['breakdown'],

            'calculation' => [
                'base_price_per_kg' => number_format(
                    $basePrice,
                    2,
                    '.',
                    ''
                ),
                'breed_factor' => $adjustments['breed_factor'],
                'age_factor' => $adjustments['age_factor'],
                'reproductive_factor' =>
                    $adjustments['reproductive_factor'],
                'frame_factor' => $adjustments['frame_factor'],
                'condition_factor' =>
                    $adjustments['condition_factor'],
                'final_price_per_kg' => number_format(
                    $finalPrice,
                    2,
                    '.',
                    ''
                ),
                'estimated_value' => number_format(
                    $fairValue,
                    2,
                    '.',
                    ''
                ),
                'currency' => 'PHP',
            ],
        ];

        /*
         * 8. Persist a new immutable calculation record.
         */
        return DB::transaction(function () use ($livestock, $weightEstimation, $weightType, $weightUsed, $basePrice, $finalPrice, $fairValue, $price, $adjustments, $snapshot, $user) {
            return Valuation::create([
                'livestock_id' => $livestock->id,
                'price_reference_id' =>
                    $price['price_reference_id'],

                'weight_estimation_id' => $weightEstimation?->id,

                'weight_type' => $weightType,
                'weight_used_kg' => $weightUsed,

                'base_price_per_kg' => $basePrice,

                'breed_factor' =>
                    $adjustments['breed_factor'],
                'age_factor' =>
                    $adjustments['age_factor'],
                'reproductive_factor' =>
                    $adjustments['reproductive_factor'],
                'frame_factor' =>
                    $adjustments['frame_factor'],
                'condition_factor' =>
                    $adjustments['condition_factor'],

                'final_price_per_kg' => $finalPrice,
                'estimated_value' => $fairValue,

                'adjustment_breakdown' =>
                    $adjustments['breakdown'],

                'calculation_snapshot' => $snapshot,
                'calculation_version' =>
                    self::CALCULATION_VERSION,

                'calculated_by' => $user->id,
            ]);
        });
    }
}