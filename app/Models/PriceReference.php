<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PriceReference extends Model
{
    use HasUuids;

    protected $fillable = [
        'municipality_id',
        'barangay_id',
        'species_id',
        'breed_id',
        'price_per_kg',
        'effective_from',
        'effective_to',
        'source',
        'remarks',
        'status',
        'sale_purpose',
        'submitted_by',
        'submitted_at',
        'reviewed_by',
        'reviewed_at',
        'approved_by',
        'approved_at',
    ];

    protected function casts(): array
    {
        return [
            'price_per_kg' => 'decimal:2',
            'effective_from' => 'date',
            'effective_to' => 'date',
            'submitted_at' => 'datetime',
            'reviewed_at' => 'datetime',
            'approved_at' => 'datetime',
        ];
    }

    /**
     * Municipality covered by this price reference.
     */
    public function municipality(): BelongsTo
    {
        return $this->belongsTo(Municipality::class);
    }

    /**
     * Barangay covered by this price reference, if applicable.
     */
    public function barangay(): BelongsTo
    {
        return $this->belongsTo(Barangay::class);
    }

    /**
     * Livestock species associated with this price.
     */
    public function species(): BelongsTo
    {
        return $this->belongsTo(Species::class);
    }

    /**
     * Breed associated with this price, if applicable.
     */
    public function breed(): BelongsTo
    {
        return $this->belongsTo(Breed::class);
    }

    /**
     * User who submitted the price reference.
     */
    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    /**
     * User who reviewed the price reference.
     */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /**
     * User who approved the price reference.
     */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * Validation history of this price reference.
     */
    public function validationLogs(): HasMany
    {
        return $this->hasMany(PriceValidationLog::class);
    }
}