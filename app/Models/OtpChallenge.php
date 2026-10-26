<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OtpChallenge extends Model
{
    use HasUuids;

    protected $fillable = ['user_id', 'purpose', 'channel', 'destination', 'code_hash', 'attempts', 'expires_at', 'consumed_at'];
    protected $hidden = ['code_hash'];

    protected function casts(): array
    {
        return ['expires_at' => 'datetime', 'consumed_at' => 'datetime', 'attempts' => 'integer'];
    }

    public function user(): BelongsTo { return $this->belongsTo(User::class); }
}
