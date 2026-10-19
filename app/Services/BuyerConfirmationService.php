<?php

namespace App\Services;

use App\Models\BuyerConfirmation;
use App\Models\Transaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class BuyerConfirmationService
{
    private const TOKEN_LIFETIME_HOURS = 24;

    /**
     * Generate a confirmation token for a submitted transaction.
     *
     * The returned raw token must only be used to build
     * the SMS URL. Never store or log it.
     *
     * Caller should send the SMS after DB commit.
     */
    public function issue(Transaction $transaction): array
    {
        return DB::transaction(function () use ($transaction) {
            $sale = Transaction::query()
                ->whereKey($transaction->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($sale->status !== 'pending') {
                throw ValidationException::withMessages([
                    'transaction' =>
                        'Transaction must be awaiting buyer confirmation.',
                ]);
            }

            if (!$sale->buyer_phone) {
                throw ValidationException::withMessages([
                    'buyer_phone' =>
                        'Buyer phone number is required.',
                ]);
            }

            if (!$sale->items()->exists()) {
                throw ValidationException::withMessages([
                    'items' =>
                        'Transaction must contain livestock items.',
                ]);
            }

            $existing = $sale->buyerConfirmations()
                ->whereIn('status', ['confirmed', 'disputed'])
                ->exists();

            if ($existing) {
                throw ValidationException::withMessages([
                    'confirmation' =>
                        'Transaction has already been confirmed.',
                ]);
            }

            $rawToken = bin2hex(random_bytes(32));

            // Revoke any previous links, including disputed ones.
            $sale->buyerConfirmations()
                ->where('status', 'pending')
                ->update(['status' => 'revoked']);

            $confirmation = $sale->buyerConfirmations()->create([
                'buyer_phone' => $sale->buyer_phone,
                'token_hash' => hash('sha256', $rawToken),
                'transaction_snapshot_hash' =>
                    $this->snapshotHash($sale),
                'status' => 'pending',
                'expires_at' => now()->addHours(
                    self::TOKEN_LIFETIME_HOURS
                ),
            ]);

            return [
                'confirmation_id' => $confirmation->id,
                'token' => $rawToken,
                'expires_at' => $confirmation->expires_at,
            ];
        }, 3);
    }

    /**
     * Read transaction details without consuming the token.
     */
    public function preview(string $token): array
    {
        $confirmation = $this->findValidConfirmation($token);

        $sale = $confirmation->transaction;

        return [
            'transaction_id' => $sale->id,
            'buyer_name' => $sale->buyer_name,
            'seller_name' => trim(
                ($sale->farmer?->user?->first_name ?? '') . ' ' .
                ($sale->farmer?->user?->last_name ?? '')
            ),
            'transaction_date' => $sale->transaction_date,
            'total_selling_price' => $sale->total_selling_price,
            'currency' => 'PHP',
            'items' => $sale->items->map(fn($item) => [
                'livestock_id' => $item->livestock_id,
                'species' => $item->livestock?->species?->name,
                'selling_price' => $item->actual_selling_price,
            ])->values()->all(),
            'expires_at' => $confirmation->expires_at,
        ];
    }

    /**
     * Buyer explicitly confirms the transaction.
     */
    public function confirm(string $token): Transaction
    {
        return DB::transaction(function () use ($token) {
            $confirmation = $this->lockConfirmation($token);

            $this->assertUsable($confirmation);

            $sale = $confirmation->transaction;

            $confirmation->update([
                'status' => 'confirmed',
                'confirmed_at' => now(),
                'consumed_at' => now(),
            ]);

            $sale->update([
                'status' => 'pending_lgu_verification',
            ]);

            $sale->statusLogs()->create([
                'from_status' => 'pending',
                'to_status' => 'pending_lgu_verification',
                'actor_type' => 'buyer',
                'actor_user_id' => null,
                'remarks' => 'Buyer confirmed the transaction.',
            ]);

            return $sale->fresh();
        }, 3);
    }

    /**
     * Buyer disputes the transaction.
     *
     * Keep the transaction pending but block verification.
     * A farmer/LGU must resolve the dispute separately.
     */
    public function dispute(
        string $token,
        string $reason
    ): BuyerConfirmation {
        $reason = trim($reason);

        if ($reason === '' || mb_strlen($reason) > 2000) {
            throw ValidationException::withMessages([
                'reason' => 'A valid dispute reason is required.',
            ]);
        }

        return DB::transaction(function () use ($token, $reason) {
            $confirmation = $this->lockConfirmation($token);

            $this->assertUsable($confirmation);

            $confirmation->update([
                'status' => 'disputed',
                'disputed_at' => now(),
                'consumed_at' => now(),
                'dispute_reason' => $reason,
            ]);

            $confirmation->transaction->statusLogs()->create([
                'from_status' => 'pending',
                'to_status' => 'pending',
                'actor_type' => 'buyer',
                'actor_user_id' => null,
                'remarks' => 'Buyer disputed the transaction.',
                'metadata' => [
                    'event' => 'buyer_disputed',
                    'confirmation_id' => $confirmation->id,
                ],
            ]);

            return $confirmation->fresh();
        }, 3);
    }

    private function findValidConfirmation(
        string $token
    ): BuyerConfirmation {
        $confirmation = BuyerConfirmation::query()
            ->where('token_hash', hash('sha256', $token))
            ->with([
                'transaction.farmer.user',
                'transaction.items.livestock.species',
            ])
            ->first();

        if (!$confirmation) {
            throw ValidationException::withMessages([
                'token' => 'Invalid confirmation link.',
            ]);
        }

        $this->assertUsable($confirmation);

        return $confirmation;
    }

    private function lockConfirmation(
        string $token
    ): BuyerConfirmation {
        // Lock the parent first, then the token row.
        // This follows the same lock order as issue().
        $id = BuyerConfirmation::query()
            ->where('token_hash', hash('sha256', $token))
            ->value('transaction_id');

        if (!$id) {
            throw ValidationException::withMessages([
                'token' => 'Invalid confirmation link.',
            ]);
        }

        $sale = Transaction::query()
            ->whereKey($id)
            ->lockForUpdate()
            ->firstOrFail();

        $confirmation = BuyerConfirmation::query()
            ->where('transaction_id', $sale->id)
            ->where('token_hash', hash('sha256', $token))
            ->lockForUpdate()
            ->first();

        if (!$confirmation) {
            throw ValidationException::withMessages([
                'token' => 'Invalid confirmation link.',
            ]);
        }

        $confirmation->setRelation('transaction', $sale);

        return $confirmation;
    }

    private function assertUsable(
        BuyerConfirmation $confirmation
    ): void {
        if (
            $confirmation->status !== 'pending' ||
            $confirmation->consumed_at !== null
        ) {
            throw ValidationException::withMessages([
                'token' =>
                    'This confirmation link has already been used or revoked.',
            ]);
        }

        if ($confirmation->expires_at->isPast()) {
            throw ValidationException::withMessages([
                'token' => 'This confirmation link has expired.',
            ]);
        }

        $sale = $confirmation->transaction;

        if ($sale->status !== 'pending') {
            throw ValidationException::withMessages([
                'transaction' =>
                    'Transaction is no longer awaiting confirmation.',
            ]);
        }

        if (
            $confirmation->buyer_phone !== $sale->buyer_phone ||
            !hash_equals(
                $confirmation->transaction_snapshot_hash,
                $this->snapshotHash($sale)
            )
        ) {
            throw ValidationException::withMessages([
                'transaction' =>
                    'Transaction details have changed. A new link is required.',
            ]);
        }
    }

    /**
     * Deterministic fingerprint of buyer-visible sale details.
     */
    private function snapshotHash(Transaction $sale): string
    {
        $sale->load([
            'items',
            'farmer.user',
            'items.livestock.species',
        ]);

        $items = $sale->items
            ->sortBy('id')
            ->map(fn($item) => [
                'id' => $item->id,
                'livestock_id' => $item->livestock_id,
                'species_id' => $item->livestock?->species_id,
                'valuation_id' => $item->valuation_id,
                'selling_price' => $item->actual_selling_price,
            ])
            ->values()
            ->all();

        $snapshot = [
            'transaction_id' => $sale->id,
            'farmer_id' => $sale->farmer_id,
            'buyer_name' => $sale->buyer_name,
            'buyer_phone' => $sale->buyer_phone,
            'transaction_date' =>
                $sale->transaction_date?->toIso8601String(),
            'total_selling_price' => $sale->total_selling_price,
            'items' => $items,
        ];

        return hash(
            'sha256',
            json_encode($snapshot, JSON_THROW_ON_ERROR)
        );
    }
}
