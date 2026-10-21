<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TransactionDispute extends Model
{
    use HasUuids;

    protected $fillable = [
        'transaction_id',
        'buyer_confirmation_id',
        'reported_by',
        'reporter_type',
        'reason',
        'description',
        'status',
        'reviewed_by',
        'reviewed_at',
        'resolved_by',
        'resolved_at',
        'resolution',
        'resolution_notes',
    ];

    protected function casts(): array
    {
        return [
            'reviewed_at' => 'datetime',
            'resolved_at' => 'datetime',
        ];
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }

    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reported_by');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function resolver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    public function buyerConfirmation(): BelongsTo
    {
        return $this->belongsTo(BuyerConfirmation::class);
    }
}
