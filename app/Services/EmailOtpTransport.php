<?php

namespace App\Services;

use App\Contracts\OtpTransport;
use App\Models\User;
use App\Notifications\OtpCodeNotification;
use Illuminate\Support\Facades\Notification;

class EmailOtpTransport implements OtpTransport
{
    public function send(User $user, string $destination, string $code, string $purpose): void
    {
        Notification::route('mail', $destination)->notify(new OtpCodeNotification($code, $purpose));
    }
}
