<?php

namespace Tests\Feature\Admin;

use App\Models\Customer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Login / cadastro / recuperação do painel: novo layout (tokens, sem jQuery/SB Admin) sem mudar o fluxo.
 */
class LoginPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_login_page_uses_new_layout(): void
    {
        $html = $this->get('/admin/login')->assertOk()->getContent();

        $this->assertStringContainsString('class="adm-auth"', $html);
        $this->assertStringContainsString('Bem-vindo de volta', $html);
        $this->assertStringContainsString('action="'.url('/admin/login').'"', $html);
        $this->assertStringContainsString('action="'.url('/newCustomer').'"', $html);
        $this->assertStringContainsString('name="_token"', $html);
        $this->assertStringContainsString('x-mask="(99) 99999-9999"', $html);
        $this->assertStringNotContainsString('sb-admin-2', $html);
        $this->assertStringNotContainsString('jquery.min.js', $html); // o CSRF do LegacyView ainda cita jQuery.ajaxSetup (etapa 7)
    }

    public function test_invalid_login_shows_translated_error(): void
    {
        Customer::factory()->create(['email' => 'dono@example.com', 'password' => Hash::make('segredo123')]);

        $this->post('/admin/login', ['email' => 'dono@example.com', 'password' => 'errada'])
            ->assertOk()
            ->assertSee('E-mail ou senha inválidos.')
            ->assertSee('value="dono@example.com"', false);

        $this->post('/admin/login', ['email' => 'dono@example.com', 'password' => 'segredo123'])->assertRedirect('/admin');
    }

    public function test_register_flow_is_unchanged(): void
    {
        $this->post('/newCustomer', ['username' => 'Maria', 'email' => 'maria@example.com', 'phone' => '(18) 99999-1234', 'password' => 'segredo123'])
            ->assertRedirect('/admin/login?status=created');
        $this->assertDatabaseHas('customers', ['email' => 'maria@example.com', 'phone' => '18999991234']);

        $this->get('/admin/login?status=created')->assertSee('Conta criada!');
        $this->get('/admin/login?status=duplicated')->assertSee('data-tab="register"', false);
    }

    public function test_login_and_recovery_in_english(): void
    {
        $this->withHeader('Accept-Language', 'en')->get('/admin/login?lang=en')
            ->assertOk()
            ->assertSee('Welcome back')
            ->assertSee('Create account');

        $this->get('/admin/account-recovery?lang=pt_BR')
            ->assertOk()
            ->assertSee('Esqueceu sua senha?')
            ->assertSee('Falar com o suporte')
            ->assertDontSee('sb-admin-2', false);
    }
}
