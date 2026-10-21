<?php

namespace App\Services;

use App\Models\Transaction;
use App\Models\TransactionDispute;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TransactionDisputeService
{
    public function __construct(
        private readonly TransactionCancellationService $cancellationService
    ) {
    }
    public function index(User $officer)
    {
        $municipalityId = $this->officerMunicipality($officer);

        return TransactionDispute::query()
            ->with(['transaction', 'reporter', 'reviewer', 'resolver'])
            ->whereHas('transaction', function ($query) use ($municipalityId) {
                $query->where('municipality_id', $municipalityId);
            })
            ->latest()
            ->paginate(15);
    }

    public function show(User $officer, string $disputeId): TransactionDispute
    {
        $municipalityId = $this->officerMunicipality($officer);

        return TransactionDispute::query()
            ->whereKey($disputeId)
            ->whereHas('transaction', function ($query) use ($municipalityId) {
                $query->where('municipality_id', $municipalityId);
            })
            ->with([
                'transaction.items.livestock',
                'buyerConfirmation',
                'reporter',
                'reviewer',
                'resolver',
            ])
            ->firstOrFail();
    }

    public function startReview(
        User $officer,
        string $disputeId
    ): TransactionDispute {
        return DB::transaction(function () use ($officer, $disputeId) {
            $municipalityId = $this->officerMunicipality($officer);

            $dispute = TransactionDispute::query()
                ->whereKey($disputeId)
                ->firstOrFail();

            $transaction = Transaction::query()
                ->whereKey($dispute->transaction_id)
                ->lockForUpdate()
                ->firstOrFail();

            $this->assertMunicipality($transaction, $municipalityId);

            $dispute = TransactionDispute::query()
                ->whereKey($disputeId)
                ->lockForUpdate()
                ->firstOrFail();

            if ($dispute->status !== 'open') {
                throw ValidationException::withMessages([
                    'status' => 'Only open disputes can be reviewed.',
                ]);
            }

            if (
                !in_array($transaction->status, [
                    'pending',
                    'pending_lgu_verification',
                ], true)
            ) {
                throw ValidationException::withMessages([
                    'transaction' => 'Transaction is no longer eligible for dispute review.',
                ]);
            }

            $dispute->update([
                'status' => 'under_review',
                'reviewed_by' => $officer->id,
                'reviewed_at' => now(),
            ]);

            $this->logEvent(
                $transaction,
                $officer,
                'dispute_review_started',
                'LGU officer started reviewing the dispute.',
                $dispute
            );

            return $dispute->fresh();
        }, 3);
    }

    public function resolve(
        User $officer,
        string $disputeId,
        string $resolution,
        string $notes
    ): TransactionDispute {
        $notes = trim($notes);

        if (!in_array($resolution, ['upheld', 'dismissed'], true)) {
            throw ValidationException::withMessages([
                'resolution' => 'Invalid dispute resolution.',
            ]);
        }

        if (mb_strlen($notes) < 10 || mb_strlen($notes) > 2000) {
            throw ValidationException::withMessages([
                'resolution_notes' =>
                    'Resolution notes must contain between 10 and 2000 characters.',
            ]);
        }

        return DB::transaction(function () use ($officer, $disputeId, $resolution, $notes) {
            $municipalityId = $this->officerMunicipality($officer);

            $dispute = TransactionDispute::query()
                ->whereKey($disputeId)
                ->firstOrFail();

            $transaction = Transaction::query()
                ->whereKey($dispute->transaction_id)
                ->lockForUpdate()
                ->firstOrFail();

            $this->assertMunicipality($transaction, $municipalityId);

            $dispute = TransactionDispute::query()
                ->whereKey($disputeId)
                ->lockForUpdate()
                ->firstOrFail();

            if ($dispute->status !== 'under_review') {
                throw ValidationException::withMessages([
                    'status' => 'Dispute must be under review before resolution.',
                ]);
            }

            if (
                !in_array($transaction->status, [
                    'pending',
                    'pending_lgu_verification',
                ], true)
            ) {
                throw ValidationException::withMessages([
                    'transaction' => 'Transaction is no longer eligible for resolution.',
                ]);
            }

            if ($resolution === 'upheld') {
                $this->cancellationService->cancelForUpheldDispute(
                    $transaction,
                    $officer,
                    $notes
                );
            }

            $dispute->update([
                'status' => $resolution === 'upheld'
                    ? 'resolved'
                    : 'dismissed',
                'resolution' => $resolution,
                'resolution_notes' => $notes,
                'resolved_by' => $officer->id,
                'resolved_at' => now(),
            ]);

            $this->logEvent(
                $transaction,
                $officer,
                'dispute_resolved',
                $notes,
                $dispute
            );

            return $dispute->fresh([
                'transaction',
                'reviewer',
                'resolver',
            ]);
        }, 3);
    }

    private function cancelTransaction(
        Transaction $transaction,
        User $officer,
        string $reason
    ): void {
        $items = $transaction->items()
            ->orderBy('livestock_id')
            ->get();

        if ($items->isEmpty()) {
            throw ValidationException::withMessages([
                'items' => 'Transaction has no livestock items.',
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
                    'items' => 'Livestock reservation is inconsistent.',
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

            $livestock->update([
                'status' => 'available',
            ]);
        }

        // Revoke unused confirmation links.
        $transaction->buyerConfirmations()
            ->where('status', 'pending')
            ->update([
                'status' => 'revoked',
            ]);

        $oldStatus = $transaction->status;

        $transaction->update([
            'status' => 'cancelled',
            'remarks' => $reason,
        ]);

        $transaction->statusLogs()->create([
            'from_status' => $oldStatus,
            'to_status' => 'cancelled',
            'actor_type' => 'lgu',
            'actor_user_id' => $officer->id,
            'remarks' => $reason,
            'metadata' => [
                'event' => 'dispute_upheld_cancellation',
            ],
        ]);
    }

    private function officerMunicipality(User $officer): string
    {
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

        return $profile->municipality_id;
    }

    private function assertMunicipality(
        Transaction $transaction,
        string $municipalityId
    ): void {
        abort_unless(
            $transaction->municipality_id === $municipalityId,
            403,
            'Transaction is outside your municipality.'
        );
    }

    private function logEvent(
        Transaction $transaction,
        User $officer,
        string $event,
        string $remarks,
        TransactionDispute $dispute
    ): void {
        $transaction->statusLogs()->create([
            'from_status' => $transaction->status,
            'to_status' => $transaction->status,
            'actor_type' => 'lgu',
            'actor_user_id' => $officer->id,
            'remarks' => $remarks,
            'metadata' => [
                'event' => $event,
                'dispute_id' => $dispute->id,
            ],
        ]);
    }
}
