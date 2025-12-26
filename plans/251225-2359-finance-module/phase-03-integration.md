# Phase 3: Integration & Tests

**Parent Plan:** [plan.md](./plan.md)
**Effort:** ~1 hour

## Overview

Wire up frontend to backend, add navigation, and write tests.

**Status:** Pending

## Steps

### 1. Add Finance to Sidebar Navigation

Update `resources/js/components/layout/data/sidebar-data.ts`:

```typescript
{
  title: 'Finance',
  icon: Wallet,
  items: [
    { title: 'Dashboard', url: '/dashboard/finance' },
    { title: 'Accounts', url: '/dashboard/finance/accounts' },
    { title: 'Transactions', url: '/dashboard/finance/transactions' },
    { title: 'Budgets', url: '/dashboard/finance/budgets' },
  ],
}
```

### 2. Register Routes in Frontend

Ensure Inertia pages resolve correctly in `resources/js/app.tsx`.

### 3. Test Backend Endpoints

Create `Modules/Finance/tests/Feature/`:

```php
// TransactionControllerTest.php
class TransactionControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_view_transactions()
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create();
        Transaction::factory()->for($account)->count(5)->create();

        $this->actingAs($user)
            ->get(route('dashboard.finance.transactions.index'))
            ->assertOk()
            ->assertInertia(fn($page) => $page
                ->component('finance/transactions/index')
                ->has('transactions.data', 5)
            );
    }

    public function test_user_cannot_view_other_users_transactions()
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        $account = Account::factory()->for($user2)->create();
        Transaction::factory()->for($account)->create();

        $this->actingAs($user1)
            ->get(route('dashboard.finance.transactions.index'))
            ->assertInertia(fn($page) => $page
                ->has('transactions.data', 0)
            );
    }

    public function test_user_can_create_income_transaction()
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create(['current_balance' => 0]);
        $category = Category::factory()->create(['type' => 'income']);

        $this->actingAs($user)
            ->post(route('dashboard.finance.transactions.store'), [
                'account_id' => $account->id,
                'category_id' => $category->id,
                'transaction_type' => 'income',
                'amount' => 10000, // $100.00 in cents
                'transaction_date' => now()->format('Y-m-d'),
                'description' => 'Salary',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('transactions', [
            'account_id' => $account->id,
            'amount' => 10000,
            'transaction_type' => 'income',
        ]);

        // Check balance updated
        $this->assertEquals(10000, $account->fresh()->current_balance);
    }

    public function test_user_can_create_expense_transaction()
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create(['current_balance' => 50000]);

        $this->actingAs($user)
            ->post(route('dashboard.finance.transactions.store'), [
                'account_id' => $account->id,
                'transaction_type' => 'expense',
                'amount' => 2500, // $25.00
                'transaction_date' => now()->format('Y-m-d'),
                'description' => 'Groceries',
            ])
            ->assertRedirect();

        // Balance should decrease
        $this->assertEquals(47500, $account->fresh()->current_balance);
    }

    public function test_cannot_expense_more_than_balance()
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create([
            'current_balance' => 1000,
            'account_type' => 'bank', // Not credit card
        ]);

        $this->actingAs($user)
            ->post(route('dashboard.finance.transactions.store'), [
                'account_id' => $account->id,
                'transaction_type' => 'expense',
                'amount' => 5000, // More than balance
                'transaction_date' => now()->format('Y-m-d'),
            ])
            ->assertSessionHasErrors();
    }
}
```

```php
// AccountControllerTest.php
class AccountControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_create_account()
    {
        $user = User::factory()->create();
        Currency::factory()->create(['code' => 'USD']);

        $this->actingAs($user)
            ->post(route('dashboard.finance.accounts.store'), [
                'name' => 'Checking',
                'account_type' => 'bank',
                'currency_code' => 'USD',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('accounts', [
            'user_id' => $user->id,
            'name' => 'Checking',
        ]);
    }

    public function test_user_cannot_delete_account_with_transactions()
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create();
        Transaction::factory()->for($account)->create();

        $this->actingAs($user)
            ->delete(route('dashboard.finance.accounts.destroy', $account))
            ->assertSessionHas('error');

        $this->assertDatabaseHas('accounts', ['id' => $account->id]);
    }
}
```

### 4. Update phpunit.xml

```xml
<testsuites>
    <testsuite name="Finance">
        <directory>Modules/Finance/tests</directory>
    </testsuite>
</testsuites>
```

### 5. Run Tests

```bash
php artisan test --filter=Finance
```

### 6. Seed Default Data

Create `Modules/Finance/database/seeders/FinanceDatabaseSeeder.php`:

```php
class FinanceDatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            CurrencySeeder::class,
            DefaultCategorySeeder::class,
        ]);
    }
}
```

### 7. Final Checklist

- [ ] Finance appears in sidebar
- [ ] Dashboard loads with summary data
- [ ] Can create/view/delete accounts
- [ ] Can create/view/delete transactions
- [ ] Can create/view budgets
- [ ] Budget progress calculates correctly
- [ ] Multi-user isolation works (User A can't see User B's data)
- [ ] All tests pass

## Test Commands

```bash
# Run all Finance tests
php artisan test --filter=Finance

# Run specific test
php artisan test --filter=TransactionControllerTest

# Run with coverage
php artisan test --filter=Finance --coverage
```

## Success Criteria

- [ ] All feature tests pass
- [ ] No TypeScript errors
- [ ] No console errors in browser
- [ ] Multi-user isolation verified
- [ ] Balance updates work correctly

## Post-Implementation

Optional enhancements for future:
- [ ] Add Goals feature
- [ ] Add Reports/Charts page
- [ ] Add CSV export
- [ ] Add recurring transactions
- [ ] Add exchange rate auto-sync
