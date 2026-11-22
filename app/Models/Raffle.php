<?php

namespace App\Models;

use App\Support\Money;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Rifa (tabela raffles).
 *
 * A coluna numbers guarda um JSON com um item por número (formato legado, lido pelo JS):
 *   {"pay":"N|S","number":1,"situation":"D|R|I","customer_id":null,"date":null}
 * A coluna winners guarda os números sorteados: [{"number","name","email","phone"}]
 */
class Raffle extends Model
{
    use HasFactory;

    public const STATUS_AWAITING_PAYMENT = 1;

    public const STATUS_ACTIVE = 2;

    public const STATUS_FINISHED = 3;

    /** Disponível */
    public const AVAILABLE = 'D';

    /** Reservado (aguardando pagamento do colaborador) */
    public const RESERVED = 'R';

    /** Indisponível (pago/confirmado) */
    public const UNAVAILABLE = 'I';

    /**
     * Prazo informado ao colaborador para pagar a reserva (minutos).
     * Ainda não há liberação automática: o dono confirma/cancela pelo painel.
     */
    public const RESERVATION_MINUTES = 30;

    protected $fillable = [
        'share_code', 'customer_id', 'total_numbers', 'numbers', 'winners',
        'draw_date', 'number_price', 'currency', 'title', 'description', 'draw_rules', 'photos', 'status',
    ];

    protected function casts(): array
    {
        return [
            'draw_date' => 'datetime',
            'number_price' => 'float',
            'status' => 'integer',
            'total_numbers' => 'integer',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class, 'raffle_id');
    }

    /**
     * Números como lista de objetos (mesmo formato do sistema legado).
     *
     * @return array<int, object>
     */
    public function numbersList(): array
    {
        return json_decode((string) $this->numbers) ?: [];
    }

    public function setNumbersList(array $numbers): void
    {
        $this->numbers = json_encode(array_values($numbers));
    }

    /** Gera a lista inicial de números, todos disponíveis. */
    public static function generateNumbers(int $quantity): array
    {
        $numbers = [];
        for ($i = 1; $i <= $quantity; $i++) {
            $numbers[] = ['pay' => 'N', 'number' => $i, 'situation' => self::AVAILABLE, 'customer_id' => null, 'date' => null];
        }

        return $numbers;
    }

    /** @return array<int, string> */
    public function photosList(): array
    {
        return array_values((array) (json_decode((string) $this->photos, true) ?: []));
    }

    /** @return array<int, object> */
    public function winnersList(): array
    {
        return array_values((array) (json_decode((string) $this->winners) ?: []));
    }

    public static function filterBySituation(array $numbers, string $situation): array
    {
        return array_filter($numbers, fn ($n) => $n->situation === $situation);
    }

    public static function filterByCustomer(array $numbers, $customerId): array
    {
        return array_filter($numbers, fn ($n) => $customerId !== null && $n->customer_id == $customerId);
    }

    /** Número com zeros à esquerda conforme o total da rifa. */
    public function label(int $number): string
    {
        return str_pad((string) $number, strlen((string) $this->total_numbers), '0', STR_PAD_LEFT);
    }

    /** Valor formatado no idioma atual; sem moeda = moeda padrão (config rifa.default_currency). */
    public static function money(float $value, ?string $currency = null): string
    {
        return Money::format($value, $currency);
    }

    /** Valor formatado na moeda desta rifa. */
    public function formatMoney(float $value): string
    {
        return Money::format($value, $this->currency ?: null);
    }
}
