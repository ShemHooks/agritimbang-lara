<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FormulaProfile extends Model
{
    use HasUuids;

    protected $fillable = [
        'formula_code',
        'name',
        'species_id',
        'breed_id',
        'required_inputs',
        'applicability',
        'version',
        'source',
        'is_fallback',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'required_inputs' => 'array',
            'applicability' => 'array',
            'is_fallback' => 'boolean',
            'is_active' => 'boolean',
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

    public function weightEstimations(): HasMany
    {
        return $this->hasMany(WeightEstimation::class);
    }
}