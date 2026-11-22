<?php

namespace Tests\Feature\Admin;

use App\Models\Customer;
use App\Models\Raffle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Formulário de criação/edição de rifa: preço mascarado nos dois idiomas, erros ao lado do campo,
 * quantidade bloqueada após a liberação, fotos (JSON) e tela sem jQuery.
 */
class RaffleFormTest extends TestCase
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

    /** @return array<string, array{0: string, 1: float}> */
    public static function maskedPrices(): array
    {
        return [
            'pt-BR com símbolo' => ['R$ 1.234,56', 1234.56],
            'pt-BR centavos' => ['10,50', 10.5],
            'en com símbolo' => ['$1,234.56', 1234.56],
            'máscara antiga' => ['R$ 1,234.56', 1234.56],
            'inteiro' => ['25', 25.0],
            'milhar sem centavos' => ['1.000', 1000.0],
        ];
    }

    #[DataProvider('maskedPrices')]
    public function test_create_raffle_with_masked_price(string $price, float $expected): void
    {
        $this->actingAs($this->owner, 'admin')
            ->post('/admin/sortition/create', [
                'title' => 'Moto Honda',
                'price' => $price,
                'generated_numbers' => 500,
                'date_sortition' => '2026-12-24T20:00',
            ])
            ->assertRedirect('/admin/sortition?status=created');

        $raffle = Raffle::query()->where('customer_id', $this->owner->id)->sole();
        $this->assertEqualsWithDelta($expected, (float) $raffle->number_price, 0.001);
        $this->assertSame(500, (int) $raffle->total_numbers);
        $this->assertCount(500, $raffle->numbersList());
        $this->assertSame(Raffle::STATUS_AWAITING_PAYMENT, (int) $raffle->status);
    }

    public function test_invalid_data_shows_errors_next_to_fields_and_keeps_old_input(): void
    {
        $this->actingAs($this->owner, 'admin')
            ->from('/admin/sortition/create')
            ->post('/admin/sortition/create', ['title' => 'Bicicleta', 'price' => 'abc', 'generated_numbers' => 0])
            ->assertRedirect('/admin/sortition/create')
            ->assertSessionHasErrors(['price', 'generated_numbers']);

        $html = $this->actingAs($this->owner, 'admin')->get('/admin/sortition/create')->assertOk()->getContent();

        $this->assertStringContainsString('id="price-error"', $html);
        $this->assertStringContainsString('Informe um preço válido', $html);
        $this->assertStringContainsString('id="generated_numbers-error"', $html);
        $this->assertStringContainsString('value="Bicicleta"', $html);
        $this->assertStringContainsString('aria-invalid="true"', $html);
        $this->assertSame(0, Raffle::query()->count());
    }

    public function test_create_page_is_sectioned_with_summary_and_without_jquery(): void
    {
        $html = $this->actingAs($this->owner, 'admin')->get('/admin/sortition/create')->assertOk()->getContent();

        $this->assertStringContainsString('x-data="raffleForm"', $html);
        $this->assertStringContainsString('id="adm-raffle-form-data"', $html);
        $this->assertStringContainsString('Números e preço', $html);
        $this->assertStringContainsString('x-mask:dynamic="$money($input, decimal, thousands)"', $html);
        $this->assertStringNotContainsString('jquery.min.js', $html);
        $this->assertStringNotContainsString('inputmask', strtolower($html));
        $this->assertStringNotContainsString('select2', strtolower($html));

        preg_match('#<script type="application/json" id="adm-raffle-form-data">(.+?)</script>#s', $html, $match);
        $data = json_decode($match[1], true, flags: JSON_THROW_ON_ERROR);
        $this->assertTrue($data['showFee']);
        $this->assertFalse($data['locked']);
        $this->assertSame(',', $data['decimal']);
    }

    public function test_edit_updates_raffle_and_formats_price_for_locale(): void
    {
        $raffle = Raffle::factory()->awaitingPayment()->create(['customer_id' => $this->owner->id, 'number_price' => 1234.5, 'title' => 'Antigo']);

        $html = $this->actingAs($this->owner, 'admin')->get("/admin/sortition/{$raffle->id}/edit")->assertOk()->getContent();
        $this->assertStringContainsString('value="1.234,50"', $html);

        $this->actingAs($this->owner, 'admin')
            ->post("/admin/sortition/{$raffle->id}/edit", ['title' => 'Novo título', 'price' => '2.500,00', 'generated_numbers' => 200])
            ->assertRedirect('/admin/sortition?status=updated');

        $raffle->refresh();
        $this->assertSame('Novo título', $raffle->title);
        $this->assertEqualsWithDelta(2500.0, (float) $raffle->number_price, 0.001);
        $this->assertSame(200, (int) $raffle->total_numbers);
    }

    public function test_english_price_format_is_used_in_english(): void
    {
        $raffle = Raffle::factory()->create(['customer_id' => $this->owner->id, 'number_price' => 1234.5]);

        $html = $this->actingAs($this->owner, 'admin')->get("/admin/sortition/{$raffle->id}/edit?lang=en")->assertOk()->getContent();

        $this->assertStringContainsString('value="1,234.50"', $html);
        $this->assertStringContainsString('Edit Raffle', $html);
    }

    public function test_released_raffle_keeps_quantity_and_explains_why(): void
    {
        $raffle = Raffle::factory()->create(['customer_id' => $this->owner->id, 'total_numbers' => 100]);

        $html = $this->actingAs($this->owner, 'admin')->get("/admin/sortition/{$raffle->id}/edit")->assertOk()->getContent();
        $this->assertStringContainsString('readonly aria-readonly="true"', $html);
        $this->assertStringContainsString('A quantidade não pode mudar', $html);

        $this->actingAs($this->owner, 'admin')
            ->post("/admin/sortition/{$raffle->id}/edit", ['title' => 'X', 'price' => '5,00', 'generated_numbers' => 900])
            ->assertRedirect('/admin/sortition?status=updated');

        $this->assertSame(100, (int) $raffle->refresh()->total_numbers);
        $this->assertCount(100, $raffle->numbersList());
    }

    public function test_delete_photo_returns_json_with_remaining_photos(): void
    {
        Storage::fake('public');
        $raffle = Raffle::factory()->create([
            'customer_id' => $this->owner->id,
            'photos' => json_encode(['storage/images/photos/a.jpg', 'storage/images/photos/b.jpg']),
        ]);

        $this->actingAs($this->owner, 'admin')
            ->postJson("/admin/sortition/{$raffle->id}/deleteImageSortition", ['keyPhoto' => 0])
            ->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonCount(1, 'photos')
            ->assertJsonPath('photos.0.key', 0);

        $this->assertSame(['storage/images/photos/b.jpg'], $raffle->refresh()->photosList());
    }

    public function test_delete_photo_of_another_owner_is_not_found(): void
    {
        $raffle = Raffle::factory()->create(['photos' => json_encode(['storage/images/photos/a.jpg'])]);

        $this->actingAs($this->owner, 'admin')
            ->postJson("/admin/sortition/{$raffle->id}/deleteImageSortition", ['keyPhoto' => 0])
            ->assertNotFound();

        $this->assertCount(1, $raffle->refresh()->photosList());
    }

    public function test_photo_too_large_is_rejected_with_message(): void
    {
        Storage::fake('public');

        $this->actingAs($this->owner, 'admin')
            ->from('/admin/sortition/create')
            ->post('/admin/sortition/create', [
                'title' => 'Com foto',
                'price' => '10,00',
                'generated_numbers' => 100,
                'photos' => [UploadedFile::fake()->image('grande.jpg')->size(6000)],
            ])
            ->assertSessionHasErrors('photos.0');

        $this->assertSame(0, Raffle::query()->count());
    }
}
