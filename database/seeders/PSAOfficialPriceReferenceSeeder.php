<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Uses PSA national farmgate prices as historical benchmark observations
 * associated with the Barili pilot municipality. These are NOT Barili market
 * prices and are superseded in the current directory by the October 2026
 * Municipality of Barili local price bulletin.
 *
 * Source: PSA Livestock and Poultry Quarterly Bulletins, 2025-Q1 to 2026-Q2.
 */
class PSAOfficialPriceReferenceSeeder extends Seeder
{
    private const MUNICIPALITY_PSGC = '0702210000';

    private const SOURCE = 'Philippine Statistics Authority (PSA), Livestock and Poultry Quarterly Bulletin';

    private const SOURCE_REMARKS = 'PSA national average farmgate price for slaughter, liveweight. Retained as a national benchmark for historical comparison only; not an actual Barili market observation.';

    /**
     * [species_code, effective_from, effective_to, price_per_kg, source_period]
     * Each PSA monthly price is stored as a monthly effective reference.
     */
    private const PRICES = [
            ['CARABAO', '2025-01-01', '2025-01-31', 180.46, '2025-Q1'],
            ['CARABAO', '2025-02-01', '2025-02-28', 178.95, '2025-Q1'],
            ['CARABAO', '2025-03-01', '2025-03-31', 180.30, '2025-Q1'],
            ['CARABAO', '2025-04-01', '2025-04-30', 190.34, '2025-Q2'],
            ['CARABAO', '2025-05-01', '2025-05-31', 195.55, '2025-Q2'],
            ['CARABAO', '2025-06-01', '2025-06-30', 194.37, '2025-Q2'],
            ['CARABAO', '2025-07-01', '2025-07-31', 185.27, '2025-Q3'],
            ['CARABAO', '2025-08-01', '2025-08-31', 186.54, '2025-Q3'],
            ['CARABAO', '2025-09-01', '2025-09-30', 188.08, '2025-Q3'],
            ['CARABAO', '2025-10-01', '2025-10-31', 193.98, '2025-Q4'],
            ['CARABAO', '2025-11-01', '2025-11-30', 193.49, '2025-Q4'],
            ['CARABAO', '2025-12-01', '2025-12-31', 196.65, '2025-Q4'],
            ['CARABAO', '2026-01-01', '2026-01-31', 190.34, '2026-Q1'],
            ['CARABAO', '2026-02-01', '2026-02-28', 193.44, '2026-Q1'],
            ['CARABAO', '2026-03-01', '2026-03-31', 196.25, '2026-Q1'],
            ['CARABAO', '2026-04-01', '2026-04-30', 199.61, '2026-Q2'],
            ['CARABAO', '2026-05-01', '2026-05-31', 201.66, '2026-Q2'],
            ['CARABAO', '2026-06-01', '2026-06-30', 201.97, '2026-Q2'],
            ['CATTLE', '2025-01-01', '2025-01-31', 194.95, '2025-Q1'],
            ['CATTLE', '2025-02-01', '2025-02-28', 190.93, '2025-Q1'],
            ['CATTLE', '2025-03-01', '2025-03-31', 199.36, '2025-Q1'],
            ['CATTLE', '2025-04-01', '2025-04-30', 213.79, '2025-Q2'],
            ['CATTLE', '2025-05-01', '2025-05-31', 210.13, '2025-Q2'],
            ['CATTLE', '2025-06-01', '2025-06-30', 221.09, '2025-Q2'],
            ['CATTLE', '2025-07-01', '2025-07-31', 212.08, '2025-Q3'],
            ['CATTLE', '2025-08-01', '2025-08-31', 210.98, '2025-Q3'],
            ['CATTLE', '2025-09-01', '2025-09-30', 213.62, '2025-Q3'],
            ['CATTLE', '2025-10-01', '2025-10-31', 218.93, '2025-Q4'],
            ['CATTLE', '2025-11-01', '2025-11-30', 217.10, '2025-Q4'],
            ['CATTLE', '2025-12-01', '2025-12-31', 220.92, '2025-Q4'],
            ['CATTLE', '2026-01-01', '2026-01-31', 200.85, '2026-Q1'],
            ['CATTLE', '2026-02-01', '2026-02-28', 201.94, '2026-Q1'],
            ['CATTLE', '2026-03-01', '2026-03-31', 209.48, '2026-Q1'],
            ['CATTLE', '2026-04-01', '2026-04-30', 220.52, '2026-Q2'],
            ['CATTLE', '2026-05-01', '2026-05-31', 222.06, '2026-Q2'],
            ['CATTLE', '2026-06-01', '2026-06-30', 222.37, '2026-Q2'],
            ['GOAT', '2025-01-01', '2025-01-31', 231.01, '2025-Q1'],
            ['GOAT', '2025-02-01', '2025-02-28', 232.20, '2025-Q1'],
            ['GOAT', '2025-03-01', '2025-03-31', 228.80, '2025-Q1'],
            ['GOAT', '2025-04-01', '2025-04-30', 243.61, '2025-Q2'],
            ['GOAT', '2025-05-01', '2025-05-31', 237.59, '2025-Q2'],
            ['GOAT', '2025-06-01', '2025-06-30', 245.34, '2025-Q2'],
            ['GOAT', '2025-07-01', '2025-07-31', 246.86, '2025-Q3'],
            ['GOAT', '2025-08-01', '2025-08-31', 249.53, '2025-Q3'],
            ['GOAT', '2025-09-01', '2025-09-30', 255.66, '2025-Q3'],
            ['GOAT', '2025-10-01', '2025-10-31', 244.94, '2025-Q4'],
            ['GOAT', '2025-11-01', '2025-11-30', 250.30, '2025-Q4'],
            ['GOAT', '2025-12-01', '2025-12-31', 255.69, '2025-Q4'],
            ['GOAT', '2026-01-01', '2026-01-31', 247.03, '2026-Q1'],
            ['GOAT', '2026-02-01', '2026-02-28', 243.12, '2026-Q1'],
            ['GOAT', '2026-03-01', '2026-03-31', 250.81, '2026-Q1'],
            ['GOAT', '2026-04-01', '2026-04-30', 243.81, '2026-Q2'],
            ['GOAT', '2026-05-01', '2026-05-31', 243.84, '2026-Q2'],
            ['GOAT', '2026-06-01', '2026-06-30', 243.00, '2026-Q2'],
            ['HOG', '2025-01-01', '2025-01-31', 206.72, '2025-Q1'],
            ['HOG', '2025-02-01', '2025-02-28', 215.08, '2025-Q1'],
            ['HOG', '2025-03-01', '2025-03-31', 215.93, '2025-Q1'],
            ['HOG', '2025-04-01', '2025-04-30', 210.32, '2025-Q2'],
            ['HOG', '2025-05-01', '2025-05-31', 210.89, '2025-Q2'],
            ['HOG', '2025-06-01', '2025-06-30', 214.52, '2025-Q2'],
            ['HOG', '2025-07-01', '2025-07-31', 193.82, '2025-Q3'],
            ['HOG', '2025-08-01', '2025-08-31', 191.22, '2025-Q3'],
            ['HOG', '2025-09-01', '2025-09-30', 189.50, '2025-Q3'],
            ['HOG', '2025-10-01', '2025-10-31', 183.65, '2025-Q4'],
            ['HOG', '2025-11-01', '2025-11-30', 177.77, '2025-Q4'],
            ['HOG', '2025-12-01', '2025-12-31', 187.05, '2025-Q4'],
            ['HOG', '2026-01-01', '2026-01-31', 172.56, '2026-Q1'],
            ['HOG', '2026-02-01', '2026-02-28', 176.31, '2026-Q1'],
            ['HOG', '2026-03-01', '2026-03-31', 179.23, '2026-Q1'],
            ['HOG', '2026-04-01', '2026-04-30', 174.00, '2026-Q2'],
            ['HOG', '2026-05-01', '2026-05-31', 171.56, '2026-Q2'],
            ['HOG', '2026-06-01', '2026-06-30', 171.76, '2026-Q2'],
    ];

    public function run(): void
    {
        $municipalityId = DB::table('municipalities')
            ->where('psgc_code', self::MUNICIPALITY_PSGC)
            ->value('id');

        if (!$municipalityId) {
            throw new RuntimeException('Barili municipality is missing. Run MunicipalitySeeder first.');
        }

        $speciesIds = DB::table('species')->pluck('id', 'code');

        foreach (['HOG', 'CATTLE', 'GOAT', 'CARABAO'] as $code) {
            if (!isset($speciesIds[$code])) {
                throw new RuntimeException("Species [{$code}] is missing. Run SpeciesSeeder first.");
            }
        }

        // Resolve submitting/reviewing users from municipal roles when available.
        // submitted_by is mandatory in the current price_references schema.
        $encoderId = DB::table('user_roles')
            ->join('roles', 'roles.id', '=', 'user_roles.role_id')
            ->join('users', 'users.id', '=', 'user_roles.user_id')
            ->join('lgu_officer_profiles', 'lgu_officer_profiles.user_id', '=', 'users.id')
            ->where('roles.slug', 'lgu_encoder')
            ->where('lgu_officer_profiles.municipality_id', $municipalityId)
            ->where('users.status', 'active')
            ->value('users.id');

        $authorityId = DB::table('user_roles')
            ->join('roles', 'roles.id', '=', 'user_roles.role_id')
            ->join('users', 'users.id', '=', 'user_roles.user_id')
            ->join('lgu_officer_profiles', 'lgu_officer_profiles.user_id', '=', 'users.id')
            ->where('roles.slug', 'lgu_authority')
            ->where('lgu_officer_profiles.municipality_id', $municipalityId)
            ->where('users.status', 'active')
            ->value('users.id');

        // A seed actor is required because submitted_by is NOT nullable.
        // If no municipal Encoder account exists, use an active platform Admin.
        $seedActorId = $encoderId ?: DB::table('user_roles')
            ->join('roles', 'roles.id', '=', 'user_roles.role_id')
            ->join('users', 'users.id', '=', 'user_roles.user_id')
            ->where('roles.slug', 'admin')
            ->where('users.status', 'active')
            ->value('users.id');

        if (!$seedActorId) {
            throw new RuntimeException(
                'No active Encoder or Admin user found. Seed/create an active user account before this price seeder.'
            );
        }

        $now = now();

        DB::transaction(function () use ($municipalityId, $speciesIds, $seedActorId, $authorityId, $now) {
            foreach (self::PRICES as [$speciesCode, $effectiveFrom, $effectiveTo, $price, $sourcePeriod]) {
                $speciesId = $speciesIds[$speciesCode];
                // Preserve the observation month in the source audit remarks.
                $monthLabel = (new \DateTimeImmutable($effectiveFrom))->format('F Y');

                // Stable natural-key matching makes this seeder safe to rerun.
                $existingId = DB::table('price_references')
                    ->where('municipality_id', $municipalityId)
                    ->where('species_id', $speciesId)
                    ->whereNull('breed_id')
                    ->whereNull('barangay_id')
                    ->where('sale_purpose', 'slaughter')
                    ->whereDate('effective_from', $effectiveFrom)
                    ->where(function ($source) { $source->where('source', 'like', 'PSA%')->orWhere('source', 'like', 'Philippine Statistics Authority%'); })
                    ->value('id');

                $id = $existingId ?: (string) Str::uuid();

                DB::table('price_references')->updateOrInsert(
                    ['id' => $id],
                    [
                        'municipality_id' => $municipalityId,
                        'barangay_id' => null,
                        'species_id' => $speciesId,
                        'breed_id' => null,
                        'sale_purpose' => 'slaughter',
                        'reference_category' => 'Slaughter',
                        'price_per_kg' => $price,
                        'price_min_per_unit' => $price,
                        'price_max_per_unit' => $price,
                        'price_unit' => 'PHP/kg',
                        'effective_from' => $effectiveFrom,
                        'effective_to' => $effectiveTo,
                        'source' => self::SOURCE,
                        'remarks' => self::SOURCE_REMARKS . ' PSA bulletin period: ' . $sourcePeriod . '; month: ' . $monthLabel . ';',
                        'status' => 'official',
                        'submitted_by' => $seedActorId,
                        'submitted_at' => $now,
                        'reviewed_by' => $authorityId,
                        'reviewed_at' => $authorityId ? $now : null,
                        'approved_by' => $authorityId,
                        'approved_at' => $authorityId ? $now : null,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]
                );
            }
        });

        $this->command?->info('Seeded ' . count(self::PRICES) . ' PSA monthly official base-price references for Barili (2025-01 through 2026-06).');
        if (!$authorityId) {
            $this->command?->warn(
                'No municipal Authority account was found. Rows are status=official for baseline data, but approved_by/approved_at remain null. Review audit/approval policy before production use.'
            );
        }
    }
}
