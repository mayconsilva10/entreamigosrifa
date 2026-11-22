<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Raffle;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Gestão de bilhetes e compradores de uma rifa (/admin/sortition/{id}/listSortition).
 *
 * Alimenta as abas Compradores (uma linha por comprador), Bilhetes (grade) e Histórico,
 * e aplica as ações do dono (confirmar/cancelar reservas em lote, atribuir números).
 *
 * Grade: status = um caractere por número, na ordem (D disponível, R reservado, U pago) — mesmo formato da página pública.
 */
final class AdminRaffleManage
{
    public const TABS = ['buyers', 'tickets', 'history'];

    public const HISTORY_LIMIT = 100;

    private const CODES = [Raffle::AVAILABLE => 'D', Raffle::RESERVED => 'R', Raffle::UNAVAILABLE => 'U'];

    /**
     * Dados da tela (abas Compradores e Bilhetes) para o JavaScript.
     *
     * @return array{total: int, price: float, currency: string, status: string, winners: list<int>, buyers: list<array<string, mixed>>}
     */
    public function payload(Raffle $raffle): array
    {
        $numbers = $raffle->numbersList();
        usort($numbers, fn ($a, $b) => $a->number <=> $b->number);

        return [
            'total' => count($numbers),
            'price' => (float) $raffle->number_price,
            'currency' => strtoupper($raffle->currency ?: (string) config('rifa.default_currency', 'BRL')),
            'status' => self::statusString($numbers),
            'winners' => array_values(array_map(fn ($w) => (int) $w->number, $raffle->winnersList())),
            'buyers' => $this->buyers($raffle, $numbers, $this->customersOf($numbers)),
        ];
    }

    /** @param  array<int, object>  $numbers  já ordenados por número */
    public static function statusString(array $numbers): string
    {
        $status = '';
        foreach ($numbers as $number) {
            $status .= self::CODES[$number->situation] ?? 'U';
        }

        return $status;
    }

    /**
     * Um item por comprador: números pagos/reservados, valores, status agregado (paid|pending|partial)
     * e grupos por data de reserva. Ordem: quem tem reserva pendente primeiro (mais antiga antes), depois por nome.
     *
     * @param  array<int, object>  $numbers
     * @return list<array<string, mixed>>
     */
    public function buyers(Raffle $raffle, array $numbers, Collection $customers): array
    {
        $price = (float) $raffle->number_price;
        $grouped = [];

        foreach ($numbers as $number) {
            if (empty($number->customer_id) || $number->situation === Raffle::AVAILABLE) {
                continue;
            }

            $id = (int) $number->customer_id;
            $paid = $number->situation === Raffle::UNAVAILABLE;
            $grouped[$id] ??= ['paid' => [], 'reserved' => [], 'groups' => []];
            $grouped[$id][$paid ? 'paid' : 'reserved'][] = (int) $number->number;

            $key = ($number->date ?? '').'|'.($paid ? 'paid' : 'pending');
            $grouped[$id]['groups'][$key][] = (int) $number->number;
        }

        $buyers = [];
        foreach ($grouped as $id => $data) {
            /** @var Customer|null $customer */
            $customer = $customers->get($id);
            $reservedDates = [];
            $groups = [];

            foreach ($data['groups'] as $key => $list) {
                [$date, $status] = explode('|', $key);
                $at = self::iso($date);
                $groups[] = ['at' => $at, 'status' => $status, 'numbers' => $list];
                if ($status === 'pending' && $at) {
                    $reservedDates[] = $at;
                }
            }
            usort($groups, fn ($a, $b) => strcmp((string) $a['at'], (string) $b['at']));
            sort($reservedDates);

            $paidCount = count($data['paid']);
            $reservedCount = count($data['reserved']);
            $phone = (string) ($customer?->phone ?? '');

            $buyers[] = [
                'id' => $id,
                'name' => $customer?->name ?: __('admin.manage.unknown_buyer'),
                'phone' => $phone !== '' ? Customer::formatPhone($phone) : '',
                'whatsapp' => self::whatsapp($phone),
                'email' => (string) ($customer?->email ?? ''),
                'paid' => $data['paid'],
                'reserved' => $data['reserved'],
                'status' => match (true) {
                    $reservedCount === 0 => 'paid',
                    $paidCount === 0 => 'pending',
                    default => 'partial',
                },
                'amount' => round(($paidCount + $reservedCount) * $price, 2),
                'pending_amount' => round($reservedCount * $price, 2),
                'reserved_at' => $reservedDates[0] ?? null,
                'groups' => $groups,
            ];
        }

        usort($buyers, function ($a, $b) {
            $aPending = $a['reserved'] !== [];
            $bPending = $b['reserved'] !== [];
            if ($aPending !== $bPending) {
                return $aPending ? -1 : 1;
            }
            if ($aPending && $a['reserved_at'] !== $b['reserved_at']) {
                return strcmp((string) ($a['reserved_at'] ?? '9'), (string) ($b['reserved_at'] ?? '9'));
            }

            return strcasecmp($a['name'], $b['name']);
        });

        return $buyers;
    }

    /**
     * Linha do tempo: reservas agrupadas por comprador/data (com o status atual), pagamentos da liberação e criação da rifa.
     * O sistema não guarda quando o dono confirmou o pagamento: a data é a da reserva.
     *
     * @return list<array{at: Carbon|null, type: string, name?: string, count?: int, numbers?: list<int>, gateway?: string|null, status?: string|null}>
     */
    public function history(Raffle $raffle): array
    {
        $numbers = $raffle->numbersList();
        $customers = $this->customersOf($numbers);
        $events = [];

        foreach ($this->buyers($raffle, $numbers, $customers) as $buyer) {
            foreach ($buyer['groups'] as $group) {
                $events[] = [
                    'at' => $group['at'] ? Carbon::parse($group['at']) : null,
                    'type' => $group['status'] === 'paid' ? 'paid' : 'reserved',
                    'name' => $buyer['name'],
                    'count' => count($group['numbers']),
                    'numbers' => $group['numbers'],
                ];
            }
        }

        foreach ($raffle->transactions()->latest()->limit(self::HISTORY_LIMIT)->get() as $transaction) {
            $events[] = ['at' => $transaction->created_at, 'type' => 'release', 'gateway' => $transaction->gateway, 'status' => $transaction->status];
        }

        if ($raffle->created_at) {
            $events[] = ['at' => $raffle->created_at, 'type' => 'created'];
        }

        usort($events, fn ($a, $b) => ($b['at']?->getTimestamp() ?? 0) <=> ($a['at']?->getTimestamp() ?? 0));

        return array_slice($events, 0, self::HISTORY_LIMIT);
    }

    /**
     * Confirma (reservado → pago) ou cancela (reservado → livre) as reservas dos compradores informados.
     * Só toca em números reservados desta rifa; trava a linha para não competir com reservas da página pública.
     *
     * @param  list<int>  $customerIds
     * @return int quantidade de números alterados
     */
    public function changeReserved(Raffle $raffle, array $customerIds, bool $confirm): int
    {
        $ids = array_flip(array_map('intval', $customerIds));
        if ($ids === []) {
            return 0;
        }

        return DB::transaction(function () use ($raffle, $ids, $confirm) {
            $locked = Raffle::query()->lockForUpdate()->findOrFail($raffle->id);
            $numbers = $locked->numbersList();
            $changed = 0;

            foreach ($numbers as $number) {
                if ($number->situation !== Raffle::RESERVED || ! isset($ids[(int) $number->customer_id])) {
                    continue;
                }

                if ($confirm) {
                    $number->situation = Raffle::UNAVAILABLE;
                    $number->pay = 'S';
                } else {
                    $number->situation = Raffle::AVAILABLE;
                    $number->customer_id = null;
                    $number->date = null;
                }
                $changed++;
            }

            if ($changed > 0) {
                $locked->setNumbersList($numbers);
                $locked->save();
                $raffle->setRawAttributes($locked->getAttributes(), true);
            }

            return $changed;
        });
    }

    /**
     * Reserva para o comprador os números livres escolhidos pelo dono.
     *
     * @param  list<int>  $selected
     * @return array{assigned: list<int>, skipped: list<int>}
     */
    public function assign(Raffle $raffle, Customer $customer, array $selected): array
    {
        $wanted = array_flip(array_map('intval', $selected));

        return DB::transaction(function () use ($raffle, $customer, $wanted) {
            $locked = Raffle::query()->lockForUpdate()->findOrFail($raffle->id);
            $numbers = $locked->numbersList();
            $assigned = [];

            foreach ($numbers as $number) {
                if (isset($wanted[(int) $number->number]) && $number->situation === Raffle::AVAILABLE) {
                    $number->customer_id = $customer->id;
                    $number->situation = Raffle::RESERVED;
                    $number->date = now()->format('Y-m-d H:i');
                    $assigned[] = (int) $number->number;
                }
            }

            if ($assigned !== []) {
                $locked->setNumbersList($numbers);
                $locked->save();
                $raffle->setRawAttributes($locked->getAttributes(), true);
            }

            sort($assigned);

            return ['assigned' => $assigned, 'skipped' => array_values(array_diff(array_keys($wanted), $assigned))];
        });
    }

    /** @param  array<int, object>  $numbers */
    private function customersOf(array $numbers): Collection
    {
        $ids = array_unique(array_filter(array_map(fn ($n) => $n->customer_id ?? null, $numbers)));

        return $ids === [] ? collect() : Customer::query()->whereIn('id', $ids)->get(['id', 'name', 'phone', 'email'])->keyBy('id');
    }

    /** Data legada "Y-m-d H:i" → ISO 8601 (null se vazia/inválida). */
    private static function iso(string $date): ?string
    {
        if ($date === '') {
            return null;
        }

        try {
            return Carbon::parse($date)->toIso8601String();
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Número para wa.me. Telefones ainda são salvos sem DDI (só Brasil); 10–11 dígitos ganham o 55.
     * TODO i18n etapa 3 (telefone E.164) substitui esta regra.
     */
    public static function whatsapp(string $phone): string
    {
        $digits = preg_replace('/\D/', '', $phone);

        return match (true) {
            $digits === '' => '',
            strlen($digits) <= 11 => '55'.$digits,
            default => $digits,
        };
    }
}
