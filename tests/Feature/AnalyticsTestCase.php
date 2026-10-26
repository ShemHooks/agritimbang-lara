<?php

namespace Tests\Feature;

use App\Models\{
    Barangay,
    FarmerProfile,
    LguOfficerProfile,
    LivestockRecord,
    Municipality,
    PriceReference,
    Role,
    Species,
    Transaction,
    User
};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

abstract class AnalyticsTestCase extends TestCase
{
    use RefreshDatabase;

    protected function municipality(bool $active = true): Municipality
    {
        return Municipality::create([
            'name' => 'Municipality ' . uniqid(),
            'province' => 'Cebu',
            'is_active' => $active,
        ]);
    }

    protected function barangay(Municipality $municipality): Barangay
    {
        return Barangay::create([
            'municipality_id' => $municipality->id,
            'name' => 'Barangay ' . uniqid(),
            'is_active' => true,
        ]);
    }

    protected function user(
        string $role,
        ?Municipality $municipality = null
    ): User {
        $user = User::create([
            'first_name' => 'Analytics',
            'last_name' => 'Tester',
            'email' => uniqid('analytics_') . '@example.test',
            'password' => 'password123',
            'status' => 'active',
            'verification_status' => 'verified',
        ]);

        $roleModel = Role::firstOrCreate(
            ['slug' => $role],
            [
                'name' => ucwords(
                    str_replace('_', ' ', $role)
                ),
            ]
        );

        $user->roles()->attach($roleModel->id);

        if ($municipality !== null) {
            LguOfficerProfile::create([
                'user_id' => $user->id,
                'municipality_id' => $municipality->id,
                'station' => 'Municipal Agriculture Office',
                'designation' => 'Officer',
            ]);
        }

        return $user;
    }

    protected function authenticate(
        string $role,
        ?Municipality $municipality = null
    ): User {
        $user = $this->user($role, $municipality);

        Sanctum::actingAs($user);

        return $user;
    }

    protected function species(): Species
    {
        return Species::create([
            'name' => 'Species ' . uniqid(),
            'code' => 'S' . strtoupper(substr(uniqid(), -7)),
            'is_active' => true,
        ]);
    }

    protected function farmer(
        Municipality $municipality,
        Barangay $barangay
    ): FarmerProfile {
        $user = $this->user('farmer');

        return FarmerProfile::create([
            'user_id' => $user->id,
            'municipality_id' => $municipality->id,
            'barangay_id' => $barangay->id,
        ]);
    }

    protected function livestock(
        FarmerProfile $farmer,
        Species $species,
        Municipality $municipality,
        Barangay $barangay,
        string $status = 'available'
    ): LivestockRecord {
        return LivestockRecord::create([
            'farmer_id' => $farmer->id,
            'species_id' => $species->id,
            'sex' => 'male',
            'municipality_id' => $municipality->id,
            'barangay_id' => $barangay->id,
            'status' => $status,
            'created_by' => $farmer->user_id,
        ]);
    }

    protected function price(
        Municipality $municipality,
        Species $species,
        string $status,
        float $price,
        string $date = '2026-10-01'
    ): PriceReference {
        // Create a valid LGU Encoder assigned to the municipality.
        $submitter = $this->user('lgu_encoder', $municipality);

        return PriceReference::create([
            'municipality_id' => $municipality->id,
            'species_id' => $species->id,
            'price_per_kg' => $price,
            'effective_from' => $date,
            'status' => $status,
            'source' => 'test',
            'submitted_by' => $submitter->id,
            'submitted_at' => now(),
        ]);
    }

    protected function transaction(
        FarmerProfile $farmer,
        Municipality $municipality,
        Barangay $barangay,
        string $status,
        float $price
    ): Transaction {
        return Transaction::create([
            'farmer_id' => $farmer->id,
            'buyer_name' => 'Sample Buyer',
            'buyer_phone' => '09123456789',
            'municipality_id' => $municipality->id,
            'barangay_id' => $barangay->id,
            'transaction_date' => now(),
            'status' => $status,
            'total_reference_value' => $price,
            'total_selling_price' => $price,
            'encoded_by' => $farmer->user_id,
            'source' => 'farmer',
            'buyer_confirmation_method' => 'sms',
            'verified_at' => $status === 'verified'
                ? now()
                : null,
        ]);
    }
}
