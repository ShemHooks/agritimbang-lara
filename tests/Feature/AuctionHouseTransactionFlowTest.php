<?php

namespace Tests\Feature;

use App\Models\AuctionHouse;
use App\Models\Barangay;
use App\Models\FarmerProfile;
use App\Models\LguOfficerProfile;
use App\Models\LivestockRecord;
use App\Models\Municipality;
use App\Models\PriceReference;
use App\Models\Role;
use App\Models\Species;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Valuation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AuctionHouseTransactionFlowTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(string $roleSlug): User
    {
        $user = User::create([
            'first_name' => 'Test',
            'last_name' => 'User',
            'email' => Str::uuid() . '@example.test',
            'password' => bcrypt('password123'),
            'status' => 'active',
            'verification_status' => 'verified',
        ]);

        $role = Role::firstOrCreate(
            ['slug' => $roleSlug],
            ['name' => ucwords(str_replace('_', ' ', $roleSlug))]
        );

        $user->roles()->attach($role->id);

        return $user;
    }

    private function makeLocation(): array
    {
        $municipality = Municipality::create([
            'name' => 'Municipality ' . Str::random(8),
            'province' => 'Negros Occidental',
        ]);

        $barangay = Barangay::create([
            'municipality_id' => $municipality->id,
            'name' => 'Barangay ' . Str::random(8),
        ]);

        return [$municipality, $barangay];
    }

    private function makeOfficer(Municipality $municipality): User
    {
        $officer = $this->makeUser('lgu_officer');

        LguOfficerProfile::create([
            'user_id' => $officer->id,
            'municipality_id' => $municipality->id,
            'station' => 'Municipal Agriculture Office',
            'designation' => 'Livestock Officer',
        ]);

        return $officer;
    }

    private function makeFixture(): array
    {
        [$municipality, $barangay] = $this->makeLocation();

        $farmerUser = $this->makeUser('farmer');

        $farmer = FarmerProfile::create([
            'user_id' => $farmerUser->id,
            'municipality_id' => $municipality->id,
            'barangay_id' => $barangay->id,
        ]);

        $encoder = $this->makeOfficer($municipality);
        $reviewer = $this->makeOfficer($municipality);

        $species = Species::create([
            'name' => 'Cattle ' . Str::random(8),
            'code' => 'CTL' . strtoupper(Str::random(6)),
            'is_active' => true,
        ]);

        $livestock = LivestockRecord::create([
            'farmer_id' => $farmer->id,
            'species_id' => $species->id,
            'sex' => 'male',
            'age_months' => 24,
            'age_group' => 'young_adult',
            'reproductive_status' => 'not_applicable',
            'sale_purpose' => 'slaughter',
            'condition_score' => 3,
            'actual_weight_kg' => 280,
            'municipality_id' => $municipality->id,
            'barangay_id' => $barangay->id,
            'status' => 'available',
            'created_by' => $farmerUser->id,
        ]);

        PriceReference::create([
            'municipality_id' => $municipality->id,
            'barangay_id' => null,
            'species_id' => $species->id,
            'breed_id' => null,
            'sale_purpose' => 'slaughter',
            'price_per_kg' => 180,
            'effective_from' => now()->subDays(7)->toDateString(),
            'effective_to' => null,
            'source' => 'Test Market Price',
            'status' => 'official',
            'submitted_by' => $reviewer->id,
            'submitted_at' => now()->subDays(2),
            'approved_by' => $reviewer->id,
            'approved_at' => now()->subDay(),
        ]);

        // Generate a real valuation using the existing FPC API.
        Sanctum::actingAs($farmerUser);

        $this->postJson(
            "/api/farmer/livestock/{$livestock->id}/valuations"
        )->assertCreated();

        $valuation = Valuation::query()
            ->where('livestock_id', $livestock->id)
            ->latest()
            ->firstOrFail();

        $house = AuctionHouse::create([
            'name' => 'Test Livestock Auction House',
            'municipality_id' => $municipality->id,
            'barangay_id' => $barangay->id,
            'is_active' => true,
        ]);

        DB::table('auction_house_encoder_assignments')->insert([
            'id' => (string) Str::uuid(),
            'auction_house_id' => $house->id,
            'user_id' => $encoder->id,
            'assigned_by' => $reviewer->id,
            'is_active' => true,
            'created_at' => now()->subMinute(),
            'updated_at' => now()->subMinute(),
        ]);

        return compact(
            'municipality',
            'barangay',
            'farmerUser',
            'farmer',
            'encoder',
            'reviewer',
            'livestock',
            'valuation',
            'house'
        );
    }

    private function salePayload(array $data): array
    {
        return [
            'auction_house_id' => $data['house']->id,
            'farmer_id' => $data['farmer']->id,
            'buyer_name' => 'Juan Dela Cruz',
            'buyer_phone' => '09123456789',
            'transaction_date' => now()->toDateString(),
            'acknowledgement_reference' => 'ACK-' . Str::random(10),
            'items' => [
                [
                    'livestock_id' => $data['livestock']->id,
                    'valuation_id' => $data['valuation']->id,
                    'actual_selling_price' => 49000,
                ],
            ],
        ];
    }

    private function recordSale(array $data): Transaction
    {
        Sanctum::actingAs($data['encoder']);

        $this->postJson(
            '/api/auction-house/transactions',
            $this->salePayload($data)
        )->assertCreated();

        return Transaction::query()
            ->where('source', 'auction_house')
            ->firstOrFail();
    }

    public function test_assigned_encoder_can_record_sale(): void
    {
        $data = $this->makeFixture();
        $transaction = $this->recordSale($data);

        $this->assertSame(
            'pending_lgu_verification',
            $transaction->status
        );

        $this->assertSame('auction_house', $transaction->source);
        $this->assertSame($data['encoder']->id, $transaction->encoded_by);
        $this->assertSame($data['farmer']->id, $transaction->farmer_id);
        $this->assertSame(1, $transaction->items()->count());
    }

    public function test_recording_sale_reserves_livestock(): void
    {
        $data = $this->makeFixture();

        $this->recordSale($data);

        $this->assertDatabaseHas('livestock_records', [
            'id' => $data['livestock']->id,
            'status' => 'in_transaction',
        ]);
    }

    public function test_different_officer_can_approve_sale(): void
    {
        $data = $this->makeFixture();
        $transaction = $this->recordSale($data);

        Sanctum::actingAs($data['reviewer']);

        $this->postJson(
            "/api/lgu/transactions/{$transaction->id}/approve"
        )->assertOk()
            ->assertJsonPath('data.status', 'verified');

        $this->assertDatabaseHas('livestock_records', [
            'id' => $data['livestock']->id,
            'status' => 'sold',
        ]);

        $this->assertDatabaseHas('transactions', [
            'id' => $transaction->id,
            'status' => 'verified',
            'verified_by' => $data['reviewer']->id,
        ]);
    }

    public function test_different_officer_can_reject_sale(): void
    {
        $data = $this->makeFixture();
        $transaction = $this->recordSale($data);

        Sanctum::actingAs($data['reviewer']);

        $this->postJson(
            "/api/lgu/transactions/{$transaction->id}/reject",
            [
                'reason' => 'Sale details do not match physical records.',
            ]
        )->assertOk()
            ->assertJsonPath('data.status', 'rejected');

        $this->assertDatabaseHas('livestock_records', [
            'id' => $data['livestock']->id,
            'status' => 'available',
        ]);
    }

    public function test_encoder_cannot_approve_own_sale(): void
    {
        $data = $this->makeFixture();
        $transaction = $this->recordSale($data);

        Sanctum::actingAs($data['encoder']);

        $this->postJson(
            "/api/lgu/transactions/{$transaction->id}/approve"
        )->assertForbidden();

        $this->assertSame(
            'pending_lgu_verification',
            $transaction->fresh()->status
        );
    }

    public function test_unassigned_officer_cannot_record_sale(): void
    {
        $data = $this->makeFixture();

        $unassigned = $this->makeOfficer($data['municipality']);

        Sanctum::actingAs($unassigned);

        $this->postJson(
            '/api/auction-house/transactions',
            $this->salePayload($data)
        )->assertForbidden();

        $this->assertDatabaseCount('transactions', 0);
    }

    public function test_sale_remains_reviewable_after_assignment_removed(): void
    {
        $data = $this->makeFixture();
        $transaction = $this->recordSale($data);

        DB::table('auction_house_encoder_assignments')
            ->where('auction_house_id', $data['house']->id)
            ->where('user_id', $data['encoder']->id)
            ->update([
                'is_active' => false,
                'updated_at' => now(),
            ]);

        Sanctum::actingAs($data['reviewer']);

        $this->postJson(
            "/api/lgu/transactions/{$transaction->id}/approve"
        )->assertOk();

        $this->assertSame('verified', $transaction->fresh()->status);
    }

    public function test_duplicate_sale_is_rejected(): void
    {
        $data = $this->makeFixture();

        $this->recordSale($data);

        Sanctum::actingAs($data['encoder']);

        $this->postJson(
            '/api/auction-house/transactions',
            $this->salePayload($data)
        )->assertUnprocessable();

        $this->assertDatabaseCount('transactions', 1);

        $this->assertDatabaseHas('livestock_records', [
            'id' => $data['livestock']->id,
            'status' => 'in_transaction',
        ]);
    }
}
