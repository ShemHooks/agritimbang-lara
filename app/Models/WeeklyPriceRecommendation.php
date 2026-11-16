<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class WeeklyPriceRecommendation extends Model
{
    use HasUuids;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'week_start'        => 'date',
            'week_end'          => 'date',
            'observations'      => 'array',
            'used_at'           => 'datetime',
            'recommended_price' => 'decimal:2',
        ];
    }

    public function species()
    {
        return $this->belongsTo(Species::class);
    }

    public function generator()
    {
        return $this->belongsTo(User::class, 'generated_by');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'used_by');
    }

    public function priceReference()
    {
        return $this->belongsTo(PriceReference::class);
    }
}