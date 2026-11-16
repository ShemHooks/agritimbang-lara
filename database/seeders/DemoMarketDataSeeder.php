<?php

namespace Database\Seeders;

use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class DemoMarketDataSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->environment('production')) {
            throw new RuntimeException('Demo market data is disabled in production.');
        }

        $municipalityId = DB::table('municipalities')
            ->where('psgc_code', '0702210000')->value('id');
        if (!$municipalityId) {
            throw new RuntimeException('Barili is missing. Run MunicipalitySeeder first.');
        }

        $users = $this->users();
        $barangays = DB::table('barangays')
            ->where('municipality_id', $municipalityId)
            ->pluck('id', 'name');
        $species = DB::table('species')->pluck('id', 'code');

        foreach (['HOG', 'CATTLE', 'GOAT', 'CARABAO'] as $code) {
            if (!isset($species[$code])) {
                throw new RuntimeException("Species [{$code}] is missing. Run SpeciesSeeder.");
            }
        }

        $this->seedAuctionHouse($municipalityId, $barangays['Mantalongon'] ?? null, $users['encoder']->id, $users['authority']->id);
        $priceIds = $this->seedPrices($municipalityId, $species, $users);
        $livestock = $this->seedLivestock($municipalityId, $barangays, $species, $users, $priceIds);
        $this->seedWeightEstimations($livestock, $users);
        $this->seedTransactions($municipalityId, $barangays, $users, $livestock, $priceIds);
        $this->seedWeeklyTransactionHistory($municipalityId, $barangays, $users, $species, $priceIds);
        $this->seedNotifications($users);

        $this->command?->info('Seeded synthetic demo prices, livestock, valuations, transactions, disputes and notifications.');
    }

    private function users(): array
    {
        $map = [];
        foreach ([
            'admin' => 'admin@gmail.com',
            'authority' => 'authority@gmail.com',
            'encoder' => 'encoder@gmail.com',
            'farmer1' => 'farmer1@gmail.com',
            'farmer2' => 'farmer2@gmail.com',
            'farmer3' => 'farmer3@gmail.com',
        ] as $key => $email) {
            $user = DB::table('users')->where('email', $email)->first();
            if (!$user) {
                throw new RuntimeException("Demo user [{$email}] is missing.");
            }
            $map[$key] = $user;
        }

        foreach (['farmer1', 'farmer2', 'farmer3'] as $key) {
            $profile = DB::table('farmer_profiles')->where('user_id', $map[$key]->id)->first();
            if (!$profile) {
                throw new RuntimeException("Farmer profile for [{$key}] is missing.");
            }
            $map[$key]->farmer_profile = $profile;
        }

        return $map;
    }

    private function seedAuctionHouse(?string $municipalityId, ?string $barangayId, string $encoderId, string $assignedBy): string
    {
        if (!$barangayId) {
            throw new RuntimeException('Mantalongon barangay is missing.');
        }

        $id = DB::table('auction_houses')
            ->where('name', 'Mantolongon Livestock Auction Market')
            ->where('municipality_id', $municipalityId)
            ->value('id') ?: (string) Str::uuid();

        DB::table('auction_houses')->updateOrInsert(
            ['id' => $id],
            [
                'name' => 'Mantolongon Livestock Auction Market',
                'municipality_id' => $municipalityId,
                'barangay_id' => $barangayId,
                'address' => 'Mantalongon, Barili, Cebu (demo venue record; confirm official venue details)',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        DB::table('auction_house_encoder_assignments')->updateOrInsert(
            ['auction_house_id' => $id, 'user_id' => $encoderId],
            [
                'id' => DB::table('auction_house_encoder_assignments')->where('auction_house_id', $id)->where('user_id', $encoderId)->value('id') ?: (string) Str::uuid(),
                'is_active' => true,
                'assigned_by' => $assignedBy,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        return $id;
    }

    private function seedPrices(string $municipalityId, $species, array $users): array
    {
        // PSA monthly observations are imported separately by PSAOfficialPriceReferenceSeeder.
        // Do not create a second, quarter-wide draft copy here.
        // Synthetic weekly observations are intentionally separate and visibly labelled.
        // They exist only so local developers can exercise the history chart and ARIMA flow.
        $bases = [
            'HOG' => 171.76,
            'CATTLE' => 222.37,
            'GOAT' => 243.00,
            'CARABAO' => 201.97,
        ];

        $monday = Carbon::now()->startOfWeek(Carbon::MONDAY);
        $firstMonday = $monday->copy()->subWeeks(104);
        $ids = [];

        foreach ($bases as $code => $base) {
            for ($week = 0; $week < 104; $week++) {
                $start = $firstMonday->copy()->addWeeks($week);
                // Deterministic smooth variation: synthetic data, not measured market history.
                $cycle = sin(($week / 104) * 2 * M_PI);
                $shortCycle = cos(($week / 26) * 2 * M_PI);
                $drift = ($week - 51.5) * 0.018;
                $factor = 1 + (0.035 * $cycle) + (0.012 * $shortCycle);
                $value = round(max(1, ($base + $drift) * $factor), 2);

                $id = $this->upsertPrice([
                    'municipality_id' => $municipalityId,
                    'barangay_id' => null,
                    'species_id' => $species[$code],
                    'breed_id' => null,
                    'sale_purpose' => 'slaughter',
                    'price_per_kg' => $value,
                    'effective_from' => $start->toDateString(),
                    'effective_to' => $start->copy()->endOfWeek(Carbon::SUNDAY)->toDateString(),
                    'source' => 'DEMO_SYNTHETIC - NOT ACTUAL MARKET DATA',
                    'remarks' => 'Artificial weekly series generated for local UI/ARIMA testing only. Not a PSA weekly observation, not a Barili MAO monitoring record, and not valid for research findings or real pricing decisions.',
                    'status' => 'official',
                    'submitted_by' => $users['encoder']->id,
                    'submitted_at' => $start->copy()->setTime(8, 0),
                    'reviewed_by' => $users['authority']->id,
                    'reviewed_at' => $start->copy()->setTime(9, 0),
                    'approved_by' => $users['authority']->id,
                    'approved_at' => $start->copy()->setTime(9, 0),
                ]);
                $ids[$code][$start->toDateString()] = $id;
            }
        }

        return $ids;
    }

    private function upsertPrice(array $data): string
    {
        $existing = DB::table('price_references')
            ->where('municipality_id', $data['municipality_id'])
            ->where('species_id', $data['species_id'])
            ->where('sale_purpose', $data['sale_purpose'])
            ->where('source', $data['source'])
            ->whereDate('effective_from', $data['effective_from'])
            ->where(function ($q) use ($data) {
                if ($data['barangay_id'] === null) {
                    $q->whereNull('barangay_id');
                } else {
                    $q->where('barangay_id', $data['barangay_id']);
                }
            })
            ->whereNull('breed_id')
            ->value('id');

        $id = $existing ?: (string) Str::uuid();
        DB::table('price_references')->updateOrInsert(
            ['id' => $id],
            $data + [
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        if ($data['status'] === 'official') {
            DB::table('price_validation_logs')->updateOrInsert(
                [
                    'price_reference_id' => $id,
                    'to_status' => 'official',
                    'action' => 'demo_seed_approved',
                ],
                [
                    'id' => (string) Str::uuid(),
                    'from_status' => 'pending_review',
                    'remarks' => 'Demo-only synthetic record. Not a real MAO-approved market observation.',
                    'performed_by' => $data['approved_by'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }

        return $id;
    }

    private function seedLivestock(
        string $municipalityId,
        $barangays,
        $species,
        array $users,
        array $priceIds
    ): array {
        $records = [
            ['key'=>'hog1','farmer'=>'farmer1','species'=>'HOG','breed'=>'PH_NATIVE','barangay'=>'Mantalongon','sex'=>'male','age'=>8,'weight'=>92.0,'purpose'=>'slaughter','price'=>172.44],
            ['key'=>'hog2','farmer'=>'farmer2','species'=>'HOG','breed'=>'LANDRACE','barangay'=>'Giloctog','sex'=>'female','age'=>10,'weight'=>108.0,'purpose'=>'slaughter','price'=>172.44],
            ['key'=>'hog3','farmer'=>'farmer3','species'=>'HOG','breed'=>'DUROC','barangay'=>'Azucena','sex'=>'male','age'=>7,'weight'=>84.0,'purpose'=>'slaughter','price'=>172.44],
            ['key'=>'cattle1','farmer'=>'farmer1','species'=>'CATTLE','breed'=>'PH_NATIVE','barangay'=>'Mantalongon','sex'=>'female','age'=>36,'weight'=>245.0,'purpose'=>'slaughter','price'=>221.65],
            ['key'=>'cattle2','farmer'=>'farmer2','species'=>'CATTLE','breed'=>'BRAHMAN','barangay'=>'Giloctog','sex'=>'male','age'=>42,'weight'=>310.0,'purpose'=>'slaughter','price'=>221.65],
            ['key'=>'cattle3','farmer'=>'farmer3','species'=>'CATTLE','breed'=>'BRAHMAN_CROSS','barangay'=>'Azucena','sex'=>'female','age'=>30,'weight'=>225.0,'purpose'=>'slaughter','price'=>221.65],
            ['key'=>'goat1','farmer'=>'farmer1','species'=>'GOAT','breed'=>'CEBU_NATIVE','barangay'=>'Mantalongon','sex'=>'female','age'=>18,'weight'=>28.0,'purpose'=>'slaughter','price'=>243.55],
            ['key'=>'goat2','farmer'=>'farmer2','species'=>'GOAT','breed'=>'BOER','barangay'=>'Giloctog','sex'=>'male','age'=>20,'weight'=>36.0,'purpose'=>'slaughter','price'=>243.55],
            ['key'=>'goat3','farmer'=>'farmer3','species'=>'GOAT','breed'=>'UPGRADED','barangay'=>'Azucena','sex'=>'female','age'=>16,'weight'=>25.0,'purpose'=>'slaughter','price'=>243.55],
            ['key'=>'carabao1','farmer'=>'farmer1','species'=>'CARABAO','breed'=>'PH_SWAMP','barangay'=>'Mantalongon','sex'=>'female','age'=>48,'weight'=>390.0,'purpose'=>'slaughter','price'=>201.97],
            ['key'=>'carabao2','farmer'=>'farmer2','species'=>'CARABAO','breed'=>'MURRAH','barangay'=>'Giloctog','sex'=>'male','age'=>54,'weight'=>460.0,'purpose'=>'slaughter','price'=>201.97],
            ['key'=>'carabao3','farmer'=>'farmer3','species'=>'CARABAO','breed'=>'MURRAH_CROSS','barangay'=>'Azucena','sex'=>'female','age'=>40,'weight'=>355.0,'purpose'=>'slaughter','price'=>201.97],
        ];

        $result = [];
        foreach ($records as $row) {
            $breedId = DB::table('breeds')
                ->where('species_id', $species[$row['species']])
                ->where('code', $row['breed'])->value('id');

            if (!$breedId) {
                throw new RuntimeException("Missing breed [{$row['breed']}] for {$row['species']}.");
            }

            $farmer = $users[$row['farmer']];
            $farmerProfile = $farmer->farmer_profile;
            $barangayId = $barangays[$row['barangay']] ?? null;
            if (!$barangayId) {
                throw new RuntimeException("Missing barangay [{$row['barangay']}].");
            }

            $id = DB::table('livestock_records')
                ->where('farmer_id', $farmerProfile->id)
                ->where('species_id', $species[$row['species']])
                ->where('breed_id', $breedId)
                ->where('actual_weight_kg', $row['weight'])
                ->value('id') ?: (string) Str::uuid();

            DB::table('livestock_records')->updateOrInsert(
                ['id' => $id],
                [
                    'farmer_id' => $farmerProfile->id,
                    'species_id' => $species[$row['species']],
                    'breed_id' => $breedId,
                    'sex' => $row['sex'],
                    'age_months' => $row['age'],
                    'age_group' => $this->ageGroup($row['species'], $row['age']),
                    'reproductive_status' => $row['sex'] === 'female' ? 'never_given_birth' : 'not_applicable',
                    'parity' => null,
                    'sale_purpose' => $row['purpose'],
                    'condition_score' => 3,
                    'actual_weight_kg' => $row['weight'],
                    'municipality_id' => $municipalityId,
                    'barangay_id' => $barangayId,
                    'status' => 'available',
                    'created_by' => $users['encoder']->id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );

            $priceReferenceId = $this->latestDemoPriceId($municipalityId, $species[$row['species']]);
            $referenceValue = round($row['weight'] * $row['price'], 2);
            $valuationId = DB::table('valuations')
                ->where('livestock_id', $id)
                ->where('calculation_version', 'DEMO-1.0')
                ->value('id') ?: (string) Str::uuid();

            DB::table('valuations')->updateOrInsert(
                ['id' => $valuationId],
                [
                    'livestock_id' => $id,
                    'price_reference_id' => $priceReferenceId,
                    'weight_estimation_id' => null,
                    'weight_type' => 'actual',
                    'weight_used_kg' => $row['weight'],
                    'base_price_per_kg' => $row['price'],
                    'breed_factor' => 1,
                    'age_factor' => 1,
                    'reproductive_factor' => 1,
                    'frame_factor' => 1,
                    'condition_factor' => 1,
                    'final_price_per_kg' => $row['price'],
                    'estimated_value' => $referenceValue,
                    'adjustment_breakdown' => json_encode(['demo_note' => 'No adjustment factors applied in demo seed.']),
                    'calculation_snapshot' => json_encode([
                        'demo' => true,
                        'weight_source' => 'actual',
                        'weight_kg' => $row['weight'],
                        'price_reference_id' => $priceReferenceId,
                        'base_price_per_kg' => $row['price'],
                        'estimated_value' => $referenceValue,
                        'source_warning' => 'Uses synthetic demo price data; not for actual pricing.',
                    ]),
                    'calculation_version' => 'DEMO-1.0',
                    'calculated_by' => $users[$row['farmer']]->id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );

            $result[$row['key']] = [
                'id' => $id,
                'farmer_key' => $row['farmer'],
                'farmer_profile_id' => $farmerProfile->id,
                'species' => $row['species'],
                'weight' => $row['weight'],
                'price' => $row['price'],
                'reference_value' => $referenceValue,
                'valuation_id' => $valuationId,
                'origin_barangay_id' => $barangayId,
            ];
        }

        return $result;
    }

    private function latestDemoPriceId(string $municipalityId, string $speciesId): string
    {
        $id = DB::table('price_references')
            ->where('municipality_id', $municipalityId)
            ->where('species_id', $speciesId)
            ->where('source', 'DEMO_SYNTHETIC - NOT ACTUAL MARKET DATA')
            ->where('status', 'official')
            ->orderByDesc('effective_from')
            ->value('id');

        if (!$id) {
            throw new RuntimeException('Synthetic demo price not found.');
        }

        return $id;
    }

    private function ageGroup(string $species, int $ageMonths): string
    {
        return match ($species) {
            'HOG' => $ageMonths < 3 ? 'piglet' : ($ageMonths < 5 ? 'grower' : 'finisher'),
            'CATTLE' => $ageMonths < 12 ? 'calf' : ($ageMonths < 24 ? 'yearling' : 'mature_adult'),
            'GOAT' => $ageMonths < 6 ? 'kid' : ($ageMonths < 12 ? 'young' : 'adult'),
            'CARABAO' => $ageMonths < 12 ? 'calf' : ($ageMonths < 24 ? 'yearling' : 'mature_adult'),
            default => 'unknown',
        };
    }

    private function seedWeightEstimations(array $livestock, array $users): void
    {
        $samples = [
            'hog2' => [
                'formula' => 'HOG_COMMERCIAL_FALLBACK',
                'hg' => 105.0, 'bl' => 110.0, 'rh' => null,
                'weight' => 83.90, 'confidence' => 'normal',
            ],
            'cattle1' => [
                'formula' => 'CATTLE_SCHAEFFER',
                'hg' => 140.0, 'bl' => 150.0, 'rh' => null,
                'weight' => 271.59, 'confidence' => 'normal',
            ],
            'goat1' => [
                'formula' => 'GOAT_BARILI_ADULT',
                'hg' => 80.0, 'bl' => 70.0, 'rh' => 60.0,
                'weight' => 35.00, 'confidence' => 'normal',
            ],
            'carabao2' => [
                'formula' => 'CARABAO_MURRAH_REGRESSION',
                'hg' => 200.0, 'bl' => null, 'rh' => null,
                'weight' => 463.27, 'confidence' => 'normal',
            ],
        ];

        foreach ($samples as $key => $sample) {
            $animal = $livestock[$key];
            $profile = DB::table('formula_profiles')
                ->where('formula_code', $sample['formula'])
                ->where('is_active', true)
                ->orderByDesc('created_at')
                ->first();
            if (!$profile) {
                throw new RuntimeException("Formula profile [{$sample['formula']}] is missing.");
            }

            $existing = DB::table('weight_estimations')
                ->where('livestock_id', $animal['id'])
                ->where('formula_code', $sample['formula'])
                ->where('formula_version', $profile->version)
                ->value('id');

            DB::table('weight_estimations')->updateOrInsert(
                ['id' => $existing ?: (string) Str::uuid()],
                [
                    'livestock_id' => $animal['id'],
                    'formula_profile_id' => $profile->id,
                    'heart_girth_cm' => $sample['hg'],
                    'body_length_cm' => $sample['bl'],
                    'rump_height_cm' => $sample['rh'],
                    'estimated_weight_kg' => $sample['weight'],
                    'formula_code' => $sample['formula'],
                    'formula_version' => $profile->version,
                    'estimate_confidence' => $sample['confidence'],
                    'warnings' => json_encode(['DEMO_ONLY: illustrative measurement and estimate; not a measured validation sample.']),
                    'frame_category' => 'unknown',
                    'frame_z' => null,
                    'reference_mean_body_length_cm' => null,
                    'reference_sd_body_length_cm' => null,
                    'reference_n' => null,
                    'reference_group' => null,
                    'frame_stats_version' => null,
                    'calculated_by' => $users[$animal['farmer_key']]->id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }
    }

    private function seedTransactions(
        string $municipalityId,
        $barangays,
        array $users,
        array $livestock,
        array $priceIds
    ): void {
        $auctionHouseId = DB::table('auction_houses')
            ->where('name', 'Mantolongon Livestock Auction Market')
            ->where('municipality_id', $municipalityId)
            ->value('id');

        $sales = [
            [
                'key'=>'private_verified_1','farmer'=>'farmer1','items'=>['hog1'],
                'source'=>'farmer','sale_barangay'=>'Mantalongon','status'=>'verified',
                'buyer'=>'Demo Buyer One','buyer_phone'=>'+639170000101','method'=>'sms',
                'days_ago'=>14,'selling_multiplier'=>1.04,'confirmation'=>'confirmed','dispute'=>null,
            ],
            [
                'key'=>'private_verified_2','farmer'=>'farmer2','items'=>['goat2'],
                'source'=>'farmer','sale_barangay'=>'Giloctog','status'=>'verified',
                'buyer'=>'Demo Buyer Two','buyer_phone'=>'+639170000102','method'=>'sms',
                'days_ago'=>9,'selling_multiplier'=>0.98,'confirmation'=>'confirmed','dispute'=>null,
            ],
            [
                'key'=>'private_pending','farmer'=>'farmer3','items'=>['cattle3'],
                'source'=>'farmer','sale_barangay'=>'Azucena','status'=>'pending_lgu_verification',
                'buyer'=>'Demo Buyer Three','buyer_phone'=>'+639170000103','method'=>'sms',
                'days_ago'=>2,'selling_multiplier'=>1.01,'confirmation'=>'pending','dispute'=>null,
            ],
            [
                'key'=>'auction_verified','farmer'=>'farmer1','items'=>['carabao1','goat1'],
                'source'=>'auction_house','sale_barangay'=>'Mantalongon','status'=>'verified',
                'buyer'=>'Demo Auction Buyer','buyer_phone'=>'+639170000104','method'=>'in_person',
                'days_ago'=>7,'selling_multiplier'=>1.03,'confirmation'=>null,'dispute'=>null,
            ],
            [
                'key'=>'private_disputed','farmer'=>'farmer2','items'=>['hog2'],
                'source'=>'farmer','sale_barangay'=>'Giloctog','status'=>'pending_lgu_verification',
                'buyer'=>'Demo Buyer Four','buyer_phone'=>'+639170000105','method'=>'sms',
                'days_ago'=>1,'selling_multiplier'=>0.90,'confirmation'=>'disputed','dispute'=>'Buyer reports that the recorded total selling price differs from the amount agreed at the market.',
            ],
        ];

        foreach ($sales as $sale) {
            $farmer = $users[$sale['farmer']];
            $farmerProfile = $farmer->farmer_profile;
            $saleBarangayId = $barangays[$sale['sale_barangay']] ?? null;
            if (!$saleBarangayId) {
                throw new RuntimeException("Sale barangay [{$sale['sale_barangay']}] missing.");
            }

            $transactionId = DB::table('transactions')
                ->where('remarks', 'like', '%DEMO_SEED:' . $sale['key'])
                ->value('id') ?: (string) Str::uuid();

            $totalReference = 0;
            $totalSelling = 0;
            $itemRows = [];

            foreach ($sale['items'] as $livestockKey) {
                $animal = $livestock[$livestockKey];
                $reference = $animal['reference_value'];
                $selling = round($reference * $sale['selling_multiplier'], 2);
                $totalReference += $reference;
                $totalSelling += $selling;
                $itemRows[] = [
                    'animal' => $animal,
                    'reference' => $reference,
                    'selling' => $selling,
                ];
            }

            $transactionDate = now()->subDays($sale['days_ago'])->setTime(5, 30);
            $verified = $sale['status'] === 'verified';

            DB::table('transactions')->updateOrInsert(
                ['id' => $transactionId],
                [
                    'farmer_id' => $farmerProfile->id,
                    'buyer_name' => $sale['buyer'],
                    'buyer_phone' => $sale['buyer_phone'],
                    'municipality_id' => $municipalityId,
                    'barangay_id' => $farmerProfile->barangay_id,
                    'sale_municipality_id' => $municipalityId,
                    'sale_barangay_id' => $saleBarangayId,
                    'transaction_date' => $transactionDate,
                    'status' => $sale['status'],
                    'total_reference_value' => round($totalReference, 2),
                    'total_selling_price' => round($totalSelling, 2),
                    'encoded_by' => $sale['source'] === 'farmer' ? $farmer->id : $users['encoder']->id,
                    'verified_by' => $verified ? $users['authority']->id : null,
                    'verified_at' => $verified ? $transactionDate->copy()->addHours(2) : null,
                    'remarks' => 'DEMO ONLY — DEMO_SEED:' . $sale['key'],
                    'source' => $sale['source'],
                    'auction_house_id' => $sale['source'] === 'auction_house' ? $auctionHouseId : null,
                    'buyer_confirmation_method' => $sale['method'],
                    'acknowledgement_reference' => $sale['source'] === 'auction_house' ? 'DEMO-AH-' . strtoupper($sale['key']) : null,
                    'acknowledgement_notes' => $sale['source'] === 'auction_house' ? 'Demo in-person acknowledgement; not an actual market ticket.' : null,
                    'created_at' => $transactionDate,
                    'updated_at' => now(),
                ]
            );

            // Clear and recreate only the items belonging to this explicitly tagged demo transaction.
            DB::table('transaction_items')->where('transaction_id', $transactionId)->delete();

            foreach ($itemRows as $item) {
                $animal = $item['animal'];
                $sellingPerKg = round($item['selling'] / $animal['weight'], 2);
                DB::table('transaction_items')->insert([
                    'id' => (string) Str::uuid(),
                    'transaction_id' => $transactionId,
                    'livestock_id' => $animal['id'],
                    'valuation_id' => $animal['valuation_id'],
                    'weight_type' => 'actual',
                    'weight_used_kg' => $animal['weight'],
                    'base_price_per_kg' => $animal['price'],
                    'final_price_per_kg' => $animal['price'],
                    'reference_value' => $item['reference'],
                    'actual_selling_price' => $item['selling'],
                    'actual_selling_price_per_kg' => $sellingPerKg,
                    'price_difference' => round($item['selling'] - $item['reference'], 2),
                    'percentage_deviation' => $item['reference'] > 0
                        ? round((($item['selling'] - $item['reference']) / $item['reference']) * 100, 4)
                        : 0,
                    'valuation_snapshot' => json_encode([
                        'demo' => true,
                        'weight_kg' => $animal['weight'],
                        'reference_price_per_kg' => $animal['price'],
                        'reference_value' => $item['reference'],
                        'actual_selling_price' => $item['selling'],
                        'warning' => 'Synthetic demo data; not a real sale.',
                    ]),
                    'calculation_version' => 'DEMO-1.0',
                    'created_at' => $transactionDate,
                    'updated_at' => now(),
                ]);

                DB::table('livestock_records')->where('id', $animal['id'])->update([
                    'status' => $verified ? 'sold' : 'in_transaction',
                    'updated_at' => now(),
                ]);
            }

            DB::table('transaction_status_logs')->where('transaction_id', $transactionId)->delete();
            $this->statusLog($transactionId, null, 'draft', 'system', null, 'Demo transaction created.');
            $this->statusLog($transactionId, 'draft', 'pending', $sale['source'] === 'farmer' ? 'farmer' : 'lgu', $sale['source'] === 'farmer' ? $farmer->id : $users['encoder']->id, 'Demo transaction submitted.');
            if ($sale['method'] === 'sms') {
                $this->statusLog($transactionId, 'pending', 'pending_lgu_verification', 'buyer', null, 'Buyer confirmation step represented in demo data.');
            }
            if ($verified) {
                $this->statusLog($transactionId, $sale['method'] === 'sms' ? 'pending_lgu_verification' : 'pending', 'verified', 'lgu', $users['authority']->id, 'Demo transaction verified.');
            }

            if ($sale['method'] === 'sms') {
                $confirmationId = DB::table('buyer_confirmations')
                    ->where('transaction_id', $transactionId)->value('id') ?: (string) Str::uuid();
                DB::table('buyer_confirmations')->updateOrInsert(
                    ['id' => $confirmationId],
                    [
                        'transaction_id' => $transactionId,
                        'buyer_phone' => $sale['buyer_phone'],
                        'token_hash' => hash('sha256', 'demo-confirmation-' . $sale['key']),
                        'transaction_snapshot_hash' => hash('sha256', 'demo-snapshot-' . $sale['key']),
                        'status' => $sale['confirmation'],
                        'expires_at' => $transactionDate->copy()->addDay(),
                        'confirmed_at' => $sale['confirmation'] === 'confirmed' ? $transactionDate->copy()->addHour() : null,
                        'disputed_at' => $sale['confirmation'] === 'disputed' ? $transactionDate->copy()->addHour() : null,
                        'consumed_at' => in_array($sale['confirmation'], ['confirmed', 'disputed'], true) ? $transactionDate->copy()->addHour() : null,
                        'dispute_reason' => $sale['dispute'],
                        'created_at' => $transactionDate,
                        'updated_at' => now(),
                    ]
                );
            }

            if ($sale['dispute']) {
                $existingDispute = DB::table('transaction_disputes')
                    ->where('transaction_id', $transactionId)
                    ->where('reason', 'buyer_dispute')
                    ->value('id') ?: (string) Str::uuid();
                $confirmationId = DB::table('buyer_confirmations')
                    ->where('transaction_id', $transactionId)->value('id');

                DB::table('transaction_disputes')->updateOrInsert(
                    ['id' => $existingDispute],
                    [
                        'transaction_id' => $transactionId,
                        'buyer_confirmation_id' => $confirmationId,
                        'reported_by' => null,
                        'reporter_type' => 'buyer',
                        'reason' => 'buyer_dispute',
                        'description' => $sale['dispute'],
                        'status' => 'open',
                        'reviewed_by' => null,
                        'reviewed_at' => null,
                        'resolved_by' => null,
                        'resolved_at' => null,
                        'resolution' => null,
                        'resolution_notes' => null,
                        'created_at' => $transactionDate->copy()->addHour(),
                        'updated_at' => now(),
                    ]
                );
            }
        }
    }

    private function statusLog(
        string $transactionId,
        ?string $from,
        string $to,
        string $actorType,
        ?string $actorId,
        string $remarks
    ): void {
        DB::table('transaction_status_logs')->insert([
            'id' => (string) Str::uuid(),
            'transaction_id' => $transactionId,
            'from_status' => $from,
            'to_status' => $to,
            'actor_type' => $actorType,
            'actor_user_id' => $actorId,
            'remarks' => $remarks,
            'metadata' => json_encode(['demo_seed' => true]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * 104 weeks x 4 species x 8 transactions = up to 3,328 synthetic, validated
     * transaction records. Every row is explicitly labelled DEMO ONLY.
     */
    private function seedWeeklyTransactionHistory(string $municipalityId, $barangays, array $users, $species, array $priceIds): void
    {
        if (app()->environment('production')) {
            throw new RuntimeException('Synthetic transaction history is disabled in production.');
        }
        $farmerKeys = ['farmer1', 'farmer2', 'farmer3'];
        $saleBarangays = array_values($barangays->all());
        if (!$saleBarangays) throw new RuntimeException('Barili barangays are required for demo transaction history.');
        $firstMonday = Carbon::now()->startOfWeek(Carbon::MONDAY)->subWeeks(104);
        $count = 0;
        foreach (['HOG', 'CATTLE', 'GOAT', 'CARABAO'] as $code) {
            for ($week = 0; $week < 104; $week++) {
                $weekDate = $firstMonday->copy()->addWeeks($week)->toDateString();
                $priceReferenceId = $priceIds[$code][$weekDate] ?? null;
                if (!$priceReferenceId) {
                    // Keep recent transaction weeks available for recommendation tests
                    // without pre-seeding an official weekly price for the same week.
                    $priceReferenceId = DB::table('price_references')->where('municipality_id', $municipalityId)
                        ->where('species_id', $species[$code])->whereNull('barangay_id')->whereNull('breed_id')
                        ->where('source', 'like', '%PSA%')->where('status', 'official')
                        ->orderByDesc('effective_from')->value('id');
                }
                if (!$priceReferenceId) continue;
                $basePrice = (float) DB::table('price_references')->where('id', $priceReferenceId)->value('price_per_kg');
                for ($sample = 0; $sample < 8; $sample++) {
                    $key = 'DEMO_WEEKLY_' . $code . '_' . $weekDate . '_' . $sample;
                    if (DB::table('transactions')->where('remarks', $key)->exists()) { $count++; continue; }
                    $farmer = $users[$farmerKeys[($week + $sample) % count($farmerKeys)]];
                    $profile = $farmer->farmer_profile;
                    $saleBarangayId = $saleBarangays[($week + $sample + array_search($code, ['HOG','CATTLE','GOAT','CARABAO'])) % count($saleBarangays)];
                    $weightRanges = ['HOG' => [55, 105], 'CATTLE' => [180, 420], 'GOAT' => [18, 42], 'CARABAO' => [250, 500]];
                    [$minWeight, $maxWeight] = $weightRanges[$code];
                    $weight = round($minWeight + (($week * 7 + $sample * 11) % max(1, ($maxWeight - $minWeight))), 2);
                    $swing = 1 + (sin(($week + 1) * 0.41 + $sample) * 0.035) + (($sample === 0) ? -0.008 : 0.012);
                    $salePurpose = $sample < 4 ? 'slaughter' : 'breeding';
                    $categoryMultiplier = $salePurpose === 'breeding' ? 1.10 : 1.00;
                    $actualPerKg = round($basePrice * $swing * $categoryMultiplier, 2);
                    $referenceValue = round($weight * $basePrice, 2);
                    $actualTotal = round($weight * $actualPerKg, 2);
                    $livestockId = (string) Str::uuid();
                    $valuationId = (string) Str::uuid();
                    $transactionId = (string) Str::uuid();
                    $saleDate = $firstMonday->copy()->addWeeks($week)->addDays($sample === 0 ? 2 : 5)->setTime(7 + $sample, 0);
                    DB::table('livestock_records')->insert([
                        'id' => $livestockId, 'farmer_id' => $profile->id, 'species_id' => $species[$code],
                        'breed_id' => null, 'sex' => $sample === 0 ? 'male' : 'female', 'age_months' => null,
                        'age_group' => null, 'reproductive_status' => 'unknown', 'parity' => null,
                        'sale_purpose' => $salePurpose, 'condition_score' => 3, 'actual_weight_kg' => $weight,
                        'municipality_id' => $municipalityId, 'barangay_id' => $profile->barangay_id,
                        'status' => 'sold', 'created_by' => $farmer->id, 'created_at' => $saleDate, 'updated_at' => $saleDate,
                    ]);
                    DB::table('valuations')->insert([
                        'id' => $valuationId, 'livestock_id' => $livestockId, 'price_reference_id' => $priceReferenceId,
                        'weight_estimation_id' => null, 'weight_type' => 'actual', 'weight_used_kg' => $weight,
                        'base_price_per_kg' => $basePrice, 'breed_factor' => 1, 'age_factor' => 1,
                        'reproductive_factor' => 1, 'frame_factor' => 1, 'condition_factor' => 1,
                        'final_price_per_kg' => $basePrice, 'estimated_value' => $referenceValue,
                        'adjustment_breakdown' => json_encode(['demo_only' => true, 'note' => 'Synthetic reference valuation.']),
                        'calculation_snapshot' => json_encode(['demo_only' => true, 'weight_kg' => $weight, 'price_per_kg' => $basePrice]),
                        'calculation_version' => 'DEMO-1.0', 'calculated_by' => $farmer->id,
                        'created_at' => $saleDate, 'updated_at' => $saleDate,
                    ]);
                    DB::table('transactions')->insert([
                        'id' => $transactionId, 'farmer_id' => $profile->id,
                        'buyer_name' => 'DEMO Buyer ' . ($sample + 1), 'buyer_phone' => null,
                        'municipality_id' => $municipalityId, 'barangay_id' => $saleBarangayId,
                        'sale_municipality_id' => $municipalityId, 'sale_barangay_id' => $saleBarangayId,
                        'transaction_date' => $saleDate, 'status' => 'verified',
                        'total_reference_value' => $referenceValue, 'total_selling_price' => $actualTotal,
                        'encoded_by' => $users['encoder']->id, 'verified_by' => $users['authority']->id,
                        'verified_at' => $saleDate->copy()->addHours(1), 'remarks' => $key . ' | DEMO ONLY — synthetic sale; not an actual Barili transaction.',
                        'source' => 'farmer', 'auction_house_id' => null, 'buyer_confirmation_method' => 'in_person',
                        'acknowledgement_reference' => null, 'acknowledgement_notes' => 'Synthetic test record; no real buyer confirmation.',
                        'created_at' => $saleDate, 'updated_at' => $saleDate,
                    ]);
                    DB::table('transaction_items')->insert([
                        'id' => (string) Str::uuid(), 'transaction_id' => $transactionId, 'livestock_id' => $livestockId,
                        'valuation_id' => $valuationId, 'weight_type' => 'actual', 'weight_used_kg' => $weight,
                        'base_price_per_kg' => $basePrice, 'final_price_per_kg' => $basePrice,
                        'reference_value' => $referenceValue, 'actual_selling_price' => $actualTotal,
                        'actual_selling_price_per_kg' => $actualPerKg, 'price_difference' => round($actualTotal - $referenceValue, 2),
                        'percentage_deviation' => round((($actualPerKg - $basePrice) / $basePrice) * 100, 4),
                        'valuation_snapshot' => json_encode(['demo_only' => true, 'note' => 'Synthetic sale history for local recommendation testing.']),
                        'calculation_version' => 'DEMO-1.0', 'created_at' => $saleDate, 'updated_at' => $saleDate,
                    ]);
                    DB::table('transaction_status_logs')->insert([
                        ['id' => (string) Str::uuid(), 'transaction_id' => $transactionId, 'from_status' => null, 'to_status' => 'draft', 'actor_type' => 'farmer', 'actor_user_id' => $farmer->id, 'remarks' => 'DEMO ONLY: synthetic transaction draft created.', 'metadata' => json_encode(['demo_only' => true]), 'created_at' => $saleDate, 'updated_at' => $saleDate],
                        ['id' => (string) Str::uuid(), 'transaction_id' => $transactionId, 'from_status' => 'pending_lgu_verification', 'to_status' => 'verified', 'actor_type' => 'lgu', 'actor_user_id' => $users['authority']->id, 'remarks' => 'DEMO ONLY: synthetic test transaction validated.', 'metadata' => json_encode(['demo_only' => true]), 'created_at' => $saleDate->copy()->addHours(1), 'updated_at' => $saleDate->copy()->addHours(1)],
                    ]);
                    $count++;
                }
            }
        }
        $this->command?->info("Available synthetic demo transaction history rows: {$count} (up to 3,328 across 104 weeks and four species (four Slaughter and four Breeding observations per species/week)). DEMO ONLY.");
    }

    private function seedNotifications(array $users): void
    {
        $notifications = [
            [$users['authority']->id, 'New demo transaction awaiting review', 'A synthetic demo transaction is pending LGU verification.'],
            [$users['encoder']->id, 'Demo price references seeded', 'Synthetic weekly prices are available for local testing only.'],
            [$users['farmer1']->id, 'Demo transaction verified', 'A synthetic demo sale has been marked verified.'],
            [$users['farmer2']->id, 'Demo buyer dispute', 'A synthetic buyer dispute is available for testing the dispute workflow.'],
        ];

        foreach ($notifications as [$userId, $title, $message]) {
            $exists = DB::table('notifications')
                ->where('notifiable_type', 'App\\Models\\User')
                ->where('notifiable_id', $userId)
                ->where('type', 'App\\Notifications\\DemoSeedNotification')
                ->where('data', 'like', '%' . addslashes($title) . '%')
                ->exists();

            if (!$exists) {
                DB::table('notifications')->insert([
                    'id' => (string) Str::uuid(),
                    'type' => 'App\\Notifications\\DemoSeedNotification',
                    'notifiable_type' => 'App\\Models\\User',
                    'notifiable_id' => $userId,
                    'data' => json_encode(['title' => $title, 'message' => $message, 'demo' => true]),
                    'read_at' => null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }
}
