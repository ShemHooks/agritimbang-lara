<?php

namespace App\Services;

use App\Models\AuctionSchedule;
use App\Models\Buyer;
use Illuminate\Support\Carbon;

class BuyerLoginService
{
    public function resolve(AuctionSchedule $schedule, string $accountCode): array
    {
        $code = strtoupper(trim($accountCode));
        $buyer = Buyer::query()->where('account_code', $code)->first();

        if (! $buyer) {
            return [
                'status' => 'not_found',
                'message' => 'Account code not found for this auction event. Please check your code or create a new interest entry.',
                'buyer' => null,
            ];
        }

        if ((string) $buyer->auction_id !== (string) $schedule->id || (string) $buyer->municipality_id !== (string) $schedule->municipality_id) {
            return [
                'status' => 'wrong_event',
                'message' => 'This account belongs to a different auction event. Please create a new entry for this event.',
                'buyer' => null,
            ];
        }

        $isExpired = $buyer->isExpired();

        return [
            'status' => $isExpired ? 'expired' : 'active',
            'message' => $isExpired
                ? 'This account has expired. You may still download your record but cannot update interests. Please register again for the new auction event.'
                : 'Welcome back! Your temporary account is active until ' . Carbon::parse($buyer->expires_at, 'Asia/Manila')->format('F j, Y') . '.',
            'buyer' => $buyer->load(['interests.livestock.farmer.user', 'interests.livestock.species', 'interests.livestock.breed']),
        ];
    }

    public function resolveForUpdate(AuctionSchedule $schedule, string $accountCode): array
    {
        $result = $this->resolve($schedule, $accountCode);

        if ($result['status'] === 'not_found' || $result['status'] === 'expired') {
            return [
                'status' => 'not_found_or_expired',
                'message' => 'Account code not found or expired for this auction event. Please create a new interest entry.',
                'buyer' => null,
            ];
        }

        return $result;
    }
}
