<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class TransactionApiTest extends TestCase
{
    use RefreshDatabase;

    private function createFarmer(
        string $verification = 'verified'
    ): User {
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

        return $user;
    }

    public function test_unauthenticated_user_cannot_create_transaction(): void
    {
        $this->postJson('/api/farmer/transactions', [
            'buyer_name' => 'Juan Dela Cruz',
            'transaction_date' => now()->toDateTimeString(),
            'items' => [],
        ])->assertUnauthorized();
    }

    public function test_authenticated_farmer_cannot_submit_empty_items(): void
    {
        $farmer = $this->createFarmer();

        $this->actingAs($farmer)
            ->postJson('/api/farmer/transactions', [
                'buyer_name' => 'Juan Dela Cruz',
                'transaction_date' => now()->toDateTimeString(),
                'items' => [],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['items']);
    }

    public function test_authenticated_farmer_cannot_submit_missing_buyer_name(): void
    {
        $farmer = $this->createFarmer();

        $this->actingAs($farmer)
            ->postJson('/api/farmer/transactions', [
                'transaction_date' => now()->toDateTimeString(),
                'items' => [],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['buyer_name']);
    }

    public function test_authenticated_farmer_cannot_submit_invalid_transaction_date(): void
    {
        $farmer = $this->createFarmer();

        $this->actingAs($farmer)
            ->postJson('/api/farmer/transactions', [
                'buyer_name' => 'Juan Dela Cruz',
                'transaction_date' => 'invalid-date',
                'items' => [],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['transaction_date']);
    }

    public function test_authenticated_farmer_cannot_submit_duplicate_livestock(): void
    {
        $farmer = $this->createFarmer();

        $livestockId = (string) Str::uuid();

        $this->actingAs($farmer)
            ->postJson('/api/farmer/transactions', [
                'buyer_name' => 'Juan Dela Cruz',
                'transaction_date' => now()->toDateTimeString(),
                'items' => [
                    [
                        'livestock_id' => $livestockId,
                        'valuation_id' => (string) Str::uuid(),
                        'actual_selling_price' => 48000,
                    ],
                    [
                        'livestock_id' => $livestockId,
                        'valuation_id' => (string) Str::uuid(),
                        'actual_selling_price' => 50000,
                    ],
                ],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'items.0.livestock_id',
                'items.1.livestock_id',
            ]);
    }
}
