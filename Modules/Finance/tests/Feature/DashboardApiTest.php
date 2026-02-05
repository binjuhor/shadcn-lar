<?php

namespace Modules\Finance\Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Modules\Finance\Models\{Account, Budget, Category, Currency, RecurringTransaction, Transaction};
use Tests\TestCase;

class DashboardApiTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected Account $account;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();

        Currency::create([
            'code' => 'USD',
            'name' => 'US Dollar',
            'symbol' => '$',
            'decimal_places' => 2,
            'is_default' => true,
        ]);

        $this->account = Account::create([
            'user_id' => $this->user->id,
            'name' => 'Test Account',
            'account_type' => 'bank',
            'currency_code' => 'USD',
            'initial_balance' => 10000,
            'current_balance' => 10000,
            'is_active' => true,
        ]);
    }

    public function test_user_can_get_dashboard_data(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->getJson('/api/v1/finance/dashboard');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'summary' => [
                        'total_assets',
                        'total_liabilities',
                        'net_worth',
                        'total_balance',
                        'currency_code',
                        'accounts_count',
                    ],
                    'recent_transactions',
                    'budgets',
                    'spending_trend',
                    'recurring_projection',
                    'upcoming_recurrings',
                ],
            ]);
    }

    public function test_dashboard_shows_recent_transactions(): void
    {
        Sanctum::actingAs($this->user);

        Transaction::create([
            'account_id' => $this->account->id,
            'user_id' => $this->user->id,
            'transaction_type' => 'expense',
            'amount' => 100,
            'currency_code' => 'USD',
            'transaction_date' => now(),
            'description' => 'Test Transaction',
        ]);

        $response = $this->getJson('/api/v1/finance/dashboard');

        $response->assertOk();
        $this->assertCount(1, $response->json('data.recent_transactions'));
    }

    public function test_dashboard_shows_active_budgets(): void
    {
        Sanctum::actingAs($this->user);

        $category = Category::create([
            'name' => 'Food',
            'type' => 'expense',
            'color' => '#ff0000',
        ]);

        Budget::create([
            'user_id' => $this->user->id,
            'name' => 'Food Budget',
            'category_id' => $category->id,
            'allocated_amount' => 500,
            'spent_amount' => 0,
            'currency_code' => 'USD',
            'period_type' => 'monthly',
            'start_date' => now()->startOfMonth(),
            'end_date' => now()->endOfMonth(),
            'is_active' => true,
        ]);

        $response = $this->getJson('/api/v1/finance/dashboard');

        $response->assertOk();
        $this->assertCount(1, $response->json('data.budgets'));
    }

    public function test_dashboard_calculates_net_worth_correctly(): void
    {
        Sanctum::actingAs($this->user);

        // Create a credit card with debt
        Account::create([
            'user_id' => $this->user->id,
            'name' => 'Credit Card',
            'account_type' => 'credit_card',
            'currency_code' => 'USD',
            'initial_balance' => 5000, // credit limit
            'current_balance' => 4000, // available credit (1000 owed)
            'is_active' => true,
            'has_credit_limit' => true,
        ]);

        $response = $this->getJson('/api/v1/finance/dashboard');

        $response->assertOk();
        $summary = $response->json('data.summary');

        $this->assertEquals(10000, $summary['total_assets']);
        $this->assertEquals(1000, $summary['total_liabilities']);
        $this->assertEquals(9000, $summary['net_worth']);
    }

    public function test_unauthenticated_user_cannot_access_dashboard(): void
    {
        $response = $this->getJson('/api/v1/finance/dashboard');

        $response->assertUnauthorized();
    }
}
