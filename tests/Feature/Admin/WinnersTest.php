<?php

namespace Tests\Feature\Admin;

use App\Models\Customer;
use App\Models\Raffle;
use App\Services\LoteriaFederal;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Ganhadores e encerramento (/admin/sortition/{id}/winnerLottery): prévia, adicionar/remover ganhador, encerrar.
 */
class WinnersTest extends TestCase
{
    use RefreshDatabase;

    private Customer $owner;

    private Customer $ana;

    private Raffle $raffle;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();

        Http::fake(['servicebus2.caixa.gov.br/*' => Http::response([
            'numero' => 5900,
            'dataApuracao' => '18/11/2026',
            'numeroConcursoProximo' => 5901,
            'listaDezenas' => ['012345', '067800', '099999'],
            'listaMunicipioUFGanhadores' => [['posicao' => 1], ['posicao' => 2], ['posicao' => 3]],
            'listaRateioPremio' => [['valorPremio' => 500000], ['valorPremio' => 27000], ['valorPremio' => 24000]],
        ]), '*' => Http::response([], 200)]);

        $this->owner = Customer::factory()->create();
        $this->ana = Customer::factory()->create(['name' => 'Ana Souza', 'phone' => '18999990001', 'email' => 'ana@example.com']);

        $this->raffle = Raffle::factory()
            ->withNumbers([45], Raffle::UNAVAILABLE, $this->ana->id)
            ->withNumbers([7], Raffle::RESERVED, $this->ana->id)
            ->create(['customer_id' => $this->owner->id, 'title' => 'Moto Honda', 'share_code' => 'moto']);
    }

    private function url(string $path = '/winnerLottery'): string
    {
        return '/admin/sortition/'.$this->raffle->id.$path;
    }

    public function test_page_shows_guided_steps_with_lottery_suggestions(): void
    {
        $this->actingAs($this->owner, 'admin')
            ->get($this->url())
            ->assertOk()
            ->assertSee('Registrar ganhador')
            ->assertSee('Resultado da Loteria Federal')
            ->assertSee('012345')
            ->assertSee('@click="pick(45)"', false)  // 100 números: 2 últimos dígitos
            ->assertSee('@click="pick(100)"', false) // "00" = 100
            ->assertSee('Nenhum ganhador ainda')
            ->assertSee('Registre pelo menos um ganhador para encerrar.')
            ->assertDontSee('jquery.min.js', false);
    }

    public function test_suggested_number_uses_last_digits(): void
    {
        $this->assertSame(45, LoteriaFederal::suggestedNumber('012345', 100));
        $this->assertSame(100, LoteriaFederal::suggestedNumber('067800', 100));
        $this->assertSame(345, LoteriaFederal::suggestedNumber('012345', 500));
        $this->assertNull(LoteriaFederal::suggestedNumber('012999', 500));
        $this->assertSame(1000, LoteriaFederal::suggestedNumber('091000', 1000));
    }

    public function test_preview_shows_buyer_without_saving(): void
    {
        $this->actingAs($this->owner, 'admin')
            ->getJson($this->url('/winnerPreview?number=45'))
            ->assertOk()
            ->assertJson(['state' => 'buyer', 'name' => 'Ana Souza', 'phone' => '(18) 99999-0001', 'status' => 'paid', 'label' => '045']);

        $this->actingAs($this->owner, 'admin')
            ->getJson($this->url('/winnerPreview?number=7'))
            ->assertOk()
            ->assertJson(['state' => 'buyer', 'status' => 'pending']);

        $this->actingAs($this->owner, 'admin')->getJson($this->url('/winnerPreview?number=50'))->assertOk()->assertJson(['state' => 'none']);
        $this->actingAs($this->owner, 'admin')->getJson($this->url('/winnerPreview?number=101'))->assertStatus(422)->assertJson(['state' => 'exceeded']);
        $this->actingAs($this->owner, 'admin')->getJson($this->url('/winnerPreview?number=0'))->assertStatus(422)->assertJson(['state' => 'invalid']);

        $this->assertSame([], $this->raffle->fresh()->winnersList());
    }

    public function test_add_winner_registers_buyer_and_celebrates(): void
    {
        $this->actingAs($this->owner, 'admin')
            ->post($this->url(), ['number' => 45])
            ->assertRedirect($this->url().'?status=created_winner');

        $winners = $this->raffle->fresh()->winnersList();
        $this->assertCount(1, $winners);
        $this->assertSame('Ana Souza', $winners[0]->name);

        $html = $this->actingAs($this->owner, 'admin')->get($this->url().'?status=created_winner')->assertOk()->getContent();
        $this->assertStringContainsString('"celebrate":true', $html);
        $this->assertStringContainsString('adm-winner is-new', $html);
        $this->assertStringContainsString('Ana Souza', $html);
        $this->assertStringContainsString('https://wa.me/5518999990001', $html);

        // Duplicado: prévia e envio recusam
        $this->actingAs($this->owner, 'admin')->getJson($this->url('/winnerPreview?number=45'))->assertStatus(422)->assertJson(['state' => 'duplicate']);
        $this->actingAs($this->owner, 'admin')->post($this->url(), ['number' => 45])->assertRedirect($this->url().'?status=duplicate_register_winner');
        $this->assertCount(1, $this->raffle->fresh()->winnersList());
    }

    public function test_add_number_without_buyer(): void
    {
        $this->actingAs($this->owner, 'admin')->post($this->url(), ['number' => 50])->assertRedirect();

        $this->actingAs($this->owner, 'admin')
            ->get($this->url())
            ->assertSee('Número sem ganhador')
            ->assertSee('Sem comprador');
    }

    public function test_remove_winner_requires_post(): void
    {
        $this->actingAs($this->owner, 'admin')->post($this->url(), ['number' => 45]);
        $this->actingAs($this->owner, 'admin')->post($this->url(), ['number' => 50]);

        $this->actingAs($this->owner, 'admin')->get($this->url('/deleteWinnerLottery/0'))->assertStatus(405);

        $this->actingAs($this->owner, 'admin')
            ->post($this->url('/deleteWinnerLottery/0'))
            ->assertRedirect($this->url().'?status=deleted_winner');

        $winners = $this->raffle->fresh()->winnersList();
        $this->assertCount(1, $winners);
        $this->assertSame(45, (int) $winners[0]->number);
    }

    public function test_finish_requires_winner_and_releases_orphan_numbers(): void
    {
        $numbers = $this->raffle->numbersList();
        foreach ($numbers as $number) {
            if ((int) $number->number === 90) {
                $number->situation = Raffle::RESERVED; // preso sem comprador
                $number->customer_id = null;
            }
        }
        $this->raffle->setNumbersList($numbers);
        $this->raffle->save();

        $this->actingAs($this->owner, 'admin')
            ->post($this->url('/executeFinishLottery'))
            ->assertRedirect($this->url().'?status=winner_not_exists');
        $this->assertSame(Raffle::STATUS_ACTIVE, $this->raffle->fresh()->status);

        $this->actingAs($this->owner, 'admin')->post($this->url(), ['number' => 45]);
        $this->actingAs($this->owner, 'admin')
            ->post($this->url('/executeFinishLottery'))
            ->assertRedirect('/admin/sortition?status=update_finish_sortition');

        $raffle = $this->raffle->fresh();
        $this->assertSame(Raffle::STATUS_FINISHED, $raffle->status);
        $ninety = collect($raffle->numbersList())->first(fn ($n) => (int) $n->number === 90);
        $this->assertSame(Raffle::AVAILABLE, $ninety->situation);

        $this->actingAs($this->owner, 'admin')
            ->get($this->url())
            ->assertSee('Rifa encerrada: os ganhadores aparecem na página pública.');
    }

    public function test_other_owner_cannot_touch_winners(): void
    {
        $intruder = Customer::factory()->create();

        $this->actingAs($intruder, 'admin')->getJson($this->url('/winnerPreview?number=45'))->assertNotFound();
        $this->actingAs($intruder, 'admin')->post($this->url(), ['number' => 45])->assertRedirect('/admin/sortition');
        $this->actingAs($intruder, 'admin')->post($this->url('/executeFinishLottery'))->assertRedirect('/admin/sortition');

        $this->assertSame([], $this->raffle->fresh()->winnersList());
        $this->assertSame(Raffle::STATUS_ACTIVE, $this->raffle->fresh()->status);
    }

    public function test_page_is_translated_to_english(): void
    {
        $this->actingAs($this->owner, 'admin')
            ->withHeader('Accept-Language', 'en')
            ->get($this->url().'?lang=en')
            ->assertOk()
            ->assertSee('Register a winner')
            ->assertSee('Federal Lottery result')
            ->assertSee('No winners yet');
    }
}
