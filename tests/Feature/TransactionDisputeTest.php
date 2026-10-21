<?php

namespace Tests\Feature;

use App\Models\Barangay;
use App\Models\FarmerProfile;
use App\Models\LguOfficerProfile;
use App\Models\LivestockRecord;
use App\Models\Municipality;
use App\Models\Role;
use App\Models\Species;
use App\Models\Transaction;
use App\Models\TransactionDispute;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TransactionDisputeTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(): User
    {
        return User::create([
            'first_name' => 'Test',
            'last_name' => 'User',
            'email' => uniqid('user_') . '@example.test',
            'password' => bcrypt('password123'),
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

    private function makeFixture(
        string $transactionStatus = 'pending'
    ): array {
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
            'status' => $transactionStatus,
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
        ]);

        $dispute = TransactionDispute::create([
            'transaction_id' => $transaction->id,
            'reported_by' => null,
            'reporter_type' => 'buyer',
            'reason' => 'incorrect_price',
            'description' => 'The selling price is incorrect.',
            'status' => 'open',
        ]);

        $officer = $this->makeOfficer($municipality);

        return compact(
            'municipality',
            'farmerUser',
            'livestock',
            'transaction',
            'dispute',
            'officer'
        );
    }

    public function test_officer_can_list_disputes_in_own_municipality(): void
    {
        $data = $this->makeFixture();

        Sanctum::actingAs($data['officer']);

        $this->getJson('/api/lgu/transaction-disputes')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_officer_can_view_dispute(): void
    {
        $data = $this->makeFixture();

        Sanctum::actingAs($data['officer']);

        $this->getJson(
            "/api/lgu/transaction-disputes/{$data['dispute']->id}"
        )
            ->assertOk()
            ->assertJsonPath('data.id', $data['dispute']->id);
    }

    public function test_officer_can_start_dispute_review(): void
    {
        $data = $this->makeFixture();

        Sanctum::actingAs($data['officer']);

        $this->postJson(
            "/api/lgu/transaction-disputes/{$data['dispute']->id}/review"
        )
            ->assertOk()
            ->assertJsonPath('data.status', 'under_review');

        $this->assertDatabaseHas('transaction_disputes', [
            'id' => $data['dispute']->id,
            'status' => 'under_review',
            'reviewed_by' => $data['officer']->id,
        ]);
    }

    public function test_officer_can_dismiss_dispute(): void
    {
        $data = $this->makeFixture();

        Sanctum::actingAs($data['officer']);

        $this->postJson(
            "/api/lgu/transaction-disputes/{$data['dispute']->id}/review"
        )->assertOk();

        $this->postJson(
            "/api/lgu/transaction-disputes/{$data['dispute']->id}/resolve",
            [
                'resolution' => 'dismissed',
                'resolution_notes' =>
                    'The submitted transaction details were validated.',
            ]
        )
            ->assertOk()
            ->assertJsonPath('data.status', 'dismissed');

        $this->assertDatabaseHas('transactions', [
            'id' => $data['transaction']->id,
            'status' => 'pending',
        ]);

        $this->assertDatabaseHas('livestock_records', [
            'id' => $data['livestock']->id,
            'status' => 'in_transaction',
        ]);
    }

    public function test_upheld_dispute_cancels_transaction(): void
    {
        $data = $this->makeFixture();

        Sanctum::actingAs($data['officer']);

        $this->postJson(
            "/api/lgu/transaction-disputes/{$data['dispute']->id}/review"
        )->assertOk();

        $this->postJson(
            "/api/lgu/transaction-disputes/{$data['dispute']->id}/resolve",
            [
                'resolution' => 'upheld',
                'resolution_notes' =>
                    'Buyer dispute was validated by the LGU officer.',
            ]
        )
            ->assertOk()
            ->assertJsonPath('data.status', 'resolved');

        $this->assertDatabaseHas('transactions', [
            'id' => $data['transaction']->id,
            'status' => 'cancelled',
        ]);

        $this->assertDatabaseHas('livestock_records', [
            'id' => $data['livestock']->id,
            'status' => 'available',
        ]);

        $this->assertDatabaseHas('transaction_disputes', [
            'id' => $data['dispute']->id,
            'status' => 'resolved',
            'resolution' => 'upheld',
            'resolved_by' => $data['officer']->id,
        ]);
    }

    public function test_officer_cannot_resolve_without_review(): void
    {
        $data = $this->makeFixture();

        Sanctum::actingAs($data['officer']);

        $this->postJson(
            "/api/lgu/transaction-disputes/{$data['dispute']->id}/resolve",
            [
                'resolution' => 'upheld',
                'resolution_notes' => 'Dispute has not been reviewed yet.',
            ]
        )
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['status']);
    }

    public function test_officer_cannot_review_dispute_twice(): void
    {
        $data = $this->makeFixture();

        Sanctum::actingAs($data['officer']);

        $url =
            "/api/lgu/transaction-disputes/{$data['dispute']->id}/review";

        $this->postJson($url)->assertOk();

        $this->postJson($url)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['status']);
    }

    public function test_officer_cannot_access_dispute_outside_municipality(): void
    {
        $data = $this->makeFixture();

        $otherMunicipality = $this->makeMunicipality();
        $otherOfficer = $this->makeOfficer($otherMunicipality);

        Sanctum::actingAs($otherOfficer);

        $this->getJson(
            "/api/lgu/transaction-disputes/{$data['dispute']->id}"
        )->assertNotFound();

        $this->postJson(
            "/api/lgu/transaction-disputes/{$data['dispute']->id}/review"
        )->assertForbidden();
    }

    public function test_farmer_cannot_review_dispute(): void
    {
        $data = $this->makeFixture();

        Sanctum::actingAs($data['farmerUser']);

        $this->postJson(
            "/api/lgu/transaction-disputes/{$data['dispute']->id}/review"
        )->assertForbidden();
    }

    public function test_upheld_dispute_records_cancellation_history(): void
    {
        $data = $this->makeFixture();

        Sanctum::actingAs($data['officer']);

        $this->postJson(
            "/api/lgu/transaction-disputes/{$data['dispute']->id}/review"
        )->assertOk();

        $this->postJson(
            "/api/lgu/transaction-disputes/{$data['dispute']->id}/resolve",
            [
                'resolution' => 'upheld',
                'resolution_notes' =>
                    'Dispute upheld after investigation of selling price.',
            ]
        )->assertOk();

        $this->assertDatabaseHas('transaction_status_logs', [
            'transaction_id' => $data['transaction']->id,
            'from_status' => 'pending',
            'to_status' => 'cancelled',
            'actor_type' => 'lgu',
            'actor_user_id' => $data['officer']->id,
        ]);
    }
}
