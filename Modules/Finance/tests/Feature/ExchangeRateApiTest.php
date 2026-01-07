<?php

namespace Modules\Finance\Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Modules\Finance\Models\Currency;
use Modules\Finance\Models\ExchangeRate;
use Tests\TestCase;

class ExchangeRateApiTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();

        Currency::insert([
            ['code' => 'USD', 'name' => 'US Dollar', 'symbol' => '$', 'decimal_places' => 2, 'is_default' => true],
            ['code' => 'VND', 'name' => 'Vietnamese Dong', 'symbol' => '₫', 'decimal_places' => 0, 'is_default' => false],
            ['code' => 'EUR', 'name' => 'Euro', 'symbol' => '€', 'decimal_places' => 2, 'is_default' => false],
        ]);
    }

    protected function createExchangeRate(array $attributes = []): ExchangeRate
    {
        return ExchangeRate::create(array_merge([
            'base_currency' => 'USD',
            'target_currency' => 'VND',
            'rate' => 24500,
            'source' => 'manual',
            'rate_date' => now(),
        ], $attributes));
    }

    public function test_user_can_list_exchange_rates(): void
    {
        Sanctum::actingAs($this->user);

        $this->createExchangeRate();
        $this->createExchangeRate(['base_currency' => 'EUR', 'target_currency' => 'USD', 'rate' => 1.08]);

        $response = $this->getJson('/api/v1/finance/exchange-rates');

        $response->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_user_can_filter_exchange_rates_by_base_currency(): void
    {
        Sanctum::actingAs($this->user);

        $this->createExchangeRate(['base_currency' => 'USD', 'target_currency' => 'VND']);
        $this->createExchangeRate(['base_currency' => 'EUR', 'target_currency' => 'USD', 'rate' => 1.08]);

        $response = $this->getJson('/api/v1/finance/exchange-rates?base=USD');

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.base_currency', 'USD');
    }

    public function test_user_can_create_exchange_rate(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->postJson('/api/v1/finance/exchange-rates', [
            'base_currency' => 'USD',
            'target_currency' => 'EUR',
            'rate' => 0.92,
            'source' => 'manual',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.base_currency', 'USD')
            ->assertJsonPath('data.target_currency', 'EUR')
            ->assertJsonPath('data.rate', 0.92);
    }

    public function test_user_cannot_create_exchange_rate_with_same_currencies(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->postJson('/api/v1/finance/exchange-rates', [
            'base_currency' => 'USD',
            'target_currency' => 'USD',
            'rate' => 1,
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['target_currency']);
    }

    public function test_user_can_view_exchange_rate(): void
    {
        Sanctum::actingAs($this->user);

        $rate = $this->createExchangeRate();

        $response = $this->getJson("/api/v1/finance/exchange-rates/{$rate->id}");

        $response->assertOk()
            ->assertJsonPath('data.id', $rate->id);

        $this->assertEquals(24500, $response->json('data.rate'));
    }

    public function test_user_can_delete_exchange_rate(): void
    {
        Sanctum::actingAs($this->user);

        $rate = $this->createExchangeRate();

        $response = $this->deleteJson("/api/v1/finance/exchange-rates/{$rate->id}");

        $response->assertOk();
        $this->assertDatabaseMissing('finance_exchange_rates', ['id' => $rate->id]);
    }

    public function test_user_can_get_latest_rate(): void
    {
        Sanctum::actingAs($this->user);

        // Create a single rate
        $this->createExchangeRate(['rate' => 24500]);

        $response = $this->getJson('/api/v1/finance/exchange-rates/latest?base=USD&target=VND');

        $response->assertOk();
        $this->assertEquals(24500, $response->json('data.rate'));
    }

    public function test_user_can_convert_currency(): void
    {
        Sanctum::actingAs($this->user);

        $this->createExchangeRate(['rate' => 24500]);

        $response = $this->postJson('/api/v1/finance/exchange-rates/convert', [
            'amount' => 100,
            'from' => 'USD',
            'to' => 'VND',
        ]);

        $response->assertOk();
        $this->assertEquals(100, $response->json('data.original_amount'));
        $this->assertEquals(2450000, $response->json('data.converted_amount'));
    }

    public function test_user_can_list_currencies(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->getJson('/api/v1/finance/exchange-rates/currencies');

        $response->assertOk()
            ->assertJsonCount(3, 'data');
    }

    public function test_user_can_list_providers(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->getJson('/api/v1/finance/exchange-rates/providers');

        $response->assertOk()
            ->assertJsonStructure(['data' => [['id', 'name']]]);
    }

    public function test_unauthenticated_user_cannot_access_exchange_rates(): void
    {
        $response = $this->getJson('/api/v1/finance/exchange-rates');

        $response->assertUnauthorized();
    }
}
