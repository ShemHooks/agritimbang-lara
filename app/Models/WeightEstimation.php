<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WeightEstimation extends Model
{
    use HasUuids;

    protected $fillable = [
        'livestock_id',
        'formula_profile_id',
        'heart_girth_cm',
        'body_length_cm',
        'rump_height_cm',
        'estimated_weight_kg',
        'formula_code',
        'formula_version',
        'estimate_confidence',
        'warnings',
        'frame_category',
        'frame_z',
        'reference_mean_body_length_cm',
        'reference_sd_body_length_cm',
        'reference_n',
        'reference_group',
        'frame_stats_version',
        'calculated_by',
    ];

    protected function casts(): array
    {
        return [
            'heart_girth_cm' => 'decimal:2',
            'body_length_cm' => 'decimal:2',
            'rump_height_cm' => 'decimal:2',
            'estimated_weight_kg' => 'decimal:4',
            'frame_z' => 'decimal:4',
            'reference_mean_body_length_cm' => 'decimal:4',
            'reference_sd_body_length_cm' => 'decimal:4',
            'reference_n' => 'integer',
            'warnings' => 'array',
        ];
    }

    public function livestock(): BelongsTo
    {
        return $this->belongsTo(
            LivestockRecord::class,
            'livestock_id'
        );
    }

    public function formulaProfile(): BelongsTo
    {
        return $this->belongsTo(FormulaProfile::class);
    }

    public function calculator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'calculated_by');
    }
}