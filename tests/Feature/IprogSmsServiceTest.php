<?php

namespace Tests\Feature;

use App\Services\IprogSmsService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class IprogSmsServiceTest extends TestCase
{
    public function test_sms_is_accepted_by_provider(): void
    {
        config()->set('services.iprog_sms.enabled', true);
        config()->set('services.iprog_sms.token', 'fake-test-token');
        config()->set(
            'services.iprog_sms.base_url',
            'https://www.iprogsms.com/api/v1'
        );

        Http::preventStrayRequests();

        Http::fake([
            'www.iprogsms.com/api/v1/sms_messages' =>
                Http::response([
                    'status' => 200,
                    'message' => 'SMS added to queue.',
                    'message_id' => 'iSms-TEST123',
                ], 200),
        ]);

        $result = app(IprogSmsService::class)->send(
            '09123456789',
            'AgriTimbang: Please confirm your transaction.'
        );

        $this->assertTrue($result['accepted']);
        $this->assertSame(
            'iSms-TEST123',
            $result['message_id']
        );

        Http::assertSent(function ($request) {
            return $request->url() ===
                'https://www.iprogsms.com/api/v1/sms_messages'
                && $request['api_token'] === 'fake-test-token'
                && $request['phone_number'] === '09123456789';
        });
    }
}
