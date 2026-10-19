<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class IprogSmsService
{
    public function send(
        string $phoneNumber,
        string $message
    ): array {
        if (!config('services.iprog_sms.enabled', false)) {
            throw new RuntimeException(
                'IPROG SMS is disabled. No message was sent.'
            );
        }

        $token = config('services.iprog_sms.token');

        if (empty($token)) {
            throw new RuntimeException(
                'IPROG SMS API token is not configured.'
            );
        }

        $url = rtrim(
            config('services.iprog_sms.base_url'),
            '/'
        ) . '/sms_messages';

        $response = Http::asForm()
            ->timeout(15)
            ->post($url, [
                'api_token' => $token,
                'message' => $message,
                'phone_number' => $phoneNumber,
            ]);

        if (!$response->successful()) {
            throw new RuntimeException(
                'SMS provider rejected the request. HTTP '
                . $response->status()
            );
        }

        $result = $response->json();

        if (
            !is_array($result) ||
            (int) ($result['status'] ?? 0) !== 200 ||
            empty($result['message_id'])
        ) {
            throw new RuntimeException(
                'SMS provider did not confirm queue acceptance.'
            );
        }

        return [
            'accepted' => true,
            'message_id' => $result['message_id'],
        ];
    }


    public function sendBuyerConfirmation(
        string $buyerPhone,
        string $buyerName,
        string $farmerName,
        string $livestockDescription,
        string $sellingPrice,
        string $confirmationUrl
    ): array {
        $message = sprintf(
            "Good day, %s!\n\n" .
            "%s has recorded your recent %s purchase " .
            "worth PHP %s in AgriTimbang.\n\n" .
            "Review and confirm:\n%s\n\n" .
            "If this wasn't you, do not confirm.",
            $buyerName,
            $farmerName,
            $livestockDescription,
            $sellingPrice,
            $confirmationUrl
        );

        return $this->send(
            $buyerPhone,
            $message
        );
    }

}
