<?php

namespace Tests\Feature;

use App\Http\Middleware\SetLocale;
use App\Models\Customer;
use App\Models\Raffle;
use App\Support\Money;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Date;
use Tests\TestCase;

class LocalizationTest extends TestCase
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

    public function test_browser_language_selects_english(): void
    {
        $raffle = Raffle::factory()->create(['number_price' => 12.5, 'currency' => 'USD']);

        $html = $this->withHeader('Accept-Language', 'en-US,en;q=0.9')
            ->get("/share/{$raffle->share_code}")->assertOk()->getContent();

        $this->assertStringContainsString('<html lang="en">', $html);
        $this->assertStringContainsString('Pick your lucky numbers', $html);
        $this->assertStringContainsString('$12.50', $html);
        $this->assertStringContainsString('og:locale" content="en_US"', $html);
        $this->assertStringNotContainsString('Escolha seus números', $html);

        $data = $this->raffleDataFrom($html);
        $this->assertSame('en', $data['locale']);
        $this->assertSame('USD', $data['currency']);
        $this->assertSame('Pick at least 1 number', $data['i18n']['pick_at_least_one']);
    }

    public function test_unsupported_browser_language_falls_back_to_app_locale(): void
    {
        $raffle = Raffle::factory()->create();

        $this->withHeader('Accept-Language', 'ja-JP')
            ->get("/share/{$raffle->share_code}")
            ->assertOk()
            ->assertSee('<html lang="pt-BR">', false);
    }

    public function test_lang_query_switches_language_and_persists_in_cookie(): void
    {
        $raffle = Raffle::factory()->create();

        $this->get("/share/{$raffle->share_code}?lang=en")
            ->assertOk()
            ->assertSee('Pick your lucky numbers')
            ->assertCookie(SetLocale::COOKIE, 'en');

        // Cookie vence o Accept-Language
        $this->withCookie(SetLocale::COOKIE, 'en')
            ->get("/share/{$raffle->share_code}")
            ->assertSee('Pick your lucky numbers');
    }

    public function test_invalid_lang_query_is_ignored(): void
    {
        $raffle = Raffle::factory()->create();

        $this->get("/share/{$raffle->share_code}?lang=xx")
            ->assertOk()
            ->assertSee('Escolha seus números')
            ->assertCookieMissing(SetLocale::COOKIE);
    }

    public function test_page_has_locale_switcher(): void
    {
        $raffle = Raffle::factory()->create();

        $html = $this->get("/share/{$raffle->share_code}")->getContent();

        // Dentro da área da foto (canto superior), com o idioma atual marcado e link para os demais
        $this->assertMatchesRegularExpression('/class="rf-hero__media".*?<details class="rf-locale">.*?<\/details>\s*<\/div>/s', $html);
        $this->assertStringContainsString('aria-label="Idioma: Português (Brasil)"', $html);
        $this->assertStringContainsString('class="rf-locale__item is-current" aria-current="true" lang="pt-BR"', $html);
        $this->assertStringContainsString('lang=en', $html);
        $this->assertSame(1, substr_count($html, 'class="rf-locale"'));
    }

    public function test_draw_date_and_countdown_are_localized(): void
    {
        Date::setTestNow('2026-05-10 12:00:00');
        $raffle = Raffle::factory()->create(['draw_date' => '2026-05-13 20:00:00']);

        $this->withHeader('Accept-Language', 'en')->get("/share/{$raffle->share_code}")
            ->assertSee('May 13, 2026 8:00 PM')
            ->assertSee('in 3 days');

        $this->withHeader('Accept-Language', 'pt-BR')->get("/share/{$raffle->share_code}")
            ->assertSee('13/05/2026 20:00')
            ->assertSee('em 3 dias');
    }

    public function test_api_messages_follow_the_visitor_language(): void
    {
        $raffle = Raffle::factory()->create();

        $this->withHeader('Accept-Language', 'en')
            ->postJson("/api/v1/reserveNumbers/{$raffle->share_code}", ['numbers' => [1]])
            ->assertStatus(401)
            ->assertJson(['message' => 'Enter your phone number to reserve the tickets.']);
    }

    public function test_quick_signup_stores_visitor_language(): void
    {
        $raffle = Raffle::factory()->create();

        $this->withHeader('Accept-Language', 'en')
            ->postJson('/customer/fastRegister', ['phone' => '(18) 99999-0000', 'username' => 'John', 'codeShare' => $raffle->share_code])
            ->assertCreated();

        $this->assertSame('en', Customer::query()->where('phone', '18999990000')->value('locale'));
    }

    public function test_reservation_total_uses_raffle_currency(): void
    {
        $visitor = Customer::factory()->create();
        $raffle = Raffle::factory()->create(['number_price' => 2.5, 'currency' => 'EUR']);

        $this->actingAs($visitor, 'website')->withHeader('Accept-Language', 'en')
            ->postJson("/api/v1/reserveNumbers/{$raffle->share_code}", ['numbers' => [1, 2]])
            ->assertOk()
            ->assertJson(['total_pay' => 5, 'total_pay_label' => '€5.00']);
    }

    public function test_locale_normalization(): void
    {
        $this->assertSame('pt_BR', SetLocale::normalize('pt-br'));
        $this->assertSame('pt_BR', SetLocale::normalize('pt'));
        $this->assertSame('pt_BR', SetLocale::normalize('pt_PT'));
        $this->assertSame('en', SetLocale::normalize('en-GB'));
        $this->assertNull(SetLocale::normalize('fr'));
        $this->assertNull(SetLocale::normalize(''));
        $this->assertNull(SetLocale::normalize(['en']));
    }

    public function test_money_format_per_locale_and_currency(): void
    {
        $this->assertSame('R$ 1.234,50', Money::format(1234.5, 'BRL', 'pt_BR'));
        $this->assertSame('$1,234.50', Money::format(1234.5, 'USD', 'en'));
        $this->assertSame('€1,234.50', Money::format(1234.5, 'EUR', 'en'));
        $this->assertSame('£0.10', Money::format(0.1, 'GBP', 'en'));
    }
}
