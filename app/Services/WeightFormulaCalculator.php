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

        // Standard livestock tape formula: pounds = HG(in)^2 × BL(in) / 300.
        // Convert the centimeter inputs to inches and the result to kilograms.
        return $this->tapeFormulaToKilograms($hg, $bl, 300);
    }

    private function calculateNativeHog(array $measurements): float
    {
        $hg = (float) $measurements['heart_girth_cm'];
        $bl = (float) $measurements['body_length_cm'];

        return -46.32 + (0.83 * $hg) + (0.27 * $bl);
    }

    private function calculateCommercialHog(array $measurements): float
    {
        // Hog fallback: pounds = HG(in)^2 × BL(in) / 400.
        $hgInches = (float) $measurements['heart_girth_cm'] / 2.54;
        $blInches = (float) $measurements['body_length_cm'] / 2.54;
        $weightLb = (($hgInches ** 2) * $blInches) / 400;

        // Add 7 lb only when the unadjusted estimate is below 150 lb.
        // The 150–400 lb range receives no adjustment; no adjustment is
        // specified for estimates above 400 lb either.
        if ($weightLb < 150) {
            $weightLb += 7;
        }

        return $weightLb / 2.2046226218;
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

        // General/unspecified-breed goat fallback: pounds = HG(in)^2 × BL(in) / 300.
        return $this->tapeFormulaToKilograms($hg, $bl, 300);
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

        // Carabao/buffalo fallback uses the same /300 tape formula as cattle.
        return $this->tapeFormulaToKilograms($hg, $bl, 300);
    }

    private function tapeFormulaToKilograms(
        float $heartGirthCm,
        float $bodyLengthCm,
        float $divisor
    ): float {
        $heartGirthIn = $heartGirthCm / 2.54;
        $bodyLengthIn = $bodyLengthCm / 2.54;
        $weightLb = (($heartGirthIn ** 2) * $bodyLengthIn) / $divisor;

        return $weightLb / 2.2046226218;
    }
}