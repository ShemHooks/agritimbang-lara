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

class TransactionCancellationTest extends TestCase
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

    private function makeFixture(string $status = 'pending'): array
    {
        $municipality = $this->makeMunicipality();

        $barangay = Barangay::create([
            'municipality_id' => $municipality->id,
            'name' => 'Barangay Test',
        ]);

        $farmerUser = $this->makeUser();

        $farmerRole = Role::firstOrCreate(
            ['slug' => 'farmer'],
            ['name' => 'Farmer']
        );

        $farmerUser->roles()->attach($farmerRole->id);

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
            'status' => $status === 'draft'
                ? 'available'
                : 'in_transaction',
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
            'status' => $status,
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

        $officer = $this->makeOfficer($municipality);

        return compact(
            'municipality',
            'farmerUser',
            'farmer',
            'livestock',
            'transaction',
            'officer'
        );
    }

    private function makeConfirmation(
        Transaction $transaction,
        string $status
    ): BuyerConfirmation {
        return BuyerConfirmation::create([
            'transaction_id' => $transaction->id,
            'buyer_phone' => $transaction->buyer_phone,
            'token_hash' => hash('sha256', uniqid('token_', true)),
            'transaction_snapshot_hash' => hash('sha256', 'snapshot'),
            'status' => $status,
            'expires_at' => now()->addDay(),
            'confirmed_at' => $status === 'confirmed' ? now() : null,
            'consumed_at' => in_array($status, ['confirmed', 'disputed'])
                ? now()
                : null,
        ]);
    }

    public function test_farmer_can_cancel_draft_transaction(): void
    {
        $data = $this->makeFixture('draft');

        Sanctum::actingAs($data['farmerUser']);

        $this->postJson(
            "/api/farmer/transactions/{$data['transaction']->id}/cancel",
            ['reason' => 'I decided not to proceed.']
        )
            ->assertOk()
            ->assertJsonPath('data.status', 'cancelled');

        $this->assertDatabaseHas('livestock_records', [
            'id' => $data['livestock']->id,
            'status' => 'available',
        ]);
    }

    public function test_farmer_can_cancel_pending_transaction(): void
    {
        $data = $this->makeFixture();

        $confirmation = $this->makeConfirmation(
            $data['transaction'],
            'pending'
        );

        Sanctum::actingAs($data['farmerUser']);

        $this->postJson(
            "/api/farmer/transactions/{$data['transaction']->id}/cancel",
            ['reason' => 'Buyer withdrew from the transaction.']
        )
            ->assertOk()
            ->assertJsonPath('data.status', 'cancelled');

        $this->assertDatabaseHas('livestock_records', [
            'id' => $data['livestock']->id,
            'status' => 'available',
        ]);

        $this->assertDatabaseHas('buyer_confirmations', [
            'id' => $confirmation->id,
            'status' => 'revoked',
        ]);
    }

    public function test_farmer_cannot_cancel_another_farmers_transaction(): void
    {
        $data = $this->makeFixture();
        $other = $this->makeFixture();

        Sanctum::actingAs($other['farmerUser']);

        $this->postJson(
            "/api/farmer/transactions/{$data['transaction']->id}/cancel",
            ['reason' => 'Attempting unauthorized cancellation.']
        )->assertForbidden();
    }

    public function test_farmer_cannot_cancel_after_buyer_confirmation(): void
    {
        $data = $this->makeFixture();

        $this->makeConfirmation($data['transaction'], 'confirmed');

        Sanctum::actingAs($data['farmerUser']);

        $this->postJson(
            "/api/farmer/transactions/{$data['transaction']->id}/cancel",
            ['reason' => 'Trying to cancel confirmed transaction.']
        )
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['buyer_confirmation']);
    }

    public function test_lgu_can_cancel_pending_transaction(): void
    {
        $data = $this->makeFixture();

        Sanctum::actingAs($data['officer']);

        $this->postJson(
            "/api/lgu/transactions/{$data['transaction']->id}/cancel",
            ['reason' => 'Transaction information is inconsistent.']
        )
            ->assertOk()
            ->assertJsonPath('data.status', 'cancelled');

        $this->assertDatabaseHas('livestock_records', [
            'id' => $data['livestock']->id,
            'status' => 'available',
        ]);
    }

    public function test_lgu_cannot_cancel_outside_municipality(): void
    {
        $data = $this->makeFixture();

        $otherMunicipality = $this->makeMunicipality();
        $otherOfficer = $this->makeOfficer($otherMunicipality);

        Sanctum::actingAs($otherOfficer);

        $this->postJson(
            "/api/lgu/transactions/{$data['transaction']->id}/cancel",
            ['reason' => 'Unauthorized municipal cancellation.']
        )->assertForbidden();
    }

    public function test_encoder_cannot_cancel_own_transaction_as_lgu(): void
    {
        $data = $this->makeFixture();

        $data['transaction']->update([
            'encoded_by' => $data['officer']->id,
        ]);

        Sanctum::actingAs($data['officer']);

        $this->postJson(
            "/api/lgu/transactions/{$data['transaction']->id}/cancel",
            ['reason' => 'Attempting self-approval equivalent.']
        )->assertForbidden();
    }

    public function test_active_dispute_blocks_normal_cancellation(): void
    {
        $data = $this->makeFixture();

        $data['transaction']->disputes()->create([
            'reported_by' => null,
            'reporter_type' => 'buyer',
            'reason' => 'incorrect_price',
            'description' => 'The agreed price is incorrect.',
            'status' => 'open',
        ]);

        Sanctum::actingAs($data['officer']);

        $this->postJson(
            "/api/lgu/transactions/{$data['transaction']->id}/cancel",
            ['reason' => 'Trying to bypass the active dispute.']
        )
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['dispute']);

        $this->assertDatabaseHas('transactions', [
            'id' => $data['transaction']->id,
            'status' => 'pending',
        ]);
    }

    public function test_transaction_cannot_be_cancelled_twice(): void
    {
        $data = $this->makeFixture();

        Sanctum::actingAs($data['farmerUser']);

        $url = "/api/farmer/transactions/{$data['transaction']->id}/cancel";

        $this->postJson($url, [
            'reason' => 'First cancellation request.',
        ])->assertOk();

        $this->postJson($url, [
            'reason' => 'Second cancellation request.',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['status']);

        $this->assertEquals(
            1,
            $data['transaction']->statusLogs()
                ->where('to_status', 'cancelled')
                ->count()
        );
    }

    public function test_cancellation_records_history_and_trimmed_reason(): void
    {
        $data = $this->makeFixture();

        Sanctum::actingAs($data['farmerUser']);

        $this->postJson(
            "/api/farmer/transactions/{$data['transaction']->id}/cancel",
            ['reason' => '  Buyer withdrew from the sale.  ']
        )->assertOk();

        $this->assertDatabaseHas('transactions', [
            'id' => $data['transaction']->id,
            'status' => 'cancelled',
            'remarks' => 'Buyer withdrew from the sale.',
        ]);

        $this->assertDatabaseHas('transaction_status_logs', [
            'transaction_id' => $data['transaction']->id,
            'from_status' => 'pending',
            'to_status' => 'cancelled',
            'actor_type' => 'farmer',
            'actor_user_id' => $data['farmerUser']->id,
        ]);
    }
}
