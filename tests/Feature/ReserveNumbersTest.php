<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Raffle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Date;
use Tests\TestCase;

class ReserveNumbersTest extends TestCase
{
    use RefreshDatabase;

    private function numberOf(Raffle $raffle, int $number): object
    {
        return collect($raffle->fresh()->numbersList())->firstWhere('number', $number);
    }

    public function test_visitor_reserves_selected_numbers_in_one_request(): void
    {
        Date::setTestNow('2026-11-21 10:00:00');
        $visitor = Customer::factory()->create();
        $raffle = Raffle::factory()->create(['number_price' => 12.5]);

        $response = $this->actingAs($visitor, 'website')
            ->postJson("/api/v1/reserveNumbers/{$raffle->share_code}", ['numbers' => [3, 7, 42]]);

        $response->assertOk()->assertJson([
            'reserved' => [3, 7, 42],
            'conflicts' => [],
            'my_reserved' => [3, 7, 42],
            'total_pay' => 37.5,
            'total_pay_label' => 'R$ 37,50',
            'expires_at' => Date::now()->addMinutes(Raffle::RESERVATION_MINUTES)->toIso8601String(),
        ]);
        $this->assertStringContainsString('PIX', strtoupper((string) $response->json('pix_html')));

        foreach ([3, 7, 42] as $number) {
            $item = $this->numberOf($raffle, $number);
            $this->assertSame(Raffle::RESERVED, $item->situation);
            $this->assertSame($visitor->id, $item->customer_id);
        }
        $this->assertSame(Raffle::AVAILABLE, $this->numberOf($raffle, 4)->situation);
    }

    public function test_numbers_taken_by_others_or_paid_are_returned_as_conflicts(): void
    {
        $visitor = Customer::factory()->create();
        $other = Customer::factory()->create();
        $raffle = Raffle::factory()
            ->withNumbers([5], Raffle::RESERVED, $other->id)
            ->withNumbers([6], Raffle::UNAVAILABLE, $other->id)
            ->create();

        $response = $this->actingAs($visitor, 'website')
            ->postJson("/api/v1/reserveNumbers/{$raffle->share_code}", ['numbers' => [5, 6, 8, 999]]);

        $response->assertOk()
            ->assertJsonPath('reserved', [8])
            ->assertJsonPath('conflicts', [5, 6, 999]);

        $this->assertSame($other->id, $this->numberOf($raffle, 5)->customer_id);
        $this->assertSame(Raffle::UNAVAILABLE, $this->numberOf($raffle, 6)->situation);
    }

    public function test_total_is_calculated_on_the_server_including_previous_reservations(): void
    {
        $visitor = Customer::factory()->create();
        $raffle = Raffle::factory()
            ->withNumbers([1, 2], Raffle::RESERVED, $visitor->id)
            ->create(['number_price' => 10]);

        $response = $this->actingAs($visitor, 'website')
            ->postJson("/api/v1/reserveNumbers/{$raffle->share_code}", ['numbers' => [2, 9], 'total_pay' => 1]);

        $response->assertOk()
            ->assertJsonPath('reserved', [2, 9])
            ->assertJsonPath('my_reserved', [1, 2, 9])
            ->assertJsonPath('total_pay', 30);
    }

    public function test_disputed_number_goes_to_the_first_visitor_and_comes_back_as_conflict_to_the_second(): void
    {
        $first = Customer::factory()->create();
        $second = Customer::factory()->create();
        $raffle = Raffle::factory()->create(['number_price' => 5]);

        $this->actingAs($first, 'website')
            ->postJson("/api/v1/reserveNumbers/{$raffle->share_code}", ['numbers' => [10, 11]])
            ->assertOk()
            ->assertJsonPath('reserved', [10, 11]);

        $this->actingAs($second, 'website')
            ->postJson("/api/v1/reserveNumbers/{$raffle->share_code}", ['numbers' => [11, 12]])
            ->assertOk()
            ->assertJsonPath('reserved', [12])
            ->assertJsonPath('conflicts', [11])
            ->assertJsonPath('my_reserved', [12])
            ->assertJsonPath('total_pay', 5);

        $this->assertSame($first->id, $this->numberOf($raffle, 11)->customer_id);
        $this->assertSame($second->id, $this->numberOf($raffle, 12)->customer_id);
    }

    public function test_conflicts_only_request_keeps_the_raffle_untouched(): void
    {
        $visitor = Customer::factory()->create();
        $other = Customer::factory()->create();
        $raffle = Raffle::factory()->withNumbers([4], Raffle::RESERVED, $other->id)->create();
        $before = $raffle->fresh()->numbers;

        $this->actingAs($visitor, 'website')
            ->postJson("/api/v1/reserveNumbers/{$raffle->share_code}", ['numbers' => [4]])
            ->assertOk()
            ->assertJsonPath('reserved', [])
            ->assertJsonPath('conflicts', [4])
            ->assertJsonPath('expires_at', null)
            ->assertJsonPath('pix_html', null);

        $this->assertSame($before, $raffle->fresh()->numbers);
    }

    public function test_price_tampered_by_the_client_is_ignored(): void
    {
        $visitor = Customer::factory()->create();
        $raffle = Raffle::factory()->create(['number_price' => 20]);

        $this->actingAs($visitor, 'website')
            ->postJson("/api/v1/reserveNumbers/{$raffle->share_code}", [
                'numbers' => [1, 2],
                'price' => 0.01,
                'number_price' => 0.01,
                'total_pay' => 0.02,
            ])
            ->assertOk()
            ->assertJsonPath('total_pay', 40)
            ->assertJsonPath('total_pay_label', 'R$ 40,00');

        $this->assertEquals(20, $raffle->fresh()->number_price);
    }

    public function test_guest_receives_401_asking_for_registration(): void
    {
        $raffle = Raffle::factory()->create();

        $this->postJson("/api/v1/reserveNumbers/{$raffle->share_code}", ['numbers' => [1]])
            ->assertUnauthorized()
            ->assertJsonPath('needs_register', true);

        $this->assertSame(Raffle::AVAILABLE, $this->numberOf($raffle, 1)->situation);
    }

    public function test_invalid_selection_is_rejected(): void
    {
        $visitor = Customer::factory()->create();
        $raffle = Raffle::factory()->create();

        $this->actingAs($visitor, 'website')
            ->postJson("/api/v1/reserveNumbers/{$raffle->share_code}", ['numbers' => [1, 1, 'x']])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['numbers.0', 'numbers.2']);

        $this->actingAs($visitor, 'website')
            ->postJson("/api/v1/reserveNumbers/{$raffle->share_code}", ['numbers' => []])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('numbers');
    }

    public function test_raffle_not_active_returns_404(): void
    {
        $visitor = Customer::factory()->create();
        $raffle = Raffle::factory()->awaitingPayment()->create();

        $this->actingAs($visitor, 'website')
            ->postJson("/api/v1/reserveNumbers/{$raffle->share_code}", ['numbers' => [1]])
            ->assertNotFound();
    }
}
