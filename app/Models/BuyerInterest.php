<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BuyerInterest extends Model
{
    use HasUuids;

    protected $fillable = [
        'buyer_id',
        'livestock_id',
        'status',
    ];

    public function buyer(): BelongsTo
    {
        return $this->belongsTo(Buyer::class);
    }

    public function livestock(): BelongsTo
    {
        return $this->belongsTo(LivestockRecord::class);
    }
}
