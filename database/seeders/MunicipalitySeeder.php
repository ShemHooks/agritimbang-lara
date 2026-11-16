<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class MunicipalitySeeder extends Seeder
{
    private const BARILI_PSGC = '0702210000';

    public function run(): void
    {
        DB::transaction(function () {
            $now = now();
            $canonical = DB::table('municipalities')->where('psgc_code', self::BARILI_PSGC)->first();
            $namedRecords = DB::table('municipalities')->where('name', 'Barili')->where('province', 'Cebu')->get();

            if ($canonical) {
                $duplicates = $namedRecords->where('id', '!=', $canonical->id);
                if ($duplicates->isNotEmpty()) {
                    throw new RuntimeException('Multiple Barili municipality records found. Resolve duplicates before seeding.');
                }
                $municipalityId = $canonical->id;
            } else {
                if ($namedRecords->count() > 1) {
                    throw new RuntimeException('Multiple Barili records found. Resolve them before seeding.');
                }
                // Preserve an existing ID and all foreign-key relationships.
                $municipalityId = $namedRecords->count() === 1 ? $namedRecords->first()->id : (string) Str::uuid();
            }

            DB::table('municipalities')->updateOrInsert(
                ['id' => $municipalityId],
                [
                    'name' => 'Barili',
                    'province' => 'Cebu',
                    'psgc_code' => self::BARILI_PSGC,
                    'is_active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]
            );
        });

        $this->command?->info('Seeded Barili, Cebu using official PSGC 0702210000. Barangays will be imported through GIS.');
    }
}
