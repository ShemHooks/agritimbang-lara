<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuctionRegistration extends Model
{
    use HasUuids;

    protected $fillable = [
        'auction_schedule_id', 'livestock_id', 'farmer_id', 'registered_by',
        'active_livestock_id', 'status', 'registered_at', 'withdrawn_at', 'remarks',
    ];

    protected function casts(): array
    {
        return ['registered_at' => 'datetime', 'withdrawn_at' => 'datetime'];
    }

    public function schedule(): BelongsTo { return $this->belongsTo(AuctionSchedule::class, 'auction_schedule_id'); }
    public function livestock(): BelongsTo { return $this->belongsTo(LivestockRecord::class, 'livestock_id'); }
    public function farmer(): BelongsTo { return $this->belongsTo(FarmerProfile::class, 'farmer_id'); }
    public function registeredBy(): BelongsTo { return $this->belongsTo(User::class, 'registered_by'); }
}
