<?php

namespace Tests\Feature;

use App\Models\Customer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FastRegisterTest extends TestCase
{
    use RefreshDatabase;

    public function test_json_request_logs_in_existing_customer_by_phone(): void
    {
        $customer = Customer::factory()->create(['name' => 'Maria Souza Lima', 'phone' => '18999998888']);

        $this->postJson('/customer/fastRegister', ['phone' => '(18) 99999-8888', 'codeShare' => 'abc'])
            ->assertOk()
            ->assertJsonPath('created', false)
            ->assertJsonPath('customer', ['id' => $customer->id, 'name' => 'Maria Souza']);

        $this->assertAuthenticatedAs($customer, 'website');
        $this->assertDatabaseCount('customers', 1);
    }

    public function test_json_request_with_new_phone_asks_for_name(): void
    {
        $this->postJson('/customer/fastRegister', ['phone' => '(18) 99999-8888'])
            ->assertUnprocessable()
            ->assertJsonPath('needs_name', true);

        $this->assertGuest('website');
        $this->assertDatabaseCount('customers', 0);
    }

    public function test_json_request_with_new_phone_and_name_creates_customer(): void
    {
        $response = $this->postJson('/customer/fastRegister', ['phone' => '(18) 99999-8888', 'username' => 'João Silva']);

        $response->assertCreated()->assertJsonPath('created', true)->assertJsonPath('customer.name', 'João Silva');
        $this->assertDatabaseHas('customers', ['phone' => '18999998888', 'name' => 'João Silva']);
        $this->assertAuthenticated('website');
    }

    public function test_json_request_with_invalid_phone_returns_validation_error(): void
    {
        $this->postJson('/customer/fastRegister', ['phone' => '123'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('phone');
    }

    public function test_legacy_form_still_redirects_to_raffle_page(): void
    {
        $this->post('/customer/fastRegister', ['phone' => '(18) 99999-8888', 'username' => 'João', 'codeShare' => 'abc'])
            ->assertRedirect('/share/abc?status=created');

        $this->assertAuthenticated('website');
    }
}
