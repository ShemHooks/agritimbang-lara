<?php

namespace Tests\Feature;

use App\Models\Barangay;
use App\Models\FarmerProfile;
use App\Models\FormulaProfile;
use App\Models\LguOfficerProfile;
use App\Models\LivestockRecord;
use App\Models\Municipality;
use App\Models\Role;
use App\Models\Species;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class WeightEstimationApiTest extends TestCase
{
    use RefreshDatabase;

    private function createMunicipality(): array
    {
        $municipality = Municipality::create([
            'name' => 'Test Municipality ' . Str::random(8),
            'province' => 'Negros Occidental',
            'is_active' => true,
        ]);

        $barangay = Barangay::create([
            'municipality_id' => $municipality->id,
            'name' => 'Test Barangay',
            'is_active' => true,
        ]);

        return [$municipality, $barangay];
    }

    private function createUser(
        string $roleSlug,
        string $verification = 'verified'
    ): User {
        $user = User::create([
            'first_name' => 'Test',
            'last_name' => 'User',
            'email' => Str::uuid() . '@example.test',
            'password' => 'password123',
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
    ): array {
        $user = $this->createUser('farmer', $verification);

        $profile = FarmerProfile::create([
            'user_id' => $user->id,
            'municipality_id' => $municipality->id,
            'barangay_id' => $barangay->id,
        ]);

        return [$user, $profile];
    }

    private function createLguEncoder(
        Municipality $municipality
    ): User {
        $user = $this->createUser('lgu_encoder');

        LguOfficerProfile::create([
            'user_id' => $user->id,
            'municipality_id' => $municipality->id,
        ]);

        return $user;
    }

    private function createCattle(
        FarmerProfile $farmer,
        Municipality $municipality,
        Barangay $barangay,
        ?float $actualWeight = null
    ): LivestockRecord {
        $species = Species::firstOrCreate(
            ['code' => 'CATTLE'],
            ['name' => 'Cattle', 'is_active' => true]
        );

        FormulaProfile::firstOrCreate(
            [
                'formula_code' => 'CATTLE_SCHAEFFER',
                'version' => '1.0',
            ],
            [
                'name' => 'Cattle Schaeffer Formula',
                'species_id' => $species->id,
                'required_inputs' => [
                    'heart_girth_cm',
                    'body_length_cm',
                ],
                'is_active' => true,
                'is_fallback' => false,
            ]
        );

        return LivestockRecord::create([
            'farmer_id' => $farmer->id,
            'species_id' => $species->id,
            'sex' => 'male',
            'age_months' => 24,
            'age_group' => 'young_adult',
            'sale_purpose' => 'slaughter',
            'condition_score' => 3,
            'actual_weight_kg' => $actualWeight,
            'municipality_id' => $municipality->id,
            'barangay_id' => $barangay->id,
            'status' => 'available',
            'created_by' => $farmer->user_id,
        ]);
    }

    private function measurements(): array
    {
        return [
            'heart_girth_cm' => 150,
            'body_length_cm' => 120,
        ];
    }

    public function test_verified_farmer_can_estimate_owned_livestock(): void
    {
        [$municipality, $barangay] = $this->createMunicipality();

        [$user, $farmer] = $this->createFarmer(
            $municipality,
            $barangay
        );

        $livestock = $this->createCattle(
            $farmer,
            $municipality,
            $barangay
        );

        $this->actingAs($user)
            ->postJson(
                "/api/farmer/livestock/{$livestock->id}/weight-estimations",
                $this->measurements()
            )
            ->assertCreated();

        $this->assertDatabaseHas('weight_estimations', [
            'livestock_id' => $livestock->id,
            'formula_code' => 'CATTLE_SCHAEFFER',
            'formula_version' => '1.0',
            'calculated_by' => $user->id,
        ]);
    }

    public function test_farmer_cannot_estimate_another_farmers_livestock(): void
    {
        [$municipality, $barangay] = $this->createMunicipality();

        [$owner, $ownerProfile] = $this->createFarmer(
            $municipality,
            $barangay
        );

        [$otherUser] = $this->createFarmer(
            $municipality,
            $barangay
        );

        $livestock = $this->createCattle(
            $ownerProfile,
            $municipality,
            $barangay
        );

        $this->actingAs($otherUser)
            ->postJson(
                "/api/farmer/livestock/{$livestock->id}/weight-estimations",
                $this->measurements()
            )
            ->assertNotFound();

        $this->assertDatabaseCount('weight_estimations', 0);
    }

    public function test_unverified_farmer_cannot_estimate_weight(): void
    {
        [$municipality, $barangay] = $this->createMunicipality();

        [$user, $farmer] = $this->createFarmer(
            $municipality,
            $barangay,
            'unverified'
        );

        $livestock = $this->createCattle(
            $farmer,
            $municipality,
            $barangay
        );

        $this->actingAs($user)
            ->postJson(
                "/api/farmer/livestock/{$livestock->id}/weight-estimations",
                $this->measurements()
            )
            ->assertUnprocessable();

        $this->assertDatabaseCount('weight_estimations', 0);
    }

    public function test_lgu_encoder_can_estimate_local_livestock(): void
    {
        [$municipality, $barangay] = $this->createMunicipality();

        [$farmerUser, $farmer] = $this->createFarmer(
            $municipality,
            $barangay
        );

        $encoder = $this->createLguEncoder($municipality);

        $livestock = $this->createCattle(
            $farmer,
            $municipality,
            $barangay
        );

        $this->actingAs($encoder)
            ->postJson(
                "/api/lgu/livestock/{$livestock->id}/weight-estimations",
                $this->measurements()
            )
            ->assertCreated();

        $this->assertDatabaseHas('weight_estimations', [
            'livestock_id' => $livestock->id,
            'calculated_by' => $encoder->id,
        ]);
    }

    public function test_lgu_encoder_cannot_estimate_outside_municipality(): void
    {
        [$municipality, $barangay] = $this->createMunicipality();
        [$otherMunicipality] = $this->createMunicipality();

        [$farmerUser, $farmer] = $this->createFarmer(
            $municipality,
            $barangay
        );

        $encoder = $this->createLguEncoder($otherMunicipality);

        $livestock = $this->createCattle(
            $farmer,
            $municipality,
            $barangay
        );

        $this->actingAs($encoder)
            ->postJson(
                "/api/lgu/livestock/{$livestock->id}/weight-estimations",
                $this->measurements()
            )
            ->assertNotFound();

        $this->assertDatabaseCount('weight_estimations', 0);
    }

    public function test_missing_required_measurements_are_rejected(): void
    {
        [$municipality, $barangay] = $this->createMunicipality();

        [$user, $farmer] = $this->createFarmer(
            $municipality,
            $barangay
        );

        $livestock = $this->createCattle(
            $farmer,
            $municipality,
            $barangay
        );

        $this->actingAs($user)
            ->postJson(
                "/api/farmer/livestock/{$livestock->id}/weight-estimations",
                ['heart_girth_cm' => 150]
            )
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['body_length_cm']);

        $this->assertDatabaseCount('weight_estimations', 0);
    }

    public function test_wet_does_not_overwrite_actual_weight(): void
    {
        [$municipality, $barangay] = $this->createMunicipality();

        [$user, $farmer] = $this->createFarmer(
            $municipality,
            $barangay
        );

        $livestock = $this->createCattle(
            $farmer,
            $municipality,
            $barangay,
            280.00
        );

        $this->actingAs($user)
            ->postJson(
                "/api/farmer/livestock/{$livestock->id}/weight-estimations",
                $this->measurements()
            )
            ->assertCreated();

        $this->assertEquals(
            280.00,
            (float) $livestock->fresh()->actual_weight_kg
        );

        $this->assertDatabaseHas('weight_estimations', [
            'livestock_id' => $livestock->id,
            'formula_code' => 'CATTLE_SCHAEFFER',
        ]);
    }
}
