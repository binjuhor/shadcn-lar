# Phase 02: Database Schema & Migrations

## Context
- Parent plan: [plan.md](../plan.md)
- Dependencies: Phase 01 (module must exist)

## Overview
- Priority: high
- Status: pending
- Description: Create all migration files for Mokey tables. Store amounts as integers (cents). Setup proper foreign keys, indexes, and soft deletes.

## Key Insights
From mokeyv2 analysis: 9 core tables needed. Amounts stored as integers for precision. Categories support nesting via parent_id. Goals support hierarchy.

## Requirements
### Functional
- 9 tables: currencies, exchange_rates, categories, accounts, transactions, account_balances, budgets, goals, goal_progress_snapshots
- Proper foreign key constraints
- Soft deletes on accounts, transactions, budgets, goals
- Encrypted account_number field

### Non-functional
- All timestamps in UTC
- Index on frequently queried columns (user_id, transaction_date, category_id)

## Related Code Files
### Files to Create
```
Modules/Mokey/database/migrations/
├── 2025_12_19_000001_create_mokey_currencies_table.php
├── 2025_12_19_000002_create_mokey_exchange_rates_table.php
├── 2025_12_19_000003_create_mokey_categories_table.php
├── 2025_12_19_000004_create_mokey_accounts_table.php
├── 2025_12_19_000005_create_mokey_transactions_table.php
├── 2025_12_19_000006_create_mokey_account_balances_table.php
├── 2025_12_19_000007_create_mokey_budgets_table.php
├── 2025_12_19_000008_create_mokey_goals_table.php
└── 2025_12_19_000009_create_mokey_goal_progress_snapshots_table.php
```

## Implementation Steps

### 1. Create currencies table
```php
Schema::create('mokey_currencies', function (Blueprint $table) {
    $table->id();
    $table->string('code', 3)->unique(); // ISO 4217
    $table->string('name');
    $table->string('symbol', 10);
    $table->unsignedTinyInteger('decimal_places')->default(2);
    $table->boolean('is_active')->default(true);
    $table->timestamps();
});
```

### 2. Create exchange_rates table
```php
Schema::create('mokey_exchange_rates', function (Blueprint $table) {
    $table->id();
    $table->string('from_currency', 3);
    $table->string('to_currency', 3);
    $table->decimal('rate', 16, 8);
    $table->date('effective_date');
    $table->timestamps();

    $table->unique(['from_currency', 'to_currency', 'effective_date']);
    $table->index('effective_date');
    $table->foreign('from_currency')->references('code')->on('mokey_currencies');
    $table->foreign('to_currency')->references('code')->on('mokey_currencies');
});
```

### 3. Create categories table
```php
Schema::create('mokey_categories', function (Blueprint $table) {
    $table->id();
    $table->string('name');
    $table->string('type')->default('expense'); // income, expense
    $table->foreignId('parent_id')->nullable()->constrained('mokey_categories')->nullOnDelete();
    $table->string('icon')->nullable();
    $table->string('color')->nullable();
    $table->boolean('is_active')->default(true);
    $table->timestamps();

    $table->index(['type', 'is_active']);
});
```

### 4. Create accounts table
```php
Schema::create('mokey_accounts', function (Blueprint $table) {
    $table->id();
    $table->foreignId('user_id')->constrained()->cascadeOnDelete();
    $table->string('account_type'); // checking, savings, credit_card, cash, investment
    $table->string('name');
    $table->string('currency_code', 3);
    $table->text('account_number')->nullable(); // encrypted
    $table->string('institution_name')->nullable();
    $table->bigInteger('current_balance')->default(0); // in cents
    $table->bigInteger('initial_balance')->default(0); // in cents
    $table->boolean('is_active')->default(true);
    $table->boolean('include_in_net_worth')->default(true);
    $table->timestamps();
    $table->softDeletes();

    $table->index(['user_id', 'is_active']);
    $table->foreign('currency_code')->references('code')->on('mokey_currencies');
});
```

### 5. Create transactions table
```php
Schema::create('mokey_transactions', function (Blueprint $table) {
    $table->id();
    $table->foreignId('user_id')->constrained()->cascadeOnDelete();
    $table->foreignId('account_id')->constrained('mokey_accounts')->cascadeOnDelete();
    $table->foreignId('category_id')->nullable()->constrained('mokey_categories')->nullOnDelete();
    $table->foreignId('transfer_account_id')->nullable()->constrained('mokey_accounts')->nullOnDelete();
    $table->string('transaction_type'); // income, expense, transfer
    $table->bigInteger('amount'); // in cents, always positive
    $table->string('currency_code', 3);
    $table->decimal('exchange_rate', 16, 8)->default(1);
    $table->bigInteger('base_amount'); // amount in user's base currency
    $table->string('description')->nullable();
    $table->text('notes')->nullable();
    $table->date('transaction_date');
    $table->timestamp('reconciled_at')->nullable();
    $table->string('reference_number')->nullable();
    $table->timestamps();
    $table->softDeletes();

    $table->index(['user_id', 'transaction_date']);
    $table->index(['account_id', 'transaction_date']);
    $table->index(['category_id']);
    $table->foreign('currency_code')->references('code')->on('mokey_currencies');
});
```

### 6. Create account_balances table (historical snapshots)
```php
Schema::create('mokey_account_balances', function (Blueprint $table) {
    $table->id();
    $table->foreignId('account_id')->constrained('mokey_accounts')->cascadeOnDelete();
    $table->bigInteger('balance'); // in cents
    $table->date('recorded_at');
    $table->timestamps();

    $table->unique(['account_id', 'recorded_at']);
    $table->index('recorded_at');
});
```

### 7. Create budgets table
```php
Schema::create('mokey_budgets', function (Blueprint $table) {
    $table->id();
    $table->foreignId('user_id')->constrained()->cascadeOnDelete();
    $table->foreignId('category_id')->nullable()->constrained('mokey_categories')->nullOnDelete();
    $table->string('period_type'); // monthly, yearly, custom
    $table->bigInteger('allocated_amount'); // in cents
    $table->bigInteger('spent_amount')->default(0); // in cents
    $table->string('currency_code', 3);
    $table->date('start_date');
    $table->date('end_date');
    $table->boolean('is_active')->default(true);
    $table->timestamps();
    $table->softDeletes();

    $table->index(['user_id', 'is_active']);
    $table->index(['start_date', 'end_date']);
    $table->foreign('currency_code')->references('code')->on('mokey_currencies');
});
```

### 8. Create goals table
```php
Schema::create('mokey_goals', function (Blueprint $table) {
    $table->id();
    $table->foreignId('user_id')->constrained()->cascadeOnDelete();
    $table->foreignId('parent_goal_id')->nullable()->constrained('mokey_goals')->nullOnDelete();
    $table->string('goal_type'); // savings, debt_payoff, purchase
    $table->string('timeframe'); // short_term, medium_term, long_term
    $table->string('name');
    $table->text('description')->nullable();
    $table->bigInteger('target_amount'); // in cents
    $table->bigInteger('current_amount')->default(0); // in cents
    $table->string('currency_code', 3);
    $table->date('start_date');
    $table->date('target_date')->nullable();
    $table->boolean('is_active')->default(true);
    $table->boolean('is_completed')->default(false);
    $table->timestamp('completed_at')->nullable();
    $table->timestamps();
    $table->softDeletes();

    $table->index(['user_id', 'is_active']);
    $table->foreign('currency_code')->references('code')->on('mokey_currencies');
});
```

### 9. Create goal_progress_snapshots table
```php
Schema::create('mokey_goal_progress_snapshots', function (Blueprint $table) {
    $table->id();
    $table->foreignId('goal_id')->constrained('mokey_goals')->cascadeOnDelete();
    $table->date('snapshot_date');
    $table->bigInteger('amount'); // in cents
    $table->decimal('progress_percent', 5, 2);
    $table->boolean('on_track')->default(true);
    $table->bigInteger('variance_amount')->default(0); // in cents
    $table->timestamps();

    $table->unique(['goal_id', 'snapshot_date']);
});
```

### 10. Create seeders
Create `Modules/Mokey/database/seeders/CurrencySeeder.php` with common currencies.
Create `Modules/Mokey/database/seeders/CategorySeeder.php` with default income/expense categories.

## Todo List
- [ ] Create all 9 migration files
- [ ] Create CurrencySeeder with USD, EUR, GBP, VND, JPY
- [ ] Create CategorySeeder with default categories
- [ ] Create factories for all models
- [ ] Run migrations: `php artisan migrate`
- [ ] Verify all tables created with correct structure

## Success Criteria
- [ ] All 9 tables exist in database with correct columns
- [ ] Foreign keys properly constrained
- [ ] Indexes created on high-query columns
- [ ] Seeders populate default data

## Risk Assessment
- **Risk:** Currency FK fails if currencies not seeded first. **Mitigation:** CurrencySeeder runs before others.
- **Risk:** Decimal precision loss. **Mitigation:** All amounts as bigInteger (cents).

## Security Considerations
- account_number field should use Laravel's encryption cast
- User can only access own data (enforced via policies in Phase 06)

## Next Steps
Proceed to Phase 03: Backend Models & Relationships
