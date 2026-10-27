<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class WebAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_can_see_login_screen(): void
    {
        $this->get('/login')->assertOk()->assertSee('Sign in to your account');
    }

    public function test_guest_cannot_open_dashboard(): void
    {
        $this->get('/dashboard')->assertRedirect('/login');
    }

    public function test_valid_credentials_create_web_session(): void
    {
        $user = User::factory()->create(['password' => Hash::make('secret-pass-123')]);
        $this->post('/login', ['identity' => $user->email, 'password' => 'secret-pass-123'])->assertRedirect('/dashboard');
        $this->assertAuthenticatedAs($user, 'web');
    }

    public function test_invalid_credentials_do_not_authenticate(): void
    {
        $user = User::factory()->create(['password' => Hash::make('secret-pass-123')]);
        $this->from('/login')->post('/login', ['identity' => $user->email, 'password' => 'wrong'])->assertRedirect('/login')->assertSessionHasErrors('identity');
        $this->assertGuest('web');
    }

    public function test_logout_invalidates_web_session(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user, 'web')->post('/logout')->assertRedirect('/login');
        $this->assertGuest('web');
    }
}
