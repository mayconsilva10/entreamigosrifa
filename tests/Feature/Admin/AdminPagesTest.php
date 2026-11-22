<?php

namespace Tests\Feature\Admin;

use App\Models\Customer;
use App\Models\Raffle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Testes de caracterização do painel /admin: cada página GET abre (200) com o título esperado
 * e o dono não acessa rifas de outro cliente. Rede de segurança para o redesign do painel.
 */
class AdminPagesTest extends TestCase
{
    use RefreshDatabase;

    private Customer $owner;

    private Raffle $raffle;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();

        // Dashboard consulta a Loteria Federal; nada de rede nos testes.
        Http::fake(['*' => Http::response([], 200)]);

        $this->owner = Customer::factory()->create(['email' => 'dono@example.com']);
        $buyer = Customer::factory()->create();
        $this->raffle = Raffle::factory()
            ->withNumbers([1, 2], Raffle::RESERVED, $buyer->id)
            ->withNumbers([3], Raffle::UNAVAILABLE, $buyer->id)
            ->create(['customer_id' => $this->owner->id, 'title' => 'Rifa do Teste']);
    }

    /** @return array<string, array{0: string, 1: string}> */
    public static function pages(): array
    {
        return [
            'dashboard' => ['/admin', 'Dashboard'],
            'sortition' => ['/admin/sortition', 'Rifas'],
            'create' => ['/admin/sortition/create', 'Criar Rifa'],
            'edit' => ['/admin/sortition/{id}/edit', 'Editar Rifa'],
            'listSortition' => ['/admin/sortition/{id}/listSortition', 'Gerenciamento da Rifa'],
            'winnerLottery' => ['/admin/sortition/{id}/winnerLottery', 'Ganhadores'],
            'history' => ['/admin/customer/payments-history', 'Histórico de pagamentos'],
            'profile' => ['/admin/customer/profile', 'Perfil'],
            'payment' => ['/admin/customer/payment', 'Pagamentos'],
            'social' => ['/admin/customer/social', 'Rede Social'],
            'notification' => ['/admin/customer/notification', 'Notificações'],
            'password' => ['/admin/customer/getPassword', 'Senha'],
        ];
    }

    #[DataProvider('pages')]
    public function test_owner_opens_admin_page(string $path, string $title): void
    {
        $url = str_replace('{id}', (string) $this->raffle->id, $path);

        $this->actingAs($this->owner, 'admin')
            ->get($url)
            ->assertOk()
            ->assertSee($title);
    }

    public function test_shell_has_landmarks_active_menu_and_no_legacy_theme(): void
    {
        $html = $this->actingAs($this->owner, 'admin')->get('/admin/sortition')->assertOk()->getContent();

        $this->assertStringContainsString('href="#adm-main"', $html);
        $this->assertStringContainsString('<main id="adm-main"', $html);
        $this->assertStringContainsString('class="adm-bottom-nav"', $html);
        $this->assertMatchesRegularExpression('#adm-nav__link is-active" href="[^"]*/admin/sortition"[^>]*aria-current="page"#', $html);
        $this->assertStringContainsString('window.__ADMIN__ = {', $html);
        $this->assertStringNotContainsString('sb-admin-2', $html);
        $this->assertStringNotContainsString('data-toggle=', $html);
        $this->assertSame(1, substr_count($html, 'bootstrap.bundle.min.js'));
    }

    public function test_status_message_becomes_flash_toast(): void
    {
        $this->actingAs($this->owner, 'admin')
            ->get('/admin/sortition?status=created')
            ->assertOk()
            ->assertSee('data-flash="success"', false)
            ->assertSee('Rifa criada com sucesso!');
    }

    public function test_shell_is_translated_to_english(): void
    {
        $this->actingAs($this->owner, 'admin')
            ->withHeader('Accept-Language', 'en')
            ->get('/admin/sortition?lang=en')
            ->assertOk()
            ->assertSee('Skip to content')
            ->assertSee('My Raffles')
            ->assertSee('<html lang="en">', false);
    }

    public function test_raffle_list_shows_owner_raffle(): void
    {
        $this->actingAs($this->owner, 'admin')
            ->get('/admin/sortition')
            ->assertOk()
            ->assertSee('Rifa do Teste');
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/admin')->assertRedirect();
    }

    /** @return array<string, array{0: string}> */
    public static function ownedPages(): array
    {
        return [
            'edit' => ['/admin/sortition/{id}/edit'],
            'list' => ['/admin/sortition/{id}/list'],
            'listSortition' => ['/admin/sortition/{id}/listSortition'],
            'winnerLottery' => ['/admin/sortition/{id}/winnerLottery'],
        ];
    }

    #[DataProvider('ownedPages')]
    public function test_owner_cannot_open_raffle_of_another_customer(string $path): void
    {
        $intruder = Customer::factory()->create();
        $url = str_replace('{id}', (string) $this->raffle->id, $path);

        $this->actingAs($intruder, 'admin')
            ->get($url)
            ->assertRedirect('/admin/sortition');
    }
}
