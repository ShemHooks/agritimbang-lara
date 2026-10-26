<?php

namespace App\Services;

use App\Contracts\OtpTransport;
use App\Models\User;
use LogicException;

class SmsOtpTransport implements OtpTransport
{
    public function send(User $user, string $destination, string $code, string $purpose): void
    {
        throw new LogicException('SMS OTP transport is not configured. Integrate a provider before enabling SMS.');
    }
}
