<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FrameReferenceStat extends Model
{
    use HasUuids;

    protected $fillable = [
        'species_id',
        'breed_id',
        'age_group',
        'sex',
        'sample_size',
        'mean_body_length_cm',
        'sd_body_length_cm',
        'version',
        'computed_at',
    ];

    protected function casts(): array
    {
        return [
            'sample_size' => 'integer',
            'mean_body_length_cm' => 'decimal:4',
            'sd_body_length_cm' => 'decimal:4',
            'computed_at' => 'datetime',
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
}