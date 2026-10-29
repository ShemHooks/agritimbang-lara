<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

class IprogSmsService
{
    public function send(
        string $phoneNumber,
        string $message
    ): array {
        $this->ensureConfigured();

        $phoneNumber = trim($phoneNumber);
        $message = $this->normalizeMessage($message);

        if ($phoneNumber === '' || $message === '') {
            throw new RuntimeException(
                'SMS phone number and message are required.'
            );
        }

        $url = rtrim(
            (string) config('services.iprog_sms.base_url'),
            '/'
        ) . '/sms_messages';

        try {
            if (app()->environment('local')) {
                Log::build([
                    'driver' => 'single',
                    'path' => storage_path('logs/iprog-sms-debug.log'),
                    'level' => 'debug',
                ])->debug('Outgoing iProg SMS', [
                            'phone' => $this->maskPhone($phoneNumber),
                            'message' => $message,
                            'message_length' => mb_strlen($message),
                            'contains_https' => str_contains($message, 'https://'),
                            'contains_asterisks' => str_contains($message, '********'),
                            'timestamp' => now()->toDateTimeString(),
                        ]);
            }

            $response = Http::asForm()
                ->acceptJson()
                ->connectTimeout(10)
                ->timeout(20)
                ->post($url, [
                    'api_token' => config('services.iprog_sms.token'),
                    'phone_number' => $phoneNumber,
                    'message' => $message,
                ]);
        } catch (ConnectionException $exception) {
            Log::error('iProg SMS connection failure', [
                'phone' => $this->maskPhone($phoneNumber),
                'error' => $exception->getMessage(),
            ]);

            throw new RuntimeException(
                'SMS provider connection failed. Delivery status is unknown.',
                0,
                $exception
            );
        } catch (Throwable $exception) {
            Log::error('iProg SMS request failure', [
                'phone' => $this->maskPhone($phoneNumber),
                'exception' => get_class($exception),
            ]);

            throw new RuntimeException(
                'Unable to complete SMS provider request.',
                0,
                $exception
            );
        }

        $result = $response->json();

        Log::info('iProg SMS provider response', [
            'phone' => $this->maskPhone($phoneNumber),
            'http_status' => $response->status(),
            'provider_status' => is_array($result)
                ? ($result['status'] ?? null)
                : null,
            'message_id' => is_array($result)
                ? ($result['message_id'] ?? null)
                : null,
            'response' => $this->sanitizeResponse($result),
            'message_length' => mb_strlen($message),
        ]);

        if (!$response->successful()) {
            throw new RuntimeException(
                'SMS provider returned HTTP ' .
                $response->status() .
                '. Check Laravel logs for details.'
            );
        }

        if (!is_array($result)) {
            throw new RuntimeException(
                'SMS provider returned an invalid JSON response.'
            );
        }

        $providerStatus = (int) ($result['status'] ?? 0);
        $messageId = $result['message_id'] ?? null;

        if ($providerStatus !== 200 || empty($messageId)) {
            Log::warning('iProg SMS not confirmed as accepted', [
                'phone' => $this->maskPhone($phoneNumber),
                'provider_status' => $providerStatus,
                'response' => $this->sanitizeResponse($result),
            ]);

            throw new RuntimeException(
                'SMS provider did not confirm queue acceptance. ' .
                'Check Laravel logs for the provider response.'
            );
        }

        return [
            'accepted' => true,
            'message_id' => (string) $messageId,
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
            "AgriTimbang: %s recorded your %s purchase (PHP %s). Confirm: %s",
            trim($farmerName),
            trim($livestockDescription),
            trim($sellingPrice),
            trim($confirmationUrl)
        );

        return $this->send($buyerPhone, $message);
    }

    private function ensureConfigured(): void
    {
        if (!config('services.iprog_sms.enabled', false)) {
            throw new RuntimeException(
                'iProg SMS is disabled.'
            );
        }

        if (empty(config('services.iprog_sms.token'))) {
            throw new RuntimeException(
                'iProg SMS API token is not configured.'
            );
        }

        if (empty(config('services.iprog_sms.base_url'))) {
            throw new RuntimeException(
                'iProg SMS base URL is not configured.'
            );
        }
    }

    private function normalizeMessage(string $message): string
    {
        $message = trim($message);

        return str_replace(
            ["\r\n", "\r"],
            "\n",
            $message
        );
    }

    private function maskPhone(string $phone): string
    {
        if (strlen($phone) <= 4) {
            return '****';
        }

        return str_repeat(
            '*',
            strlen($phone) - 4
        ) . substr($phone, -4);
    }

    private function sanitizeResponse(mixed $response): mixed
    {
        if (is_array($response)) {
            $sensitiveKeys = [
                'api_token',
                'token',
                'authorization',
                'password',
                'phone_number',
                'body',
                'message_status_link',
            ];

            foreach ($response as $key => $value) {
                $normalizedKey = strtolower((string) $key);

                if (in_array($normalizedKey, $sensitiveKeys, true)) {
                    $response[$key] = '[REDACTED]';
                } else {
                    $response[$key] = $this->sanitizeResponse($value);
                }
            }

            return $response;
        }

        if (is_string($response)) {
            return preg_replace(
                [
                    '~https?://[^\s"\']+~i',
                    '~\b(?:\+?63|0)9\d{9}\b~',
                    '~(?:api_token|token|password)\s*[=:]\s*[^\s&]+~i',
                ],
                '[REDACTED]',
                mb_substr($response, 0, 500)
            );
        }

        return $response;
    }
}
