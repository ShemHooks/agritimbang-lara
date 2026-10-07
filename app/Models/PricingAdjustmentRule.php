<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PricingAdjustmentRule extends Model
{
    use HasUuids;

    protected $fillable = [
        'dimension',
        'species_id',
        'breed_id',
        'category_key',
        'sale_purpose',
        'factor',
        'effective_from',
        'effective_to',
        'source_note',
        'status',
        'approved_by',
    ];

    protected function casts(): array
    {
        return [
            'factor' => 'decimal:4',
            'effective_from' => 'date',
            'effective_to' => 'date',
        ];
    }

    public function species(): BelongsTo
    {
        return $this->belongsTo(Species::class);
    }

    public function breed(): BelongsTo
    {
        return $this->belongsTo(Breed::class);
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}