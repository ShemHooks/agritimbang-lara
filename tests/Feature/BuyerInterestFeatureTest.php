<?php

namespace Tests\Feature;

use App\Models\AuctionHouse;
use App\Models\AuctionRegistration;
use App\Models\AuctionSchedule;
use App\Models\Barangay;
use App\Models\Buyer;
use App\Models\BuyerInterest;
use App\Models\FarmerProfile;
use App\Models\LivestockRecord;
use App\Models\Municipality;
use App\Models\Role;
use App\Models\Species;
use App\Models\User;
use App\Notifications\BuyerInterestReceived;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Tests\TestCase;

class BuyerInterestFeatureTest extends TestCase
{
    use RefreshDatabase;

    private function createMunicipality(): Municipality
    {
        return Municipality::create([
            'name' => 'Kabankalan City',
            'province' => 'Negros Occidental',
        ]);
    }

    private function createFarmer(Municipality $municipality): User
    {
        $barangay = Barangay::create([
            'municipality_id' => $municipality->id,
            'name' => 'Barangay 1',
        ]);

        $user = User::create([
            'first_name' => 'Farmer',
            'last_name' => 'Sample',
            'email' => Str::uuid() . '@example.test',
            'password' => bcrypt('password'),
            'status' => 'active',
        ]);

        $role = Role::firstOrCreate(['slug' => 'farmer'], ['name' => 'Farmer']);
        $user->roles()->attach($role->id);

        FarmerProfile::create([
            'user_id' => $user->id,
            'municipality_id' => $municipality->id,
            'barangay_id' => $barangay->id,
            'farmer_code' => 'F-' . Str::random(10),
        ]);

        return $user->fresh();
    }

    private function createSchedule(Municipality $municipality): AuctionSchedule
    {
        $barangay = Barangay::create([
            'municipality_id' => $municipality->id,
            'name' => 'Barangay 2',
        ]);

        $user = User::create([
            'first_name' => 'Encoder',
            'last_name' => 'Test',
            'email' => Str::uuid() . '@example.test',
            'password' => bcrypt('password'),
            'status' => 'active',
        ]);

        $house = AuctionHouse::create([
            'name' => 'Municipal Auction House',
            'municipality_id' => $municipality->id,
            'barangay_id' => $barangay->id,
            'address' => 'Test address',
            'is_active' => true,
        ]);

        return AuctionSchedule::create([
            'municipality_id' => $municipality->id,
            'auction_house_id' => $house->id,
            'barangay_id' => $barangay->id,
            'created_by' => $user->id,
            'title' => 'Municipal Livestock Auction',
            'auction_date' => now()->addDays(2)->toDateString(),
            'start_time' => '08:00:00',
            'expected_end_time' => '12:00:00',
            'venue' => 'Municipal Coliseum',
            'registration_opens_at' => now()->subDay(),
            'registration_closes_at' => now()->addDays(3),
            'description' => 'Test auction',
            'status' => 'published',
            'published_at' => now(),
        ]);
    }

    private function createLivestock(User $farmer, Municipality $municipality, Barangay $barangay, ?AuctionSchedule $schedule = null): LivestockRecord
    {
        $species = Species::create([
            'name' => 'Cattle',
            'code' => 'CATTLE',
        ]);

        $livestock = LivestockRecord::create([
            'farmer_id' => $farmer->farmerProfile->id,
            'species_id' => $species->id,
            'sex' => 'male',
            'age_months' => 18,
            'municipality_id' => $municipality->id,
            'barangay_id' => $barangay->id,
            'status' => 'available',
            'created_by' => $farmer->id,
            'photo_path' => 'livestock/default.png',
        ]);

        if ($schedule) {
            AuctionRegistration::create([
                'auction_schedule_id' => $schedule->id,
                'livestock_id' => $livestock->id,
                'farmer_id' => $farmer->farmerProfile->id,
                'registered_by' => $farmer->id,
                'active_livestock_id' => $livestock->id,
                'status' => 'registered',
                'registered_at' => now(),
            ]);
        }

        return $livestock;
    }

    public function test_public_for_sale_page_lists_upcoming_livestock_by_municipality(): void
    {
        $firstMunicipality = $this->createMunicipality();
        $firstSchedule = $this->createSchedule($firstMunicipality);
        $firstSchedule->update(['title' => 'Kabankalan Auction']);
        $firstFarmer = $this->createFarmer($firstMunicipality);
        $this->createLivestock(
            $firstFarmer,
            $firstMunicipality,
            $firstFarmer->farmerProfile->barangay,
            $firstSchedule
        );

        $secondMunicipality = Municipality::create([
            'name' => 'Himamaylan City',
            'province' => 'Negros Occidental',
        ]);
        $secondSchedule = $this->createSchedule($secondMunicipality);
        $secondSchedule->update(['title' => 'Himamaylan Auction']);
        $secondFarmer = $this->createFarmer($secondMunicipality);
        $this->createLivestock(
            $secondFarmer,
            $secondMunicipality,
            $secondFarmer->farmerProfile->barangay,
            $secondSchedule
        );

        $this->get(route('forsale'))
            ->assertOk()
            ->assertSee('Kabankalan City')
            ->assertSee('Himamaylan City')
            ->assertSee('Kabankalan Auction')
            ->assertSee('Himamaylan Auction')
            ->assertSee(route('buyer-interest.index', ['schedule' => $firstSchedule->id]), false)
            ->assertSee(route('buyer-interest.index', ['schedule' => $secondSchedule->id]), false);

        $this->getJson('/api/buyer-interest/auction/' . $firstSchedule->id)
            ->assertOk()
            ->assertJsonCount(1, 'data.livestock')
            ->assertJsonPath('data.schedule.municipality', 'Kabankalan City');
    }

    public function test_new_buyer_creation_and_pdf_endpoint(): void
    {
        $municipality = $this->createMunicipality();
        $schedule = $this->createSchedule($municipality);
        $farmer = $this->createFarmer($municipality);
        $barangay = $farmer->farmerProfile->barangay;
        $livestockOne = $this->createLivestock($farmer, $municipality, $barangay, $schedule);
        $livestockTwo = $this->createLivestock($farmer, $municipality, $barangay, $schedule);
        Notification::fake();

        $response = $this->postJson('/api/buyer-interest/auction/' . $schedule->id . '/create', [
            'first_name' => 'Alice',
            'last_name' => 'Buyer',
            'mobile_number' => '0912-345-6789',
            'buyer_type' => 'Individual',
            'livestock_ids' => [$livestockOne->id, $livestockTwo->id],
            'interest_status' => 'Active',
            'attendance_intention' => 'Yes — Will attend',
            'contact_consent' => true,
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.buyer.name', 'Alice Buyer')
            ->assertJsonPath('data.buyer.account_code', fn ($value) => preg_match('/^BUY-[A-Z0-9]{6}$/', (string) $value) === 1);

        $this->assertDatabaseHas('buyers', ['first_name' => 'Alice', 'last_name' => 'Buyer']);
        $this->assertDatabaseHas('buyer_interests', ['buyer_id' => Buyer::first()->id, 'status' => 'active']);
        Notification::assertSentTo($farmer, BuyerInterestReceived::class, function (BuyerInterestReceived $notification, array $channels) use ($farmer): bool {
            $message = $notification->toArray($farmer)['message'];

            return $channels === ['database']
                && str_contains($message, '(A.B.)')
                && ! str_contains($message, 'Alice Buyer');
        });

        $buyer = Buyer::first();

        $this->get('/api/buyer-interest/auction/' . $schedule->id . '/buyers/' . $buyer->id . '/pdf')
            ->assertOk();
    }

    public function test_rejects_livestock_that_do_not_belong_to_the_auction(): void
    {
        $municipality = $this->createMunicipality();
        $schedule = $this->createSchedule($municipality);
        $farmer = $this->createFarmer($municipality);
        $barangay = $farmer->farmerProfile->barangay;
        $otherLivestock = $this->createLivestock($farmer, $municipality, $barangay);

        $this->postJson('/api/buyer-interest/auction/' . $schedule->id . '/create', [
            'first_name' => 'Bob',
            'last_name' => 'Buyer',
            'mobile_number' => '0922-333-4444',
            'buyer_type' => 'Business',
            'livestock_ids' => [$otherLivestock->id],
            'interest_status' => 'Active',
            'attendance_intention' => 'Tentative',
            'contact_consent' => true,
        ])->assertStatus(422);
    }

    public function test_existing_buyer_update_adds_only_new_items_and_reactivates_withdrawn(): void
    {
        $municipality = $this->createMunicipality();
        $schedule = $this->createSchedule($municipality);
        $farmer = $this->createFarmer($municipality);
        $barangay = $farmer->farmerProfile->barangay;
        $livestockOne = $this->createLivestock($farmer, $municipality, $barangay, $schedule);
        $livestockTwo = $this->createLivestock($farmer, $municipality, $barangay, $schedule);
        $livestockThree = $this->createLivestock($farmer, $municipality, $barangay, $schedule);

        $buyer = Buyer::create([
            'first_name' => 'Cora',
            'last_name' => 'Buyer',
            'mobile_number' => '0932-111-2222',
            'buyer_type' => 'individual',
            'account_code' => 'BUY-AAAAAA',
            'auction_id' => $schedule->id,
            'municipality_id' => $municipality->id,
            'attendance_intention' => 'yes',
            'contact_consent' => true,
            'status' => 'temporary',
            'is_temporary' => true,
            'expires_at' => now()->addDays(1),
        ]);

        BuyerInterest::create(['buyer_id' => $buyer->id, 'livestock_id' => $livestockOne->id, 'status' => 'withdrawn']);
        BuyerInterest::create(['buyer_id' => $buyer->id, 'livestock_id' => $livestockTwo->id, 'status' => 'active']);
        Notification::fake();

        $this->postJson('/api/buyer-interest/auction/' . $schedule->id . '/update', [
            'account_code' => 'BUY-AAAAAA',
            'livestock_ids' => [$livestockOne->id, $livestockThree->id],
        ])->assertOk()->assertJsonPath('data.added_count', 2);

        $this->assertDatabaseHas('buyer_interests', ['buyer_id' => $buyer->id, 'livestock_id' => $livestockOne->id, 'status' => 'active']);
        $this->assertDatabaseHas('buyer_interests', ['buyer_id' => $buyer->id, 'livestock_id' => $livestockThree->id, 'status' => 'active']);
        $this->assertDatabaseCount('buyer_interests', 3);
        Notification::assertSentTo($farmer, BuyerInterestReceived::class, function (BuyerInterestReceived $notification, array $channels) use ($farmer): bool {
            return $channels === ['database']
                && str_contains($notification->toArray($farmer)['message'], '(C.B.)');
        });
    }

    public function test_login_success_not_found_wildcard_and_expired(): void
    {
        $municipality = $this->createMunicipality();
        $schedule = $this->createSchedule($municipality);
        $farmer = $this->createFarmer($municipality);
        $barangay = $farmer->farmerProfile->barangay;
        $livestock = $this->createLivestock($farmer, $municipality, $barangay, $schedule);

        $buyer = Buyer::create([
            'first_name' => 'Login',
            'last_name' => 'User',
            'mobile_number' => '0944-555-6666',
            'buyer_type' => 'business',
            'account_code' => 'BUY-ABC123',
            'auction_id' => $schedule->id,
            'municipality_id' => $municipality->id,
            'attendance_intention' => 'no',
            'contact_consent' => true,
            'status' => 'temporary',
            'is_temporary' => true,
            'expires_at' => now()->addDays(3),
        ]);

        BuyerInterest::create(['buyer_id' => $buyer->id, 'livestock_id' => $livestock->id, 'status' => 'active']);

        $this->postJson('/api/buyer-interest/auction/' . $schedule->id . '/login', ['account_code' => 'BUY-ABC123'])
            ->assertOk()
            ->assertJsonPath('data.buyer.account_code', 'BUY-ABC123');

        $this->postJson('/api/buyer-interest/auction/' . $schedule->id . '/login', ['account_code' => 'BUY-NOTFOUND'])
            ->assertStatus(404)
            ->assertJsonPath('message', 'Account code not found for this auction event. Please check your code or create a new interest entry.');

        $otherSchedule = $this->createSchedule($municipality);
        $this->postJson('/api/buyer-interest/auction/' . $otherSchedule->id . '/login', ['account_code' => 'BUY-ABC123'])
            ->assertStatus(404)
            ->assertJsonPath('message', 'This account belongs to a different auction event. Please create a new entry for this event.');

        $expiredBuyer = Buyer::create([
            'first_name' => 'Expired',
            'last_name' => 'Buyer',
            'mobile_number' => '0977-888-9999',
            'buyer_type' => 'individual',
            'account_code' => 'BUY-EXPIRED',
            'auction_id' => $schedule->id,
            'municipality_id' => $municipality->id,
            'attendance_intention' => 'yes',
            'contact_consent' => true,
            'status' => 'temporary',
            'is_temporary' => true,
            'expires_at' => now()->subDay(),
        ]);

        $this->postJson('/api/buyer-interest/auction/' . $schedule->id . '/login', ['account_code' => 'BUY-EXPIRED'])
            ->assertOk()
            ->assertJsonPath('data.buyer.is_expired', true)
            ->assertJsonPath('data.buyer.can_update', false);
    }

    public function test_expiry_boundary_at_expires_at(): void
    {
        $municipality = $this->createMunicipality();
        $schedule = $this->createSchedule($municipality);
        $buyer = Buyer::create([
            'first_name' => 'Edge',
            'last_name' => 'Buyer',
            'mobile_number' => '0966-123-4567',
            'buyer_type' => 'business',
            'account_code' => 'BUY-EDGE01',
            'auction_id' => $schedule->id,
            'municipality_id' => $municipality->id,
            'attendance_intention' => 'tentative',
            'contact_consent' => true,
            'status' => 'temporary',
            'is_temporary' => true,
            'expires_at' => now('Asia/Manila')->endOfDay(),
        ]);

        $this->assertTrue($buyer->isExpired() === now('Asia/Manila')->greaterThanOrEqualTo($buyer->expires_at));

        $this->postJson('/api/buyer-interest/auction/' . $schedule->id . '/login', ['account_code' => 'BUY-EDGE01'])
            ->assertOk();
    }

    public function test_account_code_format_and_uniqueness(): void
    {
        $schedule = $this->createSchedule($this->createMunicipality());

        $first = Buyer::create([
            'first_name' => 'One',
            'last_name' => 'Buyer',
            'mobile_number' => '0912-000-0000',
            'buyer_type' => 'individual',
            'account_code' => 'BUY-A1B2C3',
            'auction_id' => $schedule->id,
            'municipality_id' => $schedule->municipality_id,
            'attendance_intention' => 'yes',
            'contact_consent' => true,
            'status' => 'temporary',
            'is_temporary' => true,
            'expires_at' => now()->addDays(2),
        ]);

        $second = Buyer::create([
            'first_name' => 'Two',
            'last_name' => 'Buyer',
            'mobile_number' => '0912-010-0101',
            'buyer_type' => 'individual',
            'account_code' => 'BUY-A1B2C4',
            'auction_id' => $schedule->id,
            'municipality_id' => $schedule->municipality_id,
            'attendance_intention' => 'yes',
            'contact_consent' => true,
            'status' => 'temporary',
            'is_temporary' => true,
            'expires_at' => now()->addDays(2),
        ]);

        $this->assertMatchesRegularExpression('/^BUY-[A-Z0-9]{6}$/', $first->account_code);
        $this->assertMatchesRegularExpression('/^BUY-[A-Z0-9]{6}$/', $second->account_code);
        $this->assertNotSame($first->account_code, $second->account_code);
    }

    public function test_login_requests_are_rate_limited(): void
    {
        $municipality = $this->createMunicipality();
        $schedule = $this->createSchedule($municipality);
        Buyer::create([
            'first_name' => 'Rate',
            'last_name' => 'Limit',
            'mobile_number' => '0955-111-2222',
            'buyer_type' => 'individual',
            'account_code' => 'BUY-RATE01',
            'auction_id' => $schedule->id,
            'municipality_id' => $municipality->id,
            'attendance_intention' => 'yes',
            'contact_consent' => true,
            'status' => 'temporary',
            'is_temporary' => true,
            'expires_at' => now()->addDays(1),
        ]);

        for ($i = 0; $i < 10; $i++) {
            $this->postJson('/api/buyer-interest/auction/' . $schedule->id . '/login', ['account_code' => 'BUY-RATE01']);
        }

        $this->postJson('/api/buyer-interest/auction/' . $schedule->id . '/login', ['account_code' => 'BUY-RATE01'])
            ->assertStatus(429);
    }
}
