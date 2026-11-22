<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\PlatformSetting;
use App\Models\Raffle;
use App\Models\Transaction;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * Integração com a API REST do Mercado Pago (substitui o SDK mercadopago/dx-php 2.x).
 */
class MercadoPago
{
    private const BASE_URL = 'https://api.mercadopago.com';

    public function __construct(private ?string $accessToken = null)
    {
        $this->accessToken ??= config('services.mercadopago.access_token');
    }

    /** Instância usando o token do dono da rifa (pagamento dos colaboradores). */
    public static function forToken(?string $accessToken): self
    {
        return new self($accessToken);
    }

    public function configured(): bool
    {
        return ! empty($this->accessToken);
    }

    /** Valor que o dono paga à plataforma para liberar a rifa. */
    public static function releaseAmount(Raffle $raffle): float
    {
        return round($raffle->total_numbers * PlatformSetting::pricePerNumber(), 2);
    }

    /**
     * Cria um pagamento PIX para liberar a rifa.
     *
     * @return array{qr_code: string, qr_code_base64: string, amountTot: float, expires_at: ?string}
     */
    public function pixForRaffle(Raffle $raffle): array
    {
        $amount = self::releaseAmount($raffle);
        $result = ['qr_code' => '', 'qr_code_base64' => '', 'amountTot' => $amount, 'expires_at' => null];

        if (! $this->configured()) {
            return $result;
        }

        $customer = $raffle->customer;
        $names = explode(' ', trim((string) $customer?->name));

        $body = [
            'transaction_amount' => $amount,
            'description' => 'RIFA',
            'external_reference' => (string) $raffle->id,
            'payment_method_id' => 'pix',
            'binary_mode' => true,
            'payer' => [
                'email' => $customer?->email ?: 'sem-email@entreamigosrifa.com.br',
                'first_name' => $names[0] ?? '',
                'last_name' => end($names) ?: '',
            ],
        ];

        if ($url = self::notificationUrl()) {
            $body['notification_url'] = $url;
        }

        $data = $this->post('/v1/payments', $body);

        $result['qr_code'] = (string) data_get($data, 'point_of_interaction.transaction_data.qr_code', '');
        $result['qr_code_base64'] = (string) data_get($data, 'point_of_interaction.transaction_data.qr_code_base64', '');
        $result['expires_at'] = data_get($data, 'date_of_expiration') ?: null;

        return $result;
    }

    /**
     * Checkout Pro para liberar a rifa (pago pelo dono à plataforma).
     *
     * @return array{public_key: ?string, id_preference: ?string, link: ?string}
     */
    public function checkoutProForRaffle(Raffle $raffle, string $redirectPage, array $params = []): array
    {
        $query = 'redirectPage='.$redirectPage.(isset($params['link_share']) ? '&link_share='.$params['link_share'] : '');
        $back = url('/admin/payments/notificationMp').'?'.$query;

        $body = [
            'items' => [[
                'title' => $raffle->title,
                'quantity' => (int) $raffle->total_numbers,
                'unit_price' => PlatformSetting::pricePerNumber(),
                'currency_id' => 'BRL',
            ]],
            'back_urls' => [
                'success' => $back.'&status=mp_success',
                'failure' => $back.'&status=mp_failure',
                'pending' => $back.'&status=mp_pending',
            ],
            'external_reference' => (string) $raffle->id,
            'binary_mode' => true,
            'statement_descriptor' => 'ENTRE AMIGOS RIFA',
        ];

        $data = $this->createPreference($body, $redirectPage);

        return [
            'public_key' => config('services.mercadopago.public_key'),
            'id_preference' => data_get($data, 'id'),
            'link' => data_get($data, 'init_point'),
        ];
    }

    /**
     * Checkout Pro para o colaborador pagar os números ao dono da rifa (token do dono).
     *
     * @return array{id_preference: ?string, link: ?string}
     */
    public function checkoutProForNumbers(string $shareCode, int $customerId, float $price, int $quantity, string $description): array
    {
        $back = url('/checkoutFinish/'.$shareCode).'?customer_id='.$customerId;

        $body = [
            'items' => [[
                'title' => 'Compra de Números',
                'quantity' => $quantity,
                'unit_price' => $price,
                'currency_id' => 'BRL',
            ]],
            'back_urls' => [
                'success' => $back.'&status_sys=mp_success',
                'failure' => $back.'&status_sys=mp_failure',
                'pending' => $back.'&status_sys=mp_pending',
            ],
            'external_reference' => $description,
            'binary_mode' => true,
            'statement_descriptor' => 'ENTRE AMIGOS RIFA',
        ];

        $data = $this->createPreference($body, 'sortitionWebsite');

        return ['id_preference' => data_get($data, 'id'), 'link' => data_get($data, 'init_point')];
    }

    public function getPayment(string|int $paymentId): array
    {
        return $this->get('/v1/payments/'.$paymentId);
    }

    /**
     * Procura um pagamento aprovado para a rifa; se existir, registra a transação e libera a rifa.
     */
    public function syncApprovedPayment(Raffle $raffle): bool
    {
        $data = $this->get('/v1/payments/search', [
            'status' => 'approved',
            'sort' => 'date_created',
            'criteria' => 'asc',
            'external_reference' => $raffle->id,
        ]);

        $payment = data_get($data, 'results.0');
        if (! $payment || data_get($payment, 'status') !== 'approved') {
            return false;
        }

        self::approveRaffle($raffle, (string) $payment['id'], $payment['transaction_details'] ?? []);

        return true;
    }

    /** Registra a transação aprovada e muda a rifa para "em andamento". */
    public static function approveRaffle(Raffle $raffle, string $paymentId, array $data): void
    {
        Transaction::query()->firstOrCreate(
            ['raffle_id' => $raffle->id, 'gateway_reference' => $paymentId],
            ['gateway' => 'MP', 'status' => 'approved', 'payload' => json_encode($data)]
        );

        $raffle->update(['status' => Raffle::STATUS_ACTIVE]);
    }

    /** O Mercado Pago só aceita notification_url pública (https). */
    public static function notificationUrl(string $query = ''): ?string
    {
        $url = url('/admin/payments/notificationMp').($query ? '?'.$query : '');

        return str_starts_with($url, 'https://') ? $url : null;
    }

    private function createPreference(array $body, string $redirectPage): array
    {
        $body['auto_return'] = 'approved';

        // auto_return exige back_url de sucesso pública; localmente (http) é removido
        if (! str_starts_with((string) $body['back_urls']['success'], 'https://')) {
            unset($body['auto_return']);
        }

        if ($url = self::notificationUrl('redirectPage='.$redirectPage)) {
            $body['notification_url'] = $url;
        }

        return $this->post('/checkout/preferences', $body);
    }

    private function client(): PendingRequest
    {
        return Http::baseUrl(self::BASE_URL)->withToken((string) $this->accessToken)->acceptJson()->timeout(20);
    }

    private function post(string $uri, array $body): array
    {
        if (! $this->configured()) {
            return [];
        }

        try {
            $response = $this->client()->withHeaders(['X-Idempotency-Key' => (string) Str::uuid()])->post($uri, $body);
            if ($response->failed()) {
                Log::warning('Mercado Pago POST '.$uri.' falhou', ['status' => $response->status(), 'body' => $response->json()]);
            }

            return (array) $response->json();
        } catch (Throwable $e) {
            Log::error('Mercado Pago POST '.$uri.': '.$e->getMessage());

            return [];
        }
    }

    private function get(string $uri, array $query = []): array
    {
        if (! $this->configured()) {
            return [];
        }

        try {
            return (array) $this->client()->get($uri, $query)->json();
        } catch (Throwable $e) {
            Log::error('Mercado Pago GET '.$uri.': '.$e->getMessage());

            return [];
        }
    }

    public static function ownerToken(?Customer $owner): string
    {
        return $owner ? $owner->settingsArray()['payments']['mercado_pago']['access_token'] : '';
    }
}
