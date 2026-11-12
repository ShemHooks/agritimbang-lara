<?php

namespace App\Services;

use App\Jobs\SendBuyerConfirmationSms;
use App\Models\FarmerProfile;
use App\Models\LivestockRecord;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Valuation;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TransactionService
{
    private const ACTIVE_STATUSES = [
        'pending',
        'pending_lgu_verification',
        'verified',
    ];

    /*
    |--------------------------------------------------------------------------
    | FARMER TRANSACTIONS
    |--------------------------------------------------------------------------
    */

    public function createDraft(
        User $user,
        array $data
    ): Transaction {
        $farmer = $this->verifiedFarmer($user);

        return $this->createTransactionDraft(
            $user,
            $farmer,
            $data,
            'farmer'
        );
    }

    public function submit(
        User $user,
        string $transactionId,
        BuyerConfirmationService $confirmationService
    ): Transaction {
        $farmer = $this->verifiedFarmer($user);

        return $this->submitTransactionDraft(
            $user,
            $farmer,
            $transactionId,
            $confirmationService,
            'farmer'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | LGU ENCODER TRANSACTIONS
    |--------------------------------------------------------------------------
    */

    public function createEncoderDraft(
        User $encoder,
        array $data
    ): Transaction {
        $this->assertEncoder($encoder);

        $farmer = FarmerProfile::query()
            ->with('user')
            ->findOrFail($data['farmer_id']);

        $this->assertEncoderCanAccessFarmer(
            $encoder,
            $farmer
        );

        return $this->createTransactionDraft(
            $encoder,
            $farmer,
            $data,
            'lgu_encoder'
        );
    }


    public function submitEncoderDraft(
        User $encoder,
        string $transactionId,
        BuyerConfirmationService $confirmationService
    ): Transaction {
        $this->assertEncoder($encoder);

        return DB::transaction(function () use ($encoder, $transactionId) {
            $transaction = Transaction::query()
                ->whereKey($transactionId)
                ->lockForUpdate()
                ->firstOrFail();

            // Only the encoder who created the draft can submit it.
            if (
                (string) $transaction->encoded_by !==
                (string) $encoder->id
            ) {
                abort(403, 'You cannot submit this transaction.');
            }

            // Prevent submitting an already processed transaction.
            if ($transaction->status !== 'draft') {
                throw ValidationException::withMessages([
                    'status' => 'Only draft transactions can be submitted.',
                ]);
            }

            $farmer = FarmerProfile::query()
                ->with('user')
                ->findOrFail($transaction->farmer_id);

            // Verify that the farmer is still authorized
            // and belongs to the encoder's municipality.
            $this->assertEncoderCanAccessFarmer(
                $encoder,
                $farmer
            );

            if (
                (string) $transaction->municipality_id !==
                (string) $farmer->municipality_id ||
                (string) $transaction->barangay_id !==
                (string) $farmer->barangay_id
            ) {
                throw ValidationException::withMessages([
                    'farmer' => 'Transaction location no longer matches the farmer.',
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
                            'Livestock record no longer exists.',
                    ]);
                }

                if (
                    (string) $livestock->farmer_id !==
                    (string) $farmer->id ||
                    (string) $livestock->municipality_id !==
                    (string) $transaction->municipality_id ||
                    (string) $livestock->barangay_id !==
                    (string) $transaction->barangay_id
                ) {
                    throw ValidationException::withMessages([
                        "items.$index.livestock_id" =>
                            'Livestock ownership or location has changed.',
                    ]);
                }

                if ($livestock->status !== 'available') {
                    throw ValidationException::withMessages([
                        "items.$index.livestock_id" =>
                            'Livestock is no longer available for sale.',
                    ]);
                }

                if (
                    $this->hasActiveTransaction(
                        $livestock->id,
                        $transaction->id
                    )
                ) {
                    throw ValidationException::withMessages([
                        "items.$index.livestock_id" =>
                            'Livestock already belongs to another active transaction.',
                    ]);
                }

                // Reserve livestock until LGU verification.
                $livestock->update([
                    'status' => 'in_transaction',
                ]);
            }

            // Skip buyer confirmation.
            // Send directly to LGU Authority verification.
            $transaction->update([
                'status' => 'pending_lgu_verification',
            ]);

            $this->recordStatus(
                $transaction,
                'draft',
                'pending_lgu_verification',
                $encoder,
                'lgu_encoder',
                'Transaction encoded by LGU Encoder and submitted directly for LGU verification.'
            );

            return $transaction->fresh([
                'farmer',
                'items.livestock',
                'items.valuation',
                'statusLogs',
            ]);
        }, 3);
    }


    /*
    |--------------------------------------------------------------------------
    | SHARED DRAFT CREATION
    |--------------------------------------------------------------------------
    */

    private function createTransactionDraft(
        User $actor,
        FarmerProfile $farmer,
        array $data,
        string $actorType
    ): Transaction {
        $this->validateItems($data);

        return DB::transaction(function () use ($actor, $farmer, $data, $actorType) {
            // Revalidate authorization inside the transaction.
            if ($actorType === 'lgu_encoder') {
                $this->assertEncoderCanAccessFarmer(
                    $actor,
                    $farmer->fresh('user')
                );
            } else {
                $this->assertVerifiedFarmer($actor);
            }


            $transaction = Transaction::create([
                'farmer_id' => $farmer->id,
                'buyer_name' => $data['buyer_name'],
                'buyer_phone' => $data['buyer_phone'] ?? null,
                'municipality_id' => $farmer->municipality_id,
                'barangay_id' => $farmer->barangay_id,
                'transaction_date' => $data['transaction_date'] ?? now(),
                'status' => 'draft',

                'source' => $actorType === 'lgu_encoder'
                    ? 'lgu_encoder'
                    : 'farmer',

                'buyer_confirmation_method' => $actorType === 'lgu_encoder'
                    ? 'in_person'
                    : null,

                'acknowledgement_reference' => $actorType === 'lgu_encoder'
                    ? ($data['acknowledgement_reference'] ?? null)
                    : null,

                'acknowledgement_notes' => $actorType === 'lgu_encoder'
                    ? ($data['acknowledgement_notes'] ?? null)
                    : null,

                'total_reference_value' => 0,
                'total_selling_price' => 0,
                'encoded_by' => $actor->id,
                'remarks' => $data['remarks'] ?? null,
            ]);

            $totalReference = 0;
            $totalSelling = 0;

            // Stable lock ordering reduces deadlock risk.
            $items = collect($data['items'])
                ->sortBy('livestock_id')
                ->values();

            foreach ($items as $index => $item) {
                $livestock = $this->lockEligibleLivestock(
                    $farmer,
                    $item['livestock_id'],
                    $index
                );

                $valuation = $this->getValidValuation(
                    $livestock,
                    $item['valuation_id'],
                    $index
                );

                $sellingPrice = $this->validateSellingPrice(
                    $item['actual_selling_price'],
                    $index
                );

                $referenceValue = (float) $valuation->estimated_value;
                $weight = (float) $valuation->weight_used_kg;

                $difference = round(
                    $sellingPrice - $referenceValue,
                    2
                );

                $deviation = round(
                    ($difference / $referenceValue) * 100,
                    4
                );

                $pricePerKg = round(
                    $sellingPrice / $weight,
                    2
                );

                $transaction->items()->create([
                    'livestock_id' => $livestock->id,
                    'valuation_id' => $valuation->id,
                    'weight_type' => $valuation->weight_type,
                    'weight_used_kg' => $valuation->weight_used_kg,
                    'base_price_per_kg' =>
                        $valuation->base_price_per_kg,
                    'final_price_per_kg' =>
                        $valuation->final_price_per_kg,
                    'reference_value' => $referenceValue,
                    'actual_selling_price' => $sellingPrice,
                    'actual_selling_price_per_kg' => $pricePerKg,
                    'price_difference' => $difference,
                    'percentage_deviation' => $deviation,
                    'valuation_snapshot' =>
                        $this->valuationSnapshot($valuation),
                    'calculation_version' =>
                        $valuation->calculation_version,
                ]);

                $totalReference += $referenceValue;
                $totalSelling += $sellingPrice;
            }

            $transaction->update([
                'total_reference_value' => round(
                    $totalReference,
                    2
                ),
                'total_selling_price' => round(
                    $totalSelling,
                    2
                ),
            ]);

            $this->recordStatus(
                $transaction,
                null,
                'draft',
                $actor,
                $actorType,
                $actorType === 'lgu_encoder'
                ? 'Draft transaction encoded by LGU Encoder.'
                : 'Draft transaction created.'
            );

            return $transaction->load([
                'items.livestock',
                'items.valuation',
                'farmer',
                'statusLogs',
            ]);
        }, 3);
    }

    /*
    |--------------------------------------------------------------------------
    | SHARED TRANSACTION SUBMISSION
    |--------------------------------------------------------------------------
    */

    private function submitTransactionDraft(
        User $actor,
        FarmerProfile $farmer,
        string $transactionId,
        BuyerConfirmationService $confirmationService,
        string $actorType
    ): Transaction {
        return DB::transaction(function () use ($actor, $farmer, $transactionId, $confirmationService, $actorType) {
            $transaction = Transaction::query()
                ->whereKey($transactionId)
                ->lockForUpdate()
                ->firstOrFail();

            if (
                (string) $transaction->farmer_id !==
                (string) $farmer->id
            ) {
                abort(403, 'You cannot submit this transaction.');
            }

            if (
                (string) $transaction->encoded_by !==
                (string) $actor->id
            ) {
                abort(403, 'Only the transaction creator may submit it.');
            }

            if ($actorType === 'lgu_encoder') {
                $this->assertEncoder($actor);
                $this->assertEncoderCanAccessFarmer(
                    $actor,
                    $farmer->fresh('user')
                );
            } else {
                $this->assertVerifiedFarmer($actor);
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
                    (string) $livestock->farmer_id !==
                    (string) $farmer->id ||
                    (string) $livestock->municipality_id !==
                    (string) $transaction->municipality_id ||
                    (string) $livestock->barangay_id !==
                    (string) $transaction->barangay_id
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

                if (
                    $this->hasActiveTransaction(
                        $livestock->id,
                        $transaction->id
                    )
                ) {
                    throw ValidationException::withMessages([
                        "items.$index.livestock_id" =>
                            'Livestock belongs to another active transaction.',
                    ]);
                }

                $livestock->update([
                    'status' => 'in_transaction',
                ]);
            }

            $transaction->update([
                'status' => 'pending',
            ]);

            $this->recordStatus(
                $transaction,
                'draft',
                'pending',
                $actor,
                $actorType,
                'Submitted for buyer confirmation.'
            );

            $issued = $confirmationService->issue(
                $transaction
            );

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

    /*
    |--------------------------------------------------------------------------
    | FARMER & ENCODER AUTHORIZATION
    |--------------------------------------------------------------------------
    */

    private function verifiedFarmer(User $user): FarmerProfile
    {
        $this->assertVerifiedFarmer($user);

        return $user->farmerProfile;
    }

    private function assertVerifiedFarmer(User $user): void
    {
        if (
            !$user->farmerProfile ||
            $user->verification_status !== 'verified'
        ) {
            throw ValidationException::withMessages([
                'farmer' => 'A verified farmer profile is required.',
            ]);
        }
    }

    private function assertEncoder(User $user): void
    {
        abort_unless(
            $user->roles()
                ->where('slug', 'lgu_encoder')
                ->exists(),
            403,
            'Only LGU Encoders may encode transactions.'
        );
    }

    private function assertEncoderCanAccessFarmer(
        User $encoder,
        FarmerProfile $farmer
    ): void {
        $this->assertEncoder($encoder);

        $municipalityId =
            $encoder->lguOfficerProfile?->municipality_id;

        abort_unless(
            $municipalityId &&
            (string) $municipalityId ===
            (string) $farmer->municipality_id,
            403,
            'Farmer is outside your assigned municipality.'
        );

        if (
            !$farmer->user ||
            $farmer->user->verification_status !== 'verified'
        ) {
            throw ValidationException::withMessages([
                'farmer_id' =>
                    'The selected farmer must be verified.',
            ]);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | LIVESTOCK VALIDATION
    |--------------------------------------------------------------------------
    */

    private function validateItems(array $data): void
    {
        if (
            empty($data['items']) ||
            !is_array($data['items'])
        ) {
            throw ValidationException::withMessages([
                'items' => 'At least one livestock item is required.',
            ]);
        }

        $ids = array_column(
            $data['items'],
            'livestock_id'
        );

        if (count($ids) !== count(array_unique($ids))) {
            throw ValidationException::withMessages([
                'items' => 'Duplicate livestock items are not allowed.',
            ]);
        }
    }

    private function lockEligibleLivestock(
        FarmerProfile $farmer,
        string $livestockId,
        int $index
    ): LivestockRecord {
        $livestock = LivestockRecord::query()
            ->whereKey($livestockId)
            ->lockForUpdate()
            ->first();

        if (!$livestock) {
            throw ValidationException::withMessages([
                "items.$index.livestock_id" =>
                    'Livestock record not found.',
            ]);
        }

        if (
            (string) $livestock->farmer_id !==
            (string) $farmer->id
        ) {
            throw ValidationException::withMessages([
                "items.$index.livestock_id" =>
                    'This livestock does not belong to the farmer.',
            ]);
        }

        if (
            (string) $livestock->municipality_id !==
            (string) $farmer->municipality_id ||
            (string) $livestock->barangay_id !==
            (string) $farmer->barangay_id
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

        if ($this->hasActiveTransaction($livestock->id)) {
            throw ValidationException::withMessages([
                "items.$index.livestock_id" =>
                    'Livestock already belongs to an active or completed transaction.',
            ]);
        }

        return $livestock;
    }

    private function hasActiveTransaction(
        string $livestockId,
        ?string $exceptTransactionId = null
    ): bool {
        return DB::table('transaction_items')
            ->join(
                'transactions',
                'transactions.id',
                '=',
                'transaction_items.transaction_id'
            )
            ->where(
                'transaction_items.livestock_id',
                $livestockId
            )
            ->whereIn(
                'transactions.status',
                self::ACTIVE_STATUSES
            )
            ->when(
                $exceptTransactionId,
                fn($query) => $query->where(
                    'transactions.id',
                    '!=',
                    $exceptTransactionId
                )
            )
            ->exists();
    }

    /*
    |--------------------------------------------------------------------------
    | VALUATION & SELLING PRICE
    |--------------------------------------------------------------------------
    */

    private function getValidValuation(
        LivestockRecord $livestock,
        string $valuationId,
        int $index
    ): Valuation {
        $valuation = Valuation::query()
            ->whereKey($valuationId)
            ->where('livestock_id', $livestock->id)
            ->first();

        if (!$valuation) {
            throw ValidationException::withMessages([
                "items.$index.valuation_id" =>
                    'A valid valuation for this livestock is required.',
            ]);
        }

        if (
            !is_finite((float) $valuation->estimated_value) ||
            (float) $valuation->estimated_value <= 0
        ) {
            throw ValidationException::withMessages([
                "items.$index.valuation_id" =>
                    'The selected valuation has an invalid reference value.',
            ]);
        }

        if (
            !is_finite((float) $valuation->weight_used_kg) ||
            (float) $valuation->weight_used_kg <= 0
        ) {
            throw ValidationException::withMessages([
                "items.$index.valuation_id" =>
                    'The selected valuation has an invalid weight.',
            ]);
        }

        return $valuation;
    }

    private function validateSellingPrice(
        mixed $price,
        int $index
    ): float {
        if (
            !is_numeric($price) ||
            !is_finite((float) $price) ||
            (float) $price <= 0
        ) {
            throw ValidationException::withMessages([
                "items.$index.actual_selling_price" =>
                    'Selling price must be greater than zero.',
            ]);
        }

        return round((float) $price, 2);
    }

    private function valuationSnapshot(
        Valuation $valuation
    ): array {
        return [
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
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | TRANSACTION STATUS LOGGING
    |--------------------------------------------------------------------------
    */

    private function recordStatus(
        Transaction $transaction,
        ?string $from,
        string $to,
        User $actor,
        string $actorType,
        string $remarks
    ): void {
        $transaction->statusLogs()->create([
            'from_status' => $from,
            'to_status' => $to,
            'actor_type' => $actorType,
            'actor_user_id' => $actor->id,
            'remarks' => $remarks,
        ]);
    }
}
