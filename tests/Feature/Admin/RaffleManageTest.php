<?php

namespace Tests\Feature\Admin;

use App\Models\Customer;
use App\Models\Raffle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Gestão de bilhetes e compradores (/admin/sortition/{id}/listSortition): abas, JSON da grade,
 * confirmação/cancelamento em lote e atribuição de números.
 */
class RaffleManageTest extends TestCase
{
    use RefreshDatabase;

    private Customer $owner;

    private Customer $ana;

    private Customer $bruno;

    private Raffle $raffle;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Http::fake(['*' => Http::response([], 200)]);
        $this->travelTo(Carbon::parse('2026-11-21 10:00:00'));

        $this->owner = Customer::factory()->create();
        $this->ana = Customer::factory()->create(['name' => 'Ana Souza', 'phone' => '18999990001']);
        $this->bruno = Customer::factory()->create(['name' => 'Bruno Lima', 'phone' => '18999990002']);

        // Ana: 1, 2 reservados + 3 pago (parcial). Bruno: 10, 11 reservados (pendente).
        $this->raffle = Raffle::factory()
            ->withNumbers([1, 2], Raffle::RESERVED, $this->ana->id)
            ->withNumbers([3], Raffle::UNAVAILABLE, $this->ana->id)
            ->withNumbers([10, 11], Raffle::RESERVED, $this->bruno->id)
            ->create(['customer_id' => $this->owner->id, 'title' => 'Moto Honda', 'share_code' => 'moto', 'number_price' => 5]);
    }

    private function manageUrl(string $query = ''): string
    {
        return '/admin/sortition/'.$this->raffle->id.'/listSortition'.$query;
    }

    /** JSON injetado em <script id="adm-manage-data">. */
    private function pageData(string $html): array
    {
        $this->assertMatchesRegularExpression('#<script type="application/json" id="adm-manage-data">(.+?)</script>#s', $html);
        preg_match('#<script type="application/json" id="adm-manage-data">(.+?)</script>#s', $html, $match);

        return json_decode($match[1], true, flags: JSON_THROW_ON_ERROR);
    }

    /** @return array<int, string> número => situação */
    private function situations(): array
    {
        $map = [];
        foreach ($this->raffle->fresh()->numbersList() as $number) {
            $map[$number->number] = $number->situation;
        }

        return $map;
    }

    private function postJson_(string $url, array $data, ?Customer $as = null)
    {
        return $this->actingAs($as ?? $this->owner, 'admin')->postJson($url, $data);
    }

    public function test_page_has_tabs_kpis_and_no_jquery(): void
    {
        $html = $this->actingAs($this->owner, 'admin')->get($this->manageUrl())->assertOk()->getContent();

        $this->assertStringContainsString('x-data="raffleManage"', $html);
        $this->assertStringContainsString('role="tablist"', $html);
        $this->assertStringContainsString('id="adm-tab-buyers"', $html);
        $this->assertStringContainsString('id="adm-tab-tickets"', $html);
        $this->assertStringContainsString('id="adm-tab-history"', $html);
        $this->assertStringContainsString('Gerenciamento da Rifa', $html);
        $this->assertStringContainsString('href="'.url('/share/moto').'"', $html);
        $this->assertStringNotContainsString('jquery.min.js', $html);
    }

    public function test_grid_json_has_status_per_number_and_buyers(): void
    {
        $html = $this->actingAs($this->owner, 'admin')->get($this->manageUrl())->assertOk()->getContent();
        $data = $this->pageData($html);

        $this->assertSame(100, $data['total']);
        $this->assertSame(100, strlen($data['status']));
        $this->assertSame('RRU', substr($data['status'], 0, 3));
        $this->assertSame('D', $data['status'][3]);
        $this->assertSame('RR', substr($data['status'], 9, 2));
        $this->assertSame(95, substr_count($data['status'], 'D'));
        $this->assertSame('pending', $data['filter']);

        $buyers = collect($data['buyers'])->keyBy('name');
        $this->assertSame('partial', $buyers['Ana Souza']['status']);
        $this->assertSame([3], $buyers['Ana Souza']['paid']);
        $this->assertSame([1, 2], $buyers['Ana Souza']['reserved']);
        $this->assertEquals(15, $buyers['Ana Souza']['amount']);
        $this->assertEquals(10, $buyers['Ana Souza']['pending_amount']);
        $this->assertSame('5518999990001', $buyers['Ana Souza']['whatsapp']);
        $this->assertSame('pending', $buyers['Bruno Lima']['status']);
    }

    public function test_buyer_name_cannot_inject_template_variables(): void
    {
        $this->ana->update(['name' => 'Ana {{header}} </script><b>x</b>']);

        $html = $this->actingAs($this->owner, 'admin')->get($this->manageUrl())->assertOk()->getContent();
        $data = $this->pageData($html);

        $this->assertContains('Ana {{header}} </script><b>x</b>', array_column($data['buyers'], 'name'));
    }

    public function test_default_filter_is_all_without_pending(): void
    {
        $raffle = Raffle::factory()->withNumbers([5], Raffle::UNAVAILABLE, $this->ana->id)->create(['customer_id' => $this->owner->id]);

        $html = $this->actingAs($this->owner, 'admin')->get('/admin/sortition/'.$raffle->id.'/listSortition?tab=tickets')->assertOk()->getContent();
        $data = $this->pageData($html);

        $this->assertSame('all', $data['filter']);
        $this->assertSame('tickets', $data['tab']);
    }

    public function test_bulk_confirm_changes_only_selected_buyers(): void
    {
        $response = $this->postJson_('/admin/sortition/'.$this->raffle->id.'/confirmNumbersCustomers', ['customers' => [$this->ana->id]])
            ->assertOk()
            ->assertJson(['status' => 'success', 'changed' => 2]);

        $situations = $this->situations();
        $this->assertSame(Raffle::UNAVAILABLE, $situations[1]);
        $this->assertSame(Raffle::UNAVAILABLE, $situations[2]);
        $this->assertSame(Raffle::UNAVAILABLE, $situations[3]);
        $this->assertSame(Raffle::RESERVED, $situations[10]);
        $this->assertSame(Raffle::RESERVED, $situations[11]);
        $this->assertSame('UUU', substr($response->json('data.status'), 0, 3));
    }

    public function test_bulk_confirm_with_several_buyers(): void
    {
        $this->postJson_('/admin/sortition/'.$this->raffle->id.'/confirmNumbersCustomers', ['customers' => [$this->ana->id, $this->bruno->id]])
            ->assertOk()
            ->assertJson(['status' => 'success', 'changed' => 4]);

        $situations = $this->situations();
        foreach ([1, 2, 3, 10, 11] as $number) {
            $this->assertSame(Raffle::UNAVAILABLE, $situations[$number]);
        }
    }

    public function test_owner_cannot_confirm_numbers_of_another_customer_raffle(): void
    {
        $intruder = Customer::factory()->create();

        $this->postJson_('/admin/sortition/'.$this->raffle->id.'/confirmNumbersCustomers', ['customers' => [$this->ana->id]], $intruder)
            ->assertNotFound()
            ->assertJson(['status' => 'error']);

        $this->actingAs($intruder, 'admin')
            ->post('/admin/sortition/'.$this->raffle->id.'/cancelNumbersCustomers', ['idCustomer' => $this->ana->id])
            ->assertRedirect('/admin/sortition');

        $this->assertSame(Raffle::RESERVED, $this->situations()[1]);
    }

    public function test_cancel_releases_reserved_numbers_and_keeps_paid(): void
    {
        $this->postJson_('/admin/sortition/'.$this->raffle->id.'/cancelNumbersCustomers', ['customers' => [$this->ana->id]])
            ->assertOk()
            ->assertJson(['status' => 'success', 'changed' => 2]);

        $numbers = collect($this->raffle->fresh()->numbersList())->keyBy('number');
        $this->assertSame(Raffle::AVAILABLE, $numbers[1]->situation);
        $this->assertNull($numbers[1]->customer_id);
        $this->assertNull($numbers[2]->customer_id);
        $this->assertSame(Raffle::UNAVAILABLE, $numbers[3]->situation);
        $this->assertSame(Raffle::RESERVED, $numbers[10]->situation);
    }

    public function test_confirm_without_pending_returns_error(): void
    {
        $paidOnly = Customer::factory()->create();

        $this->postJson_('/admin/sortition/'.$this->raffle->id.'/confirmNumbersCustomers', ['customers' => [$paidOnly->id]])
            ->assertStatus(422)
            ->assertJson(['status' => 'error']);
    }

    public function test_legacy_form_post_redirects_to_buyers_tab(): void
    {
        $this->actingAs($this->owner, 'admin')
            ->post('/admin/sortition/'.$this->raffle->id.'/confirmNumbersCustomers', ['idCustomer' => $this->bruno->id])
            ->assertRedirect($this->manageUrl('?status=numbers_confirmed'));

        $this->assertSame(Raffle::UNAVAILABLE, $this->situations()[10]);
    }

    public function test_old_marked_route_redirects_to_buyers_tab(): void
    {
        $this->actingAs($this->owner, 'admin')
            ->get('/admin/sortition/'.$this->raffle->id.'/list')
            ->assertRedirect($this->manageUrl());
    }

    public function test_assign_reserves_free_numbers_and_skips_taken(): void
    {
        $response = $this->postJson_('/admin/sortition/fastRegisterCustomersNumbers', [
            'codeIDSortition' => $this->raffle->id,
            'numbers' => '4,5,10',
            'phone' => '(18) 98888-7777',
            'username' => 'Carla Nova',
        ])->assertOk()->assertJson(['status' => 'success', 'assigned' => [4, 5], 'skipped' => [10]]);

        $carla = Customer::query()->where('phone', '18988887777')->firstOrFail();
        $this->assertSame('Carla Nova', $carla->name);
        $this->assertSame($carla->id, $response->json('buyer_id'));

        $numbers = collect($this->raffle->fresh()->numbersList())->keyBy('number');
        $this->assertSame(Raffle::RESERVED, $numbers[4]->situation);
        $this->assertSame($carla->id, (int) $numbers[4]->customer_id);
        $this->assertSame('2026-11-21 10:00', $numbers[4]->date);
        $this->assertSame($this->bruno->id, (int) $numbers[10]->customer_id);
    }

    public function test_assign_reuses_existing_customer_by_phone(): void
    {
        $this->postJson_('/admin/sortition/fastRegisterCustomersNumbers', [
            'codeIDSortition' => $this->raffle->id,
            'numbers' => '20',
            'phone' => '18999990002',
            'username' => 'Outro nome',
        ])->assertOk();

        $this->assertSame($this->bruno->id, (int) collect($this->raffle->fresh()->numbersList())->firstWhere('number', 20)->customer_id);
        $this->assertSame('Bruno Lima', $this->bruno->fresh()->name);
    }

    public function test_assign_fails_when_all_numbers_are_taken(): void
    {
        $this->postJson_('/admin/sortition/fastRegisterCustomersNumbers', [
            'codeIDSortition' => $this->raffle->id,
            'numbers' => '1,3',
            'phone' => '18977776666',
            'username' => 'Davi',
        ])->assertStatus(409)->assertJson(['status' => 'error']);
    }

    public function test_assign_on_another_customer_raffle_is_rejected(): void
    {
        $intruder = Customer::factory()->create();

        $this->postJson_('/admin/sortition/fastRegisterCustomersNumbers', [
            'codeIDSortition' => $this->raffle->id,
            'numbers' => '50',
            'phone' => '18977776666',
        ], $intruder)->assertNotFound();

        $this->assertSame(Raffle::AVAILABLE, $this->situations()[50]);
    }

    public function test_history_lists_reservations(): void
    {
        $numbers = $this->raffle->numbersList();
        foreach ($numbers as $number) {
            if (in_array($number->number, [1, 2], true)) {
                $number->date = '2026-11-20 18:30';
            }
        }
        $this->raffle->setNumbersList($numbers);
        $this->raffle->save();

        $this->actingAs($this->owner, 'admin')
            ->get($this->manageUrl('?tab=history'))
            ->assertOk()
            ->assertSee('class="adm-timeline"', false)
            ->assertSee('Ana Souza reservou 2 números (aguardando pagamento)')
            ->assertSee('20/11/2026 18:30')
            ->assertSee('Rifa criada');
    }

    public function test_large_raffle_payload_is_compact(): void
    {
        $raffle = Raffle::factory()->create([
            'customer_id' => $this->owner->id,
            'total_numbers' => 10000,
            'numbers' => json_encode(Raffle::generateNumbers(10000)),
        ]);

        $html = $this->actingAs($this->owner, 'admin')->get('/admin/sortition/'.$raffle->id.'/listSortition')->assertOk()->getContent();
        $data = $this->pageData($html);

        $this->assertSame(10000, strlen($data['status']));
        $this->assertLessThan(150_000, strlen($html));
    }

    public function test_page_is_translated_to_english(): void
    {
        $this->actingAs($this->owner, 'admin')
            ->get($this->manageUrl('?lang=en'))
            ->assertOk()
            ->assertSee('Manage raffle')
            ->assertSee('Buyers')
            ->assertSee('Tickets');
    }
}
