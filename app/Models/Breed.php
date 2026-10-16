<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Breed extends Model
{
    use HasUuids;

    protected $fillable = [
        'species_id',
        'name',
        'code',
        'breed_group',
        'description',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    /**
     * Species this breed belongs to.
     */
    public function species(): BelongsTo
    {
        return $this->belongsTo(Species::class);
    }

    public function formulaProfiles(): HasMany
    {
        return $this->hasMany(FormulaProfile::class);
    }

    public function frameReferenceStats(): HasMany
    {
        return $this->hasMany(FrameReferenceStat::class);
    }

    public function pricingAdjustmentRules(): HasMany
    {
        return $this->hasMany(PricingAdjustmentRule::class);
    }
}