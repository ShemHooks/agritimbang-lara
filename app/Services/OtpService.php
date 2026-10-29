<?php

namespace App\Services;

use App\Contracts\OtpTransport;
use App\Models\OtpChallenge;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

class OtpService
{
    public const PURPOSE_EMAIL = 'email_verification';
    public const PURPOSE_ACCOUNT = 'account_confirmation';

    public function __construct(private readonly EmailOtpTransport $emailTransport)
    {
    }

    public function request(User $user, string $purpose = self::PURPOSE_EMAIL): void
    {
        $this->validatePurpose($purpose);
        $key = 'otp:send:' . $user->getKey() . ':' . $purpose;
        if (RateLimiter::tooManyAttempts($key, 1)) {
            throw ValidationException::withMessages(['otp' => 'Please wait 60 seconds before requesting another code.']);
        }

        // Database cooldown also applies across cache resets and multiple app workers.
        $latest = OtpChallenge::where('user_id', $user->getKey())->where('purpose', $purpose)
            ->latest('created_at')->first();
        if ($latest && $latest->created_at->greaterThan(now()->subSeconds(60))) {
            throw ValidationException::withMessages(['otp' => 'Please wait 60 seconds before requesting another code.']);
        }

        $code = (string) random_int(100000, 999999);
        $challenge = DB::transaction(function () use ($user, $purpose, $code) {
            OtpChallenge::where('user_id', $user->getKey())->where('purpose', $purpose)
                ->whereNull('consumed_at')->update(['consumed_at' => now()]);
            return OtpChallenge::create([
                'user_id' => $user->getKey(),
                'purpose' => $purpose,
                'channel' => 'email',
                'destination' => $user->email,
                'code_hash' => Hash::make($code),
                'expires_at' => now()->addMinutes(5),
            ]);
        });

        try {
            $this->emailTransport->send($user, $user->email, $code, $purpose);
        } catch (\Throwable $e) {
            $challenge->update(['consumed_at' => now()]);
            throw $e;
        }
        RateLimiter::hit($key, 60);
    }

    public function verify(User $user, string $code, string $purpose = self::PURPOSE_EMAIL): void
    {
        $this->validatePurpose($purpose);
        $verified = DB::transaction(function () use ($user, $code, $purpose) {
            $challenge = OtpChallenge::where('user_id', $user->getKey())
                ->where('purpose', $purpose)->whereNull('consumed_at')
                ->latest('created_at')->lockForUpdate()->first();
            if (!$challenge || $challenge->expires_at->isPast() || $challenge->attempts >= 5) {
                throw ValidationException::withMessages(['code' => 'The verification code is expired or unavailable.']);
            }
            if (!hash_equals((string) $challenge->destination, (string) $user->email)) {
                throw ValidationException::withMessages(['code' => 'The email address has changed. Request a new code.']);
            }
            if (!Hash::check($code, $challenge->code_hash)) {
                $challenge->increment('attempts');
                // ValidationException inside the transaction would roll back the increment.
                return false;
            }
            $challenge->update(['consumed_at' => now()]);
            if ($purpose === self::PURPOSE_EMAIL) {
                // Laravel's conventional email verification column; no identity-document approval implied.
                $user->forceFill(['email_verified_at' => now()])->save();
            }
            return true;
        });
        if (!$verified) {
            throw ValidationException::withMessages(['code' => 'Invalid verification code.']);
        }
    }

    private function validatePurpose(string $purpose): void
    {
        if (!in_array($purpose, [self::PURPOSE_EMAIL, self::PURPOSE_ACCOUNT], true)) {
            throw ValidationException::withMessages(['purpose' => 'Unsupported OTP purpose.']);
        }
    }
}
