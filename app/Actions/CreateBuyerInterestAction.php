<?php

namespace App\Actions;

use App\Models\AuctionRegistration;
use App\Models\AuctionSchedule;
use App\Models\Buyer;
use App\Models\BuyerInterest;
use App\Models\LivestockRecord;
use App\Notifications\BuyerInterestReceived;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CreateBuyerInterestAction
{
    public function __invoke(AuctionSchedule $schedule, array $payload, array $livestockIds): array
    {
        $selected = array_values(array_unique(array_map('strval', $livestockIds)));

        if (count($selected) === 0) {
            throw ValidationException::withMessages(['livestock_ids' => 'Please select at least one livestock.']);
        }

        $scheduleIds = AuctionRegistration::query()
            ->where('auction_schedule_id', $schedule->id)
            ->pluck('livestock_id')
            ->map(fn ($id) => (string) $id)
            ->all();

        $invalidIds = array_diff($selected, $scheduleIds);
        if ($invalidIds !== []) {
            throw ValidationException::withMessages(['livestock_ids' => 'One or more selected livestock do not belong to this auction event.']);
        }

        $livestockById = LivestockRecord::query()
            ->whereIn('id', $selected)
            ->with(['species', 'breed', 'farmer.user'])
            ->get()
            ->keyBy(fn (LivestockRecord $livestock) => (string) $livestock->id);

        return DB::transaction(function () use ($schedule, $payload, $selected, $livestockById) {
            $code = $this->generateCode();

            $buyer = Buyer::create([
                'first_name' => trim((string) ($payload['first_name'] ?? '')),
                'last_name' => trim((string) ($payload['last_name'] ?? '')),
                'mobile_number' => $this->normalizeMobile((string) ($payload['mobile_number'] ?? '')),
                'buyer_type' => strtolower((string) ($payload['buyer_type'] ?? 'individual')),
                'account_code' => $code,
                'auction_id' => $schedule->id,
                'municipality_id' => $schedule->municipality_id,
                'attendance_intention' => $this->normalizeAttendance((string) ($payload['attendance_intention'] ?? 'yes')),
                'contact_consent' => (bool) ($payload['contact_consent'] ?? false),
                'status' => 'temporary',
                'is_temporary' => true,
                'expires_at' => Carbon::parse($schedule->auction_date, 'Asia/Manila')->endOfDay()->setTimezone('Asia/Manila'),
            ]);

            foreach ($selected as $livestockId) {
                $status = $this->normalizeInterestStatus((string) ($payload['interest_status'] ?? 'active'));
                BuyerInterest::create([
                    'buyer_id' => $buyer->id,
                    'livestock_id' => $livestockId,
                    'status' => $status,
                ]);

                if ($status === 'active') {
                    BuyerInterestReceived::sendToLivestockFarmer($buyer, $livestockById->get($livestockId), $schedule);
                }
            }

            return [
                'buyer' => $buyer->fresh(),
                'interest_count' => count($selected),
                'pdf_url' => route('buyer-interest.pdf', ['schedule' => $schedule->id, 'buyer' => $buyer->id]),
            ];
        });
    }

    protected function normalizeMobile(string $value): string
    {
        $digits = preg_replace('/\D+/', '', $value) ?? '';

        if (strlen($digits) === 11 && str_starts_with($digits, '09')) {
            return substr($digits, 0, 2) . '-' . substr($digits, 2, 3) . '-' . substr($digits, 5, 4);
        }

        if (strlen($digits) === 10 && str_starts_with($digits, '9')) {
            return substr($digits, 0, 2) . '-' . substr($digits, 2, 3) . '-' . substr($digits, 5, 4);
        }

        return preg_replace('/\s+/', '', $value);
    }

    protected function normalizeAttendance(string $value): string
    {
        $normalized = strtolower(trim($value));

        return match ($normalized) {
            'yes — will attend', 'yes' => 'yes',
            'no — cannot attend', 'no' => 'no',
            'tentative' => 'tentative',
            default => 'tentative',
        };
    }

    protected function normalizeInterestStatus(string $value): string
    {
        $normalized = strtolower(trim($value));

        return match ($normalized) {
            'withdrawn' => 'withdrawn',
            default => 'active',
        };
    }

    protected function generateCode(): string
    {
        $alphabet = 'ABCDEFGHJKLMNPRSTUVWXYZ23456789';

        do {
            $code = 'BUY-';
            for ($i = 0; $i < 6; $i++) {
                $code .= $alphabet[random_int(0, strlen($alphabet) - 1)];
            }
        } while (Buyer::where('account_code', $code)->exists());

        return $code;
    }
}
