# Phase 1: Create Module + Copy Backend

**Parent Plan:** [plan.md](./plan.md)
**Source:** `~/Development/mokeyv2`
**Effort:** ~1 hour

## Overview

Create Finance module structure and copy/adapt backend code from mokeyv2.

**Status:** Pending

## Steps

### 1. Install Required Packages

```bash
composer require brick/money owen-it/laravel-auditing kalnoy/nestedset
```

### 2. Create Module

```bash
php artisan module:make Finance
```

### 3. Copy Migrations (Adapt namespaces)

From mokeyv2 `database/migrations/`:
- [ ] `create_currencies_table.php` → `Modules/Finance/database/migrations/`
- [ ] `create_categories_table.php` → adapt for module
- [ ] `create_accounts_table.php` → adapt for module
- [ ] `create_transactions_table.php` → adapt for module
- [ ] `create_budgets_table.php` → adapt for module

**Note:** Add `add_default_currency_to_users_table.php` migration

### 4. Copy Models (Update namespaces)

From mokeyv2 `app/Models/`:
- [ ] `Currency.php` → `Modules/Finance/Models/Currency.php`
- [ ] `Category.php` → `Modules/Finance/Models/Category.php`
- [ ] `Account.php` → `Modules/Finance/Models/Account.php`
- [ ] `Transaction.php` → `Modules/Finance/Models/Transaction.php`
- [ ] `Budget.php` → `Modules/Finance/Models/Budget.php`

**Changes:**
- Update namespace to `Modules\Finance\Models`
- Update use statements for relationships

### 5. Copy ValueObjects

- [ ] `app/ValueObjects/Money.php` → `Modules/Finance/ValueObjects/Money.php`

### 6. Copy Services

From mokeyv2 `app/Services/`:
- [ ] `TransactionService.php` → `Modules/Finance/Services/TransactionService.php`
- [ ] `BudgetService.php` → `Modules/Finance/Services/BudgetService.php`

**Changes:**
- Update namespaces
- Update model imports

### 7. Copy Controllers (Adapt for module routing)

From mokeyv2 `app/Http/Controllers/`:
- [ ] `AccountController.php` → `Modules/Finance/Http/Controllers/`
- [ ] `TransactionController.php` → `Modules/Finance/Http/Controllers/`
- [ ] `BudgetController.php` → `Modules/Finance/Http/Controllers/`
- [ ] `DashboardController.php` → `Modules/Finance/Http/Controllers/FinanceDashboardController.php`

**Changes:**
- Update namespaces
- Update Inertia render paths: `'Transactions/Index'` → `'finance/transactions/index'`
- Update route names for module prefix

### 8. Copy Request Classes

- [ ] `StoreTransactionRequest.php` → `Modules/Finance/Http/Requests/`
- [ ] Create `StoreAccountRequest.php`
- [ ] Create `StoreBudgetRequest.php`

### 9. Copy Policies

From mokeyv2 `app/Policies/`:
- [ ] `AccountPolicy.php` → `Modules/Finance/Policies/`
- [ ] `TransactionPolicy.php` → `Modules/Finance/Policies/`
- [ ] `BudgetPolicy.php` → `Modules/Finance/Policies/`

### 10. Copy Events & Listeners

- [ ] `app/Events/TransactionCreated.php` → `Modules/Finance/Events/`
- [ ] `app/Listeners/UpdateAccountBalance.php` → `Modules/Finance/Listeners/`

### 11. Copy Factories & Seeders

- [ ] `database/factories/*.php` → `Modules/Finance/database/factories/`
- [ ] `database/seeders/DefaultCategorySeeder.php` → `Modules/Finance/database/seeders/`

### 12. Setup Routes

Create `Modules/Finance/routes/web.php`:

```php
<?php

use Illuminate\Support\Facades\Route;
use Modules\Finance\Http\Controllers\AccountController;
use Modules\Finance\Http\Controllers\BudgetController;
use Modules\Finance\Http\Controllers\FinanceDashboardController;
use Modules\Finance\Http\Controllers\TransactionController;

Route::middleware(['auth', 'verified'])
    ->prefix('dashboard/finance')
    ->name('dashboard.finance.')
    ->group(function () {
        Route::get('/', [FinanceDashboardController::class, 'index'])->name('index');
        Route::resource('accounts', AccountController::class);
        Route::resource('transactions', TransactionController::class);
        Route::post('transactions/{transaction}/reconcile', [TransactionController::class, 'reconcile'])
            ->name('transactions.reconcile');
        Route::resource('budgets', BudgetController::class);
    });
```

### 13. Register Service Provider

Update `Modules/Finance/Providers/FinanceServiceProvider.php`:
- Register policies
- Register events/listeners
- Load migrations

### 14. Run Migrations

```bash
php artisan module:migrate Finance
php artisan db:seed --class="Modules\Finance\Database\Seeders\DefaultCategorySeeder"
```

## Namespace Changes Checklist

| Original | New |
|----------|-----|
| `App\Models\Account` | `Modules\Finance\Models\Account` |
| `App\Models\Transaction` | `Modules\Finance\Models\Transaction` |
| `App\Services\TransactionService` | `Modules\Finance\Services\TransactionService` |
| `App\ValueObjects\Money` | `Modules\Finance\ValueObjects\Money` |

## Success Criteria

- [ ] All migrations run successfully
- [ ] Models load with correct relationships
- [ ] TransactionService can record income/expense
- [ ] Routes registered correctly
- [ ] Policies authorize correctly

## Next Phase

→ [phase-02-frontend.md](./phase-02-frontend.md) - Convert Vue components to React
