<?php

namespace App\Services;

use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TransactionCancellationService
{



    private function validateReason(string $reason): string
    {
        $reason = trim($reason);

        if (mb_strlen($reason) < 5 || mb_strlen($reason) > 2000) {
            throw ValidationException::withMessages([
                'reason' => 'Reason must contain between 5 and 2000 characters.',
            ]);
        }

        return $reason;
    }

    public function cancelByFarmer(
        User $farmer,
        string $transactionId,
        string $reason
    ): Transaction {
        $reason = $this->validateReason($reason);

        return DB::transaction(function () use ($farmer, $transactionId, $reason) {
            $transaction = $this->lockTransaction($transactionId);

            abort_unless(
                $farmer->status === 'active' &&
                $farmer->farmerProfile?->id === $transaction->farmer_id,
                403,
                'You cannot cancel this transaction.'
            );

            if (!in_array($transaction->status, ['draft', 'pending'], true)) {
                throw ValidationException::withMessages([
                    'status' => 'This transaction can no longer be cancelled by the farmer.',
                ]);
            }

            if (
                $transaction->buyerConfirmations()
                    ->whereIn('status', ['confirmed', 'disputed'])
                    ->exists()
            ) {
                throw ValidationException::withMessages([
                    'buyer_confirmation' => 'A confirmed or disputed transaction requires LGU intervention.',
                ]);
            }

            $this->assertNoActiveDisputes($transaction);

            return $this->performCancellation(
                $transaction,
                $farmer,
                'farmer',
                $reason
            );
        }, 3);
    }

    public function cancelByLgu(
        User $officer,
        string $transactionId,
        string $reason
    ): Transaction {
        $reason = $this->validateReason($reason);

        return DB::transaction(function () use ($officer, $transactionId, $reason) {
            $transaction = $this->lockTransaction($transactionId);

            $this->authorizeOfficer($officer, $transaction);

            if (
                !in_array($transaction->status, [
                    'pending',
                    'pending_lgu_verification',
                ], true)
            ) {
                throw ValidationException::withMessages([
                    'status' => 'Transaction is not eligible for LGU cancellation.',
                ]);
            }

            $this->assertNoActiveDisputes($transaction);

            return $this->performCancellation(
                $transaction,
                $officer,
                'lgu',
                $reason
            );
        }, 3);
    }

    /**
     * Called only from the authorized LGU dispute-resolution workflow.
     * The parent transaction must already be locked.
     */
    public function cancelForUpheldDispute(
        Transaction $transaction,
        User $officer,
        string $reason
    ): Transaction {
        $reason = $this->validateReason($reason);

        if (!DB::transactionLevel()) {
            throw new \LogicException(
                'Dispute cancellation requires an active database transaction.'
            );
        }

        if (
            !in_array($transaction->status, [
                'pending',
                'pending_lgu_verification',
            ], true)
        ) {
            throw ValidationException::withMessages([
                'status' => 'Transaction is not eligible for dispute cancellation.',
            ]);
        }

        return $this->performCancellation(
            $transaction,
            $officer,
            'lgu',
            $reason,
            'dispute_upheld_cancellation'
        );
    }

    private function performCancellation(
        Transaction $transaction,
        User $actor,
        string $actorType,
        string $reason,
        string $event = 'transaction_cancelled'
    ): Transaction {
        $oldStatus = $transaction->status;

        $items = $transaction->items()
            ->orderBy('livestock_id')
            ->get();

        if ($oldStatus !== 'draft' && $items->isEmpty()) {
            throw ValidationException::withMessages([
                'items' => 'Submitted transaction must contain livestock.',
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

            if ($livestock->farmer_id !== $transaction->farmer_id) {
                throw ValidationException::withMessages([
                    'items' => 'Livestock ownership is inconsistent.',
                ]);
            }

            $hasOtherActiveSale = $livestock->transactionItems()
                ->whereHas('transaction', function ($query) use ($transaction) {
                    $query->where('transactions.id', '!=', $transaction->id)
                        ->whereIn('transactions.status', [
                            'pending',
                            'pending_lgu_verification',
                            'verified',
                        ]);
                })
                ->exists();

            if ($hasOtherActiveSale) {
                throw ValidationException::withMessages([
                    'items' => 'Livestock belongs to another active or verified sale.',
                ]);
            }

            if ($oldStatus !== 'draft') {
                if ($livestock->status !== 'in_transaction') {
                    throw ValidationException::withMessages([
                        'items' => 'Livestock reservation is inconsistent.',
                    ]);
                }

                $livestock->update(['status' => 'available']);
            } elseif ($livestock->status !== 'available') {
                throw ValidationException::withMessages([
                    'items' => 'Draft transaction livestock must be available.',
                ]);
            }
        }

        $transaction->buyerConfirmations()
            ->where('status', 'pending')
            ->update([
                'status' => 'revoked',
            ]);

        $transaction->update([
            'status' => 'cancelled',
            'remarks' => $reason,
        ]);

        $transaction->statusLogs()->create([
            'from_status' => $oldStatus,
            'to_status' => 'cancelled',
            'actor_type' => $actorType,
            'actor_user_id' => $actor->id,
            'remarks' => $reason,
            'metadata' => [
                'event' => $event,
            ],
        ]);

        return $transaction->fresh(['items']);
    }

    private function lockTransaction(string $transactionId): Transaction
    {
        return Transaction::query()
            ->whereKey($transactionId)
            ->lockForUpdate()
            ->firstOrFail();
    }

    private function assertNoActiveDisputes(Transaction $transaction): void
    {
        if (
            $transaction->disputes()
                ->whereIn('status', ['open', 'under_review'])
                ->exists()
        ) {
            throw ValidationException::withMessages([
                'dispute' => 'Resolve active disputes before cancelling this transaction.',
            ]);
        }
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
            'You cannot cancel your own encoded transaction.'
        );
    }


}
