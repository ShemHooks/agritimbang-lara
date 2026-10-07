<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LivestockRecord extends Model
{
    use HasUuids;

    protected $fillable = [
        'farmer_id',
        'species_id',
        'breed_id',
        'sex',
        'age_months',
        'age_group',
        'reproductive_status',
        'parity',
        'sale_purpose',
        'condition_score',
        'actual_weight_kg',
        'municipality_id',
        'barangay_id',
        'status',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'age_months' => 'integer',
            'parity' => 'integer',
            'condition_score' => 'integer',
            'actual_weight_kg' => 'decimal:2',
        ];
    }

    /**
     * Farmer who owns this livestock.
     */
    public function farmer(): BelongsTo
    {
        return $this->belongsTo(FarmerProfile::class, 'farmer_id');
    }

    /**
     * Species of the livestock.
     */
    public function species(): BelongsTo
    {
        return $this->belongsTo(Species::class);
    }

    /**
     * Breed of the livestock, if specified.
     */
    public function breed(): BelongsTo
    {
        return $this->belongsTo(Breed::class);
    }

    /**
     * Municipality where the livestock is registered.
     */
    public function municipality(): BelongsTo
    {
        return $this->belongsTo(Municipality::class);
    }

    /**
     * Barangay where the livestock is registered.
     */
    public function barangay(): BelongsTo
    {
        return $this->belongsTo(Barangay::class);
    }

    /**
     * User who created the livestock record.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Weight estimation records for this livestock.
     */
    public function weightEstimations(): HasMany
    {
        return $this->hasMany(WeightEstimation::class, 'livestock_id');
    }

    /**
     * Valuation history for this livestock.
     */
    public function valuations(): HasMany
    {
        return $this->hasMany(Valuation::class, 'livestock_id');
    }
}