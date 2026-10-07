<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Species extends Model
{
    use HasUuids;

    protected $fillable = [
        'name',
        'code',
        'description',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }


    public function breeds(): HasMany
    {
        return $this->hasMany(Breed::class);
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