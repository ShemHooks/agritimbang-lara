<?php

namespace Tests\Feature;

use App\Models\AuctionHouse;
use App\Models\Barangay;
use App\Models\LguOfficerProfile;
use App\Models\Municipality;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class LguAuctionHouseTest extends TestCase
{
    use RefreshDatabase;

    private function municipality(): Municipality
    {
        return Municipality::create([
            'name' => 'Municipality ' . uniqid(),
            'province' => 'Negros Occidental',
        ]);
    }

    private function officer(Municipality $municipality): User
    {
        $user = User::factory()->create([
            'status' => 'active',
            'verification_status' => 'verified',
        ]);

        $role = Role::firstOrCreate(
            ['slug' => 'lgu_officer'],
            ['name' => 'LGU Officer']
        );

        $user->roles()->syncWithoutDetaching([$role->id]);

        LguOfficerProfile::create([
            'user_id' => $user->id,
            'municipality_id' => $municipality->id,
        ]);

        return $user;
    }

    private function house(
        Municipality $municipality,
        bool $active = true
    ): AuctionHouse {
        return AuctionHouse::create([
            'name' => 'Auction House ' . uniqid(),
            'municipality_id' => $municipality->id,
            'is_active' => $active,
        ]);
    }

    private function authenticate(User $user): void
    {
        Sanctum::actingAs($user);
    }

    private function assignUrl(AuctionHouse $house): string
    {
        return "/api/lgu/auction-houses/{$house->id}/encoders";
    }

    public function test_lgu_can_create_auction_house(): void
    {
        $municipality = $this->municipality();
        $officer = $this->officer($municipality);
        $this->authenticate($officer);

        $this->postJson('/api/lgu/auction-houses', [
            'name' => 'Kabankalan Livestock Auction',
            'address' => 'Public Market',
        ])->assertCreated()
            ->assertJsonPath(
                'data.municipality_id',
                $municipality->id
            );

        $this->assertDatabaseHas('auction_houses', [
            'name' => 'Kabankalan Livestock Auction',
            'municipality_id' => $municipality->id,
        ]);
    }

    public function test_lgu_only_lists_own_municipality(): void
    {
        $own = $this->municipality();
        $other = $this->municipality();

        $officer = $this->officer($own);
        $ownHouse = $this->house($own);
        $otherHouse = $this->house($other);

        $this->authenticate($officer);

        $this->getJson('/api/lgu/auction-houses')
            ->assertOk()
            ->assertJsonFragment(['id' => $ownHouse->id])
            ->assertDontSee($otherHouse->id);
    }

    public function test_lgu_can_update_own_auction_house(): void
    {
        $municipality = $this->municipality();
        $officer = $this->officer($municipality);
        $house = $this->house($municipality);

        $this->authenticate($officer);

        $this->patchJson(
            "/api/lgu/auction-houses/{$house->id}",
            ['name' => 'Updated Auction House']
        )->assertOk();

        $this->assertDatabaseHas('auction_houses', [
            'id' => $house->id,
            'name' => 'Updated Auction House',
        ]);
    }

    public function test_lgu_can_deactivate_auction_house(): void
    {
        $municipality = $this->municipality();
        $officer = $this->officer($municipality);
        $house = $this->house($municipality);

        $this->authenticate($officer);

        $this->patchJson(
            "/api/lgu/auction-houses/{$house->id}/status",
            ['is_active' => false]
        )->assertOk();

        $this->assertFalse($house->fresh()->is_active);
    }

    public function test_lgu_can_assign_verified_encoder(): void
    {
        $municipality = $this->municipality();
        $officer = $this->officer($municipality);
        $encoder = $this->officer($municipality);
        $house = $this->house($municipality);

        $this->authenticate($officer);

        $this->postJson($this->assignUrl($house), [
            'user_id' => $encoder->id,
        ])->assertOk();

        $this->assertDatabaseHas(
            'auction_house_encoder_assignments',
            [
                'auction_house_id' => $house->id,
                'user_id' => $encoder->id,
                'assigned_by' => $officer->id,
                'is_active' => true,
            ]
        );
    }

    public function test_lgu_can_remove_encoder(): void
    {
        $municipality = $this->municipality();
        $officer = $this->officer($municipality);
        $encoder = $this->officer($municipality);
        $house = $this->house($municipality);

        $this->authenticate($officer);

        $this->postJson($this->assignUrl($house), [
            'user_id' => $encoder->id,
        ])->assertOk();

        $this->deleteJson(
            $this->assignUrl($house) . "/{$encoder->id}"
        )->assertOk();

        $this->assertDatabaseHas(
            'auction_house_encoder_assignments',
            [
                'auction_house_id' => $house->id,
                'user_id' => $encoder->id,
                'is_active' => false,
            ]
        );
    }

    public function test_lgu_cannot_access_other_municipality_house(): void
    {
        $own = $this->municipality();
        $other = $this->municipality();

        $officer = $this->officer($own);
        $house = $this->house($other);

        $this->authenticate($officer);

        $this->patchJson(
            "/api/lgu/auction-houses/{$house->id}",
            ['name' => 'Unauthorized Change']
        )->assertNotFound();
    }

    public function test_lgu_cannot_assign_cross_municipality_encoder(): void
    {
        $own = $this->municipality();
        $other = $this->municipality();

        $officer = $this->officer($own);
        $encoder = $this->officer($other);
        $house = $this->house($own);

        $this->authenticate($officer);

        $this->postJson($this->assignUrl($house), [
            'user_id' => $encoder->id,
        ])->assertUnprocessable();
    }

    public function test_lgu_cannot_use_barangay_from_other_municipality(): void
    {
        $own = $this->municipality();
        $other = $this->municipality();

        $officer = $this->officer($own);

        $barangay = Barangay::create([
            'name' => 'Barangay ' . uniqid(),
            'municipality_id' => $other->id,
        ]);

        $this->authenticate($officer);

        $this->postJson('/api/lgu/auction-houses', [
            'name' => 'Invalid Auction House',
            'barangay_id' => $barangay->id,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('barangay_id');
    }

    public function test_farmer_cannot_create_auction_house(): void
    {
        $farmer = User::factory()->create([
            'status' => 'active',
            'verification_status' => 'verified',
        ]);

        $this->authenticate($farmer);

        $this->postJson('/api/lgu/auction-houses', [
            'name' => 'Unauthorized Auction House',
        ])->assertForbidden();
    }

    public function test_cannot_assign_encoder_to_inactive_house(): void
    {
        $municipality = $this->municipality();
        $officer = $this->officer($municipality);
        $encoder = $this->officer($municipality);
        $house = $this->house($municipality, false);

        $this->authenticate($officer);

        $this->postJson($this->assignUrl($house), [
            'user_id' => $encoder->id,
        ])->assertUnprocessable();
    }

    public function test_existing_encoder_assignment_can_be_reactivated(): void
    {
        $municipality = $this->municipality();
        $officer = $this->officer($municipality);
        $encoder = $this->officer($municipality);
        $house = $this->house($municipality);

        $this->authenticate($officer);

        $this->postJson($this->assignUrl($house), [
            'user_id' => $encoder->id,
        ])->assertOk();

        $this->deleteJson(
            $this->assignUrl($house) . "/{$encoder->id}"
        )->assertOk();

        $this->postJson($this->assignUrl($house), [
            'user_id' => $encoder->id,
        ])->assertOk();

        $this->assertDatabaseHas(
            'auction_house_encoder_assignments',
            [
                'auction_house_id' => $house->id,
                'user_id' => $encoder->id,
                'is_active' => true,
            ]
        );

        $this->assertSame(
            1,
            DB::table('auction_house_encoder_assignments')
                ->where('auction_house_id', $house->id)
                ->where('user_id', $encoder->id)
                ->count()
        );
    }
}
