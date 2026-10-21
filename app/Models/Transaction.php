<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Transaction extends Model
{
    use HasUuids;

    protected $fillable = [
        'farmer_id',
        'buyer_name',
        'buyer_phone',
        'municipality_id',
        'barangay_id',
        'transaction_date',
        'status',
        'total_reference_value',
        'total_selling_price',
        'encoded_by',
        'verified_by',
        'verified_at',
        'remarks',
        'source',
        'auction_house_id',
        'buyer_confirmation_method',
        'acknowledgement_reference',
        'acknowledgement_notes',
    ];

    protected function casts(): array
    {
        return [
            'transaction_date' => 'datetime',
            'verified_at' => 'datetime',
            'total_reference_value' => 'decimal:2',
            'total_selling_price' => 'decimal:2',
        ];
    }

    public function farmer(): BelongsTo
    {
        return $this->belongsTo(FarmerProfile::class);
    }

    public function municipality(): BelongsTo
    {
        return $this->belongsTo(Municipality::class);
    }

    public function barangay(): BelongsTo
    {
        return $this->belongsTo(Barangay::class);
    }

    public function encoder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'encoded_by');
    }

    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(TransactionItem::class);
    }

    public function buyerConfirmations(): HasMany
    {
        return $this->hasMany(BuyerConfirmation::class);
    }

    public function statusLogs(): HasMany
    {
        return $this->hasMany(TransactionStatusLog::class);
    }

    public function auctionHouse(): BelongsTo
    {
        return $this->belongsTo(AuctionHouse::class);
    }

    public function disputes(): HasMany
    {
        return $this->hasMany(TransactionDispute::class);
    }
}
