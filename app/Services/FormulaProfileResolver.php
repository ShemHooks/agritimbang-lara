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
                $breedCode,
                $breedGroup,
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

        // Any unrecognized, unspecified, or "other" hog breed uses the
        // commercial fallback so every registered hog can be estimated.
        return 'HOG_COMMERCIAL_FALLBACK';
    }

    private function resolveGoat(
        string $breedCode,
        string $breedGroup,
        string $ageGroup
    ): ?string {
        // Missing or generic breed values use the general goat fallback.
        // This formula uses heart girth and body length only, so rump height
        // is not required for an unspecified-breed goat.
        if ($this->isGenericBreed($breedCode, $breedGroup)) {
            return 'GOAT_YOUNG_FALLBACK';
        }

        return match ($ageGroup) {
            'adult' => 'GOAT_BARILI_ADULT',

            'kid',
            'young',
            'yearling',
            'young_adult',
            'unspecified',
            'unknown',
            'other',
            'others',
            '' => 'GOAT_YOUNG_FALLBACK',

            default => 'GOAT_YOUNG_FALLBACK',
        };
    }

    private function isGenericBreed(
        string $breedCode,
        string $breedGroup
    ): bool {
        $code = strtolower(trim($breedCode));
        $group = strtolower(trim($breedGroup));

        if ($code === '') {
            return true;
        }

        foreach (['unspecified', 'unknown', 'other', 'others'] as $generic) {
            if (str_contains($code, $generic) || str_contains($group, $generic)) {
                return true;
            }
        }

        return false;
    }

    private function resolveCarabao(
        string $breedCode,
        string $breedGroup
    ): ?string {
        if (
            in_array($breedCode, [
                'MURRAH',
                'MURRAH_CROSS',
            ], true) ||
            in_array($breedGroup, [
                'murrah',
                'murrah_cross',
            ], true)
        ) {
            return 'CARABAO_MURRAH_REGRESSION';
        }

        if (
            in_array($breedCode, ['PH_SWAMP', 'BUFFALO', 'WATER_BUFFALO'], true) ||
            in_array($breedGroup, ['native_swamp', 'buffalo', 'water_buffalo'], true)
        ) {
            return 'CARABAO_NATIVE_FALLBACK';
        }

        // Unrecognized, unspecified, and other breeds use the
        // native/swamp fallback rather than being treated as Murrah.
        return 'CARABAO_NATIVE_FALLBACK';
    }
}