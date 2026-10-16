<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BuyerConfirmation extends Model
{
    use HasUuids;

    protected $fillable = [
        'transaction_id',
        'buyer_phone',
        'token_hash',
        'transaction_snapshot_hash',
        'status',
        'expires_at',
        'confirmed_at',
        'disputed_at',
        'consumed_at',
        'dispute_reason',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'confirmed_at' => 'datetime',
            'disputed_at' => 'datetime',
            'consumed_at' => 'datetime',
        ];
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }
}
