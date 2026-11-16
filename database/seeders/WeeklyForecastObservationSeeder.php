<?php

namespace Database\Seeders;

use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Creates 13 consecutive synthetic weekly PHP/kg observations for local
 * ARIMA/forecast testing. These are NOT PSA data or actual Barili market prices.
 *
 * Run after the normal reference-data and demo-user seeders:
 * php artisan db:seed --class=WeeklyForecastObservationSeeder
 */
class WeeklyForecastObservationSeeder extends Seeder
{
    private const MUNICIPALITY_PSGC = '0702210000';
    private const SOURCE = 'DEMO_SYNTHETIC_WEEKLY_FORECAST - NOT ACTUAL MARKET DATA';

    public function run(): void
    {
        if (app()->environment('production')) {
            throw new RuntimeException('Synthetic weekly forecast observations are disabled in production.');
        }

        $municipalityId = DB::table('municipalities')
            ->where('psgc_code', self::MUNICIPALITY_PSGC)
            ->value('id');

        if (!$municipalityId) {
            throw new RuntimeException('Barili municipality is missing. Run MunicipalitySeeder first.');
        }

        $encoderId = DB::table('users')->where('email', 'encoder@gmail.com')->value('id');
        $authorityId = DB::table('users')->where('email', 'authority@gmail.com')->value('id');
        if (!$encoderId || !$authorityId) {
            throw new RuntimeException('Demo Encoder and Authority accounts are required. Run DemoUsersSeeder first.');
        }

        $species = DB::table('species')->pluck('id', 'code');
        $series = [
            'CARABAO' => [198.40, 199.10, 197.85, 200.20, 201.35, 200.70, 202.10, 201.60, 203.25, 204.10, 203.70, 205.20, 204.80],
            'CATTLE'  => [218.20, 219.40, 217.90, 220.10, 221.35, 220.80, 222.40, 221.75, 223.10, 224.20, 223.60, 225.10, 224.70],
            'GOAT'    => [238.50, 239.20, 240.10, 239.40, 241.30, 242.10, 241.60, 243.20, 244.10, 243.50, 245.00, 245.80, 246.20],
            'HOG'     => [168.20, 169.10, 167.80, 170.40, 171.20, 170.60, 172.10, 171.50, 173.20, 174.10, 173.60, 175.00, 174.70],
        ];

        foreach (array_keys($series) as $code) {
            if (!isset($species[$code])) {
                throw new RuntimeException("Species [{$code}] is missing. Run SpeciesSeeder first.");
            }
        }

        // Thirteen complete Monday-Sunday weeks ending with the last completed week.
        $lastMonday = CarbonImmutable::now()->startOfWeek(CarbonImmutable::MONDAY)->subWeek();
        $firstMonday = $lastMonday->subWeeks(12);

        DB::transaction(function () use ($series, $species, $municipalityId, $encoderId, $authorityId, $firstMonday) {
            foreach ($series as $speciesCode => $prices) {
                foreach ($prices as $index => $price) {
                    $start = $firstMonday->addWeeks($index);
                    $end = $start->addDays(6);
                    $key = [
                        'municipality_id' => $municipalityId,
                        'species_id' => $species[$speciesCode],
                        'source' => self::SOURCE,
                        'effective_from' => $start->toDateString(),
                    ];

                    $existingId = DB::table('price_references')
                        ->where($key)
                        ->whereNull('barangay_id')
                        ->whereNull('breed_id')
                        ->value('id');

                    DB::table('price_references')->updateOrInsert(
                        ['id' => $existingId ?: (string) Str::uuid()],
                        [
                            'municipality_id' => $municipalityId,
                            'barangay_id' => null,
                            'species_id' => $species[$speciesCode],
                            'breed_id' => null,
                            'price_per_kg' => $price,
                            'effective_from' => $start->toDateString(),
                            'effective_to' => $end->toDateString(),
                            'source' => self::SOURCE,
                            'remarks' => 'Synthetic weekly observation for ARIMA/forecast testing only. Not PSA data, not an actual Barili market observation, and not valid for official pricing or research.',
                            'status' => 'official',
                            'sale_purpose' => 'slaughter',
                            'reference_category' => 'Slaughter',
                            'price_min_per_unit' => $price,
                            'price_max_per_unit' => $price,
                            'price_unit' => 'PHP/kg',
                            'submitted_by' => $encoderId,
                            'submitted_at' => $start->setTime(8, 0),
                            'reviewed_by' => $authorityId,
                            'reviewed_at' => $start->setTime(9, 0),
                            'approved_by' => $authorityId,
                            'approved_at' => $start->setTime(9, 0),
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]
                    );
                }
            }
        });

        $this->command?->info('Seeded 13 consecutive synthetic weekly slaughter observations for each of 4 species (52 records). DEMO ONLY.');
    }
}
