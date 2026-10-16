<?php

namespace App\Services;

use App\Models\LivestockRecord;
use App\Models\WeightEstimation;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class WeightEstimationService
{
    public function __construct(
        private readonly FormulaProfileResolver $formulaResolver,
        private readonly WeightFormulaCalculator $calculator,
        private readonly AgeGroupService $ageGroupService,
        private readonly FrameClassificationService $frameClassifier,
    ) {
    }

    public function estimate(
        LivestockRecord $livestock,
        array $measurements,
        User $user
    ): WeightEstimation {
        $livestock->loadMissing(['species', 'breed']);

        // 1. Select the applicable WET formula.
        $profile = $this->formulaResolver->resolve($livestock);

        // 2. Calculate estimated weight.
        $estimatedWeight = $this->calculator->calculate(
            $profile,
            $measurements
        );

        // 3. Determine confidence and warnings.
        [$confidence, $warnings] = $this->evaluateConfidence(
            $profile->formula_code,
            $estimatedWeight
        );

        /*
         * 4. Determine the effective age/stage.
         *
         * Hog stages depend on weight. Other species use
         * their recorded age-based classification.
         */
        $effectiveAgeGroup = $livestock->age_group;

        if (strtoupper($livestock->species->code) === 'HOG') {
            $effectiveAgeGroup = $this->ageGroupService->resolve(
                species: $livestock->species,
                ageMonths: $livestock->age_months,
                weightKg: $livestock->actual_weight_kg !== null
                ? (float) $livestock->actual_weight_kg
                : $estimatedWeight,
            );
        }

        /*
         * 5. Classify frame using the effective stage.
         *
         * Do not mutate the original model yet.
         * Use a temporary copy for statistics lookup.
         */
        $classificationLivestock = clone $livestock;
        $classificationLivestock->age_group = $effectiveAgeGroup;

        $frame = $this->frameClassifier->classify(
            $classificationLivestock,
            isset($measurements['body_length_cm'])
            ? (float) $measurements['body_length_cm']
            : null
        );

        // 6. Persist the estimation and its classification snapshot.
        return DB::transaction(function () use ($livestock, $profile, $measurements, $estimatedWeight, $confidence, $warnings, $effectiveAgeGroup, $frame, $user) {
            $estimation = WeightEstimation::create([
                'livestock_id' => $livestock->id,
                'formula_profile_id' => $profile->id,

                'heart_girth_cm' =>
                    $measurements['heart_girth_cm'] ?? null,

                'body_length_cm' =>
                    $measurements['body_length_cm'] ?? null,

                'rump_height_cm' =>
                    $measurements['rump_height_cm'] ?? null,

                'estimated_weight_kg' => $estimatedWeight,

                'formula_code' => $profile->formula_code,
                'formula_version' => $profile->version,

                'estimate_confidence' => $confidence,
                'warnings' => empty($warnings) ? null : $warnings,

                'frame_category' => $frame['frame_category'],
                'frame_z' => $frame['frame_z'],
                'reference_mean_body_length_cm' =>
                    $frame['reference_mean_body_length_cm'],
                'reference_sd_body_length_cm' =>
                    $frame['reference_sd_body_length_cm'],
                'reference_n' => $frame['reference_n'],
                'reference_group' => $frame['reference_group'],
                'frame_stats_version' => $frame['frame_stats_version'],

                'calculated_by' => $user->id,
            ]);

            /*
             * Update the hog's current age/stage only when
             * no actual scale weight is recorded.
             */
            if (
                strtoupper($livestock->species->code) === 'HOG' &&
                $livestock->actual_weight_kg === null &&
                $effectiveAgeGroup !== $livestock->age_group
            ) {
                $livestock->update([
                    'age_group' => $effectiveAgeGroup,
                ]);
            }

            return $estimation->fresh([
                'formulaProfile',
                'livestock',
            ]);
        });
    }

    private function evaluateConfidence(
        string $formulaCode,
        float $estimatedWeight
    ): array {
        $confidence = 'normal';
        $warnings = [];

        /*
         * Philippine native hog regression was developed
         * for approximately 20–50 kg animals.
         *
         * We still return the estimate outside that range,
         * but explicitly lower confidence.
         */
        if (
            $formulaCode === 'HOG_NATIVE_REGRESSION' &&
            ($estimatedWeight < 20 || $estimatedWeight > 50)
        ) {
            $confidence = 'low';

            $warnings[] =
                'Estimated weight is outside the validated 20–50 kg source range for the Philippine native hog formula.';
        }

        /*
         * These profiles are explicitly lower-confidence
         * fallback/provisional formulas in WET v1.
         */
        if (
            in_array($formulaCode, [
                'GOAT_YOUNG_FALLBACK',
                'CARABAO_NATIVE_FALLBACK',
            ], true)
        ) {
            $confidence = 'low';

            $warnings[] = match ($formulaCode) {
                'GOAT_YOUNG_FALLBACK' =>
                    'A lower-confidence fallback formula was used for the young goat.',

                'CARABAO_NATIVE_FALLBACK' =>
                    'A provisional lower-confidence bovine fallback formula was used for the native/swamp carabao.',
            };
        }

        return [
            $confidence,
            array_values(array_unique($warnings)),
        ];
    }
}