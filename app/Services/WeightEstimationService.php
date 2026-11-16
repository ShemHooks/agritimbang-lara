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

    /**
     * Calculate a temporary/manual estimate without creating a livestock
     * record or a weight_estimation row.
     */
    public function preview(
        LivestockRecord $livestock,
        array $measurements
    ): array {
        $livestock->loadMissing(['species', 'breed']);

        // Older goat records may have age_months but no age_group. Resolve the
        // stage from the saved age before choosing the goat formula.
        if (
            strtoupper((string) $livestock->species?->code) === 'GOAT' &&
            empty($livestock->age_group)
        ) {
            $livestock->age_group = $this->ageGroupService->resolve(
                $livestock->species,
                $livestock->age_months !== null ? (int) $livestock->age_months : null,
            );
        }

        $profile = $this->formulaResolver->resolve($livestock);

        $estimatedWeight = $this->calculator->calculate(
            $profile,
            $measurements
        );

        [$confidence, $warnings] = $this->evaluateConfidence(
            $profile->formula_code,
            $estimatedWeight
        );

        $effectiveAgeGroup = $livestock->age_group;

        if (strtoupper((string) $livestock->species?->code) === 'HOG') {
            $effectiveAgeGroup = $this->ageGroupService->resolve(
                $livestock->species,
                $livestock->age_months !== null ? (int) $livestock->age_months : null,
                $estimatedWeight,
            );
        }

        $classificationLivestock = clone $livestock;
        $classificationLivestock->age_group = $effectiveAgeGroup;

        $frame = $this->frameClassifier->classify(
            $classificationLivestock,
            isset($measurements['body_length_cm'])
                ? (float) $measurements['body_length_cm']
                : null
        );

        return [
            'livestock_id' => null,
            'formula_profile_id' => $profile->id,
            'heart_girth_cm' => $measurements['heart_girth_cm'] ?? null,
            'body_length_cm' => $measurements['body_length_cm'] ?? null,
            'rump_height_cm' => $measurements['rump_height_cm'] ?? null,
            'estimated_weight_kg' => $estimatedWeight,
            'formula_code' => $profile->formula_code,
            'formula_version' => $profile->version,
            'estimate_confidence' => $confidence,
            'warnings' => empty($warnings) ? [] : $warnings,
            'frame_category' => $frame['frame_category'],
            'frame_z' => $frame['frame_z'],
            'reference_mean_body_length_cm' => $frame['reference_mean_body_length_cm'],
            'reference_sd_body_length_cm' => $frame['reference_sd_body_length_cm'],
            'reference_n' => $frame['reference_n'],
            'reference_group' => $frame['reference_group'],
            'frame_stats_version' => $frame['frame_stats_version'],
            'age_group' => $effectiveAgeGroup,
            'preview_only' => true,
        ];
    }

    public function estimate(
        LivestockRecord $livestock,
        array $measurements,
        User $user
    ): WeightEstimation {
        $livestock->loadMissing(['species', 'breed']);

        // Legacy goat records can have a valid age in months but a missing
        // age_group. Resolve the formula stage from the saved age.
        if (
            strtoupper((string) $livestock->species?->code) === 'GOAT' &&
            empty($livestock->age_group)
        ) {
            $livestock->age_group = $this->ageGroupService->resolve(
                $livestock->species,
                $livestock->age_months !== null ? (int) $livestock->age_months : null,
            );
        }

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
                    'A lower-confidence general goat fallback formula was used because the breed is unspecified or the animal is in a young age group.',

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