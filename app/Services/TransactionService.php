<?php

namespace App\Services;

use App\Models\LivestockRecord;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Valuation;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use App\Services\BuyerConfirmationService;
use App\Jobs\SendBuyerConfirmationSms;

class TransactionService
{

    public function createDraft(
        User $user,
        array $data
    ): Transaction {
        $farmer = $user->farmerProfile;

        if (!$farmer) {
            throw ValidationException::withMessages([
                'farmer' => 'A farmer profile is required.',
            ]);
        }

        if ($user->verification_status !== 'verified') {
            throw ValidationException::withMessages([
                'farmer' => 'Your farmer account must be verified.',
            ]);
        }

        if (empty($data['items']) || !is_array($data['items'])) {
            throw ValidationException::withMessages([
                'items' => 'At least one livestock item is required.',
            ]);
        }

        $livestockIds = array_column(
            $data['items'],
            'livestock_id'
        );

        if (count($livestockIds) !== count(array_unique($livestockIds))) {
            throw ValidationException::withMessages([
                'items' => 'Duplicate livestock items are not allowed.',
            ]);
        }

        return DB::transaction(function () use ($user, $farmer, $data) {
            $transaction = Transaction::create([
                'farmer_id' => $farmer->id,
                'buyer_name' => $data['buyer_name'],
                'buyer_phone' => $data['buyer_phone'] ?? null,
                'municipality_id' => $farmer->municipality_id,
                'barangay_id' => $farmer->barangay_id,
                'transaction_date' => $data['transaction_date'] ?? now(),
                'status' => 'draft',
                'total_reference_value' => 0,
                'total_selling_price' => 0,
                'encoded_by' => $user->id,
                'remarks' => $data['remarks'] ?? null,
            ]);

            $totalReferenceValue = 0;
            $totalSellingPrice = 0;

            foreach ($data['items'] as $index => $item) {
                $livestock = LivestockRecord::query()
                    ->whereKey($item['livestock_id'])
                    ->lockForUpdate()
                    ->first();

                if (!$livestock) {
                    throw ValidationException::withMessages([
                        "items.$index.livestock_id" =>
                            'Livestock record not found.',
                    ]);
                }

                if ($livestock->farmer_id !== $farmer->id) {
                    throw ValidationException::withMessages([
                        "items.$index.livestock_id" =>
                            'This livestock does not belong to the farmer.',
                    ]);
                }

                if (
                    $livestock->municipality_id !== $farmer->municipality_id ||
                    $livestock->barangay_id !== $farmer->barangay_id
                ) {
                    throw ValidationException::withMessages([
                        "items.$index.livestock_id" =>
                            'Livestock location does not match the farmer profile.',
                    ]);
                }

                if ($livestock->status !== 'available') {
                    throw ValidationException::withMessages([
                        "items.$index.livestock_id" =>
                            'Livestock is not available for sale.',
                    ]);
                }

                /*
                 * Do not allow livestock already reserved by
                 * another active transaction.
                 */
                $hasActiveTransaction = DB::table('transaction_items')
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

                if ($hasActiveTransaction) {
                    throw ValidationException::withMessages([
                        "items.$index.livestock_id" =>
                            'Livestock already belongs to an active or completed transaction.',
                    ]);
                }

                $valuation = Valuation::query()
                    ->whereKey($item['valuation_id'])
                    ->where('livestock_id', $livestock->id)
                    ->first();

                if (!$valuation) {
                    throw ValidationException::withMessages([
                        "items.$index.valuation_id" =>
                            'A valid valuation for this livestock is required.',
                    ]);
                }

                $sellingPrice = $item['actual_selling_price'];

                if (
                    !is_numeric($sellingPrice) ||
                    !is_finite((float) $sellingPrice) ||
                    (float) $sellingPrice <= 0
                ) {
                    throw ValidationException::withMessages([
                        "items.$index.actual_selling_price" =>
                            'Selling price must be greater than zero.',
                    ]);
                }

                /*
                 * Monetary values are copied from the saved FPC
                 * valuation, not accepted from the client.
                 */
                $referenceValue = (float) $valuation->estimated_value;
                $sellingPrice = round((float) $sellingPrice, 2);

                if (
                    !is_finite($referenceValue) ||
                    $referenceValue <= 0
                ) {
                    throw ValidationException::withMessages([
                        "items.$index.valuation_id" =>
                            'The selected valuation has an invalid reference value.',
                    ]);
                }

                $weightUsed = (float) $valuation->weight_used_kg;

                if (
                    !is_finite($weightUsed) ||
                    $weightUsed <= 0
                ) {
                    throw ValidationException::withMessages([
                        "items.$index.valuation_id" =>
                            'The selected valuation has an invalid weight.',
                    ]);
                }

                $priceDifference = round(
                    $sellingPrice - $referenceValue,
                    2
                );

                $percentageDeviation = round(
                    ($priceDifference / $referenceValue) * 100,
                    4
                );

                $sellingPricePerKg = round(
                    $sellingPrice / $weightUsed,
                    2
                );

                $transaction->items()->create([
                    'livestock_id' => $livestock->id,
                    'valuation_id' => $valuation->id,
                    'weight_type' => $valuation->weight_type,
                    'weight_used_kg' => $valuation->weight_used_kg,
                    'base_price_per_kg' => $valuation->base_price_per_kg,
                    'final_price_per_kg' => $valuation->final_price_per_kg,
                    'reference_value' => $valuation->estimated_value,
                    'actual_selling_price' => $sellingPrice,
                    'actual_selling_price_per_kg' => $sellingPricePerKg,
                    'price_difference' => $priceDifference,
                    'percentage_deviation' => $percentageDeviation,
                    'valuation_snapshot' => [
                        'valuation_id' => $valuation->id,
                        'calculation_version' => $valuation->calculation_version,
                        'calculation_snapshot' => $valuation->calculation_snapshot,
                        'adjustment_breakdown' => $valuation->adjustment_breakdown,
                        'weight_type' => $valuation->weight_type,
                        'weight_used_kg' => $valuation->weight_used_kg,
                        'base_price_per_kg' => $valuation->base_price_per_kg,
                        'final_price_per_kg' => $valuation->final_price_per_kg,
                        'estimated_value' => $valuation->estimated_value,
                    ],
                    'calculation_version' => $valuation->calculation_version,
                ]);

                $totalReferenceValue += $referenceValue;
                $totalSellingPrice += $sellingPrice;
            }

            $transaction->update([
                'total_reference_value' => round(
                    $totalReferenceValue,
                    2
                ),
                'total_selling_price' => round(
                    $totalSellingPrice,
                    2
                ),
            ]);

            $transaction->statusLogs()->create([
                'from_status' => null,
                'to_status' => 'draft',
                'actor_type' => 'farmer',
                'actor_user_id' => $user->id,
                'remarks' => 'Draft transaction created.',
            ]);

            return $transaction->load([
                'items.livestock',
                'items.valuation',
                'farmer',
                'statusLogs',
            ]);
        }, 3);
    }


    /**
     * Submit a draft sale for buyer confirmation.
     *
     * Reserves livestock and issues a confirmation token.
     */
    public function submit(
        User $user,
        string $transactionId,
        BuyerConfirmationService $confirmationService
    ): Transaction {
        $farmer = $user->farmerProfile;

        if (!$farmer || $user->verification_status !== 'verified') {
            throw ValidationException::withMessages([
                'farmer' => 'A verified farmer profile is required.',
            ]);
        }

        return DB::transaction(function () use ($user, $farmer, $transactionId, $confirmationService) {
            $transaction = Transaction::query()
                ->whereKey($transactionId)
                ->lockForUpdate()
                ->firstOrFail();

            if ($transaction->farmer_id !== $farmer->id) {
                abort(403, 'You cannot submit this transaction.');
            }

            if ($transaction->status !== 'draft') {
                throw ValidationException::withMessages([
                    'status' => 'Only draft transactions can be submitted.',
                ]);
            }

            if (!$transaction->buyer_phone) {
                throw ValidationException::withMessages([
                    'buyer_phone' =>
                        'Buyer phone number is required for confirmation.',
                ]);
            }

            $items = $transaction->items()
                ->orderBy('livestock_id')
                ->get();

            if ($items->isEmpty()) {
                throw ValidationException::withMessages([
                    'items' => 'Transaction has no livestock items.',
                ]);
            }

            foreach ($items as $index => $item) {
                $livestock = LivestockRecord::query()
                    ->whereKey($item->livestock_id)
                    ->lockForUpdate()
                    ->first();

                if (!$livestock) {
                    throw ValidationException::withMessages([
                        "items.$index.livestock_id" =>
                            'Livestock no longer exists.',
                    ]);
                }

                if (
                    $livestock->farmer_id !== $farmer->id ||
                    $livestock->municipality_id !== $transaction->municipality_id ||
                    $livestock->barangay_id !== $transaction->barangay_id
                ) {
                    throw ValidationException::withMessages([
                        "items.$index.livestock_id" =>
                            'Livestock ownership or location has changed.',
                    ]);
                }

                if ($livestock->status !== 'available') {
                    throw ValidationException::withMessages([
                        "items.$index.livestock_id" =>
                            'Livestock is no longer available.',
                    ]);
                }

                $livestock->update([
                    'status' => 'in_transaction',
                ]);
            }

            $transaction->update([
                'status' => 'pending',
            ]);

            $transaction->statusLogs()->create([
                'from_status' => 'draft',
                'to_status' => 'pending',
                'actor_type' => 'farmer',
                'actor_user_id' => $user->id,
                'remarks' => 'Submitted for buyer confirmation.',
            ]);

            $issued = $confirmationService->issue($transaction);

            /*
             * Dispatch only after the outer database transaction
             * successfully commits.
             *
             * Never include the raw token in logs.
             */
            SendBuyerConfirmationSms::dispatch(
                $transaction->id,
                $issued['token']
            )->afterCommit();

            return $transaction->fresh([
                'items',
                'buyerConfirmations',
                'statusLogs',
            ]);
        }, 3);
    }

}
