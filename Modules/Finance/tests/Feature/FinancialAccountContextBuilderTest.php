<?php

namespace Modules\Finance\Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Finance\Models\{Account, Currency};
use Modules\Finance\Services\FinancialAccountContextBuilder;
use Tests\TestCase;

class FinancialAccountContextBuilderTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

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

        $this->actingAs($this->user);
    }

    public function test_it_reports_account_totals_largest_account_and_debt(): void
    {
        $this->account('Checking', 'bank', 5000);
        $this->account('Savings', 'bank', 9000);
        $this->account('Credit Card', 'credit_card', 1500, [
            'initial_balance' => 5000,
            'has_credit_limit' => true,
        ]);

        $context = app(FinancialAccountContextBuilder::class)->build($this->user->id);

        $this->assertStringContainsString('Account Balance and Debt Report', $context);
        $this->assertStringContainsString('Total included account balance: 15500 USD', $context);
        $this->assertStringContainsString('Total debt/liabilities: 3500 USD', $context);
        $this->assertStringContainsString('Account with most money: Savings (bank): 9000 USD', $context);
        $this->assertStringContainsString('Credit Card (credit_card): debt 3500 USD', $context);
    }

    protected function account(string $name, string $type, float $currentBalance, array $attributes = []): Account
    {
        return Account::create(array_merge([
            'user_id' => $this->user->id,
            'name' => $name,
            'account_type' => $type,
            'currency_code' => 'USD',
            'initial_balance' => 0,
            'current_balance' => $currentBalance,
            'is_active' => true,
        ], $attributes));
    }
}
