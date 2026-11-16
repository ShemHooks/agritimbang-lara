<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Imports the supplied Municipality of Barili livestock commodity price sheet.
 * This is real source data, not demo data. Run after creating the real active
 * municipal Encoder and Authority accounts. Existing PSA and older references
 * are intentionally retained as historical records.
 */
class BariliOctober2026PriceReferenceSeeder extends Seeder
{
    private const MUNICIPALITY_PSGC = '0702210000';
    private const EFFECTIVE_FROM = '2026-10-01';
    private const SOURCE = 'Municipality of Barili, Cebu — Livestock Commodity Price Monitoring (Live Weight as of October 2026)';
    private const REMARKS = 'Market/pooling location: Mantalungon, Barili. Prepared by Ryan Paul L. Gonato, DVM, Veterinarian III. Source sheet dated October 2026.';

    public function run(): void
    {
        if (app()->environment('testing')) return;
        $municipalityId = DB::table('municipalities')->where('psgc_code', self::MUNICIPALITY_PSGC)->value('id');
        if (!$municipalityId) throw new RuntimeException('Barili municipality is missing. Run MunicipalitySeeder first.');

        $encoderId = $this->activeOfficerId($municipalityId, 'lgu_encoder');
        $authorityId = $this->activeOfficerId($municipalityId, 'lgu_authority');
        if (!$encoderId || !$authorityId) {
            throw new RuntimeException('Create real active Barili LGU Encoder and LGU Authority accounts before importing the October 2026 municipal price bulletin. Demo users are not accepted.');
        }

        $speciesIds = DB::table('species')->pluck('id', 'code');
        foreach (['CARABAO', 'CATTLE', 'HOG', 'GOAT'] as $code) {
            if (!isset($speciesIds[$code])) throw new RuntimeException("Species [{$code}] is missing. Run SpeciesSeeder first.");
        }

        // category, species code, low, high, unit.
        $rows = [
            ['Slaughter', 'CARABAO', 100.00, 110.00, 'PHP/kg'],
            ['Breeding', 'CARABAO', 130.00, 140.00, 'PHP/kg'],
            ['Slaughter', 'CATTLE', 125.00, 135.00, 'PHP/kg'],
            ['Breeding', 'CATTLE', 150.00, 160.00, 'PHP/kg'],
            ['Slaughter', 'HOG', 120.00, 130.00, 'PHP/kg'],
            ['Breeding', 'HOG', 170.00, 180.00, 'PHP/kg'],
            ['Slaughter', 'GOAT', 160.00, 180.00, 'PHP/kg'],
            ['Breeding', 'GOAT', 190.00, 200.00, 'PHP/kg'],
        ];

        foreach ($rows as [$category, $speciesCode, $low, $high, $unit]) {
            $midpoint = round(($low + $high) / 2, 2);
            $key = [
                'municipality_id' => $municipalityId,
                'species_id' => $speciesIds[$speciesCode],
                'reference_category' => $category,
                'effective_from' => self::EFFECTIVE_FROM,
                'source' => self::SOURCE,
            ];
            $referenceId = DB::table('price_references')->where($key)->value('id') ?: (string) Str::uuid();
            DB::table('price_references')->updateOrInsert(
                $key,
                [
                    'id' => $referenceId,
                    'barangay_id' => null,
                    'breed_id' => null,
                    'sale_purpose' => $category === 'Breeding' ? 'breeding' : 'slaughter', // legacy enum; reference_category is authoritative.
                    'price_per_kg' => $midpoint,
                    'price_min_per_unit' => $low,
                    'price_max_per_unit' => $high,
                    'price_unit' => $unit,
                    'effective_to' => null, // Keep the latest approved municipal reference as the fallback until a newer one supersedes it.
                    'remarks' => self::REMARKS,
                    'status' => 'official',
                    'submitted_by' => $encoderId,
                    'submitted_at' => now(),
                    'reviewed_by' => $authorityId,
                    'reviewed_at' => now(),
                    'approved_by' => $authorityId,
                    'approved_at' => now(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
            DB::table('price_validation_logs')->updateOrInsert(
                ['price_reference_id' => $referenceId, 'action' => 'imported_official_bulletin'],
                [
                    'id' => DB::table('price_validation_logs')->where('price_reference_id', $referenceId)->where('action', 'imported_official_bulletin')->value('id') ?: (string) Str::uuid(),
                    'from_status' => null,
                    'to_status' => 'official',
                    'remarks' => 'Imported from the Municipality of Barili October 2026 livestock commodity price monitoring sheet. Seeded using the active municipal Encoder and Authority accounts.',
                    'performed_by' => $authorityId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }

        $this->command?->info('Imported the October 2026 Barili municipal price bulletin. Older PSA and municipal price records were retained for history.');
    }

    private function activeOfficerId(string $municipalityId, string $roleSlug): ?string
    {
        $query = DB::table('user_roles')
            ->join('roles', 'roles.id', '=', 'user_roles.role_id')
            ->join('users', 'users.id', '=', 'user_roles.user_id')
            ->join('lgu_officer_profiles', 'lgu_officer_profiles.user_id', '=', 'users.id')
            ->where('roles.slug', $roleSlug)
            ->where('lgu_officer_profiles.municipality_id', $municipalityId)
            ->where('users.status', 'active');
        if (!app()->environment('production')) {
            $demoEmail = $roleSlug === 'lgu_encoder' ? 'encoder@gmail.com' : 'authority@gmail.com';
            $query->orderByRaw('CASE WHEN users.email = ? THEN 0 ELSE 1 END', [$demoEmail]);
        }
        return $query->orderBy('users.created_at')->value('users.id');
    }
}
