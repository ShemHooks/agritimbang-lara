<?php

namespace Database\Seeders;

use App\Models\FormulaProfile;
use App\Models\Species;
use Illuminate\Database\Seeder;
use RuntimeException;

class FormulaProfileSeeder extends Seeder
{
    public function run(): void
    {
        $hog = $this->species('HOG');
        $cattle = $this->species('CATTLE');
        $goat = $this->species('GOAT');
        $carabao = $this->species('CARABAO');

        /*
         * CATTLE
         *
         * W = (HG² × BL) / 10840
         * HG and BL are centimeters.
         */
        $this->profile([
            'formula_code' => 'CATTLE_SCHAEFFER',
            'name' => 'Cattle Heart Girth and Body Length Formula',
            'species_id' => $cattle->id,
            'breed_id' => null,

            'required_inputs' => [
                'heart_girth_cm',
                'body_length_cm',
            ],

            'applicability' => [
                'unit' => 'cm',
                'profile' => 'default',
            ],

            'version' => '1.0',

            'source' =>
                'AgriTimbang livestock tape fallback specification: HG(in)^2 x BL(in) / 300, converted to kilograms.',

            'is_fallback' => false,
            'is_active' => true,
        ]);

        /*
         * HOG — Philippine native
         *
         * W = -46.32 + 0.83(HG) + 0.27(BL)
         *
         * Validated/developed for approximately 20–50 kg.
         */
        $this->profile([
            'formula_code' => 'HOG_NATIVE_REGRESSION',
            'name' => 'Philippine Native Hog Regression Formula',
            'species_id' => $hog->id,
            'breed_id' => null,

            'required_inputs' => [
                'heart_girth_cm',
                'body_length_cm',
            ],

            'applicability' => [
                'profile' => 'philippine_native',
                'input_unit' => 'cm',
                'source_weight_range_kg' => [
                    'min' => 20,
                    'max' => 50,
                ],
            ],

            'version' => '1.0',

            'source' =>
                'Validated AgriTimbang WET/FPC specification.',

            'is_fallback' => false,
            'is_active' => true,
        ]);

        /*
         * HOG — commercial/crossbred/unspecified-breed fallback
         *
         * W(lb) = HG(in)² × BL(in) / 400
         * Add 7 lb when the unadjusted estimate is below 150 lb.
         * AgriTimbang stores the final result in kilograms.
         */
        $this->profile([
            'formula_code' => 'HOG_COMMERCIAL_FALLBACK',
            'name' => 'Commercial/Unspecified-Breed Hog Fallback Formula',
            'species_id' => $hog->id,
            'breed_id' => null,

            'required_inputs' => [
                'heart_girth_cm',
                'body_length_cm',
            ],

            'applicability' => [
                'profile' => 'commercial_crossbred_or_unspecified_breed',
                'input_unit' => 'cm',
                'formula_unit' => 'inches',
                'output_unit' => 'kg',
                'divisor' => 400,
                'under_150_lb_adjustment' => 7,
            ],

            'version' => '1.0',

            'source' =>
                'AgriTimbang hog tape fallback specification: HG(in)^2 x BL(in) / 400, with +7 lb below 150 lb.',

            'is_fallback' => true,
            'is_active' => true,
        ]);

        /*
         * GOAT — adult Barili formula
         *
         * W = ((2RH + 4BL + 6HG) / 10) - 53
         */
        $this->profile([
            'formula_code' => 'GOAT_BARILI_ADULT',
            'name' => 'Adult Goat Barili Weight Formula',
            'species_id' => $goat->id,
            'breed_id' => null,

            'required_inputs' => [
                'rump_height_cm',
                'body_length_cm',
                'heart_girth_cm',
            ],

            'applicability' => [
                'age_group' => 'adult',
                'input_unit' => 'cm',
            ],

            'version' => '1.0',

            'source' =>
                'Validated AgriTimbang WET/FPC specification.',

            'is_fallback' => false,
            'is_active' => true,
        ]);

        /*
         * GOAT — young or unspecified-breed fallback
         *
         * W(lb) = HG(in)² × BL(in) / 300
         * AgriTimbang stores the converted result in kilograms.
         * This fallback does not require rump height.
         */
        $this->profile([
            'formula_code' => 'GOAT_YOUNG_FALLBACK',
            'name' => 'Young/Unspecified-Breed Goat Fallback Formula',
            'species_id' => $goat->id,
            'breed_id' => null,

            'required_inputs' => [
                'heart_girth_cm',
                'body_length_cm',
            ],

            'applicability' => [
                'age_groups' => [
                    'kid',
                    'young',
                    'yearling',
                ],
                'profile' => 'young_or_unspecified_breed',
                'input_unit' => 'cm',
                'formula_unit' => 'inches',
                'output_unit' => 'kg',
                'divisor' => 300,
                'requires_rump_height' => false,
                'confidence' => 'low',
            ],

            'version' => '1.0',

            'source' =>
                'AgriTimbang goat tape fallback specification: HG(in)^2 x BL(in) / 300, converted to kilograms.',

            'is_fallback' => true,
            'is_active' => true,
        ]);

        /*
         * CARABAO — Philippine Carabao/Murrah crossbred
         *
         * W = -522.73 + 4.93(HG)
         */
        $this->profile([
            'formula_code' => 'CARABAO_MURRAH_REGRESSION',
            'name' => 'Carabao-Murrah Crossbred Regression Formula',
            'species_id' => $carabao->id,
            'breed_id' => null,

            'required_inputs' => [
                'heart_girth_cm',
            ],

            'applicability' => [
                'profile' => 'philippine_carabao_murrah_crossbred',
                'input_unit' => 'cm',
            ],

            'version' => '1.0',

            'source' =>
                'Validated AgriTimbang WET/FPC specification.',

            'is_fallback' => false,
            'is_active' => true,
        ]);

        /*
         * CARABAO/BUFFALO — native/swamp/unspecified-breed fallback
         *
         * W(lb) = HG(in)² × BL(in) / 300
         * AgriTimbang stores the converted result in kilograms.
         *
         * Provisional/lower-confidence fallback.
         */
        $this->profile([
            'formula_code' => 'CARABAO_NATIVE_FALLBACK',
            'name' => 'Native/Swamp/Unspecified Carabao-Buffalo Fallback Formula',
            'species_id' => $carabao->id,
            'breed_id' => null,

            'required_inputs' => [
                'heart_girth_cm',
                'body_length_cm',
            ],

            'applicability' => [
                'profile' => 'native_swamp_buffalo_or_unspecified_breed',
                'input_unit' => 'cm',
                'formula_unit' => 'inches',
                'output_unit' => 'kg',
                'divisor' => 300,
                'confidence' => 'low',
                'provisional' => true,
            ],

            'version' => '1.0',

            'source' =>
                'AgriTimbang carabao/buffalo tape fallback specification: HG(in)^2 x BL(in) / 300, converted to kilograms.',

            'is_fallback' => true,
            'is_active' => true,
        ]);
    }

    private function species(string $code): Species
    {
        $species = Species::query()
            ->where('code', $code)
            ->first();

        if (!$species) {
            throw new RuntimeException(
                "Species with code [{$code}] was not found."
            );
        }

        return $species;
    }

    private function profile(array $data): void
    {
        FormulaProfile::updateOrCreate(
            [
                'formula_code' => $data['formula_code'],
                'version' => $data['version'],
            ],
            $data
        );
    }
}