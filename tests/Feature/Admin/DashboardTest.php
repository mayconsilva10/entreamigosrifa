<?php

namespace Tests\Feature\Admin;

use App\Models\Customer;
use App\Models\Raffle;
use App\Services\AdminDashboard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Dashboard do painel (/admin): KPIs, fila "Precisa da sua atenção", progresso e onboarding.
 */
class DashboardTest extends TestCase
{
    use RefreshDatabase;

    private Customer $owner;

    private Customer $buyer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Http::fake(['*' => Http::response([], 200)]);
        $this->travelTo(Carbon::parse('2026-11-21 10:00:00'));

        $this->owner = Customer::factory()->create(['name' => 'Maria Souza']);
        $this->buyer = Customer::factory()->create();
    }

    /** Ativa A: 3 pagos + 2 reservados a R$ 10; ativa B: 1 pago + 3 reservados a R$ 5; encerrada C: 2 pagos a R$ 20. */
    private function seedRaffles(): array
    {
        $a = Raffle::factory()
            ->withNumbers([3, 4, 5], Raffle::UNAVAILABLE, $this->buyer->id)
            ->withNumbers([1, 2], Raffle::RESERVED, $this->buyer->id)
            ->create(['customer_id' => $this->owner->id, 'title' => 'Rifa A', 'number_price' => 10, 'draw_date' => '2026-11-23 20:00:00']);

        $b = Raffle::factory()
            ->withNumbers([1], Raffle::UNAVAILABLE, $this->buyer->id)
            ->withNumbers([2, 3, 4], Raffle::RESERVED, $this->buyer->id)
            ->create(['customer_id' => $this->owner->id, 'title' => 'Rifa B', 'number_price' => 5, 'draw_date' => '2026-12-20 20:00:00']);

        $c = Raffle::factory()
            ->withNumbers([1, 2], Raffle::UNAVAILABLE, $this->buyer->id)
            ->create(['customer_id' => $this->owner->id, 'title' => 'Rifa C', 'number_price' => 20, 'status' => Raffle::STATUS_FINISHED]);

        return [$a, $b, $c];
    }

    public function test_summary_calculates_kpis_from_paid_and_reserved_numbers(): void
    {
        [, $b] = $this->seedRaffles();
        Raffle::factory()->create(['customer_id' => Customer::factory()->create()->id]); // de outro cliente

        $summary = app(AdminDashboard::class)->summary($this->owner);

        $this->assertSame(['BRL' => 75.0], $summary['collected']);
        $this->assertSame(6, $summary['paid_numbers']);
        $this->assertSame(5, $summary['pending']['count']);
        $this->assertSame(['BRL' => 35.0], $summary['pending']['amounts']);
        $this->assertSame($b->id, $summary['pending']['raffle_id']);
        $this->assertSame(2, $summary['active']);
        $this->assertSame(3, $summary['total']);
        $this->assertSame(4, $summary['sold']);
        $this->assertSame(200, $summary['capacity']);
        $this->assertSame(2, $summary['sold_percent']);
        $this->assertCount(2, $summary['raffles']);
    }

    public function test_dashboard_shows_kpis_attention_queue_and_progress(): void
    {
        [$a, $b] = $this->seedRaffles();

        $response = $this->actingAs($this->owner, 'admin')->get('/admin')->assertOk();

        $response->assertSee('R$ 75,00')
            ->assertSee('x-count-up="75"', false)
            ->assertSee('R$ 35,00 aguardando sua confirmação')
            ->assertSee('adm-kpi--pending', false)
            ->assertSee('href="'.url('/admin/sortition/'.$b->id.'/list').'"', false)
            ->assertSee('Precisa da sua atenção')
            ->assertSee('3 reservas aguardando confirmação (R$ 15,00)')
            ->assertSee('Sorteio em 2 dias')
            ->assertSee('Chave Pix não configurada')
            ->assertSee('Progresso das rifas')
            ->assertSee('3 de 100 vendidos')
            ->assertSee('data-url="'.url('/share/'.$a->share_code).'"', false)
            ->assertDontSee('Rifa C')
            ->assertDontSee('jquery.min.js', false);
    }

    public function test_awaiting_raffle_asks_for_release_payment_and_pix_item_disappears_when_configured(): void
    {
        $this->owner->saveSettings(['payments' => ['pix' => ['type_key' => 'email', 'pix_key' => 'maria@example.com']]]);
        $raffle = Raffle::factory()->awaitingPayment()->create(['customer_id' => $this->owner->id, 'title' => 'Rifa Nova']);

        $this->actingAs($this->owner, 'admin')->get('/admin')
            ->assertOk()
            ->assertSee('Pague a liberação para começar a receber reservas.')
            ->assertSee('href="'.url('/admin/'.$raffle->id.'/checkout').'"', false)
            ->assertSee('Aguardando liberação')
            ->assertDontSee('Chave Pix não configurada');
    }

    public function test_amounts_in_different_currencies_are_grouped_without_count_up(): void
    {
        Raffle::factory()->withNumbers([1], Raffle::UNAVAILABLE, $this->buyer->id)
            ->create(['customer_id' => $this->owner->id, 'number_price' => 10, 'currency' => 'BRL']);
        Raffle::factory()->withNumbers([1, 2], Raffle::UNAVAILABLE, $this->buyer->id)
            ->create(['customer_id' => $this->owner->id, 'number_price' => 5, 'currency' => 'USD']);

        $summary = app(AdminDashboard::class)->summary($this->owner);
        $this->assertSame(['BRL' => 10.0, 'USD' => 10.0], $summary['collected']);

        $this->actingAs($this->owner, 'admin')->get('/admin')
            ->assertOk()
            ->assertSee('R$ 10,00 · ')
            ->assertSee('x-count-up="0" data-format="money"', false);
    }

    public function test_overdue_draw_asks_to_record_winner(): void
    {
        $raffle = Raffle::factory()->create(['customer_id' => $this->owner->id, 'draw_date' => '2026-11-18 20:00:00']);

        $this->actingAs($this->owner, 'admin')->get('/admin')
            ->assertOk()
            ->assertSee('A data do sorteio já passou (18/11/2026)')
            ->assertSee('href="'.url('/admin/sortition/'.$raffle->id.'/winnerLottery').'"', false);
    }

    public function test_first_visit_shows_onboarding_checklist_instead_of_kpis(): void
    {
        $this->actingAs($this->owner, 'admin')->get('/admin')
            ->assertOk()
            ->assertSee('Comece em 3 passos')
            ->assertSee('0 de 3 concluídos')
            ->assertSee('href="'.url('/admin/customer/payment').'"', false)
            ->assertSee('href="'.url('/admin/sortition/create').'"', false)
            ->assertDontSee('adm-kpis', false);
    }

    public function test_dashboard_is_translated_to_english(): void
    {
        $this->seedRaffles();

        $this->actingAs($this->owner, 'admin')
            ->withHeader('Accept-Language', 'en')
            ->get('/admin?lang=en')
            ->assertOk()
            ->assertSee('Collected (paid)')
            ->assertSee('Needs your attention')
            ->assertSee('Draw in 2 days')
            ->assertSee('Raffle progress');
    }
}
