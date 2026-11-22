<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Raffle;
use Illuminate\Support\Carbon;

/**
 * Números do dashboard do painel (/admin): KPIs, fila de ações e progresso das rifas.
 *
 * Regras:
 *  - Arrecadado = números pagos (situação I) × preço, em todas as rifas do cliente, agrupado por moeda.
 *  - A confirmar = números reservados (situação R) nas rifas ativas (qtd + valor por moeda).
 *  - % vendido geral = pagos ÷ total de números das rifas ativas.
 */
final class AdminDashboard
{
    /** Sorteios a até N dias entram na fila "Precisa da sua atenção". */
    public const DRAW_SOON_DAYS = 3;

    /**
     * @return array{
     *     total: int,
     *     active: int,
     *     awaiting: int,
     *     collected: array<string, float>,
     *     paid_numbers: int,
     *     pending: array{count: int, amounts: array<string, float>, raffle_id: int|null},
     *     sold: int,
     *     capacity: int,
     *     sold_percent: int,
     *     has_pix: bool,
     *     raffles: list<array{raffle: Raffle, currency: string, paid: int, reserved: int, free: int, total: int, percent: int, paid_amount: float, reserved_amount: float}>,
     *     attention: list<array{type: string, raffle: Raffle|null, count?: int, amount?: float, currency?: string, days?: int}>
     * }
     */
    public function summary(Customer $customer, ?Carbon $now = null): array
    {
        $today = ($now ?? Carbon::now())->copy()->startOfDay();

        $raffles = Raffle::query()
            ->where('customer_id', $customer->id)
            ->orderBy('id', 'desc')
            ->get(['id', 'title', 'share_code', 'number_price', 'currency', 'numbers', 'winners', 'total_numbers', 'draw_date', 'photos', 'status']);

        $summary = [
            'total' => $raffles->count(),
            'active' => 0,
            'awaiting' => 0,
            'collected' => [],
            'paid_numbers' => 0,
            'pending' => ['count' => 0, 'amounts' => [], 'raffle_id' => null],
            'sold' => 0,
            'capacity' => 0,
            'sold_percent' => 0,
            'has_pix' => trim($customer->settingsArray()['payments']['pix']['pix_key']) !== '',
            'raffles' => [],
            'attention' => [],
        ];

        $pendingByRaffle = [];
        $awaiting = $draws = [];

        foreach ($raffles as $raffle) {
            $stats = self::stats($raffle);
            $currency = $stats['currency'];
            $paid = $stats['paid'];
            $reserved = $stats['reserved'];
            $total = $stats['total'];

            $summary['paid_numbers'] += $paid;
            $summary['collected'][$currency] = ($summary['collected'][$currency] ?? 0) + $stats['paid_amount'];

            if ($raffle->status === Raffle::STATUS_AWAITING_PAYMENT) {
                $summary['awaiting']++;
                $summary['raffles'][] = $stats;
                $awaiting[] = ['type' => 'awaiting', 'raffle' => $raffle];

                continue;
            }

            if ($raffle->status !== Raffle::STATUS_ACTIVE) {
                continue;
            }

            $summary['active']++;
            $summary['raffles'][] = $stats;
            $summary['sold'] += $paid;
            $summary['capacity'] += $total;

            if ($reserved > 0) {
                $summary['pending']['count'] += $reserved;
                $summary['pending']['amounts'][$currency] = ($summary['pending']['amounts'][$currency] ?? 0) + $stats['reserved_amount'];
                $pendingByRaffle[] = ['type' => 'pending', 'raffle' => $raffle, 'count' => $reserved, 'amount' => $stats['reserved_amount'], 'currency' => $currency];
            }

            if ($raffle->draw_date) {
                $days = (int) $today->diffInDays($raffle->draw_date->copy()->startOfDay(), false);
                if ($days <= self::DRAW_SOON_DAYS) {
                    $draws[] = ['type' => $days < 0 ? 'overdue' : 'draw_soon', 'raffle' => $raffle, 'days' => $days];
                }
            }
        }

        ksort($summary['collected']);
        ksort($summary['pending']['amounts']);

        $summary['sold_percent'] = $summary['capacity'] > 0 ? (int) floor($summary['sold'] * 100 / $summary['capacity']) : 0;

        // Mais pendências primeiro; o KPI "A confirmar" aponta para a primeira.
        usort($pendingByRaffle, fn ($a, $b) => $b['count'] <=> $a['count']);
        $summary['pending']['raffle_id'] = $pendingByRaffle[0]['raffle']->id ?? null;

        usort($draws, fn ($a, $b) => $a['days'] <=> $b['days']);

        // Rifas com sorteio mais próximo primeiro; sem data no fim.
        usort($summary['raffles'], fn ($a, $b) => [$a['raffle']->draw_date === null, $a['raffle']->draw_date?->timestamp]
            <=> [$b['raffle']->draw_date === null, $b['raffle']->draw_date?->timestamp]);

        $summary['attention'] = array_merge(
            $pendingByRaffle,
            $awaiting,
            $draws,
            $summary['has_pix'] ? [] : [['type' => 'pix', 'raffle' => null]],
        );

        return $summary;
    }

    /**
     * Números de uma rifa: pagos, reservados, livres, % vendido e valores.
     *
     * @return array{raffle: Raffle, currency: string, paid: int, reserved: int, free: int, total: int, percent: int, paid_amount: float, reserved_amount: float}
     */
    public static function stats(Raffle $raffle): array
    {
        $numbers = $raffle->numbersList();
        $paid = count(Raffle::filterBySituation($numbers, Raffle::UNAVAILABLE));
        $reserved = count(Raffle::filterBySituation($numbers, Raffle::RESERVED));
        $total = (int) $raffle->total_numbers ?: count($numbers);
        $price = (float) $raffle->number_price;

        return [
            'raffle' => $raffle,
            'currency' => strtoupper($raffle->currency ?: (string) config('rifa.default_currency', 'BRL')),
            'paid' => $paid,
            'reserved' => $reserved,
            'free' => max(0, $total - $paid - $reserved),
            'total' => $total,
            'percent' => $total > 0 ? (int) floor($paid * 100 / $total) : 0,
            'paid_amount' => $paid * $price,
            'reserved_amount' => $reserved * $price,
        ];
    }
}
