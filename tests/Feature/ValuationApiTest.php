<?php

namespace Tests\Feature;

use App\Models\LivestockRecord;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\Barangay;
use App\Models\FarmerProfile;
use App\Models\LguOfficerProfile;
use App\Models\Municipality;
use App\Models\PriceReference;
use App\Models\Role;
use App\Models\Species;
use App\Models\Valuation;
use App\Models\WeightEstimation;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Tests\TestCase;
use App\Models\PricingAdjustmentRule;

class ValuationApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_unauthenticated_farmer_cannot_create_valuation(): void
    {
        $livestockId = '00000000-0000-4000-8000-000000000001';

        $this->postJson(
            "/api/farmer/livestock/{$livestockId}/valuations",
            []
        )->assertUnauthorized();
    }

    public function test_unauthenticated_farmer_cannot_view_valuation_history(): void
    {
        $livestockId = '00000000-0000-4000-8000-000000000001';

        $this->getJson(
            "/api/farmer/livestock/{$livestockId}/valuations"
        )->assertUnauthorized();
    }

    public function test_unauthenticated_lgu_cannot_create_valuation(): void
    {
        $livestockId = '00000000-0000-4000-8000-000000000001';

        $this->postJson(
            "/api/lgu/livestock/{$livestockId}/valuations",
            []
        )->assertUnauthorized();
    }

    public function test_unauthenticated_lgu_cannot_view_valuation_history(): void
    {
        $livestockId = '00000000-0000-4000-8000-000000000001';

        $this->getJson(
            "/api/lgu/livestock/{$livestockId}/valuations"
        )->assertUnauthorized();
    }



    private function createLocation(): array
    {
        $municipality = Municipality::create([
            'name' => 'Test Municipality ' . Str::random(8),
            'province' => 'Negros Occidental',
        ]);

        $barangay = Barangay::create([
            'municipality_id' => $municipality->id,
            'name' => 'Test Barangay ' . Str::random(8),
        ]);

        return [$municipality, $barangay];
    }

    private function createUserWithRole(
        string $roleSlug,
        string $verification = 'verified'
    ): User {
        $user = User::create([
            'first_name' => 'Test',
            'last_name' => 'User',
            'email' => Str::uuid() . '@example.test',
            'password' => bcrypt('password'),
            'status' => 'active',
            'verification_status' => $verification,
        ]);

        $role = Role::firstOrCreate(
            ['slug' => $roleSlug],
            ['name' => ucwords(str_replace('_', ' ', $roleSlug))]
        );

        $user->roles()->attach($role->id);

        return $user;
    }

    private function createFarmer(
        Municipality $municipality,
        Barangay $barangay,
        string $verification = 'verified'
    ): User {
        $user = $this->createUserWithRole('farmer', $verification);

        FarmerProfile::create([
            'user_id' => $user->id,
            'municipality_id' => $municipality->id,
            'barangay_id' => $barangay->id,
        ]);

        return $user;
    }

    private function createEncoder(Municipality $municipality): User
    {
        $user = $this->createUserWithRole('lgu_encoder');

        LguOfficerProfile::create([
            'user_id' => $user->id,
            'municipality_id' => $municipality->id,
        ]);

        return $user;
    }

    private function createLivestock(
        User $farmer,
        Municipality $municipality,
        Barangay $barangay,
        ?float $actualWeight = 280.0
    ): LivestockRecord {
        $species = Species::firstOrCreate(
            ['code' => 'CATTLE'],
            ['name' => 'Cattle', 'is_active' => true]
        );

        return LivestockRecord::create([
            'farmer_id' => $farmer->farmerProfile->id,
            'species_id' => $species->id,
            'breed_id' => null,
            'sex' => 'male',
            'age_months' => 24,
            'age_group' => 'young_adult',
            'reproductive_status' => 'not_applicable',
            'sale_purpose' => 'slaughter',
            'condition_score' => 3,
            'actual_weight_kg' => $actualWeight,
            'municipality_id' => $municipality->id,
            'barangay_id' => $barangay->id,
            'status' => 'available',
            'created_by' => $farmer->id,
        ]);
    }

    private function createOfficialPrice(
        LivestockRecord $livestock,
        User $approver,
        float $pricePerKg = 180.0
    ): PriceReference {
        return PriceReference::create([
            'municipality_id' => $livestock->municipality_id,
            'barangay_id' => null,
            'species_id' => $livestock->species_id,
            'breed_id' => null,
            'sale_purpose' => 'slaughter',
            'price_per_kg' => $pricePerKg,
            'effective_from' => Carbon::today()->subDays(7),
            'effective_to' => null,
            'source' => 'MAO Test Reference',
            'status' => 'official',
            'submitted_by' => $approver->id,
            'submitted_at' => Carbon::now()->subDays(2),
            'approved_by' => $approver->id,
            'approved_at' => Carbon::now()->subDay(),
        ]);
    }

    private function createWeightEstimation(
        LivestockRecord $livestock,
        User $calculator,
        float $weight = 250.0
    ): WeightEstimation {
        // A WET record must reference an existing formula profile.
        $formula = \App\Models\FormulaProfile::firstOrCreate(
            [
                'formula_code' => 'CATTLE_SCHAEFFER',
                'version' => '1.0',
            ],
            [
                'name' => 'Cattle Schaeffer Formula',
                'species_id' => $livestock->species_id,
                'required_inputs' => [
                    'heart_girth_cm',
                    'body_length_cm',
                ],
                'is_active' => true,
            ]
        );

        return WeightEstimation::create([
            'livestock_id' => $livestock->id,
            'formula_profile_id' => $formula->id,
            'heart_girth_cm' => 150,
            'body_length_cm' => 120,
            'estimated_weight_kg' => $weight,
            'formula_code' => $formula->formula_code,
            'formula_version' => $formula->version,
            'estimate_confidence' => 'normal',
            'frame_category' => 'unknown',
            'calculated_by' => $calculator->id,
        ]);
    }


    public function test_verified_farmer_can_calculate_fair_price(): void
    {
        [$municipality, $barangay] = $this->createLocation();

        $farmer = $this->createFarmer($municipality, $barangay);
        $livestock = $this->createLivestock($farmer, $municipality, $barangay);

        $price = $this->createOfficialPrice($livestock, $farmer);

        $this->actingAs($farmer)
            ->postJson("/api/farmer/livestock/{$livestock->id}/valuations")
            ->assertCreated();

        $valuation = Valuation::firstOrFail();

        $this->assertSame($livestock->id, $valuation->livestock_id);
        $this->assertSame($price->id, $valuation->price_reference_id);
        $this->assertSame('50400.00', $valuation->estimated_value);
    }

    public function test_farmer_cannot_value_another_farmers_livestock(): void
    {
        [$municipality, $barangay] = $this->createLocation();

        $owner = $this->createFarmer($municipality, $barangay);
        $other = $this->createFarmer($municipality, $barangay);

        $livestock = $this->createLivestock($owner, $municipality, $barangay);

        $response = $this->actingAs($other)
            ->postJson("/api/farmer/livestock/{$livestock->id}/valuations");

        $this->assertContains($response->status(), [403, 404]);
        $this->assertDatabaseCount('valuations', 0);
    }

    public function test_unverified_farmer_cannot_calculate_valuation(): void
    {
        [$municipality, $barangay] = $this->createLocation();

        $farmer = $this->createFarmer(
            $municipality,
            $barangay,
            'unverified'
        );

        $livestock = $this->createLivestock($farmer, $municipality, $barangay);

        $response = $this->actingAs($farmer)
            ->postJson("/api/farmer/livestock/{$livestock->id}/valuations");

        $this->assertContains($response->status(), [403, 422]);
        $this->assertDatabaseCount('valuations', 0);
    }

    public function test_lgu_encoder_cannot_value_outside_municipality(): void
    {
        [$municipalityA] = $this->createLocation();
        [$municipalityB, $barangayB] = $this->createLocation();

        $encoder = $this->createEncoder($municipalityA);
        $farmer = $this->createFarmer($municipalityB, $barangayB);

        $livestock = $this->createLivestock(
            $farmer,
            $municipalityB,
            $barangayB
        );

        $response = $this->actingAs($encoder)
            ->postJson("/api/lgu/livestock/{$livestock->id}/valuations");

        $this->assertContains($response->status(), [403, 404]);
        $this->assertDatabaseCount('valuations', 0);
    }

    public function test_actual_weight_takes_priority_over_wet(): void
    {
        [$municipality, $barangay] = $this->createLocation();

        $farmer = $this->createFarmer($municipality, $barangay);

        $livestock = $this->createLivestock(
            $farmer,
            $municipality,
            $barangay,
            280
        );

        $this->createOfficialPrice($livestock, $farmer);
        $wet = $this->createWeightEstimation($livestock, $farmer, 250);

        $this->actingAs($farmer)
            ->postJson(
                "/api/farmer/livestock/{$livestock->id}/valuations",
                ['weight_estimation_id' => $wet->id]
            )
            ->assertCreated();

        $valuation = Valuation::firstOrFail();

        $this->assertSame('actual', $valuation->weight_type);
        $this->assertSame('280.0000', $valuation->weight_used_kg);
        $this->assertSame('50400.00', $valuation->estimated_value);
    }

    public function test_wet_is_used_when_actual_weight_is_missing(): void
    {
        [$municipality, $barangay] = $this->createLocation();

        $farmer = $this->createFarmer($municipality, $barangay);

        $livestock = $this->createLivestock(
            $farmer,
            $municipality,
            $barangay,
            null
        );

        $this->createOfficialPrice($livestock, $farmer);
        $wet = $this->createWeightEstimation($livestock, $farmer, 250);

        $this->actingAs($farmer)
            ->postJson("/api/farmer/livestock/{$livestock->id}/valuations")
            ->assertCreated();

        $valuation = Valuation::firstOrFail();

        $this->assertSame('estimated', $valuation->weight_type);
        $this->assertSame($wet->id, $valuation->weight_estimation_id);
        $this->assertSame('250.0000', $valuation->weight_used_kg);
        $this->assertSame('45000.00', $valuation->estimated_value);
    }

    public function test_missing_official_price_returns_validation_error(): void
    {
        [$municipality, $barangay] = $this->createLocation();

        $farmer = $this->createFarmer($municipality, $barangay);
        $livestock = $this->createLivestock($farmer, $municipality, $barangay);

        $this->actingAs($farmer)
            ->postJson("/api/farmer/livestock/{$livestock->id}/valuations")
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['price_reference']);

        $this->assertDatabaseCount('valuations', 0);
    }

    public function test_valuation_history_contains_saved_snapshot(): void
    {
        [$municipality, $barangay] = $this->createLocation();

        $farmer = $this->createFarmer($municipality, $barangay);
        $livestock = $this->createLivestock($farmer, $municipality, $barangay);

        $this->createOfficialPrice($livestock, $farmer);

        $this->actingAs($farmer)
            ->postJson("/api/farmer/livestock/{$livestock->id}/valuations")
            ->assertCreated();

        $valuation = Valuation::firstOrFail();

        $this->assertSame('FPC-1.1', $valuation->calculation_version);

        $this->assertSame(
            'PHP',
            $valuation->calculation_snapshot['calculation']['currency']
        );

        $this->actingAs($farmer)
            ->getJson("/api/farmer/livestock/{$livestock->id}/valuations")
            ->assertOk();

        $this->assertDatabaseCount('valuations', 1);
    }


    public function test_barangay_price_takes_priority_over_municipality_price(): void
    {
        [$municipality, $barangay] = $this->createLocation();

        $farmer = $this->createFarmer($municipality, $barangay);
        $livestock = $this->createLivestock(
            $farmer,
            $municipality,
            $barangay,
            280
        );

        // Municipality-level official price: PHP 180/kg
        $municipalPrice = $this->createOfficialPrice(
            $livestock,
            $farmer,
            180
        );

        // Barangay-level official price: PHP 200/kg
        $barangayPrice = $this->createOfficialPrice(
            $livestock,
            $farmer,
            200
        );

        $barangayPrice->update([
            'barangay_id' => $barangay->id,
        ]);

        $this->actingAs($farmer)
            ->postJson(
                "/api/farmer/livestock/{$livestock->id}/valuations"
            )
            ->assertCreated();

        $valuation = Valuation::firstOrFail();

        $this->assertSame(
            $barangayPrice->id,
            $valuation->price_reference_id
        );

        $this->assertSame(
            '200.00',
            $valuation->base_price_per_kg
        );

        // 280 kg x PHP 200 = PHP 56,000
        $this->assertSame(
            '56000.00',
            $valuation->estimated_value
        );
    }

    public function test_expired_official_price_is_not_used(): void
    {
        [$municipality, $barangay] = $this->createLocation();

        $farmer = $this->createFarmer($municipality, $barangay);
        $livestock = $this->createLivestock(
            $farmer,
            $municipality,
            $barangay
        );

        $price = $this->createOfficialPrice(
            $livestock,
            $farmer,
            180
        );

        // Expired before today's valuation date.
        $price->update([
            'effective_from' => Carbon::today()->subDays(30),
            'effective_to' => Carbon::today()->subDay(),
        ]);

        $this->actingAs($farmer)
            ->postJson(
                "/api/farmer/livestock/{$livestock->id}/valuations"
            )
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['price_reference']);

        $this->assertDatabaseCount('valuations', 0);
    }

    public function test_unapproved_price_is_not_used(): void
    {
        [$municipality, $barangay] = $this->createLocation();

        $farmer = $this->createFarmer($municipality, $barangay);
        $livestock = $this->createLivestock(
            $farmer,
            $municipality,
            $barangay
        );

        $price = $this->createOfficialPrice(
            $livestock,
            $farmer,
            180
        );

        $price->update([
            'status' => 'pending_review',
            'approved_by' => null,
            'approved_at' => null,
        ]);

        $this->actingAs($farmer)
            ->postJson(
                "/api/farmer/livestock/{$livestock->id}/valuations"
            )
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['price_reference']);

        $this->assertDatabaseCount('valuations', 0);
    }

    public function test_condition_score_four_applies_twelve_percent_premium(): void
    {
        [$municipality, $barangay] = $this->createLocation();

        $farmer = $this->createFarmer($municipality, $barangay);
        $livestock = $this->createLivestock(
            $farmer,
            $municipality,
            $barangay,
            280
        );

        $livestock->update([
            'condition_score' => 4,
        ]);

        $this->createOfficialPrice(
            $livestock,
            $farmer,
            180
        );

        $this->actingAs($farmer)
            ->postJson(
                "/api/farmer/livestock/{$livestock->id}/valuations"
            )
            ->assertCreated();

        $valuation = Valuation::firstOrFail();

        // Condition score 4 = factor 1.1200
        $this->assertSame(
            '1.1200',
            $valuation->condition_factor
        );

        // PHP 180 x 1.12 = PHP 201.60/kg
        $this->assertSame(
            '201.60',
            $valuation->final_price_per_kg
        );

        // 280 kg x PHP 201.60 = PHP 56,448
        $this->assertSame(
            '56448.00',
            $valuation->estimated_value
        );
    }


    private function createApprovedAdjustmentRule(
        LivestockRecord $livestock,
        User $approver,
        string $dimension,
        string $categoryKey,
        float $factor
    ): PricingAdjustmentRule {
        return PricingAdjustmentRule::create([
            'dimension' => $dimension,
            'species_id' => $livestock->species_id,
            'breed_id' => null,
            'category_key' => $categoryKey,
            'sale_purpose' => $livestock->sale_purpose,
            'factor' => $factor,
            'effective_from' => Carbon::today()->subDays(7),
            'effective_to' => null,
            'source_note' => 'Approved FPC integration test rule',
            'status' => 'approved',
            'approved_by' => $approver->id,
        ]);
    }


    public function test_approved_breed_adjustment_is_applied(): void
    {
        [$municipality, $barangay] = $this->createLocation();

        $farmer = $this->createFarmer($municipality, $barangay);
        $livestock = $this->createLivestock(
            $farmer,
            $municipality,
            $barangay,
            280
        );

        $breed = \App\Models\Breed::create([
            'species_id' => $livestock->species_id,
            'name' => 'Test Premium Cattle',
            'code' => 'TEST_PREMIUM',
            'is_active' => true,
        ]);

        $livestock->update([
            'breed_id' => $breed->id,
        ]);

        $this->createOfficialPrice($livestock, $farmer, 180);

        $this->createApprovedAdjustmentRule(
            $livestock,
            $farmer,
            'breed',
            'TEST_PREMIUM',
            1.10
        );

        $this->actingAs($farmer)
            ->postJson("/api/farmer/livestock/{$livestock->id}/valuations")
            ->assertCreated();

        $valuation = Valuation::firstOrFail();

        $this->assertSame('1.1000', $valuation->breed_factor);
        $this->assertSame('198.00', $valuation->final_price_per_kg);
        $this->assertSame('55440.00', $valuation->estimated_value);
    }


    public function test_approved_age_stage_adjustment_is_applied(): void
    {
        [$municipality, $barangay] = $this->createLocation();

        $farmer = $this->createFarmer($municipality, $barangay);
        $livestock = $this->createLivestock(
            $farmer,
            $municipality,
            $barangay,
            280
        );

        $this->createOfficialPrice($livestock, $farmer, 180);

        $this->createApprovedAdjustmentRule(
            $livestock,
            $farmer,
            'age_stage',
            'young_adult',
            1.05
        );

        $this->actingAs($farmer)
            ->postJson("/api/farmer/livestock/{$livestock->id}/valuations")
            ->assertCreated();

        $valuation = Valuation::firstOrFail();

        $this->assertSame('1.0500', $valuation->age_factor);
        $this->assertSame('189.00', $valuation->final_price_per_kg);
        $this->assertSame('52920.00', $valuation->estimated_value);
    }


    public function test_approved_reproductive_adjustment_is_applied(): void
    {
        [$municipality, $barangay] = $this->createLocation();

        $farmer = $this->createFarmer($municipality, $barangay);
        $livestock = $this->createLivestock(
            $farmer,
            $municipality,
            $barangay,
            280
        );

        $livestock->update([
            'sex' => 'female',
            'reproductive_status' => 'has_given_birth',
            'parity' => 2,
        ]);

        $this->createOfficialPrice($livestock, $farmer, 180);

        $this->createApprovedAdjustmentRule(
            $livestock,
            $farmer,
            'reproductive_status',
            'has_given_birth',
            1.15
        );

        $this->actingAs($farmer)
            ->postJson("/api/farmer/livestock/{$livestock->id}/valuations")
            ->assertCreated();

        $valuation = Valuation::firstOrFail();

        $this->assertSame('1.1500', $valuation->reproductive_factor);
        $this->assertSame('207.00', $valuation->final_price_per_kg);
        $this->assertSame('57960.00', $valuation->estimated_value);
    }


    public function test_approved_frame_adjustment_is_applied_from_wet(): void
    {
        [$municipality, $barangay] = $this->createLocation();

        $farmer = $this->createFarmer($municipality, $barangay);
        $livestock = $this->createLivestock(
            $farmer,
            $municipality,
            $barangay,
            280
        );

        $this->createOfficialPrice($livestock, $farmer, 180);

        $wet = $this->createWeightEstimation(
            $livestock,
            $farmer,
            250
        );

        $wet->update([
            'frame_category' => 'large',
        ]);

        $this->createApprovedAdjustmentRule(
            $livestock,
            $farmer,
            'frame',
            'large',
            1.08
        );

        $this->actingAs($farmer)
            ->postJson(
                "/api/farmer/livestock/{$livestock->id}/valuations",
                ['weight_estimation_id' => $wet->id]
            )
            ->assertCreated();

        $valuation = Valuation::firstOrFail();

        $this->assertSame('actual', $valuation->weight_type);
        $this->assertSame('1.0800', $valuation->frame_factor);
        $this->assertSame('194.40', $valuation->final_price_per_kg);
        $this->assertSame('54432.00', $valuation->estimated_value);
    }


    public function test_farmer_cannot_view_another_farmers_valuation_history(): void
    {
        [$municipality, $barangay] = $this->createLocation();

        $owner = $this->createFarmer($municipality, $barangay);
        $other = $this->createFarmer($municipality, $barangay);

        $livestock = $this->createLivestock(
            $owner,
            $municipality,
            $barangay
        );

        $this->createOfficialPrice($livestock, $owner, 180);

        // Owner creates a valuation.
        $this->actingAs($owner)
            ->postJson("/api/farmer/livestock/{$livestock->id}/valuations")
            ->assertCreated();

        $this->assertDatabaseCount('valuations', 1);

        // Another farmer attempts to read it.
        $response = $this->actingAs($other)
            ->getJson("/api/farmer/livestock/{$livestock->id}/valuations");

        $this->assertContains($response->status(), [403, 404]);

        // Unauthorized access must not delete or alter the valuation.
        $this->assertDatabaseCount('valuations', 1);
    }


}
