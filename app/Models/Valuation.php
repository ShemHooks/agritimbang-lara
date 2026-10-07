<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Valuation extends Model
{
    use HasUuids;

    protected $fillable = [
        'livestock_id',
        'price_reference_id',
        'weight_estimation_id',
        'weight_type',
        'weight_used_kg',
        'base_price_per_kg',
        'breed_factor',
        'age_factor',
        'reproductive_factor',
        'frame_factor',
        'condition_factor',
        'final_price_per_kg',
        'estimated_value',
        'adjustment_breakdown',
        'calculation_snapshot',
        'calculation_version',
        'calculated_by',
    ];

    protected function casts(): array
    {
        return [
            'weight_used_kg' => 'decimal:4',
            'base_price_per_kg' => 'decimal:2',
            'breed_factor' => 'decimal:4',
            'age_factor' => 'decimal:4',
            'reproductive_factor' => 'decimal:4',
            'frame_factor' => 'decimal:4',
            'condition_factor' => 'decimal:4',
            'final_price_per_kg' => 'decimal:2',
            'estimated_value' => 'decimal:2',
            'adjustment_breakdown' => 'array',
            'calculation_snapshot' => 'array',
        ];
    }

    public function livestock(): BelongsTo
    {
        return $this->belongsTo(
            LivestockRecord::class,
            'livestock_id'
        );
    }

    public function priceReference(): BelongsTo
    {
        return $this->belongsTo(PriceReference::class);
    }

    public function weightEstimation(): BelongsTo
    {
        return $this->belongsTo(WeightEstimation::class);
    }

    public function calculator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'calculated_by');
    }
}