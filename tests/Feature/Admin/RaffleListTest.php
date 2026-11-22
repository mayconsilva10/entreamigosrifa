<?php

namespace Tests\Feature\Admin;

use App\Models\Customer;
use App\Models\Raffle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Lista "Minhas Rifas" (/admin/sortition): filtros, busca, contagens, ordenação, paginação e modais.
 */
class RaffleListTest extends TestCase
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

        $this->owner = Customer::factory()->create();
        $this->buyer = Customer::factory()->create();
    }

    /** 2 ativas, 1 aguardando liberação, 1 encerrada (+1 de outro dono). */
    private function seedRaffles(): void
    {
        Raffle::factory()
            ->withNumbers([1, 2, 3], Raffle::UNAVAILABLE, $this->buyer->id)
            ->withNumbers([4, 5], Raffle::RESERVED, $this->buyer->id)
            ->create(['customer_id' => $this->owner->id, 'title' => 'Moto Honda', 'share_code' => 'moto-honda', 'draw_date' => '2026-12-20 20:00:00']);
        Raffle::factory()
            ->withNumbers([1, 2, 3, 4, 5, 6, 7, 8], Raffle::UNAVAILABLE, $this->buyer->id)
            ->create(['customer_id' => $this->owner->id, 'title' => 'Cesta de Natal', 'share_code' => 'cesta-natal', 'draw_date' => '2026-11-25 20:00:00']);
        Raffle::factory()->awaitingPayment()
            ->create(['customer_id' => $this->owner->id, 'title' => 'Smart TV', 'share_code' => 'tv-55']);
        Raffle::factory()
            ->create(['customer_id' => $this->owner->id, 'title' => 'Bicicleta', 'share_code' => 'bike', 'status' => Raffle::STATUS_FINISHED]);

        Raffle::factory()->create(['title' => 'Rifa de outro dono', 'share_code' => 'outro']);
    }

    private function list(string $query = ''): string
    {
        return $this->actingAs($this->owner, 'admin')->get('/admin/sortition'.$query)->assertOk()->getContent();
    }

    /** Títulos na ordem em que aparecem na tabela. */
    private function titles(string $html): array
    {
        preg_match_all('#class="adm-rl__title"[^>]*>([^<]+)</a>#', $html, $matches);

        return $matches[1];
    }

    public function test_lists_all_owner_raffles_including_closed_without_jquery(): void
    {
        $this->seedRaffles();

        $html = $this->list();

        $this->assertSame(['Bicicleta', 'Smart TV', 'Cesta de Natal', 'Moto Honda'], $this->titles($html));
        $this->assertStringNotContainsString('Rifa de outro dono', $html);
        $this->assertStringNotContainsString('jquery.min.js', $html);
        $this->assertStringNotContainsString('$.post', $html);
        $this->assertStringContainsString('x-data="raffleList"', $html);
        $this->assertStringContainsString('Mostrando 1–4 de 4', $html);
    }

    public function test_filters_by_status(): void
    {
        $this->seedRaffles();

        $this->assertSame(['Cesta de Natal', 'Moto Honda'], $this->titles($this->list('?status=ativa')));
        $this->assertSame(['Smart TV'], $this->titles($this->list('?status=aguardando')));
        $this->assertSame(['Bicicleta'], $this->titles($this->list('?status=encerrada')));
        // Valor desconhecido = todas
        $this->assertCount(4, $this->titles($this->list('?status=xyz')));
    }

    public function test_searches_by_title_or_share_code(): void
    {
        $this->seedRaffles();

        $this->assertSame(['Moto Honda'], $this->titles($this->list('?q=honda')));
        $this->assertSame(['Smart TV'], $this->titles($this->list('?q=tv-55')));
        $this->assertSame([], $this->titles($this->list('?q=outro')));
    }

    public function test_chips_show_counts_and_keep_search(): void
    {
        $this->seedRaffles();

        $html = $this->list();
        $this->assertMatchesRegularExpression('#adm-chip is-active"[^>]*aria-current="true">Todas<span class="adm-chip__count">4</span>#', $html);
        $this->assertStringContainsString('Ativas<span class="adm-chip__count">2</span>', $html);
        $this->assertStringContainsString('Aguardando liberação<span class="adm-chip__count">1</span>', $html);
        $this->assertStringContainsString('Encerradas<span class="adm-chip__count">1</span>', $html);

        // Contagens acompanham a busca e os links dos chips mantêm o ?q=
        $html = $this->list('?q=a&status=ativa');
        $this->assertStringContainsString('Ativas<span class="adm-chip__count">2</span>', $html);
        $this->assertStringContainsString('href="'.url('/admin/sortition').'?status=encerrada&amp;q=a"', $html);
        $this->assertStringContainsString('name="status" value="ativa"', $html);
    }

    public function test_sorts_by_closest_draw_and_best_selling(): void
    {
        $this->seedRaffles();

        $this->assertSame(['Cesta de Natal', 'Moto Honda', 'Bicicleta', 'Smart TV'], $this->titles($this->list('?sort=draw')));
        $this->assertSame(['Cesta de Natal', 'Moto Honda', 'Bicicleta', 'Smart TV'], $this->titles($this->list('?sort=sold')));
    }

    public function test_row_shows_status_price_sold_pending_and_actions(): void
    {
        $this->seedRaffles();
        $moto = Raffle::query()->where('share_code', 'moto-honda')->first();

        $html = $this->list('?q=honda');

        $this->assertStringContainsString('data-status="active"', $html);
        $this->assertStringContainsString('R$ 10,00', $html);
        $this->assertStringContainsString('<strong>3</strong>/100', $html);
        $this->assertStringContainsString('href="'.url('/admin/sortition/'.$moto->id.'/list').'"', $html); // badge pendentes
        $this->assertStringContainsString('20/12/2026 20:00', $html);
        $this->assertStringContainsString('Sorteio em 29 dias', $html);
        $this->assertStringContainsString('href="'.url('/admin/sortition/'.$moto->id.'/listSortition').'"', $html);
        $this->assertStringContainsString('data-url="'.url('/share/moto-honda').'"', $html);
        // Ativa com números vendidos: encerrar sim, excluir não
        $this->assertStringContainsString('Encerrar', $html);
        $this->assertStringNotContainsString("openDialog('delete'", $html);

        // Aguardando liberação e sem números escolhidos: pagar e excluir
        $html = $this->list('?status=aguardando');
        $this->assertStringContainsString('Pagar liberação', $html);
        $this->assertStringContainsString("openDialog('delete'", $html);
    }

    public function test_paginates_keeping_filters(): void
    {
        Raffle::factory()->count(12)->sequence(fn ($sequence) => ['title' => 'Rifa '.($sequence->index + 1)])
            ->create(['customer_id' => $this->owner->id]);

        $html = $this->list('?status=ativa&page=2');

        $this->assertStringContainsString('Mostrando 11–12 de 12', $html);
        $this->assertCount(2, $this->titles($html));
        $this->assertStringContainsString('href="'.url('/admin/sortition').'?status=ativa" rel="prev"', $html);
        $this->assertStringContainsString('aria-current="page"', $html);
    }

    public function test_empty_states(): void
    {
        $this->assertStringContainsString('Você ainda não criou nenhuma rifa', $this->list());

        $this->seedRaffles();
        $html = $this->list('?q=inexistente');
        $this->assertStringContainsString('Nenhuma rifa encontrada', $html);
        $this->assertStringContainsString('Limpar filtros', $html);
    }

    public function test_list_is_translated_to_english(): void
    {
        $this->seedRaffles();

        $this->actingAs($this->owner, 'admin')
            ->get('/admin/sortition?lang=en')
            ->assertOk()
            ->assertSee('Search by title or code…')
            ->assertSee('Awaiting release')
            ->assertSee('Showing 1–4 of 4');
    }

    public function test_custom_link_is_updated_via_json(): void
    {
        $this->seedRaffles();
        $moto = Raffle::query()->where('share_code', 'moto-honda')->first();
        $other = Raffle::query()->where('share_code', 'outro')->first();
        $post = fn (array $data) => $this->actingAs($this->owner, 'admin')->postJson('/api/v1/sortition/newCodeShared', $data);

        $post(['idSortitionEditLink' => $moto->id, 'newCodeShare' => 'Minha Moto 2026'])
            ->assertOk()
            ->assertJson(['status' => 'success', 'code' => 'minha-moto-2026', 'url' => url('/share/minha-moto-2026')]);
        $this->assertSame('minha-moto-2026', $moto->fresh()->share_code);

        $post(['idSortitionEditLink' => $moto->id, 'newCodeShare' => 'cesta-natal'])
            ->assertJson(['status' => 'error', 'message' => 'O código de compartilhamento já existe!']);
        $post(['idSortitionEditLink' => $moto->id, 'newCodeShare' => '  '])
            ->assertJson(['status' => 'error']);
        $post(['idSortitionEditLink' => $other->id, 'newCodeShare' => 'roubado'])
            ->assertJson(['status' => 'error', 'message' => 'Rifa não encontrada!']);
        $this->assertSame('outro', $other->fresh()->share_code);
    }

    public function test_delete_dialog_form_removes_awaiting_raffle(): void
    {
        $this->seedRaffles();
        $tv = Raffle::query()->where('share_code', 'tv-55')->first();

        $this->actingAs($this->owner, 'admin')
            ->post('/admin/sortition/delete', ['idDeleteSortition' => $tv->id])
            ->assertRedirect('/admin/sortition?status=deleted');

        $this->assertNull($tv->fresh());
        $this->assertStringContainsString('Rifa excluída com sucesso!', $this->list('?status=deleted'));
    }
}
