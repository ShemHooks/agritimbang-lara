<?php

namespace App\Services;

use App\Models\Species;

class AgeGroupService
{
    public function resolve(
        Species $species,
        ?int $ageMonths,
        ?float $weightKg = null
    ): ?string {
        return match (strtoupper($species->code)) {
            'HOG' => $this->resolveHog($weightKg),
            'CATTLE' => $this->resolveCattle($ageMonths),
            'GOAT' => $this->resolveGoat($ageMonths),
            'CARABAO' => $this->resolveCarabao($ageMonths),
            default => null,
        };
    }

    private function resolveHog(
        ?float $weightKg
    ): ?string {
        if ($weightKg === null) {
            return null;
        }

        return match (true) {
            $weightKg <= 22 => 'piglet_pre_starter',
            $weightKg <= 40 => 'starter',
            $weightKg <= 62 => 'grower',
            $weightKg <= 100 => 'finisher',
            default => 'adult_breeder',
        };
    }

    private function resolveCattle(
        ?int $ageMonths
    ): ?string {
        if ($ageMonths === null) {
            return null;
        }

        return match (true) {
            $ageMonths < 12 => 'calf',
            $ageMonths < 24 => 'yearling',
            $ageMonths < 42 => 'young_adult',
            default => 'mature_adult',
        };
    }

    private function resolveGoat(
        ?int $ageMonths
    ): ?string {
        if ($ageMonths === null) {
            return null;
        }

        return match (true) {
            $ageMonths < 6 => 'kid',
            $ageMonths < 12 => 'young',
            $ageMonths < 24 => 'yearling',
            default => 'adult',
        };
    }

    private function resolveCarabao(
        ?int $ageMonths
    ): ?string {
        if ($ageMonths === null) {
            return null;
        }

        return match (true) {
            $ageMonths < 12 => 'calf',
            $ageMonths < 24 => 'yearling',
            $ageMonths < 42 => 'young_adult',
            default => 'mature_adult',
        };
    }
}