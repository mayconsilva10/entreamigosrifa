<?php

namespace Tests\Feature\Admin;

use App\Models\Customer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Configurações da conta: navegação lateral, Pix por tipo, conta bancária (tom-select), toggles e senha — sem jQuery.
 */
class SettingsTest extends TestCase
{
    use RefreshDatabase;

    private Customer $owner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Http::fake(['*' => Http::response([], 200)]);
        $this->owner = Customer::factory()->create();
    }

    /** @return array<string, array{0: string}> */
    public static function pages(): array
    {
        return [
            'profile' => ['/admin/customer/profile'],
            'password' => ['/admin/customer/getPassword'],
            'payment' => ['/admin/customer/payment'],
            'social' => ['/admin/customer/social'],
            'notification' => ['/admin/customer/notification'],
        ];
    }

    #[DataProvider('pages')]
    public function test_settings_pages_have_side_navigation_and_no_legacy_scripts(string $path): void
    {
        $html = $this->actingAs($this->owner, 'admin')->get($path)->assertOk()->getContent();

        $this->assertStringContainsString('class="adm-settings"', $html);
        $this->assertMatchesRegularExpression('#adm-settings__link is-active" href="[^"]*'.preg_quote($path, '#').'" aria-current="page"#', $html);
        $this->assertStringContainsString('data-adm-submit', $html);
        $this->assertStringNotContainsString('jquery.min.js', $html);
        $this->assertStringNotContainsString('inputmask', strtolower($html));
        $this->assertStringNotContainsString('vanilla-masker', strtolower($html));
        $this->assertStringNotContainsString('select2', strtolower($html));
    }

    /** @return array<string, array{0: string, 1: string, 2: string}> */
    public static function pixKeys(): array
    {
        return [
            'cpf mascarado' => ['document', '123.456.789-09', '12345678909'],
            'telefone mascarado' => ['phone', '(18) 99999-0001', '18999990001'],
            'email mantém pontos' => ['email', 'joao.silva-rifa@example.com', 'joao.silva-rifa@example.com'],
            'aleatória mantém hífens' => ['random', '123e4567-e89b-12d3-a456-426614174000', '123e4567-e89b-12d3-a456-426614174000'],
        ];
    }

    #[DataProvider('pixKeys')]
    public function test_save_pix_key_by_type(string $type, string $posted, string $stored): void
    {
        $this->actingAs($this->owner, 'admin')
            ->post('/admin/customer/setPayments', ['type_key' => $type, 'pix_key' => $posted])
            ->assertRedirect('/admin/customer/payment?status=payments_updated');

        $pix = $this->owner->refresh()->settingsArray()['payments']['pix'];
        $this->assertSame($type, $pix['type_key']);
        $this->assertSame($stored, $pix['pix_key']);
    }

    public function test_unknown_pix_type_is_discarded(): void
    {
        $this->actingAs($this->owner, 'admin')
            ->post('/admin/customer/setPayments', ['type_key' => '<script>', 'pix_key' => 'x'])
            ->assertRedirect();

        $this->assertSame('', $this->owner->refresh()->settingsArray()['payments']['pix']['type_key']);
    }

    public function test_bank_account_is_saved_and_listed_in_searchable_select(): void
    {
        $this->actingAs($this->owner, 'admin')
            ->post('/admin/customer/setPayments', [
                'type_key' => 'email', 'pix_key' => 'dono@example.com',
                'bank' => '260 - Nubank', 'type_account' => 'checkingAccount', 'holder' => 'Dono da Rifa',
                'document' => '123.456.789-09', 'agency' => '0001', 'account' => '12345-6',
            ])
            ->assertRedirect();

        $bank = $this->owner->refresh()->settingsArray()['payments']['bank'];
        $this->assertSame('260 - Nubank', $bank['bank']);
        $this->assertSame('checkingAccount', $bank['type_account']);
        $this->assertSame('12345678909', $bank['document']);

        $html = $this->actingAs($this->owner, 'admin')->get('/admin/customer/payment')->assertOk()->getContent();
        $this->assertStringContainsString('x-data="bankSelect"', $html);
        $this->assertStringContainsString('<option value="260 - Nubank" selected>', $html);
        $this->assertStringContainsString('<option value="checkingAccount" selected>', $html);
        $this->assertStringContainsString('<option value="email" selected>', $html);
    }

    public function test_access_token_is_kept_when_hash_is_posted_back(): void
    {
        $settings = $this->owner->settingsArray();
        $settings['payments']['mercado_pago']['access_token'] = 'APP_USR-secret';
        $this->owner->saveSettings($settings);

        $html = $this->actingAs($this->owner, 'admin')->get('/admin/customer/payment')->assertOk()->getContent();
        $this->assertStringNotContainsString('APP_USR-secret', $html);

        $this->actingAs($this->owner, 'admin')
            ->post('/admin/customer/setPayments', ['access_token' => hash('sha256', 'APP_USR-secret')])
            ->assertRedirect();

        $this->assertSame('APP_USR-secret', $this->owner->refresh()->settingsArray()['payments']['mercado_pago']['access_token']);
    }

    public function test_social_toggle_off_clears_network(): void
    {
        $settings = $this->owner->settingsArray();
        $settings['social']['instagram'] = 'https://instagram.com/antigo';
        $this->owner->saveSettings($settings);

        $html = $this->actingAs($this->owner, 'admin')->get('/admin/customer/social')->assertOk()->getContent();
        $this->assertStringContainsString('role="switch"', $html);
        $this->assertStringContainsString('value="https://instagram.com/antigo"', $html);

        // Interruptor desligado = campo desabilitado = não enviado
        $this->actingAs($this->owner, 'admin')
            ->post('/admin/customer/social', ['social_facebook' => ' https://facebook.com/rifa '])
            ->assertRedirect('/admin/customer/social?status=social_updated');

        $social = $this->owner->refresh()->settingsArray()['social'];
        $this->assertSame('https://facebook.com/rifa', $social['facebook']);
        $this->assertSame('', $social['instagram']);
    }

    public function test_password_change_and_mismatch(): void
    {
        $this->actingAs($this->owner, 'admin')
            ->post('/admin/customer/setPassword', ['new_pass' => 'Nova#Senha123', 'confir_new_pass' => 'outra'])
            ->assertRedirect('/admin/customer/getPassword?status=password_not_updated');

        $this->actingAs($this->owner, 'admin')
            ->post('/admin/customer/setPassword', ['new_pass' => 'Nova#Senha123', 'confir_new_pass' => 'Nova#Senha123'])
            ->assertRedirect('/admin/customer/getPassword?status=password_updated');

        $this->assertTrue(Hash::check('Nova#Senha123', $this->owner->refresh()->password));

        $html = $this->actingAs($this->owner, 'admin')->get('/admin/customer/getPassword?status=password_updated')->assertOk()->getContent();
        $this->assertStringContainsString('Senha alterada.', $html);
        $this->assertStringContainsString('type="password"', $html);
    }

    public function test_settings_are_translated_to_english(): void
    {
        $html = $this->actingAs($this->owner, 'admin')->get('/admin/customer/payment?lang=en')->assertOk()->getContent();

        $this->assertStringContainsString('Bank account', $html);
        $this->assertStringContainsString('Random key', $html);
        $this->assertStringContainsString('Settings sections', $html);
    }
}
