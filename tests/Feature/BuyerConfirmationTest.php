<?php

namespace Tests\Feature;

use App\Jobs\SendBuyerConfirmationSms;
use App\Models\BuyerConfirmation;
use App\Models\Transaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Str;
use Tests\TestCase;
use App\Models\Barangay;
use App\Models\FarmerProfile;
use App\Models\LivestockRecord;
use App\Models\Municipality;
use App\Models\Role;
use App\Models\Species;
use App\Models\User;
use App\Models\Valuation;

// Add the model imports required by your copied fixture helpers.

class BuyerConfirmationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Bus::fake();
    }

    private function createFarmer(
        string $verification = 'verified'
    ): User {
        $municipality = Municipality::create([
            'name' => 'Kabankalan City',
            'province' => 'Negros Occidental',
        ]);

        $barangay = Barangay::create([
            'municipality_id' => $municipality->id,
            'name' => 'Barangay Test',
        ]);

        $user = User::create([
            'first_name' => 'Test',
            'last_name' => 'Farmer',
            'email' => Str::uuid() . '@example.test',
            'password' => bcrypt('password'),
            'status' => 'active',
            'verification_status' => $verification,
        ]);

        $role = Role::firstOrCreate(
            ['slug' => 'farmer'],
            ['name' => 'Farmer']
        );

        $user->roles()->attach($role->id);

        FarmerProfile::create([
            'user_id' => $user->id,
            'municipality_id' => $municipality->id,
            'barangay_id' => $barangay->id,
            'farmer_code' => 'F-' . Str::random(10),
        ]);

        return $user->fresh();
    }

    private function createDraft(
        User $farmer,
        ?string $buyerPhone = '09123456789'
    ): Transaction {
        $profile = $farmer->farmerProfile;

        $species = Species::create([
            'name' => 'Cattle',
            'code' => 'CATTLE',
        ]);

        $livestock = LivestockRecord::create([
            'farmer_id' => $profile->id,
            'species_id' => $species->id,
            'sex' => 'male',
            'age_months' => 24,
            'municipality_id' => $profile->municipality_id,
            'barangay_id' => $profile->barangay_id,
            'status' => 'available',
            'created_by' => $farmer->id,
        ]);

        $transaction = Transaction::create([
            'farmer_id' => $profile->id,
            'buyer_name' => 'Shem Regidor',
            'buyer_phone' => $buyerPhone,
            'municipality_id' => $profile->municipality_id,
            'barangay_id' => $profile->barangay_id,
            'transaction_date' => now(),
            'status' => 'draft',
            'total_reference_value' => 47000,
            'total_selling_price' => 45000,
            'encoded_by' => $farmer->id,
        ]);


        $valuation = Valuation::create([
            'livestock_id' => $livestock->id,
            'price_reference_id' => null,
            'weight_estimation_id' => null,

            'weight_type' => 'actual',
            'weight_used_kg' => 250.0000,

            'base_price_per_kg' => 188.00,
            'breed_factor' => 1.0000,
            'age_factor' => 1.0000,
            'reproductive_factor' => 1.0000,
            'frame_factor' => 1.0000,
            'condition_factor' => 1.0000,

            'final_price_per_kg' => 188.00,
            'estimated_value' => 47000.00,

            'adjustment_breakdown' => [],
            'calculation_snapshot' => [
                'species' => 'Cattle',
                'weight_kg' => 250,
                'price_per_kg' => 188,
                'estimated_value' => 47000,
            ],

            'calculation_version' => 'FPC-TEST-1.0',
            'calculated_by' => $farmer->id,
        ]);

        $transaction->items()->create([
            'livestock_id' => $livestock->id,
            'valuation_id' => $valuation->id,

            'weight_type' => 'actual',
            'weight_used_kg' => 250.0000,

            'base_price_per_kg' => 188.00,
            'final_price_per_kg' => 188.00,

            'reference_value' => 47000.00,
            'actual_selling_price' => 45000.00,
            'actual_selling_price_per_kg' => 180.00,

            'price_difference' => -2000.00,
            'percentage_deviation' => -4.2553,

            'valuation_snapshot' => [
                'valuation_id' => $valuation->id,
                'estimated_value' => 47000,
                'actual_selling_price' => 45000,
            ],

            'calculation_version' => 'FPC-TEST-1.0',
        ]);

        return $transaction;
    }

    private function submitAndGetToken(): array
    {
        $farmer = $this->createFarmer();
        $transaction = $this->createDraft($farmer);

        $this->actingAs($farmer)
            ->postJson(
                "/api/farmer/transactions/{$transaction->id}/submit"
            )
            ->assertOk();

        Bus::assertDispatched(SendBuyerConfirmationSms::class, 1);

        $job = Bus::dispatched(
            SendBuyerConfirmationSms::class
        )->first();

        return [
            $transaction,
            $job->token,
        ];
    }

    public function test_valid_token_displays_transaction_details(): void
    {
        [$transaction, $token] = $this->submitAndGetToken();

        $this->getJson("/api/buyer-confirmations/{$token}")
            ->assertOk()
            ->assertJsonPath(
                'data.transaction_id',
                $transaction->id
            )
            ->assertJsonPath('data.buyer_name', 'Shem Regidor')
            ->assertJsonPath('data.currency', 'PHP');

        $this->assertDatabaseHas('buyer_confirmations', [
            'transaction_id' => $transaction->id,
            'status' => 'pending',
        ]);
    }

    public function test_buyer_can_confirm_transaction(): void
    {
        [$transaction, $token] = $this->submitAndGetToken();

        $this->postJson(
            "/api/buyer-confirmations/{$token}/confirm"
        )->assertOk();

        $this->assertDatabaseHas('transactions', [
            'id' => $transaction->id,
            'status' => 'pending_lgu_verification',
        ]);

        $this->assertDatabaseHas('buyer_confirmations', [
            'transaction_id' => $transaction->id,
            'status' => 'confirmed',
        ]);

        $this->assertDatabaseHas('transaction_status_logs', [
            'transaction_id' => $transaction->id,
            'to_status' => 'pending_lgu_verification',
            'actor_type' => 'buyer',
        ]);
    }

    public function test_confirmation_token_cannot_be_reused(): void
    {
        [, $token] = $this->submitAndGetToken();

        $url = "/api/buyer-confirmations/{$token}/confirm";

        $this->postJson($url)->assertOk();

        $this->postJson($url)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['token']);
    }

    public function test_expired_token_is_rejected(): void
    {
        [$transaction, $token] = $this->submitAndGetToken();

        BuyerConfirmation::where(
            'transaction_id',
            $transaction->id
        )->update([
                    'expires_at' => now()->subMinute(),
                ]);

        $this->postJson(
            "/api/buyer-confirmations/{$token}/confirm"
        )
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['token']);

        $this->assertDatabaseHas('transactions', [
            'id' => $transaction->id,
            'status' => 'pending',
        ]);
    }


    public function test_buyer_can_dispute_transaction(): void
    {
        [$transaction, $token] = $this->submitAndGetToken();

        $this->postJson(
            "/api/buyer-confirmations/{$token}/dispute",
            ['reason' => 'The agreed price is incorrect.']
        )->assertOk();

        $confirmation = BuyerConfirmation::where(
            'transaction_id',
            $transaction->id
        )->firstOrFail();

        // Buyer confirmation must be disputed.
        $this->assertDatabaseHas('buyer_confirmations', [
            'id' => $confirmation->id,
            'transaction_id' => $transaction->id,
            'status' => 'disputed',
            'dispute_reason' => 'The agreed price is incorrect.',
        ]);

        // A formal LGU dispute case must be created.
        $this->assertDatabaseHas('transaction_disputes', [
            'transaction_id' => $transaction->id,
            'buyer_confirmation_id' => $confirmation->id,
            'reported_by' => null,
            'reporter_type' => 'buyer',
            'reason' => 'buyer_disputed_transaction',
            'description' => 'The agreed price is incorrect.',
            'status' => 'open',
        ]);

        $this->assertDatabaseHas('transactions', [
            'id' => $transaction->id,
            'status' => 'pending',
        ]);

        $this->assertDatabaseHas('transaction_status_logs', [
            'transaction_id' => $transaction->id,
            'from_status' => 'pending',
            'to_status' => 'pending',
            'actor_type' => 'buyer',
        ]);
    }


    public function test_invalid_token_is_rejected(): void
    {
        $token = Str::random(64);

        $this->getJson("/api/buyer-confirmations/{$token}")
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['token']);
    }

    public function test_changed_transaction_details_invalidate_token(): void
    {
        [$transaction, $token] = $this->submitAndGetToken();

        $transaction->update([
            'total_selling_price' => 50000,
        ]);

        $this->postJson(
            "/api/buyer-confirmations/{$token}/confirm"
        )
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['transaction']);

        $this->assertDatabaseHas('transactions', [
            'id' => $transaction->id,
            'status' => 'pending',
        ]);
    }

    public function test_buyer_cannot_dispute_twice_using_same_token(): void
    {
        [$transaction, $token] = $this->submitAndGetToken();

        $url = "/api/buyer-confirmations/{$token}/dispute";

        $this->postJson($url, [
            'reason' => 'Incorrect selling price.',
        ])->assertOk();

        $this->postJson($url, [
            'reason' => 'Trying to submit another dispute.',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['token']);

        $this->assertDatabaseCount('transaction_disputes', 1);

        $this->assertDatabaseHas('transaction_disputes', [
            'transaction_id' => $transaction->id,
            'description' => 'Incorrect selling price.',
            'status' => 'open',
        ]);
    }


    public function test_disputed_transaction_cannot_be_confirmed_using_same_token(): void
    {
        [$transaction, $token] = $this->submitAndGetToken();

        $this->postJson(
            "/api/buyer-confirmations/{$token}/dispute",
            ['reason' => 'Incorrect livestock details.']
        )->assertOk();

        $this->postJson(
            "/api/buyer-confirmations/{$token}/confirm"
        )
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['token']);

        $this->assertDatabaseHas('transactions', [
            'id' => $transaction->id,
            'status' => 'pending',
        ]);

        $this->assertDatabaseHas('buyer_confirmations', [
            'transaction_id' => $transaction->id,
            'status' => 'disputed',
        ]);

        $this->assertDatabaseCount('transaction_disputes', 1);
    }

}
