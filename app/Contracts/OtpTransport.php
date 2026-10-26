<?php

namespace App\Contracts;

use App\Models\User;

interface OtpTransport
{
    public function send(User $user, string $destination, string $code, string $purpose): void;
}
