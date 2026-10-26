<?php

namespace App\Services;

use App\Models\User;
use App\Notifications\SystemAlertNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class NotificationService
{
    public function send(
        User $recipient,
        SystemAlertNotification $notification
    ): void {
        if ($notification->municipalityId !== null) {
            $profile = DB::table('lgu_officer_profiles')
                ->where('user_id', $recipient->getKey())
                ->first(['municipality_id']);

            if ($profile !== null) {
                if (
                    (string) $profile->municipality_id !==
                    (string) $notification->municipalityId
                ) {
                    throw ValidationException::withMessages([
                        'recipient' =>
                            'Recipient is outside the municipality scope.',
                    ]);
                }
            } elseif (
                $recipient->roles()
                    ->whereIn('name', ['lgu_authority', 'lgu_encoder'])
                    ->exists()
            ) {
                throw ValidationException::withMessages([
                    'recipient' =>
                        'LGU recipient has no municipality assignment.',
                ]);
            }
        }

        $recipient->notify($notification);
    }
}