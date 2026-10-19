<?php

namespace App\Services;

use App\Models\AuctionHouse;
use App\Models\FarmerProfile;
use App\Models\LivestockRecord;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Valuation;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AuctionHouseTransactionService
{
    public function recordCompletedSale(
        User $encoder,
        array $data
    ): Transaction {
        if (
            $encoder->status !== 'active' ||
            $encoder->verification_status !== 'verified'
        ) {
            throw ValidationException::withMessages([
                'encoder' => 'An active, verified encoder account is required.',
            ]);
        }

        if (empty($data['items'])) {
            throw ValidationException::withMessages([
                'items' => 'At least one livestock item is required.',
            ]);
        }

        abort_unless(
            $encoder->lguOfficerProfile !== null &&
            $encoder->roles()->where('slug', 'lgu_officer')->exists(),
            403,
            'Only LGU personnel can encode auction-house sales.'
        );

        $livestockIds = array_column(
            $data['items'],
            'livestock_id'
        );

        if (count($livestockIds) !== count(array_unique($livestockIds))) {
            throw ValidationException::withMessages([
                'items' => 'Duplicate livestock items are not allowed.',
            ]);
        }

        return DB::transaction(function () use ($encoder, $data) {
            $house = AuctionHouse::query()
                ->whereKey($data['auction_house_id'])
                ->where('is_active', true)
                ->first();

            if (!$house) {
                throw ValidationException::withMessages([
                    'auction_house_id' => 'Auction house is unavailable.',
                ]);
            }

            $assigned = DB::table('auction_house_encoder_assignments')
                ->where('auction_house_id', $house->id)
                ->where('user_id', $encoder->id)
                ->where('is_active', true)
                ->exists();

            if (!$assigned) {
                abort(403, 'You are not assigned to this auction house.');
            }

            if (
                $encoder->lguOfficerProfile->municipality_id !==
                $house->municipality_id
            ) {
                abort(
                    403,
                    'You cannot encode transactions outside your municipality.'
                );
            }

            $farmer = FarmerProfile::query()
                ->with('user')
                ->whereKey($data['farmer_id'])
                ->first();

            if (
                !$farmer ||
                !$farmer->user ||
                $farmer->user->status !== 'active' ||
                $farmer->user->verification_status !== 'verified'
            ) {
                throw ValidationException::withMessages([
                    'farmer_id' => 'A registered, verified farmer is required.',
                ]);
            }

            if ($farmer->municipality_id !== $house->municipality_id) {
                throw ValidationException::withMessages([
                    'farmer_id' =>
                        'Farmer municipality must match the auction house.',
                ]);
            }

            $transaction = Transaction::create([
                'farmer_id' => $farmer->id,
                'buyer_name' => $data['buyer_name'],
                'buyer_phone' => $data['buyer_phone'] ?? null,
                'municipality_id' => $farmer->municipality_id,
                'barangay_id' => $farmer->barangay_id,
                'transaction_date' => $data['transaction_date'],
                'status' => 'pending_lgu_verification',
                'source' => 'auction_house',
                'auction_house_id' => $house->id,
                'buyer_confirmation_method' => 'in_person',
                'acknowledgement_reference' =>
                    $data['acknowledgement_reference'],
                'acknowledgement_notes' =>
                    $data['acknowledgement_notes'] ?? null,
                'total_reference_value' => 0,
                'total_selling_price' => 0,
                'encoded_by' => $encoder->id,
                'remarks' => $data['remarks'] ?? null,
            ]);

            $totalReference = 0;
            $totalSelling = 0;

            foreach ($data['items'] as $index => $item) {
                $livestock = LivestockRecord::query()
                    ->whereKey($item['livestock_id'])
                    ->lockForUpdate()
                    ->first();

                if (
                    !$livestock ||
                    $livestock->farmer_id !== $farmer->id ||
                    $livestock->municipality_id !== $farmer->municipality_id ||
                    $livestock->barangay_id !== $farmer->barangay_id ||
                    $livestock->status !== 'available'
                ) {
                    throw ValidationException::withMessages([
                        "items.$index.livestock_id" =>
                            'Livestock is unavailable or does not belong to this farmer.',
                    ]);
                }

                $alreadyUsed = DB::table('transaction_items')
                    ->join(
                        'transactions',
                        'transactions.id',
                        '=',
                        'transaction_items.transaction_id'
                    )
                    ->where(
                        'transaction_items.livestock_id',
                        $livestock->id
                    )
                    ->whereIn('transactions.status', [
                        'pending',
                        'pending_lgu_verification',
                        'verified',
                    ])
                    ->exists();

                if ($alreadyUsed) {
                    throw ValidationException::withMessages([
                        "items.$index.livestock_id" =>
                            'Livestock is already in an active or verified sale.',
                    ]);
                }

                $valuation = Valuation::query()
                    ->whereKey($item['valuation_id'])
                    ->where('livestock_id', $livestock->id)
                    ->first();

                if (!$valuation) {
                    throw ValidationException::withMessages([
                        "items.$index.valuation_id" =>
                            'A valid saved valuation is required.',
                    ]);
                }

                $weight = (float) $valuation->weight_used_kg;
                $reference = (float) $valuation->estimated_value;
                $selling = round(
                    (float) $item['actual_selling_price'],
                    2
                );

                if (
                    !is_finite($weight) ||
                    !is_finite($reference) ||
                    !is_finite($selling) ||
                    $weight <= 0 ||
                    $reference <= 0 ||
                    $selling <= 0
                ) {
                    throw ValidationException::withMessages([
                        "items.$index" =>
                            'Weight, reference value, and selling price must be positive.',
                    ]);
                }

                $difference = round($selling - $reference, 2);
                $deviation = round(
                    ($difference / $reference) * 100,
                    4
                );

                $transaction->items()->create([
                    'livestock_id' => $livestock->id,
                    'valuation_id' => $valuation->id,
                    'weight_type' => $valuation->weight_type,
                    'weight_used_kg' => $valuation->weight_used_kg,
                    'base_price_per_kg' => $valuation->base_price_per_kg,
                    'final_price_per_kg' => $valuation->final_price_per_kg,
                    'reference_value' => $valuation->estimated_value,
                    'actual_selling_price' => $selling,
                    'actual_selling_price_per_kg' =>
                        round($selling / $weight, 2),
                    'price_difference' => $difference,
                    'percentage_deviation' => $deviation,
                    'valuation_snapshot' => [
                        'valuation_id' => $valuation->id,
                        'calculation_version' =>
                            $valuation->calculation_version,
                        'calculation_snapshot' =>
                            $valuation->calculation_snapshot,
                        'adjustment_breakdown' =>
                            $valuation->adjustment_breakdown,
                        'weight_type' => $valuation->weight_type,
                        'weight_used_kg' => $valuation->weight_used_kg,
                        'base_price_per_kg' =>
                            $valuation->base_price_per_kg,
                        'final_price_per_kg' =>
                            $valuation->final_price_per_kg,
                        'estimated_value' =>
                            $valuation->estimated_value,
                    ],
                    'calculation_version' =>
                        $valuation->calculation_version,
                ]);

                $livestock->update([
                    'status' => 'in_transaction',
                ]);

                $totalReference += $reference;
                $totalSelling += $selling;
            }

            $transaction->update([
                'total_reference_value' =>
                    round($totalReference, 2),
                'total_selling_price' =>
                    round($totalSelling, 2),
            ]);

            $transaction->statusLogs()->create([
                'from_status' => null,
                'to_status' => 'pending_lgu_verification',
                'actor_type' => 'system',
                'actor_user_id' => $encoder->id,
                'remarks' =>
                    'Completed auction-house sale encoded for LGU verification.',
                'metadata' => [
                    'source' => 'auction_house',
                    'auction_house_id' => $house->id,
                ],
            ]);

            return $transaction->fresh([
                'items.livestock',
                'items.valuation',
                'farmer',
                'auctionHouse',
                'statusLogs',
            ]);
        }, 3);
    }
}
