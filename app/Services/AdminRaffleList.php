<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Raffle;

/**
 * Lista "Minhas Rifas" do painel: filtro por status, busca, ordenação, contagens dos chips e paginação.
 *
 * Os números ficam em JSON (raffles.numbers), então "mais vendida" e as estatísticas são calculadas em PHP;
 * a busca e o dono são filtrados no banco.
 */
final class AdminRaffleList
{
    /** Valor do ?status= → Raffle::STATUS_*. */
    public const STATUSES = [
        'ativa' => Raffle::STATUS_ACTIVE,
        'aguardando' => Raffle::STATUS_AWAITING_PAYMENT,
        'encerrada' => Raffle::STATUS_FINISHED,
    ];

    public const SORTS = ['recent', 'draw', 'sold'];

    public const PER_PAGE = 10;

    /**
     * @param  array{status?: mixed, q?: mixed, sort?: mixed, page?: mixed}  $input
     * @return array{
     *     status: string,
     *     q: string,
     *     sort: string,
     *     page: int,
     *     last_page: int,
     *     per_page: int,
     *     total: int,
     *     counts: array{all: int, ativa: int, aguardando: int, encerrada: int},
     *     owned: int,
     *     items: list<array{raffle: Raffle, currency: string, paid: int, reserved: int, free: int, total: int, percent: int, paid_amount: float, reserved_amount: float}>
     * }
     */
    public function search(Customer $customer, array $input): array
    {
        $status = is_string($input['status'] ?? null) && isset(self::STATUSES[$input['status']]) ? $input['status'] : '';
        $q = is_string($input['q'] ?? null) ? mb_substr(trim($input['q']), 0, 100) : '';
        $sort = in_array($input['sort'] ?? null, self::SORTS, true) ? $input['sort'] : 'recent';

        $owned = Raffle::query()->where('customer_id', $customer->id);
        $ownedCount = (clone $owned)->count();

        $query = (clone $owned)->select(['id', 'title', 'share_code', 'number_price', 'currency', 'numbers', 'winners', 'total_numbers', 'draw_date', 'photos', 'status', 'created_at']);
        if ($q !== '') {
            $like = '%'.$q.'%';
            $query->where(fn ($where) => $where->where('title', 'like', $like)->orWhere('share_code', 'like', $like));
        }

        $raffles = $query->orderByDesc('id')->get();

        $counts = ['all' => $raffles->count()];
        foreach (self::STATUSES as $key => $value) {
            $counts[$key] = $raffles->where('status', $value)->count();
        }

        $items = $raffles
            ->when($status !== '', fn ($list) => $list->where('status', self::STATUSES[$status]))
            ->map(fn (Raffle $raffle) => AdminDashboard::stats($raffle))
            ->values()
            ->all();

        usort($items, match ($sort) {
            // Sorteio mais próximo primeiro; sem data no fim.
            'draw' => fn ($a, $b) => [$a['raffle']->draw_date === null, $a['raffle']->draw_date?->timestamp, -$a['raffle']->id]
                <=> [$b['raffle']->draw_date === null, $b['raffle']->draw_date?->timestamp, -$b['raffle']->id],
            'sold' => fn ($a, $b) => [$b['paid'], $b['percent'], $b['raffle']->id] <=> [$a['paid'], $a['percent'], $a['raffle']->id],
            default => fn ($a, $b) => $b['raffle']->id <=> $a['raffle']->id,
        });

        $total = count($items);
        $lastPage = max(1, (int) ceil($total / self::PER_PAGE));
        $page = is_numeric($input['page'] ?? null) ? max(1, min((int) $input['page'], $lastPage)) : 1;

        return [
            'status' => $status,
            'q' => $q,
            'sort' => $sort,
            'page' => $page,
            'last_page' => $lastPage,
            'per_page' => self::PER_PAGE,
            'total' => $total,
            'counts' => $counts,
            'owned' => $ownedCount,
            'items' => array_slice($items, ($page - 1) * self::PER_PAGE, self::PER_PAGE),
        ];
    }
}
