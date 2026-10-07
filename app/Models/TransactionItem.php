<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TransactionItem extends Model
{
    use HasUuids;

    protected $fillable = [
        'transaction_id',
        'livestock_id',
        'valuation_id',
        'weight_type',
        'weight_used_kg',
        'base_price_per_kg',
        'final_price_per_kg',
        'reference_value',
        'actual_selling_price',
        'actual_selling_price_per_kg',
        'price_difference',
        'percentage_deviation',
        'valuation_snapshot',
        'calculation_version',
    ];

    protected function casts(): array
    {
        return [
            'weight_used_kg' => 'decimal:4',
            'base_price_per_kg' => 'decimal:2',
            'final_price_per_kg' => 'decimal:2',
            'reference_value' => 'decimal:2',
            'actual_selling_price' => 'decimal:2',
            'actual_selling_price_per_kg' => 'decimal:2',
            'price_difference' => 'decimal:2',
            'percentage_deviation' => 'decimal:4',
            'valuation_snapshot' => 'array',
        ];
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }

    public function livestock(): BelongsTo
    {
        return $this->belongsTo(
            LivestockRecord::class,
            'livestock_id'
        );
    }

    public function valuation(): BelongsTo
    {
        return $this->belongsTo(Valuation::class);
    }
}