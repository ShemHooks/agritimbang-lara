<?php

namespace App\Services;

use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class LguTransactionVerificationService
{
    public function approve(User $officer, string $transactionId): Transaction
    {
        return $this->process($officer, $transactionId, true);
    }

    public function reject(
        User $officer,
        string $transactionId,
        string $reason
    ): Transaction {
        $reason = trim($reason);

        if (mb_strlen($reason) < 5 || mb_strlen($reason) > 2000) {
            throw ValidationException::withMessages([
                'reason' => 'A rejection reason between 5 and 2000 characters is required.',
            ]);
        }

        return $this->process($officer, $transactionId, false, $reason);
    }

    private function process(
        User $officer,
        string $transactionId,
        bool $approved,
        ?string $reason = null
    ): Transaction {
        return DB::transaction(function () use ($officer, $transactionId, $approved, $reason) {
            $transaction = Transaction::query()
                ->whereKey($transactionId)
                ->lockForUpdate()
                ->firstOrFail();

            $this->authorizeOfficer($officer, $transaction);
            $this->validateTransaction($transaction);

            $items = $transaction->items()
                ->orderBy('livestock_id')
                ->get();

            if ($items->isEmpty()) {
                throw ValidationException::withMessages([
                    'items' => 'Transaction must contain livestock.',
                ]);
            }

            if ($items->pluck('livestock_id')->unique()->count() !== $items->count()) {
                throw ValidationException::withMessages([
                    'items' => 'Duplicate livestock records are not allowed.',
                ]);
            }

            foreach ($items as $item) {
                $livestock = $item->livestock()
                    ->lockForUpdate()
                    ->firstOrFail();

                if (
                    $livestock->farmer_id !== $transaction->farmer_id ||
                    $livestock->status !== 'in_transaction'
                ) {
                    throw ValidationException::withMessages([
                        'items' => 'Livestock ownership or reservation is inconsistent.',
                    ]);
                }

                if (!$approved) {
                    $hasOtherActiveSale = $livestock->transactionItems()
                        ->whereHas('transaction', function ($query) use ($transaction) {
                            $query->where('transactions.id', '!=', $transaction->id)
                                ->whereIn('transactions.status', [
                                    'pending',
                                    'pending_lgu_verification',
                                ]);
                        })
                        ->exists();

                    if ($hasOtherActiveSale) {
                        throw ValidationException::withMessages([
                            'items' => 'Livestock belongs to another active transaction.',
                        ]);
                    }
                }

                $livestock->update([
                    'status' => $approved ? 'sold' : 'available',
                ]);
            }

            $newStatus = $approved ? 'verified' : 'rejected';

            $transaction->update([
                'status' => $newStatus,
                'verified_by' => $officer->id,
                'verified_at' => now(),
                'remarks' => $approved
                    ? 'Verified by LGU officer.'
                    : $reason,
            ]);

            $transaction->statusLogs()->create([
                'from_status' => 'pending_lgu_verification',
                'to_status' => $newStatus,
                'actor_type' => 'lgu',
                'actor_user_id' => $officer->id,
                'remarks' => $approved
                    ? 'LGU officer approved the transaction.'
                    : $reason,
            ]);

            return $transaction->fresh(['items']);
        }, 3);
    }


    private function authorizeOfficer(
        User $officer,
        Transaction $transaction
    ): void {
        $profile = $officer->lguOfficerProfile;

        abort_unless(
            $profile !== null &&
            $officer->status === 'active' &&
            $officer->verification_status === 'verified' &&
            $officer->roles()
                ->where('slug', 'lgu_officer')
                ->exists(),
            403,
            'An active, verified LGU officer is required.'
        );

        abort_unless(
            $profile->municipality_id === $transaction->municipality_id,
            403,
            'Transaction is outside your municipality.'
        );

        abort_if(
            $transaction->encoded_by === $officer->id,
            403,
            'You cannot verify your own encoded transaction.'
        );
    }


    private function validateTransaction(Transaction $transaction): void
    {
        if ($transaction->status !== 'pending_lgu_verification') {
            throw ValidationException::withMessages([
                'status' => 'Transaction is not awaiting LGU verification.',
            ]);
        }

        if (!$transaction->farmer_id || !$transaction->farmer()->exists()) {
            throw ValidationException::withMessages([
                'farmer' => 'A registered farmer is required.',
            ]);
        }

        if ($transaction->source === 'farmer') {
            if (
                !$transaction->buyerConfirmations()
                    ->where('status', 'confirmed')
                    ->exists()
            ) {
                throw ValidationException::withMessages([
                    'buyer_confirmation' => 'Buyer confirmation is required.',
                ]);
            }

            if (
                $transaction->buyerConfirmations()
                    ->where('status', 'disputed')
                    ->exists()
            ) {
                throw ValidationException::withMessages([
                    'buyer_confirmation' => 'Buyer dispute must be resolved first.',
                ]);
            }

            return;
        }


        if ($transaction->source === 'auction_house') {

            $farmer = $transaction->farmer;

            if (
                !$farmer ||
                $farmer->municipality_id !== $transaction->municipality_id
            ) {
                throw ValidationException::withMessages([
                    'farmer_id' =>
                        'Transaction farmer does not belong to this municipality.',
                ]);
            }

            $validHouse = $transaction->auctionHouse()
                ->where('municipality_id', $transaction->municipality_id)
                ->exists();

            if (
                !$validHouse ||
                !$transaction->encoded_by ||
                $transaction->buyer_confirmation_method !== 'in_person' ||
                !filled($transaction->acknowledgement_reference)
            ) {
                throw ValidationException::withMessages([
                    'acknowledgement' =>
                        'Valid auction-house acknowledgement is required.',
                ]);
            }

            $encoderWasAssigned = DB::table(
                'auction_house_encoder_assignments'
            )
                ->where('auction_house_id', $transaction->auction_house_id)
                ->where('user_id', $transaction->encoded_by)
                ->where('created_at', '<=', $transaction->created_at)
                ->exists();

            if (!$encoderWasAssigned) {
                throw ValidationException::withMessages([
                    'encoder' =>
                        'Transaction encoder has no valid assignment record.',
                ]);
            }

            return;
        }




        throw ValidationException::withMessages([
            'source' => 'Unsupported transaction source.',
        ]);
    }
}
