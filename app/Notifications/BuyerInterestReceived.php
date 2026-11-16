<?php

namespace App\Notifications;

use App\Models\AuctionSchedule;
use App\Models\Buyer;
use App\Models\LivestockRecord;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;
use LogicException;

class BuyerInterestReceived extends Notification
{
    public function __construct(
        private readonly string $buyerInitials,
        private readonly string $livestockDescription,
        private readonly string $auctionTitle,
        private readonly string $municipality,
        private readonly string $auctionDate,
    ) {
    }

    public static function sendToLivestockFarmer(Buyer $buyer, ?LivestockRecord $livestock, AuctionSchedule $schedule): void
    {
        if (! $livestock) {
            throw new LogicException('Cannot notify the farmer because the interested livestock could not be found.');
        }

        $farmer = $livestock->farmer?->user;
        if (! $farmer) {
            throw new LogicException('Cannot notify the farmer because this livestock has no associated user.');
        }

        $description = trim(implode(' ', array_filter([
            $livestock->species?->name,
            $livestock->breed?->name,
        ]))) ?: 'livestock';

        $farmer->notify(new self(
            self::initials($buyer),
            $description,
            $schedule->title,
            $schedule->municipality?->name ?? 'the municipality',
            $schedule->auction_date?->format('F j, Y') ?? 'the scheduled auction date',
        ));
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'New buyer interest',
            'message' => sprintf(
                'A buyer (%s) expressed interest in your %s for %s in %s on %s.',
                $this->buyerInitials,
                $this->livestockDescription,
                $this->auctionTitle,
                $this->municipality,
                $this->auctionDate,
            ),
        ];
    }

    private static function initials(Buyer $buyer): string
    {
        $parts = preg_split('/\s+/u', trim($buyer->full_name), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        return collect($parts)
            ->map(fn (string $part) => Str::upper(Str::substr($part, 0, 1)))
            ->implode('.');
    }
}
