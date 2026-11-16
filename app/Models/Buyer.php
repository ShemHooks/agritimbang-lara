<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

class Buyer extends Model
{
    use HasUuids;

    protected $fillable = [
        'first_name',
        'last_name',
        'mobile_number',
        'buyer_type',
        'account_code',
        'auction_id',
        'municipality_id',
        'attendance_intention',
        'contact_consent',
        'status',
        'is_temporary',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'contact_consent' => 'boolean',
            'is_temporary' => 'boolean',
            'expires_at' => 'datetime',
        ];
    }

    public function auction(): BelongsTo
    {
        return $this->belongsTo(AuctionSchedule::class, 'auction_id');
    }

    public function municipality(): BelongsTo
    {
        return $this->belongsTo(Municipality::class);
    }

    public function interests(): HasMany
    {
        return $this->hasMany(BuyerInterest::class, 'buyer_id');
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->lte(Carbon::now('Asia/Manila'));
    }

    public function getFullNameAttribute(): string
    {
        return trim(($this->first_name ?? '') . ' ' . ($this->last_name ?? '')) ?: 'Buyer';
    }
}
