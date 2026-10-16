<?php

namespace App\Services;

use App\Models\FrameReferenceStat;
use App\Models\LivestockRecord;

class FrameClassificationService
{
    public function classify(
        LivestockRecord $livestock,
        ?float $bodyLengthCm
    ): array {
        $unknown = [
            'frame_category' => 'unknown',
            'frame_z' => null,
            'reference_mean_body_length_cm' => null,
            'reference_sd_body_length_cm' => null,
            'reference_n' => null,
            'reference_group' => null,
            'frame_stats_version' => null,
        ];

        if ($bodyLengthCm === null || $bodyLengthCm <= 0) {
            return $unknown;
        }

        if (!$livestock->age_group || !$livestock->sex) {
            return $unknown;
        }

        $baseQuery = FrameReferenceStat::query()
            ->where('species_id', $livestock->species_id)
            ->where('age_group', $livestock->age_group)
            ->where('sex', $livestock->sex)
            ->where('sample_size', '>=', 30)
            ->where('sd_body_length_cm', '>', 0);

        /*
         * First preference: breed-specific reference statistics.
         */
        $stat = null;

        if ($livestock->breed_id) {
            $stat = (clone $baseQuery)
                ->where('breed_id', $livestock->breed_id)
                ->orderByDesc('computed_at')
                ->first();
        }

        /*
         * Second preference: species-level statistics.
         */
        if (!$stat) {
            $stat = (clone $baseQuery)
                ->whereNull('breed_id')
                ->orderByDesc('computed_at')
                ->first();
        }

        if (!$stat) {
            return $unknown;
        }

        $mean = (float) $stat->mean_body_length_cm;
        $sd = (float) $stat->sd_body_length_cm;

        $z = ($bodyLengthCm - $mean) / $sd;

        $category = match (true) {
            $z <= -1 => 'small',
            $z >= 1 => 'large',
            default => 'medium',
        };

        return [
            'frame_category' => $category,
            'frame_z' => round($z, 4),
            'reference_mean_body_length_cm' => $mean,
            'reference_sd_body_length_cm' => $sd,
            'reference_n' => $stat->sample_size,
            'reference_group' => $stat->breed_id
                ? 'breed_specific'
                : 'species_level',
            'frame_stats_version' => $stat->version,
        ];
    }
}