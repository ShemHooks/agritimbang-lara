<?php

namespace Tests\Feature;

use App\Models\OtpChallenge;
use App\Notifications\OtpCodeNotification;
use App\Notifications\SystemAlertNotification;
use App\Services\NotificationService;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\Feature\AnalyticsTestCase;

class NotificationOtpTest extends AnalyticsTestCase
{
    public function test_guest_cannot_send_otp(): void
    {
        $this->postJson('/api/otp/send')->assertUnauthorized();
    }

    public function test_authenticated_user_can_request_email_otp(): void
    {
        Notification::fake();
        $user = $this->authenticate('farmer');
        $this->postJson('/api/otp/send')->assertOk();
        $this->assertDatabaseHas('otp_challenges', ['user_id' => $user->id, 'channel' => 'email']);
        $challenge = OtpChallenge::where('user_id', $user->id)->firstOrFail();
        $this->assertNotSame('123456', $challenge->code_hash);
        Notification::assertSentOnDemand(OtpCodeNotification::class);
    }

    public function test_resend_cooldown_blocks_immediate_request(): void
    {
        Notification::fake();
        $this->authenticate('farmer');
        $this->postJson('/api/otp/send')->assertOk();
        $this->postJson('/api/otp/send')->assertUnprocessable();
    }

    public function test_wrong_code_increments_attempts(): void
    {
        $user = $this->authenticate('farmer');
        $challenge = $this->challenge($user, '123456');
        $this->postJson('/api/otp/verify', ['code' => '000000'])->assertUnprocessable();
        $this->assertSame(1, $challenge->fresh()->attempts);
    }

    public function test_correct_code_is_single_use(): void
    {
        $user = $this->authenticate('farmer');
        $challenge = $this->challenge($user, '123456');
        $this->postJson('/api/otp/verify', ['code' => '123456'])->assertOk();
        $this->assertNotNull($challenge->fresh()->consumed_at);
        $this->postJson('/api/otp/verify', ['code' => '123456'])->assertUnprocessable();
    }

    public function test_expired_code_is_rejected(): void
    {
        $user = $this->authenticate('farmer');
        $this->challenge($user, '123456', -1);
        $this->postJson('/api/otp/verify', ['code' => '123456'])->assertUnprocessable();
    }

    public function test_five_failed_attempts_lock_challenge(): void
    {
        $user = $this->authenticate('farmer');
        $challenge = $this->challenge($user, '123456');
        for ($i = 0; $i < 5; $i++) $this->postJson('/api/otp/verify', ['code' => '000000'])->assertUnprocessable();
        $this->assertSame(5, $challenge->fresh()->attempts);
        $this->postJson('/api/otp/verify', ['code' => '123456'])->assertUnprocessable();
    }

    public function test_notification_history_is_private_to_recipient(): void
    {
        $one = $this->authenticate('farmer');
        app(NotificationService::class)->send($one, new SystemAlertNotification('account.updated', 'Account', 'Updated'));
        $two = $this->authenticate('farmer');
        $this->getJson('/api/notifications')->assertOk()->assertJsonCount(0, 'data');
        $this->assertSame(1, $one->notifications()->count());
    }

    public function test_user_can_mark_only_own_notification_read(): void
    {
        $one = $this->authenticate('farmer');
        app(NotificationService::class)->send($one, new SystemAlertNotification('account.updated', 'Account', 'Updated'));
        $id = $one->notifications()->firstOrFail()->id;
        $two = $this->authenticate('farmer');
        $this->patchJson('/api/notifications/'.$id.'/read')->assertNotFound();
        $this->assertNull($one->notifications()->first()->read_at);
        $this->actingAs($one, 'sanctum');
        $this->patchJson('/api/notifications/'.$id.'/read')->assertOk();
        $this->assertNotNull($one->notifications()->first()->read_at);
    }

    public function test_lgu_cannot_receive_other_municipality_notification(): void
    {
        $a = $this->municipality();
        $b = $this->municipality();
        $lgu = $this->user('lgu_encoder', $a);
        $this->expectException(\Illuminate\Validation\ValidationException::class);
        app(NotificationService::class)->send($lgu, new SystemAlertNotification('price.approved', 'Price', 'Approved', $b->id));
    }

    private function challenge($user, string $code, int $expiryMinutes = 5): OtpChallenge
    {
        return OtpChallenge::create([
            'user_id' => $user->id, 'purpose' => 'email_verification', 'channel' => 'email',
            'destination' => $user->email, 'code_hash' => Hash::make($code),
            'expires_at' => now()->addMinutes($expiryMinutes),
        ]);
    }
}
