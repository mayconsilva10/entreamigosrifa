<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Raffle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Date;
use Tests\TestCase;

class SharedRafflePageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    /** @return array<string, mixed> */
    private function raffleDataFrom(string $html): array
    {
        $this->assertSame(1, preg_match('/window\.__RAFFLE__ = (\{.*\});/', $html, $match));

        return json_decode($match[1], true);
    }

    public function test_page_exposes_compact_grid_status_for_the_client(): void
    {
        $visitor = Customer::factory()->create();
        $other = Customer::factory()->create();
        $raffle = Raffle::factory()
            ->withNumbers([2], Raffle::RESERVED, $visitor->id)
            ->withNumbers([3], Raffle::UNAVAILABLE, $other->id)
            ->create(['total_numbers' => 5, 'numbers' => json_encode(Raffle::generateNumbers(5)), 'number_price' => 2.5]);

        $html = $this->actingAs($visitor, 'website')->get("/share/{$raffle->share_code}")->assertOk()->getContent();
        $data = $this->raffleDataFrom($html);

        $this->assertSame('DRUDD', $data['status']);
        $this->assertSame([2], $data['mine']['reserved']);
        $this->assertEquals(2.5, $data['price']);
        $this->assertSame($visitor->id, $data['customer']['id']);
        $this->assertSame(url("/api/v1/reserveNumbers/{$raffle->share_code}"), $data['endpoints']['reserve']);
    }

    public function test_large_raffle_ships_one_status_character_per_number(): void
    {
        $raffle = Raffle::factory()->create(['total_numbers' => 10000, 'numbers' => json_encode(Raffle::generateNumbers(10000))]);

        $html = $this->get("/share/{$raffle->share_code}")->assertOk()->getContent();
        $data = $this->raffleDataFrom($html);

        $this->assertSame(10000, $data['total']);
        $this->assertSame(str_repeat('D', 10000), $data['status']);
        $this->assertLessThan(50, substr_count($html, 'class="rf-num"'), 'a grade deve ser montada no cliente, não no HTML');
    }

    public function test_guest_sees_new_layout_without_forced_registration_or_tabs(): void
    {
        $raffle = Raffle::factory()->create();

        $html = $this->get("/share/{$raffle->share_code}")->assertOk()->getContent();

        $this->assertStringContainsString('x-data="raffleGrid"', $html);
        $this->assertStringContainsString('x-data="raffleCheckout"', $html);
        $this->assertStringContainsString('Escolha ao menos 1 número', $html);
        $this->assertStringNotContainsString('id="fastRegister"', $html);
        $this->assertStringNotContainsString('modal-backdrop', $html);
        $this->assertStringNotContainsString('nav-tabs', $html);
        $this->assertNull($this->raffleDataFrom($html)['customer']);
        $this->assertStringNotContainsString('{{', $html);
    }

    public function test_grid_shows_skeleton_and_animation_hooks(): void
    {
        $raffle = Raffle::factory()->create();

        $html = $this->get("/share/{$raffle->share_code}")->assertOk()->getContent();

        $this->assertStringContainsString('rf-grid--skeleton', $html);
        $this->assertStringContainsString('x-show="!ready"', $html);
        $this->assertStringContainsString('@click="pick(n, $el)"', $html);
        $this->assertStringContainsString('shareRaffle(this)', $html);
        $this->assertStringContainsString("'is-urgent': urgent", $html);
    }

    public function test_hero_shows_draw_countdown_and_sold_percentage(): void
    {
        Date::setTestNow('2026-11-21 10:00:00');
        $buyer = Customer::factory()->create();
        $raffle = Raffle::factory()
            ->withNumbers([1, 2, 3], Raffle::UNAVAILABLE, $buyer->id)
            ->withNumbers([4], Raffle::RESERVED, $buyer->id)
            ->create(['total_numbers' => 10, 'numbers' => json_encode(Raffle::generateNumbers(10)), 'draw_date' => '2026-11-24 20:00:00']);

        $this->get("/share/{$raffle->share_code}")
            ->assertOk()
            ->assertSee('em 3 dias')
            ->assertSee('40% vendido');
    }

    public function test_page_uses_new_microcopy_and_accessibility_hooks(): void
    {
        $raffle = Raffle::factory()
            ->withNumbers([1], Raffle::UNAVAILABLE, Customer::factory()->create()->id)
            ->create(['total_numbers' => 4, 'numbers' => json_encode(Raffle::generateNumbers(4))]);

        $html = $this->get("/share/{$raffle->share_code}")->assertOk()->getContent();

        $this->assertStringContainsString('Escolha seus números da sorte 🍀', $html);
        $this->assertMatchesRegularExpression('/<strong x-text="counts.D">3<\/strong> de <span x-text="total">4<\/span> ainda disponíveis/', $html);
        $this->assertStringContainsString('Surpresinha', $html);
        $this->assertStringContainsString('role="tablist" aria-label="Como escolher seus números"', $html);
        $this->assertStringContainsString('Escolher', $html);
        $this->assertStringNotContainsString('Disponíveis <span', $html);
        $this->assertStringContainsString('Só falta seu WhatsApp para reservar seus números', $html);
        $this->assertStringContainsString('Usamos só para avisar se você ganhar', $html);
        $this->assertStringContainsString('aria-describedby="rf-phone-help"', $html);
        $this->assertStringContainsString('Seus números estão guardados por', $html);
        $this->assertStringContainsString('Compartilhar rifa', $html);
        $this->assertStringContainsString(":aria-pressed=\"state(n) === 'S'\"", $html);
        $this->assertStringContainsString('x-text="liveSummary"', $html);
        $this->assertStringContainsString('<html lang="pt-BR">', $html);
        $this->assertStringNotContainsString('Aleatório', $html);
        $this->assertStringNotContainsString('jquery.min.js', $html);
        $this->assertStringNotContainsString('slick', $html);
    }

    public function test_open_graph_shows_prize_photo_and_price(): void
    {
        $raffle = Raffle::factory()->create([
            'title' => 'Moto 0 km',
            'number_price' => 12.5,
            'photos' => json_encode(['storage/images/raffles/moto.jpg']),
        ]);

        $html = $this->get("/share/{$raffle->share_code}")->assertOk()->getContent();

        $this->assertStringContainsString('<meta property="og:title" content="Moto 0 km · R$ 12,50" />', $html);
        $this->assertMatchesRegularExpression('/og:description" content="R\$ 12,50 por número · Sorteio:/', $html);
        $this->assertStringContainsString('<meta property="og:image" content="'.url('storage/images/raffles/moto.jpg').'" />', $html);
        $this->assertStringContainsString('<meta property="og:url" content="'.url("/share/{$raffle->share_code}").'" />', $html);
        $this->assertStringContainsString('twitter:card', $html);
    }

    public function test_pending_reservation_exposes_checkout_endpoints_and_pix(): void
    {
        $visitor = Customer::factory()->create();
        $raffle = Raffle::factory()->withNumbers([7], Raffle::RESERVED, $visitor->id)->create();

        $html = $this->actingAs($visitor, 'website')->get("/share/{$raffle->share_code}")->assertOk()->getContent();
        $data = $this->raffleDataFrom($html);

        $this->assertSame(url("/checkoutFinish/{$raffle->share_code}"), $data['endpoints']['finish']);
        $this->assertSame(url("/checkout/{$raffle->share_code}"), $data['endpoints']['myNumbers']);
        $this->assertNull($data['endpoints']['mercadoPago']);
        $this->assertMatchesRegularExpression('/<template x-ref="initialPix">\s*\S/', $html);
    }
}
