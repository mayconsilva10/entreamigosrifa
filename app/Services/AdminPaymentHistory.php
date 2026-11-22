<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Transaction;
use Illuminate\Support\Collection;

/**
 * Histórico de pagamentos de liberação ("repasses") do dono: só transações das rifas dele,
 * com filtro por situação (chips com contagem), busca pelo título da rifa e paginação.
 */
final class AdminPaymentHistory
{
    public const PER_PAGE = 15;

    /** Grupos de situação (chips). */
    public const STATUSES = ['approved', 'pending', 'failed'];

    /** Situação do gateway → grupo. */
    public static function group(?string $status): string
    {
        return match ((string) $status) {
            'approved' => 'approved',
            'pending', 'in_process', 'authorized', 'in_mediation' => 'pending',
            default => 'failed',
        };
    }

    /** Valor pago: o que o gateway informou; sem isso, a tarifa atual da rifa. */
    public static function amount(Transaction $transaction): float
    {
        $payload = json_decode((string) $transaction->payload, true) ?: [];
        $amount = data_get($payload, 'total_paid_amount') ?? data_get($payload, 'transaction_amount');

        if (is_numeric($amount)) {
            return (float) $amount;
        }

        return $transaction->raffle ? MercadoPago::releaseAmount($transaction->raffle) : 0.0;
    }

    /**
     * @param  array<string, mixed>  $query
     * @return array{items: Collection<int, Transaction>, total: int, page: int, per_page: int, status: string, q: string, counts: array<string, int>}
     */
    public function search(Customer $customer, array $query): array
    {
        $status = in_array($query['status'] ?? '', self::STATUSES, true) ? (string) $query['status'] : '';
        $q = mb_substr(trim((string) ($query['q'] ?? '')), 0, 100);

        $all = Transaction::query()
            ->with('raffle')
            ->whereHas('raffle', fn ($raffle) => $raffle->where('customer_id', $customer->id))
            ->latest()
            ->latest('id')
            ->get();

        $searched = $q === ''
            ? $all
            : $all->filter(fn (Transaction $t) => mb_stripos((string) $t->raffle?->title, $q) !== false
                || mb_stripos((string) $t->gateway_reference, $q) !== false);

        $counts = ['all' => $searched->count()];
        foreach (self::STATUSES as $key) {
            $counts[$key] = $searched->filter(fn (Transaction $t) => self::group($t->status) === $key)->count();
        }

        $filtered = $status === '' ? $searched : $searched->filter(fn (Transaction $t) => self::group($t->status) === $status);
        $total = $filtered->count();
        $last = max(1, (int) ceil($total / self::PER_PAGE));
        $page = max(1, min((int) ($query['page'] ?? 1), $last));

        return [
            'items' => $filtered->values()->slice(($page - 1) * self::PER_PAGE, self::PER_PAGE)->values(),
            'total' => $total,
            'page' => $page,
            'per_page' => self::PER_PAGE,
            'status' => $status,
            'q' => $q,
            'counts' => $counts,
        ];
    }
}
