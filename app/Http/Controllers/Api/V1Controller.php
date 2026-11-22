<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ReserveNumbersRequest;
use App\Models\Customer;
use App\Models\Raffle;
use App\Services\MercadoPago;
use App\Support\RaffleHelpers;
use Firebase\JWT\JWT;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * Endpoints /api/v1 usados pelo JavaScript das páginas (rodam com sessão, grupo "web").
 */
class V1Controller extends Controller
{
    public function details(): array
    {
        return ['name' => 'API - Entre Amigos Rifa', 'version' => 'v1.0.0'];
    }

    /** Gera um JWT (HS256) com e-mail/senha. */
    public function token(Request $request): JsonResponse
    {
        $customer = Customer::query()->where('email', (string) $request->input('email'))->first();

        if (! $request->filled(['email', 'password']) || ! $customer?->password || ! Hash::check((string) $request->input('password'), $customer->password)) {
            return response()->json(['error' => 'O usuário ou senha são inválidos'], 400);
        }

        return response()->json(['publicKey' => JWT::encode(['email' => $customer->email], (string) config('services.jwt.key'), 'HS256')], 201);
    }

    /** Nome do cliente pelo telefone (autopreenchimento no cadastro rápido do painel). */
    public function customerByPhone(Request $request): array
    {
        $customer = Customer::query()->where('phone', Customer::onlyDigitsPhone($request->query('phone')))->first();

        return ['name' => $customer->name ?? ''];
    }

    /**
     * Colaborador clica num número: reserva (D -> R) ou desfaz a própria reserva (R -> D).
     * Resposta no formato esperado pelo JavaScript de website/shared/shared.php.
     */
    public function toggleNumber(Request $request, string $shareCode, int $idCustomer): array
    {
        $visitor = auth('website')->user();

        if (! $visitor || $visitor->id !== $idCustomer) {
            return ['status' => 2, 'error' => __('raffle.register_to_pick')];
        }

        $selectedNumber = (int) $request->input('id', 1);

        return DB::transaction(function () use ($shareCode, $visitor, $selectedNumber) {
            $raffle = Raffle::query()->with('customer')->where('share_code', $shareCode)
                ->where('status', Raffle::STATUS_ACTIVE)->lockForUpdate()->first();

            if (! $raffle) {
                return ['status' => 2, 'error' => __('raffle.not_found')];
            }

            $numbers = $raffle->numbersList();
            $key = array_search($selectedNumber, array_column($numbers, 'number'), true);
            if ($key === false) {
                return ['status' => 2, 'error' => __('raffle.invalid_number')];
            }

            $number = $numbers[$key];
            $response = (array) $number;

            if ($number->situation === Raffle::UNAVAILABLE) {
                return $response + ['status' => 2, 'msg' => __('raffle.number_already_paid', ['number' => $selectedNumber])];
            }

            if ($number->situation === Raffle::AVAILABLE) {
                $number->customer_id = $visitor->id;
                $number->situation = Raffle::RESERVED;
                $number->date = now()->format('Y-m-d H:i');
                $response['status'] = 1;
                $response['msg'] = __('raffle.number_reserved', ['number' => $selectedNumber]);
            } elseif ($number->customer_id == $visitor->id && $number->pay !== 'S') {
                $number->customer_id = null;
                $number->situation = Raffle::AVAILABLE;
                $number->date = null;
                $response['status'] = 1;
                $response['msg'] = __('raffle.number_removed', ['number' => $selectedNumber]);
            } else {
                return $response + ['status' => 2, 'msg' => __('raffle.number_taken')];
            }

            $raffle->setNumbersList($numbers);
            $raffle->save();

            $mine = Raffle::filterByCustomer($numbers, $visitor->id);
            $reservedByMe = array_column(Raffle::filterBySituation($mine, Raffle::RESERVED), 'number');
            $total = round($raffle->number_price * count($reservedByMe), 2);

            return $response + [
                'total_pay' => $total,
                'total_pay_label' => $raffle->formatMoney($total),
                'selecteds_numbers' => $mine,
                'pageHtmlPix' => RaffleHelpers::ownerPix($raffle->customer, $total, implode(',', $reservedByMe)),
            ];
        });
    }

    /**
     * Reserva em lote do carrinho (D -> R) numa única transação.
     * Números já reservados pelo próprio visitante contam como reservados (idempotente);
     * os demais indisponíveis voltam em "conflicts". O total é sempre calculado no servidor.
     */
    public function reserveNumbers(ReserveNumbersRequest $request, string $shareCode): JsonResponse
    {
        $visitor = auth('website')->user();
        $requested = $request->numbers();

        return DB::transaction(function () use ($shareCode, $visitor, $requested) {
            $raffle = Raffle::query()->with('customer')->where('share_code', $shareCode)
                ->where('status', Raffle::STATUS_ACTIVE)->lockForUpdate()->first();

            if (! $raffle) {
                return response()->json(['message' => __('raffle.not_found')], 404);
            }

            $numbers = $raffle->numbersList();
            $positions = array_flip(array_column($numbers, 'number'));
            $reserved = $conflicts = [];
            $changed = false;

            foreach ($requested as $selectedNumber) {
                $number = isset($positions[$selectedNumber]) ? $numbers[$positions[$selectedNumber]] : null;

                if ($number?->situation === Raffle::AVAILABLE) {
                    $number->customer_id = $visitor->id;
                    $number->situation = Raffle::RESERVED;
                    $number->date = now()->format('Y-m-d H:i');
                    $reserved[] = $selectedNumber;
                    $changed = true;
                } elseif ($number?->situation === Raffle::RESERVED && $number->customer_id == $visitor->id) {
                    $reserved[] = $selectedNumber;
                } else {
                    $conflicts[] = $selectedNumber;
                }
            }

            if ($changed) {
                $raffle->setNumbersList($numbers);
                $raffle->save();
            }

            $reservedByMe = array_column(Raffle::filterBySituation(Raffle::filterByCustomer($numbers, $visitor->id), Raffle::RESERVED), 'number');
            $total = round($raffle->number_price * count($reservedByMe), 2);

            return response()->json([
                'reserved' => $reserved,
                'conflicts' => $conflicts,
                'my_reserved' => array_values($reservedByMe),
                'total_pay' => $total,
                'total_pay_label' => $raffle->formatMoney($total),
                'expires_at' => $reserved ? now()->addMinutes(Raffle::RESERVATION_MINUTES)->toIso8601String() : null,
                // Pix do dono atualizado para o total reservado (só quando não há Mercado Pago)
                'pix_html' => $reservedByMe && MercadoPago::ownerToken($raffle->customer) === ''
                    ? RaffleHelpers::ownerPix($raffle->customer, $total, implode(',', $reservedByMe))
                    : null,
            ]);
        });
    }

    public function numbersOfCustomer(string $shareCode, int $idCustomer): array
    {
        $raffle = Raffle::query()->where('share_code', $shareCode)->firstOrFail();
        $numbers = $raffle->numbersList();

        $data = [
            'selecteds' => Raffle::filterByCustomer($numbers, $idCustomer),
            'available' => Raffle::filterBySituation($numbers, Raffle::AVAILABLE),
            'unavailable' => Raffle::filterBySituation($numbers, Raffle::UNAVAILABLE),
            'reserved' => Raffle::filterBySituation($numbers, Raffle::RESERVED),
        ];

        return [
            'statistics' => ['total' => $raffle->total_numbers] + array_map('count', $data),
            'dataNumbers' => $data,
        ];
    }
}
