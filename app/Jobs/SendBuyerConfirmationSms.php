<?php

namespace App\Jobs;

use App\Models\Transaction;
use App\Services\IprogSmsService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SendBuyerConfirmationSms implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(
        public string $transactionId,
        public string $token
    ) {
    }

    public function handle(IprogSmsService $sms): void
    {
        $transaction = Transaction::query()
            ->with([
                'farmer.user',
                'items.livestock.species',
            ])
            ->findOrFail($this->transactionId);

        if ($transaction->status !== 'pending') {
            return;
        }

        $confirmation = $transaction->buyerConfirmations()
            ->where('token_hash', hash('sha256', $this->token))
            ->where('status', 'pending')
            ->first();

        if (
            !$confirmation ||
            $confirmation->expires_at->isPast()
        ) {
            return;
        }

        $farmer = $transaction->farmer?->user;

        $farmerName = trim(
            ($farmer?->first_name ?? '') . ' ' .
            ($farmer?->last_name ?? '')
        );

        $species = $transaction->items
            ->pluck('livestock.species.name')
            ->filter()
            ->unique()
            ->implode(', ');

        $confirmationUrl = route('buyer.confirmation.show', [
            'token' => $this->token,
        ]);

        $sms->sendBuyerConfirmation(
            buyerPhone: $transaction->buyer_phone,
            buyerName: $transaction->buyer_name,
            farmerName: $farmerName,
            livestockDescription: $species ?: 'livestock',
            sellingPrice: number_format(
                (float) $transaction->total_selling_price,
                2
            ),
            confirmationUrl: $confirmationUrl
        );
    }
}
