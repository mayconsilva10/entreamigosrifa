<?php

namespace Tests\Feature\Admin;

use App\Models\Customer;
use App\Models\Raffle;
use App\Models\Transaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Pagamento da liberação (/admin/{id}/checkout), verificação de status e histórico de pagamentos
 * (/admin/customer/payments-history).
 */
class PaymentsTest extends TestCase
{
    use RefreshDatabase;

    private Customer $owner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->owner = Customer::factory()->create();
    }

    private function transaction(Raffle $raffle, string $status, array $payload = []): Transaction
    {
        return Transaction::query()->create([
            'raffle_id' => $raffle->id,
            'gateway' => 'MP',
            'status' => $status,
            'gateway_reference' => 'PAY-'.$raffle->id.'-'.$status,
            'payload' => json_encode($payload),
        ]);
    }

    public function test_history_lists_only_owner_transactions(): void
    {
        Http::fake(['*' => Http::response([], 200)]);
        $mine = Raffle::factory()->create(['customer_id' => $this->owner->id, 'title' => 'Minha Moto']);
        $other = Raffle::factory()->create(['title' => 'Rifa Alheia']);
        $this->transaction($mine, 'approved', ['total_paid_amount' => 12.5]);
        $this->transaction($other, 'approved', ['total_paid_amount' => 99]);

        $this->actingAs($this->owner, 'admin')
            ->get('/admin/customer/payments-history')
            ->assertOk()
            ->assertSee('Histórico de pagamentos')
            ->assertSee('Minha Moto')
            ->assertSee('Mercado Pago')
            ->assertSee('R$ 12,50')
            ->assertSee('data-status="paid"', false)
            ->assertDontSee('Rifa Alheia')
            ->assertDontSee('PAY-'.$other->id, false);
    }

    public function test_history_filters_searches_and_paginates(): void
    {
        Http::fake(['*' => Http::response([], 200)]);
        $moto = Raffle::factory()->create(['customer_id' => $this->owner->id, 'title' => 'Moto']);
        $carro = Raffle::factory()->create(['customer_id' => $this->owner->id, 'title' => 'Carro']);
        $this->transaction($moto, 'approved');
        $this->transaction($carro, 'pending');

        $html = $this->actingAs($this->owner, 'admin')->get('/admin/customer/payments-history?status=pending')->assertOk()->getContent();
        $this->assertStringContainsString('Carro', $html);
        $this->assertStringNotContainsString('>Moto<', $html);
        $this->assertMatchesRegularExpression('#Pendentes<span class="adm-chip__count">1</span>#', $html);

        $this->actingAs($this->owner, 'admin')
            ->get('/admin/customer/payments-history?q=moto')
            ->assertSee('>Moto<', false)
            ->assertDontSee('>Carro<', false);

        $this->actingAs($this->owner, 'admin')
            ->get('/admin/customer/payments-history?q=nada')
            ->assertSee('Nenhum pagamento encontrado');

        for ($i = 0; $i < 15; $i++) {
            $this->transaction($moto, 'approved')->update(['gateway_reference' => 'X'.$i]);
        }
        $this->actingAs($this->owner, 'admin')
            ->get('/admin/customer/payments-history?page=2')
            ->assertOk()
            ->assertSee('Mostrando 16–17 de 17');
    }

    public function test_history_empty_state_and_english(): void
    {
        $this->actingAs($this->owner, 'admin')->get('/admin/customer/payments-history')->assertSee('Nenhum pagamento ainda');

        $this->actingAs($this->owner, 'admin')
            ->withHeader('Accept-Language', 'en')
            ->get('/admin/customer/payments-history?lang=en')
            ->assertSee('Payment history')
            ->assertSee('No payments yet');
    }

    public function test_checkout_shows_summary_pix_and_status_states(): void
    {
        config(['services.mercadopago.access_token' => 'TEST-token', 'rifa.price_per_number' => 0.5]);
        Http::fake([
            'api.mercadopago.com/v1/payments/search*' => Http::response(['results' => []]),
            'api.mercadopago.com/v1/payments' => Http::response([
                'date_of_expiration' => now()->addDay()->toIso8601String(),
                'point_of_interaction' => ['transaction_data' => ['qr_code' => 'PIX-COPIA-E-COLA', 'qr_code_base64' => 'QUJD']],
            ]),
            'api.mercadopago.com/checkout/preferences' => Http::response(['id' => 'PREF-1']),
            '*' => Http::response([], 200),
        ]);
        $raffle = Raffle::factory()->awaitingPayment()->create(['customer_id' => $this->owner->id, 'title' => 'Moto']);

        $this->actingAs($this->owner, 'admin')
            ->get('/admin/'.$raffle->id.'/checkout')
            ->assertOk()
            ->assertSee('Resumo do pedido')
            ->assertSee('R$ 0,50')
            ->assertSee('R$ 50,00')
            ->assertSee('value="PIX-COPIA-E-COLA"', false)
            ->assertSee('x-copy=', false)
            ->assertSee('Aguardando pagamento')
            ->assertSee('Já paguei, verificar')
            ->assertSee('data-preference="PREF-1"', false)
            ->assertDontSee('alert(', false);

        $this->actingAs($this->owner, 'admin')
            ->getJson('/admin/'.$raffle->id.'/checkout/status')
            ->assertOk()
            ->assertJson(['status' => 'pending', 'redirect' => null]);
    }

    public function test_checkout_status_approves_paid_raffle(): void
    {
        config(['services.mercadopago.access_token' => 'TEST-token']);
        Http::fake(['api.mercadopago.com/v1/payments/search*' => Http::response([
            'results' => [['id' => 777, 'status' => 'approved', 'transaction_details' => ['total_paid_amount' => 50]]],
        ])]);
        $raffle = Raffle::factory()->awaitingPayment()->create(['customer_id' => $this->owner->id]);

        $this->actingAs($this->owner, 'admin')
            ->getJson('/admin/'.$raffle->id.'/checkout/status')
            ->assertOk()
            ->assertJson(['status' => 'paid', 'redirect' => url('/admin/sortition?status=mp_approved')]);

        $this->assertSame(Raffle::STATUS_ACTIVE, $raffle->fresh()->status);
        $this->assertDatabaseHas('transactions', ['raffle_id' => $raffle->id, 'gateway_reference' => '777']);

        $this->actingAs($this->owner, 'admin')
            ->get('/admin/customer/payments-history')
            ->assertSee('R$ 50,00');
    }

    public function test_checkout_status_is_owner_only(): void
    {
        $raffle = Raffle::factory()->awaitingPayment()->create();

        $this->actingAs($this->owner, 'admin')->getJson('/admin/'.$raffle->id.'/checkout/status')->assertNotFound();
    }

    public function test_preview_opens_public_page_or_explains_awaiting(): void
    {
        $active = Raffle::factory()->create(['customer_id' => $this->owner->id, 'share_code' => 'ativa']);
        $awaiting = Raffle::factory()->awaitingPayment()->create(['customer_id' => $this->owner->id, 'share_code' => 'nova']);

        $this->actingAs($this->owner, 'admin')->get('/admin/sortition/viewSortition/ativa')->assertRedirect('/share/ativa');

        $this->actingAs($this->owner, 'admin')
            ->get('/admin/sortition/viewSortition/nova')
            ->assertOk()
            ->assertSee('A página pública abre depois da liberação')
            ->assertSee('href="'.url('/admin/'.$awaiting->id.'/checkout').'"', false);

        $this->assertNotNull($active);
    }
}
