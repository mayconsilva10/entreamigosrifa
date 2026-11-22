<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Configuração da plataforma (linha id = 1).
 */
class PlatformSetting extends Model
{
    protected $fillable = ['price_per_number'];

    protected function casts(): array
    {
        return ['price_per_number' => 'float'];
    }

    /** Valor cobrado por número gerado para liberar uma rifa. */
    public static function pricePerNumber(): float
    {
        return (float) (static::query()->find(1)?->price_per_number ?? config('rifa.price_per_number'));
    }
}
