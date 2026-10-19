<?php

namespace Tests\Feature;

use App\Jobs\SendBuyerConfirmationSms;
use App\Models\Barangay;
use App\Models\FarmerProfile;
use App\Models\LivestockRecord;
use App\Models\Municipality;
use App\Models\Role;
use App\Models\Species;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Str;
use Tests\TestCase;
use App\Models\Valuation;
use Illuminate\Support\Facades\Hash;

class TransactionSubmissionTest extends TestCase
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

    public function test_unverified_farmer_cannot_submit(): void
    {
        $farmer = $this->createFarmer('unverified');

        $transaction = $this->createDraft($farmer);

        $this->actingAs($farmer)
            ->postJson(
                "/api/farmer/transactions/{$transaction->id}/submit"
            )
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['farmer']);

        Bus::assertNotDispatched(SendBuyerConfirmationSms::class);
    }

    public function test_transaction_without_buyer_phone_cannot_be_submitted(): void
    {
        $farmer = $this->createFarmer();

        $transaction = $this->createDraft($farmer, null);

        $this->actingAs($farmer)
            ->postJson(
                "/api/farmer/transactions/{$transaction->id}/submit"
            )
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['buyer_phone']);

        $this->assertDatabaseHas('transactions', [
            'id' => $transaction->id,
            'status' => 'draft',
        ]);

        Bus::assertNotDispatched(SendBuyerConfirmationSms::class);
    }

    public function test_another_farmer_cannot_submit_transaction(): void
    {
        $owner = $this->createFarmer();

        $otherFarmer = $this->createFarmer();

        $transaction = $this->createDraft($owner);

        $this->actingAs($otherFarmer)
            ->postJson(
                "/api/farmer/transactions/{$transaction->id}/submit"
            )
            ->assertForbidden();

        Bus::assertNotDispatched(SendBuyerConfirmationSms::class);
    }


    public function test_verified_farmer_can_submit_transaction(): void
    {
        $farmer = $this->createFarmer();

        $transaction = $this->createDraft($farmer);

        $livestockId = $transaction->items()
            ->firstOrFail()
            ->livestock_id;

        $this->actingAs($farmer)
            ->postJson(
                "/api/farmer/transactions/{$transaction->id}/submit"
            )
            ->assertOk()
            ->assertJsonPath('data.status', 'pending');

        $this->assertDatabaseHas('transactions', [
            'id' => $transaction->id,
            'status' => 'pending',
        ]);

        $this->assertDatabaseHas('livestock_records', [
            'id' => $livestockId,
            'status' => 'in_transaction',
        ]);

        $this->assertDatabaseHas('buyer_confirmations', [
            'transaction_id' => $transaction->id,
            'buyer_phone' => '09123456789',
            'status' => 'pending',
        ]);

        $this->assertDatabaseHas('transaction_status_logs', [
            'transaction_id' => $transaction->id,
            'from_status' => 'draft',
            'to_status' => 'pending',
            'actor_type' => 'farmer',
        ]);

        Bus::assertDispatched(
            SendBuyerConfirmationSms::class,
            1
        );
    }


    public function test_confirmation_token_is_stored_as_hash(): void
    {
        $farmer = $this->createFarmer();
        $transaction = $this->createDraft($farmer);

        $this->actingAs($farmer)
            ->postJson(
                "/api/farmer/transactions/{$transaction->id}/submit"
            )
            ->assertOk();

        $confirmation = $transaction
            ->buyerConfirmations()
            ->firstOrFail();

        $this->assertSame(
            64,
            strlen($confirmation->token_hash)
        );

        $this->assertNotEmpty(
            $confirmation->transaction_snapshot_hash
        );

        Bus::assertDispatched(
            SendBuyerConfirmationSms::class,
            function ($job) use ($confirmation, $transaction) {
                return $job->transactionId === $transaction->id
                    && hash('sha256', $job->token)
                    === $confirmation->token_hash;
            }
        );
    }


    public function test_transaction_cannot_be_submitted_twice(): void
    {
        $farmer = $this->createFarmer();
        $transaction = $this->createDraft($farmer);

        $url = "/api/farmer/transactions/{$transaction->id}/submit";

        $this->actingAs($farmer)
            ->postJson($url)
            ->assertOk();

        $this->actingAs($farmer)
            ->postJson($url)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['status']);

        $this->assertSame(
            1,
            $transaction->buyerConfirmations()->count()
        );

        Bus::assertDispatched(
            SendBuyerConfirmationSms::class,
            1
        );
    }

    public function test_unavailable_livestock_cannot_be_submitted(): void
    {
        $farmer = $this->createFarmer();
        $transaction = $this->createDraft($farmer);

        $livestock = $transaction->items()
            ->firstOrFail()
            ->livestock;

        $livestock->update([
            'status' => 'sold',
        ]);

        $this->actingAs($farmer)
            ->postJson(
                "/api/farmer/transactions/{$transaction->id}/submit"
            )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'items.0.livestock_id',
            ]);

        $this->assertDatabaseHas('transactions', [
            'id' => $transaction->id,
            'status' => 'draft',
        ]);

        $this->assertSame(
            0,
            $transaction->buyerConfirmations()->count()
        );

        Bus::assertNotDispatched(
            SendBuyerConfirmationSms::class
        );
    }

}
