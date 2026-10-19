<?php

namespace Tests\Feature;

use App\Models\Barangay;
use App\Models\BuyerConfirmation;
use App\Models\FarmerProfile;
use App\Models\LguOfficerProfile;
use App\Models\LivestockRecord;
use App\Models\Municipality;
use App\Models\Role;
use App\Models\Species;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class LguTransactionVerificationTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(): User
    {
        return User::create([
            'first_name' => 'Test',
            'last_name' => 'User',
            'email' => uniqid('user_') . '@example.test',
            'password' => 'password123',
            'status' => 'active',
            'verification_status' => 'verified',
        ]);
    }

    private function makeMunicipality(): Municipality
    {
        return Municipality::create([
            'name' => 'Municipality ' . uniqid(),
            'province' => 'Negros Occidental',
        ]);
    }

    private function makeOfficer(Municipality $municipality): User
    {
        $officer = $this->makeUser();

        $role = Role::firstOrCreate(
            ['slug' => 'lgu_officer'],
            ['name' => 'LGU Officer']
        );

        $officer->roles()->attach($role->id);

        LguOfficerProfile::create([
            'user_id' => $officer->id,
            'municipality_id' => $municipality->id,
            'station' => 'Municipal Agriculture Office',
            'designation' => 'Livestock Officer',
        ]);

        return $officer;
    }

    private function makeTransaction(): array
    {
        $municipality = $this->makeMunicipality();

        $barangay = Barangay::create([
            'municipality_id' => $municipality->id,
            'name' => 'Barangay Test',
        ]);

        $farmerUser = $this->makeUser();

        $farmer = FarmerProfile::create([
            'user_id' => $farmerUser->id,
            'municipality_id' => $municipality->id,
            'barangay_id' => $barangay->id,
        ]);

        $species = Species::create([
            'name' => 'Cattle ' . uniqid(),
            'code' => 'CTL' . strtoupper(substr(uniqid(), -6)),
        ]);

        $livestock = LivestockRecord::create([
            'farmer_id' => $farmer->id,
            'species_id' => $species->id,
            'sex' => 'male',
            'municipality_id' => $municipality->id,
            'barangay_id' => $barangay->id,
            'status' => 'in_transaction',
            'created_by' => $farmerUser->id,
        ]);

        $transaction = Transaction::create([
            'farmer_id' => $farmer->id,
            'buyer_name' => 'Sample Buyer',
            'buyer_phone' => '09123456789',
            'municipality_id' => $municipality->id,
            'barangay_id' => $barangay->id,
            'transaction_date' => now(),
            'source' => 'farmer',
            'buyer_confirmation_method' => 'sms',
            'status' => 'pending_lgu_verification',
            'total_reference_value' => 47000,
            'total_selling_price' => 45000,
            'encoded_by' => $farmerUser->id,
        ]);

        $transaction->items()->create([
            'livestock_id' => $livestock->id,
            'weight_type' => 'actual',
            'weight_used_kg' => 250,
            'base_price_per_kg' => 188,
            'final_price_per_kg' => 188,
            'reference_value' => 47000,
            'actual_selling_price' => 45000,
            'valuation_snapshot' => [
                'weight_kg' => 250,
                'reference_value' => 47000,
            ],
            'calculation_version' => '1.0',
            // Set valuation_id using a real Valuation fixture
            // if your schema requires it.
        ]);

        BuyerConfirmation::create([
            'transaction_id' => $transaction->id,
            'buyer_phone' => '09123456789',
            'token_hash' => hash('sha256', uniqid('token_', true)),
            'transaction_snapshot_hash' => hash('sha256', 'test-snapshot'),
            'status' => 'confirmed',
            'expires_at' => now()->addDay(),
            'confirmed_at' => now(),
            'consumed_at' => now(),
        ]);

        return compact('municipality', 'farmerUser', 'livestock', 'transaction');
    }

    public function test_lgu_officer_can_approve_confirmed_transaction(): void
    {
        $data = $this->makeTransaction();
        $officer = $this->makeOfficer($data['municipality']);

        Sanctum::actingAs($officer);

        $this->postJson(
            "/api/lgu/transactions/{$data['transaction']->id}/approve"
        )->assertOk()
            ->assertJsonPath('data.status', 'verified');

        $this->assertDatabaseHas('livestock_records', [
            'id' => $data['livestock']->id,
            'status' => 'sold',
        ]);

        $this->assertDatabaseHas('transactions', [
            'id' => $data['transaction']->id,
            'status' => 'verified',
            'verified_by' => $officer->id,
        ]);
    }

    public function test_lgu_officer_can_reject_transaction(): void
    {
        $data = $this->makeTransaction();
        $officer = $this->makeOfficer($data['municipality']);

        Sanctum::actingAs($officer);

        $this->postJson(
            "/api/lgu/transactions/{$data['transaction']->id}/reject",
            ['reason' => 'Transaction details do not match records.']
        )->assertOk()
            ->assertJsonPath('data.status', 'rejected');

        $this->assertDatabaseHas('livestock_records', [
            'id' => $data['livestock']->id,
            'status' => 'available',
        ]);
    }

    public function test_officer_cannot_verify_outside_municipality(): void
    {
        $data = $this->makeTransaction();
        $otherMunicipality = $this->makeMunicipality();
        $officer = $this->makeOfficer($otherMunicipality);

        Sanctum::actingAs($officer);

        $this->postJson(
            "/api/lgu/transactions/{$data['transaction']->id}/approve"
        )->assertForbidden();
    }

    public function test_encoder_cannot_verify_own_transaction(): void
    {
        $data = $this->makeTransaction();
        $officer = $this->makeOfficer($data['municipality']);

        $data['transaction']->update([
            'encoded_by' => $officer->id,
        ]);

        Sanctum::actingAs($officer);

        $this->postJson(
            "/api/lgu/transactions/{$data['transaction']->id}/approve"
        )->assertForbidden();
    }

    public function test_unconfirmed_transaction_cannot_be_approved(): void
    {
        $data = $this->makeTransaction();
        $officer = $this->makeOfficer($data['municipality']);

        BuyerConfirmation::where(
            'transaction_id',
            $data['transaction']->id
        )->update(['status' => 'pending']);

        Sanctum::actingAs($officer);

        $this->postJson(
            "/api/lgu/transactions/{$data['transaction']->id}/approve"
        )->assertUnprocessable();
    }

    public function test_disputed_transaction_cannot_be_approved(): void
    {
        $data = $this->makeTransaction();
        $officer = $this->makeOfficer($data['municipality']);

        BuyerConfirmation::where(
            'transaction_id',
            $data['transaction']->id
        )->update(['status' => 'disputed']);

        Sanctum::actingAs($officer);

        $this->postJson(
            "/api/lgu/transactions/{$data['transaction']->id}/approve"
        )->assertUnprocessable();
    }

    public function test_verified_transaction_cannot_be_approved_twice(): void
    {
        $data = $this->makeTransaction();
        $officer = $this->makeOfficer($data['municipality']);

        Sanctum::actingAs($officer);

        $url = "/api/lgu/transactions/{$data['transaction']->id}/approve";

        $this->postJson($url)->assertOk();
        $this->postJson($url)->assertUnprocessable();

        $this->assertEquals(
            1,
            $data['transaction']->statusLogs()
                ->where('to_status', 'verified')
                ->count()
        );
    }

    public function test_rejection_requires_reason(): void
    {
        $data = $this->makeTransaction();
        $officer = $this->makeOfficer($data['municipality']);

        Sanctum::actingAs($officer);

        $this->postJson(
            "/api/lgu/transactions/{$data['transaction']->id}/reject",
            []
        )->assertUnprocessable();
    }
}
