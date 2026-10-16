<?php

namespace Tests\Unit;

use App\Models\FormulaProfile;
use App\Services\WeightFormulaCalculator;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class WeightFormulaCalculatorTest extends TestCase
{
    public static function formulaCases(): array
    {
        return [
            'cattle' => [
                'CATTLE_SCHAEFFER',
                ['heart_girth_cm' => 150, 'body_length_cm' => 120],
                ['heart_girth_cm', 'body_length_cm'],
                249.0775,
            ],
            'native hog' => [
                'HOG_NATIVE_REGRESSION',
                ['heart_girth_cm' => 80, 'body_length_cm' => 70],
                ['heart_girth_cm', 'body_length_cm'],
                38.98,
            ],
            'commercial hog' => [
                'HOG_COMMERCIAL_FALLBACK',
                ['heart_girth_cm' => 100, 'body_length_cm' => 100],
                ['heart_girth_cm', 'body_length_cm'],
                69.3,
            ],
            'adult goat' => [
                'GOAT_BARILI_ADULT',
                [
                    'rump_height_cm' => 70,
                    'body_length_cm' => 80,
                    'heart_girth_cm' => 90,
                ],
                [
                    'rump_height_cm',
                    'body_length_cm',
                    'heart_girth_cm',
                ],
                47.0,
            ],
            'young goat' => [
                'GOAT_YOUNG_FALLBACK',
                ['heart_girth_cm' => 60, 'body_length_cm' => 50],
                ['heart_girth_cm', 'body_length_cm'],
                16.6082,
            ],
            'Murrah carabao' => [
                'CARABAO_MURRAH_REGRESSION',
                ['heart_girth_cm' => 150],
                ['heart_girth_cm'],
                216.77,
            ],
            'native carabao' => [
                'CARABAO_NATIVE_FALLBACK',
                ['heart_girth_cm' => 150, 'body_length_cm' => 120],
                ['heart_girth_cm', 'body_length_cm'],
                249.0775,
            ],
        ];
    }

    #[DataProvider('formulaCases')]
    public function test_calculates_weight(
        string $code,
        array $measurements,
        array $requiredInputs,
        float $expected
    ): void {
        $profile = new FormulaProfile();

        $profile->formula_code = $code;
        $profile->required_inputs = $requiredInputs;

        $result = (new WeightFormulaCalculator())->calculate(
            $profile,
            $measurements
        );

        $this->assertEqualsWithDelta(
            $expected,
            $result,
            0.0001
        );
    }

    public function test_rejects_missing_required_measurement(): void
    {
        $profile = new FormulaProfile();

        $profile->formula_code = 'CATTLE_SCHAEFFER';
        $profile->required_inputs = [
            'heart_girth_cm',
            'body_length_cm',
        ];

        $this->expectException(ValidationException::class);

        (new WeightFormulaCalculator())->calculate(
            $profile,
            ['heart_girth_cm' => 150]
        );
    }

    public function test_rejects_nonpositive_weight(): void
    {
        $profile = new FormulaProfile();

        $profile->formula_code = 'HOG_NATIVE_REGRESSION';
        $profile->required_inputs = [
            'heart_girth_cm',
            'body_length_cm',
        ];

        $this->expectException(ValidationException::class);

        (new WeightFormulaCalculator())->calculate(
            $profile,
            [
                'heart_girth_cm' => 1,
                'body_length_cm' => 1,
            ]
        );
    }
}
