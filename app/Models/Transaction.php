<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Transaction extends Model
{
    protected $fillable = ['raffle_id', 'gateway', 'status', 'gateway_reference', 'payload'];

    public function raffle(): BelongsTo
    {
        return $this->belongsTo(Raffle::class, 'raffle_id');
    }
}
