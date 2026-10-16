<?php

namespace App\Services;

use App\Models\FormulaProfile;
use App\Models\LivestockRecord;
use Illuminate\Validation\ValidationException;

class FormulaProfileResolver
{
    public function resolve(LivestockRecord $livestock): FormulaProfile
    {
        $livestock->loadMissing([
            'species',
            'breed',
        ]);

        $speciesCode = strtoupper($livestock->species?->code ?? '');
        $breedCode = strtoupper($livestock->breed?->code ?? '');
        $breedGroup = strtolower($livestock->breed?->breed_group ?? '');
        $ageGroup = strtolower($livestock->age_group ?? '');

        $formulaCode = match ($speciesCode) {
            'CATTLE' => $this->resolveCattle(),

            'HOG' => $this->resolveHog(
                $breedCode,
                $breedGroup
            ),

            'GOAT' => $this->resolveGoat(
                $ageGroup
            ),

            'CARABAO' => $this->resolveCarabao(
                $breedCode,
                $breedGroup
            ),

            default => null,
        };

        if (!$formulaCode) {
            throw ValidationException::withMessages([
                'formula_profile' =>
                    'No validated weight-estimation formula is available for this livestock profile.',
            ]);
        }

        $profile = FormulaProfile::query()
            ->where('formula_code', $formulaCode)
            ->where('species_id', $livestock->species_id)
            ->where('is_active', true)
            ->latest('created_at')
            ->first();

        if (!$profile) {
            throw ValidationException::withMessages([
                'formula_profile' =>
                    "The required formula profile [{$formulaCode}] is not currently available.",
            ]);
        }

        return $profile;
    }

    private function resolveCattle(): string
    {
        return 'CATTLE_SCHAEFFER';
    }

    private function resolveHog(
        string $breedCode,
        string $breedGroup
    ): ?string {
        if (
            $breedCode === 'PH_NATIVE' ||
            $breedGroup === 'native'
        ) {
            return 'HOG_NATIVE_REGRESSION';
        }

        if (
            in_array($breedCode, [
                'LANDRACE',
                'LARGE_WHITE',
                'DUROC',
                'PIETRAIN',
                'COMMERCIAL_CROSS',
            ], true) ||
            in_array($breedGroup, [
                'commercial',
                'commercial_crossbred',
            ], true)
        ) {
            return 'HOG_COMMERCIAL_FALLBACK';
        }

        return null;
    }

    private function resolveGoat(
        string $ageGroup
    ): ?string {
        return match ($ageGroup) {
            'adult' => 'GOAT_BARILI_ADULT',

            'kid',
            'young',
            'yearling' => 'GOAT_YOUNG_FALLBACK',

            default => null,
        };
    }

    private function resolveCarabao(
        string $breedCode,
        string $breedGroup
    ): ?string {
        if (
            $breedCode === 'MURRAH_CROSS' ||
            $breedGroup === 'murrah_cross'
        ) {
            return 'CARABAO_MURRAH_REGRESSION';
        }

        if (
            $breedCode === 'PH_SWAMP' ||
            $breedGroup === 'native_swamp'
        ) {
            return 'CARABAO_NATIVE_FALLBACK';
        }

        return null;
    }
}