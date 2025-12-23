# Phase 15: Testing & Documentation

## Context
- Parent plan: [plan.md](../plan.md)
- Dependencies: All previous phases

## Overview
- Priority: high
- Status: pending
- Description: Create comprehensive tests for backend functionality. Document API endpoints and user guides.

## Requirements
### Functional
- Feature tests for all controllers
- Unit tests for services
- Policy tests
- Factory tests

### Non-functional
- 80%+ code coverage
- Clear documentation
- API endpoint reference

## Related Code Files
### Files to Create
```
Modules/Mokey/Tests/
├── Feature/
│   ├── AccountControllerTest.php
│   ├── TransactionControllerTest.php
│   ├── BudgetControllerTest.php
│   ├── GoalControllerTest.php
│   └── DashboardControllerTest.php
├── Unit/
│   ├── TransactionServiceTest.php
│   ├── BudgetServiceTest.php
│   ├── GoalServiceTest.php
│   └── ExchangeRateServiceTest.php
└── Policies/
    └── AccountPolicyTest.php

docs/mokey/
├── api-reference.md
├── user-guide.md
└── developer-guide.md
```

## Implementation Steps

### 1. Create Transaction Service Unit Tests
```php
// Modules/Mokey/Tests/Unit/TransactionServiceTest.php
namespace Modules\Mokey\Tests\Unit;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Mokey\Models\Account;
use Modules\Mokey\Models\Transaction;
use Modules\Mokey\Services\TransactionService;

class TransactionServiceTest extends TestCase
{
    use RefreshDatabase;

    private TransactionService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(TransactionService::class);
    }

    public function test_create_income_increases_account_balance(): void
    {
        $account = Account::factory()->create(['current_balance' => 10000]); // $100

        $transaction = $this->service->create([
            'user_id' => $account->user_id,
            'account_id' => $account->id,
            'transaction_type' => 'income',
            'amount' => 5000, // $50
            'currency_code' => $account->currency_code,
            'transaction_date' => now()->toDateString(),
        ]);

        $this->assertEquals(15000, $account->fresh()->current_balance); // $150
    }

    public function test_create_expense_decreases_account_balance(): void
    {
        $account = Account::factory()->create(['current_balance' => 10000]);

        $this->service->create([
            'user_id' => $account->user_id,
            'account_id' => $account->id,
            'transaction_type' => 'expense',
            'amount' => 3000,
            'currency_code' => $account->currency_code,
            'transaction_date' => now()->toDateString(),
        ]);

        $this->assertEquals(7000, $account->fresh()->current_balance);
    }

    public function test_transfer_moves_between_accounts(): void
    {
        $fromAccount = Account::factory()->create(['current_balance' => 10000, 'currency_code' => 'USD']);
        $toAccount = Account::factory()->create(['current_balance' => 5000, 'currency_code' => 'USD', 'user_id' => $fromAccount->user_id]);

        $this->service->create([
            'user_id' => $fromAccount->user_id,
            'account_id' => $fromAccount->id,
            'transfer_account_id' => $toAccount->id,
            'transaction_type' => 'transfer',
            'amount' => 2000,
            'currency_code' => 'USD',
            'transaction_date' => now()->toDateString(),
        ]);

        $this->assertEquals(8000, $fromAccount->fresh()->current_balance);
        $this->assertEquals(7000, $toAccount->fresh()->current_balance);
    }

    public function test_delete_reverses_balance_effect(): void
    {
        $account = Account::factory()->create(['current_balance' => 10000]);

        $transaction = $this->service->create([
            'user_id' => $account->user_id,
            'account_id' => $account->id,
            'transaction_type' => 'expense',
            'amount' => 3000,
            'currency_code' => $account->currency_code,
            'transaction_date' => now()->toDateString(),
        ]);

        $this->assertEquals(7000, $account->fresh()->current_balance);

        $this->service->delete($transaction);

        $this->assertEquals(10000, $account->fresh()->current_balance);
    }

    public function test_update_adjusts_balance_correctly(): void
    {
        $account = Account::factory()->create(['current_balance' => 10000]);

        $transaction = $this->service->create([
            'user_id' => $account->user_id,
            'account_id' => $account->id,
            'transaction_type' => 'expense',
            'amount' => 3000,
            'currency_code' => $account->currency_code,
            'transaction_date' => now()->toDateString(),
        ]);

        $this->assertEquals(7000, $account->fresh()->current_balance);

        $this->service->update($transaction, [
            'amount' => 5000, // Changed from 3000 to 5000
            'transaction_type' => 'expense',
            'currency_code' => $account->currency_code,
        ]);

        $this->assertEquals(5000, $account->fresh()->current_balance);
    }
}
```

### 2. Create Account Controller Feature Tests
```php
// Modules/Mokey/Tests/Feature/AccountControllerTest.php
namespace Modules\Mokey\Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\User;
use Modules\Mokey\Models\Account;
use Modules\Mokey\Models\Currency;

class AccountControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        Currency::factory()->create(['code' => 'USD']);
    }

    public function test_index_shows_user_accounts(): void
    {
        Account::factory()->count(3)->create(['user_id' => $this->user->id]);
        Account::factory()->count(2)->create(); // Other user's accounts

        $response = $this->actingAs($this->user)
            ->get(route('dashboard.mokey.accounts.index'));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('mokey/accounts')
            ->has('accounts.data', 3)
        );
    }

    public function test_store_creates_account(): void
    {
        $response = $this->actingAs($this->user)
            ->post(route('dashboard.mokey.accounts.store'), [
                'name' => 'Test Account',
                'account_type' => 'checking',
                'currency_code' => 'USD',
                'initial_balance' => 10000,
                'is_active' => true,
                'include_in_net_worth' => true,
            ]);

        $response->assertRedirect(route('dashboard.mokey.accounts.index'));
        $this->assertDatabaseHas('mokey_accounts', [
            'user_id' => $this->user->id,
            'name' => 'Test Account',
            'current_balance' => 10000,
        ]);
    }

    public function test_cannot_view_other_users_account(): void
    {
        $otherAccount = Account::factory()->create();

        $response = $this->actingAs($this->user)
            ->get(route('dashboard.mokey.accounts.show', $otherAccount));

        $response->assertStatus(403);
    }

    public function test_cannot_update_other_users_account(): void
    {
        $otherAccount = Account::factory()->create();

        $response = $this->actingAs($this->user)
            ->put(route('dashboard.mokey.accounts.update', $otherAccount), [
                'name' => 'Hacked',
            ]);

        $response->assertStatus(403);
    }

    public function test_destroy_deletes_account(): void
    {
        $account = Account::factory()->create(['user_id' => $this->user->id]);

        $response = $this->actingAs($this->user)
            ->delete(route('dashboard.mokey.accounts.destroy', $account));

        $response->assertRedirect(route('dashboard.mokey.accounts.index'));
        $this->assertSoftDeleted('mokey_accounts', ['id' => $account->id]);
    }
}
```

### 3. Create Budget Service Unit Tests
```php
// Modules/Mokey/Tests/Unit/BudgetServiceTest.php
namespace Modules\Mokey\Tests\Unit;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Mokey\Models\Budget;
use Modules\Mokey\Models\Transaction;
use Modules\Mokey\Models\Category;
use Modules\Mokey\Services\BudgetService;

class BudgetServiceTest extends TestCase
{
    use RefreshDatabase;

    private BudgetService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(BudgetService::class);
    }

    public function test_calculate_variance_under_budget(): void
    {
        $budget = Budget::factory()->create([
            'allocated_amount' => 50000, // $500
            'spent_amount' => 30000, // $300
        ]);

        $variance = $this->service->calculateVariance($budget);

        $this->assertEquals(50000, $variance['allocated']);
        $this->assertEquals(30000, $variance['spent']);
        $this->assertEquals(20000, $variance['remaining']);
        $this->assertEquals(20000, $variance['variance']);
        $this->assertEquals(60, $variance['percent_used']);
        $this->assertFalse($variance['is_over_budget']);
    }

    public function test_calculate_variance_over_budget(): void
    {
        $budget = Budget::factory()->create([
            'allocated_amount' => 50000,
            'spent_amount' => 60000,
        ]);

        $variance = $this->service->calculateVariance($budget);

        $this->assertEquals(0, $variance['remaining']);
        $this->assertEquals(-10000, $variance['variance']);
        $this->assertTrue($variance['is_over_budget']);
    }

    public function test_recalculate_spent_from_transactions(): void
    {
        $category = Category::factory()->create(['type' => 'expense']);
        $budget = Budget::factory()->create([
            'category_id' => $category->id,
            'spent_amount' => 0,
            'start_date' => now()->startOfMonth(),
            'end_date' => now()->endOfMonth(),
        ]);

        Transaction::factory()->create([
            'user_id' => $budget->user_id,
            'category_id' => $category->id,
            'transaction_type' => 'expense',
            'base_amount' => 10000,
            'transaction_date' => now(),
        ]);

        Transaction::factory()->create([
            'user_id' => $budget->user_id,
            'category_id' => $category->id,
            'transaction_type' => 'expense',
            'base_amount' => 5000,
            'transaction_date' => now(),
        ]);

        $this->service->recalculateSpent($budget);

        $this->assertEquals(15000, $budget->fresh()->spent_amount);
    }
}
```

### 4. Create API Documentation
```markdown
# docs/mokey/api-reference.md

# Mokey Finance Module API Reference

## Base URL
All endpoints are prefixed with `/api/mokey/`

## Authentication
All endpoints require authentication via Laravel Sanctum.
Include bearer token in header: `Authorization: Bearer {token}`

## Endpoints

### Accounts

#### Get Account Balance
```
GET /accounts/{id}/balance
```
Response:
```json
{
  "balance": 15000,
  "balance_formatted": "$150.00",
  "currency": "USD"
}
```

### Transactions

#### Get Transaction Stats
```
GET /transactions/stats?start_date=2025-01-01&end_date=2025-01-31
```
Response:
```json
{
  "total_income": 500000,
  "total_expenses": 350000,
  "net": 150000,
  "transaction_count": 45
}
```

#### Quick Create Transaction
```
POST /transactions/quick
```
Body:
```json
{
  "account_id": 1,
  "transaction_type": "expense",
  "amount": 2500,
  "description": "Coffee"
}
```

### Exchange Rates

#### Get Rate
```
GET /exchange-rates/{from}/{to}
```
Example: `GET /exchange-rates/USD/EUR`

Response:
```json
{
  "from": "USD",
  "to": "EUR",
  "rate": 0.92,
  "effective_date": "2025-01-15"
}
```

## Error Responses

All errors return:
```json
{
  "message": "Error description",
  "errors": {
    "field": ["Validation error"]
  }
}
```

Common status codes:
- 401: Unauthenticated
- 403: Forbidden
- 404: Not Found
- 422: Validation Error
```

### 5. Create User Guide
```markdown
# docs/mokey/user-guide.md

# Mokey Finance Module User Guide

## Overview
Mokey is a personal finance tracking module that helps you manage accounts,
track transactions, set budgets, and achieve financial goals.

## Getting Started

### Setting Up Accounts
1. Navigate to Mokey > Accounts
2. Click "Add Account"
3. Enter account details:
   - Name (e.g., "Main Checking")
   - Type (Checking, Savings, Credit Card, Cash, Investment)
   - Currency
   - Initial Balance

### Recording Transactions
1. Go to Mokey > Transactions
2. Click "Add Transaction"
3. Select transaction type:
   - **Income**: Money coming in
   - **Expense**: Money going out
   - **Transfer**: Moving between your accounts
4. Fill in amount, category, and date
5. Add description for reference

### Setting Budgets
1. Navigate to Mokey > Budgets
2. Click "Add Budget"
3. Choose a category (or leave blank for all expenses)
4. Set your budget amount
5. Select period (Monthly, Yearly, Custom)

### Tracking Goals
1. Go to Mokey > Goals
2. Click "Add Goal"
3. Set goal type:
   - Savings: Building an emergency fund
   - Debt Payoff: Paying off loans
   - Purchase: Saving for a big purchase
4. Enter target amount and optional target date

## Tips

### Reconciliation
Mark transactions as "reconciled" after verifying with bank statements.
This helps track which transactions have been confirmed.

### Categories
Use categories to organize transactions. This helps with budgeting
and understanding spending patterns.

### Multi-Currency
Mokey supports multiple currencies. Set your primary currency in
account settings, and exchange rates are applied automatically.
```

## Todo List
- [ ] Create TransactionServiceTest
- [ ] Create BudgetServiceTest
- [ ] Create GoalServiceTest
- [ ] Create ExchangeRateServiceTest
- [ ] Create AccountControllerTest
- [ ] Create TransactionControllerTest
- [ ] Create BudgetControllerTest
- [ ] Create GoalControllerTest
- [ ] Create policy tests
- [ ] Write API documentation
- [ ] Write user guide
- [ ] Write developer guide
- [ ] Run coverage report
- [ ] Fix any failing tests

## Success Criteria
- [ ] All tests pass
- [ ] 80%+ code coverage
- [ ] API documentation complete
- [ ] User guide covers all features
- [ ] Developer guide explains architecture

## Risk Assessment
- **Risk:** Tests slow due to database. **Mitigation:** Use RefreshDatabase trait, in-memory SQLite.
- **Risk:** Coverage gaps. **Mitigation:** Use coverage report to identify.

## Security Considerations
- Tests verify authorization policies
- No real credentials in test data
- Test isolation prevents data leakage

## Completion Checklist
After this phase:
- [ ] All 15 phases completed
- [ ] Module fully functional
- [ ] Tests passing
- [ ] Documentation complete
- [ ] Ready for code review
