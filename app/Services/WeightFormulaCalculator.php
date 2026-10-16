<?php

namespace App\Services;

use App\Models\FormulaProfile;
use Illuminate\Validation\ValidationException;

class WeightFormulaCalculator
{
    public function calculate(
        FormulaProfile $profile,
        array $measurements
    ): float {
        $this->validateRequiredInputs($profile, $measurements);

        $weight = match ($profile->formula_code) {
            'CATTLE_SCHAEFFER' =>
                $this->calculateCattle($measurements),

            'HOG_NATIVE_REGRESSION' =>
                $this->calculateNativeHog($measurements),

            'HOG_COMMERCIAL_FALLBACK' =>
                $this->calculateCommercialHog($measurements),

            'GOAT_BARILI_ADULT' =>
                $this->calculateAdultGoat($measurements),

            'GOAT_YOUNG_FALLBACK' =>
                $this->calculateYoungGoat($measurements),

            'CARABAO_MURRAH_REGRESSION' =>
                $this->calculateCarabaoMurrah($measurements),

            'CARABAO_NATIVE_FALLBACK' =>
                $this->calculateNativeCarabao($measurements),

            default => throw ValidationException::withMessages([
                'formula' =>
                    "Unsupported weight formula [{$profile->formula_code}].",
            ]),
        };

        $roundedWeight = round($weight, 4);

        if (
            !is_finite($weight) ||
            $roundedWeight <= 0
        ) {
            throw ValidationException::withMessages([
                'measurements' =>
                    'The supplied measurements produced an invalid estimated weight.',
            ]);
        }

        return $roundedWeight;
    }

    private function validateRequiredInputs(
        FormulaProfile $profile,
        array $measurements
    ): void {
        foreach ($profile->required_inputs as $input) {
            if (
                !array_key_exists($input, $measurements) ||
                $measurements[$input] === null ||
                !is_numeric($measurements[$input]) ||
                (float) $measurements[$input] <= 0
            ) {
                throw ValidationException::withMessages([
                    $input =>
                        "A positive numeric value for {$input} is required by this formula.",
                ]);
            }
        }
    }

    private function calculateCattle(array $measurements): float
    {
        $hg = (float) $measurements['heart_girth_cm'];
        $bl = (float) $measurements['body_length_cm'];

        return (($hg ** 2) * $bl) / 10840;
    }

    private function calculateNativeHog(array $measurements): float
    {
        $hg = (float) $measurements['heart_girth_cm'];
        $bl = (float) $measurements['body_length_cm'];

        return -46.32 + (0.83 * $hg) + (0.27 * $bl);
    }

    private function calculateCommercialHog(array $measurements): float
    {
        /*
         * Measurements enter AgriTimbang in centimeters,
         * but this formula requires meters.
         */
        $hgMeters =
            (float) $measurements['heart_girth_cm'] / 100;

        $blMeters =
            (float) $measurements['body_length_cm'] / 100;

        return ($hgMeters ** 2) * $blMeters * 69.3;
    }

    private function calculateAdultGoat(array $measurements): float
    {
        $rh = (float) $measurements['rump_height_cm'];
        $bl = (float) $measurements['body_length_cm'];
        $hg = (float) $measurements['heart_girth_cm'];

        return (
            (2 * $rh) +
            (4 * $bl) +
            (6 * $hg)
        ) / 10 - 53;
    }

    private function calculateYoungGoat(array $measurements): float
    {
        $hg = (float) $measurements['heart_girth_cm'];
        $bl = (float) $measurements['body_length_cm'];

        return (($hg ** 2) * $bl) / 10838;
    }

    private function calculateCarabaoMurrah(array $measurements): float
    {
        $hg = (float) $measurements['heart_girth_cm'];

        return -522.73 + (4.93 * $hg);
    }

    private function calculateNativeCarabao(array $measurements): float
    {
        $hg = (float) $measurements['heart_girth_cm'];
        $bl = (float) $measurements['body_length_cm'];

        return (($hg ** 2) * $bl) / 10840;
    }
}