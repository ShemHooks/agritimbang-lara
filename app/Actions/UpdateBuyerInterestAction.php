<?php

namespace App\Actions;

use App\Models\AuctionRegistration;
use App\Models\AuctionSchedule;
use App\Models\Buyer;
use App\Models\BuyerInterest;
use App\Models\LivestockRecord;
use App\Notifications\BuyerInterestReceived;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class UpdateBuyerInterestAction
{
    public function __invoke(Buyer $buyer, AuctionSchedule $schedule, array $livestockIds): array
    {
        if ((string) $buyer->auction_id !== (string) $schedule->id || (string) $buyer->municipality_id !== (string) $schedule->municipality_id) {
            throw ValidationException::withMessages(['account_code' => 'This account belongs to a different auction event. Please create a new entry for this event.']);
        }

        if ($buyer->isExpired()) {
            throw ValidationException::withMessages(['account_code' => 'This temporary account has expired. Accounts are valid only until the end of the auction day.']);
        }

        $selected = array_values(array_unique(array_map('strval', $livestockIds)));
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

        $added = 0;

        DB::transaction(function () use ($buyer, $schedule, $selected, $livestockById, &$added) {
            foreach ($selected as $livestockId) {
                $interest = BuyerInterest::query()
                    ->where('buyer_id', $buyer->id)
                    ->where('livestock_id', $livestockId)
                    ->first();

                if ($interest) {
                    if ($interest->status === 'withdrawn') {
                        $interest->status = 'active';
                        $interest->save();
                        BuyerInterestReceived::sendToLivestockFarmer($buyer, $livestockById->get($livestockId), $schedule);
                        $added++;
                    }

                    continue;
                }

                BuyerInterest::create([
                    'buyer_id' => $buyer->id,
                    'livestock_id' => $livestockId,
                    'status' => 'active',
                ]);

                BuyerInterestReceived::sendToLivestockFarmer($buyer, $livestockById->get($livestockId), $schedule);
                $added++;
            }
        });

        $buyer->touch();

        return [
            'buyer' => $buyer->fresh(),
            'added_count' => $added,
            'pdf_url' => route('buyer-interest.pdf', ['schedule' => $schedule->id, 'buyer' => $buyer->id]),
        ];
    }
}
