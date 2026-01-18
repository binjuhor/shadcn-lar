<?php

namespace Modules\Finance\Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Modules\Finance\Models\{Account, Category, Currency, Transaction};
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

class ReportApiTest extends TestCase
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

    protected function createCategory(string $type, string $name): Category
    {
        return Category::create([
            'name' => $name,
            'type' => $type,
            'color' => '#3b82f6',
        ]);
    }

    protected function createTransaction(array $attributes = []): Transaction
    {
        return Transaction::create(array_merge([
            'account_id' => $this->account->id,
            'user_id' => $this->user->id,
            'transaction_type' => 'expense',
            'amount' => 100,
            'currency_code' => 'USD',
            'transaction_date' => now(),
            'description' => 'Test Transaction',
        ], $attributes));
    }

    #[Group('mysql')]
    public function test_user_can_get_overview_report(): void
    {
        // Skip on SQLite - DATE_FORMAT is MySQL specific
        if (config('database.default') === 'sqlite') {
            $this->markTestSkipped('This test requires MySQL (DATE_FORMAT function)');
        }

        Sanctum::actingAs($this->user);

        $category = $this->createCategory('expense', 'Food');
        $this->createTransaction(['amount' => 500, 'transaction_type' => 'expense', 'category_id' => $category->id]);
        $this->createTransaction(['amount' => 1000, 'transaction_type' => 'income']);

        $response = $this->getJson('/api/v1/finance/reports/overview?range=30d');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'summary' => ['total_income', 'total_expense', 'net_change', 'savings_rate', 'transaction_count'],
                    'trend',
                    'currency_code',
                    'date_from',
                    'date_to',
                ],
            ])
            ->assertJsonPath('data.summary.total_income', 1000)
            ->assertJsonPath('data.summary.total_expense', 500)
            ->assertJsonPath('data.summary.transaction_count', 2);
    }

    #[Group('mysql')]
    public function test_user_can_get_income_expense_trend(): void
    {
        // Skip on SQLite - DATE_FORMAT is MySQL specific
        if (config('database.default') === 'sqlite') {
            $this->markTestSkipped('This test requires MySQL (DATE_FORMAT function)');
        }

        Sanctum::actingAs($this->user);

        $this->createTransaction(['amount' => 500, 'transaction_type' => 'expense']);
        $this->createTransaction(['amount' => 1000, 'transaction_type' => 'income']);

        $response = $this->getJson('/api/v1/finance/reports/income-expense-trend?range=30d');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [['period', 'income', 'expense', 'net']],
                'currency_code',
            ]);
    }

    public function test_user_can_get_category_breakdown(): void
    {
        Sanctum::actingAs($this->user);

        $food = $this->createCategory('expense', 'Food');
        $transport = $this->createCategory('expense', 'Transport');

        $this->createTransaction(['amount' => 300, 'category_id' => $food->id]);
        $this->createTransaction(['amount' => 200, 'category_id' => $transport->id]);

        $response = $this->getJson('/api/v1/finance/reports/category-breakdown?type=expense&range=30d');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [['id', 'name', 'color', 'amount', 'percentage']],
                'type',
                'currency_code',
            ])
            ->assertJsonCount(2, 'data');
    }

    public function test_user_can_get_account_distribution(): void
    {
        Sanctum::actingAs($this->user);

        Account::create([
            'user_id' => $this->user->id,
            'name' => 'Investment Account',
            'account_type' => 'investment',
            'currency_code' => 'USD',
            'initial_balance' => 5000,
            'current_balance' => 5500,
            'is_active' => true,
        ]);

        $response = $this->getJson('/api/v1/finance/reports/account-distribution');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [['type', 'label', 'balance', 'count', 'is_liability']],
                'currency_code',
            ]);
    }

    #[Group('mysql')]
    public function test_user_can_get_cashflow_analysis(): void
    {
        // Skip on SQLite - DATE_FORMAT is MySQL specific
        if (config('database.default') === 'sqlite') {
            $this->markTestSkipped('This test requires MySQL (DATE_FORMAT function)');
        }

        Sanctum::actingAs($this->user);

        $this->createTransaction(['amount' => 500, 'transaction_type' => 'expense']);
        $this->createTransaction(['amount' => 2000, 'transaction_type' => 'income']);

        $response = $this->getJson('/api/v1/finance/reports/cashflow-analysis');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'monthly_data' => [['period', 'label', 'passive_income', 'active_income', 'total_income', 'expense', 'surplus', 'passive_coverage']],
                    'averages' => ['passive_income', 'expense', 'coverage'],
                    'financial_freedom_progress',
                ],
                'currency_code',
            ]);
    }

    public function test_user_can_get_net_worth(): void
    {
        Sanctum::actingAs($this->user);

        Account::create([
            'user_id' => $this->user->id,
            'name' => 'Credit Card',
            'account_type' => 'credit_card',
            'currency_code' => 'USD',
            'initial_balance' => 0,
            'current_balance' => -1500,
            'is_active' => true,
        ]);

        $response = $this->getJson('/api/v1/finance/reports/net-worth');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => ['total_assets', 'total_liabilities', 'net_worth', 'accounts_count'],
                'currency_code',
            ]);

        $this->assertEquals(10000, $response->json('data.total_assets'));
        $this->assertEquals(1500, $response->json('data.total_liabilities'));
        $this->assertEquals(8500, $response->json('data.net_worth'));
    }

    #[Group('mysql')]
    public function test_overview_supports_custom_date_range(): void
    {
        // Skip on SQLite - DATE_FORMAT is MySQL specific
        if (config('database.default') === 'sqlite') {
            $this->markTestSkipped('This test requires MySQL (DATE_FORMAT function)');
        }

        Sanctum::actingAs($this->user);

        $this->createTransaction([
            'amount' => 100,
            'transaction_date' => now()->subDays(5),
        ]);

        $response = $this->getJson('/api/v1/finance/reports/overview?range=custom&start='.now()->subDays(10)->format('Y-m-d').'&end='.now()->format('Y-m-d'));

        $response->assertOk()
            ->assertJsonPath('data.summary.transaction_count', 1);
    }

    public function test_unauthenticated_user_cannot_access_reports(): void
    {
        $response = $this->getJson('/api/v1/finance/reports/overview');

        $response->assertUnauthorized();
    }
}
