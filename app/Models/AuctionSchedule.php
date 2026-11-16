<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

class AuctionSchedule extends Model
{
    use HasUuids;

    protected $fillable = [
        'municipality_id', 'auction_house_id', 'barangay_id', 'created_by', 'title',
        'auction_date', 'start_time', 'expected_end_time', 'venue',
        'registration_opens_at', 'registration_closes_at', 'description',
        'status', 'published_at',
    ];

    protected function casts(): array
    {
        return [
            'auction_date' => 'date',
            'registration_opens_at' => 'datetime',
            'registration_closes_at' => 'datetime',
            'published_at' => 'datetime',
        ];
    }

    public function municipality(): BelongsTo { return $this->belongsTo(Municipality::class); }
    public function auctionHouse(): BelongsTo { return $this->belongsTo(AuctionHouse::class); }
    public function barangay(): BelongsTo { return $this->belongsTo(Barangay::class); }
    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
    public function registrations(): HasMany { return $this->hasMany(AuctionRegistration::class); }

    public function registrationIsOpen(): bool
    {
        return $this->status === 'published'
            && now('UTC')->greaterThanOrEqualTo($this->registration_opens_at)
            && now('UTC')->lessThan($this->registration_closes_at)
            && now('UTC')->lessThan(Carbon::parse($this->auction_date->format('Y-m-d').' '.$this->start_time, 'Asia/Manila')->utc());
    }
}
