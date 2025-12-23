# Phase 08: Routes & Navigation

## Context
- Parent plan: [plan.md](../plan.md)
- Dependencies: Phase 05 (controllers must exist)

## Overview
- Priority: medium
- Status: pending
- Description: Define web and API routes. Integrate with sidebar navigation. Follow Ecommerce module routing pattern.

## Key Insights
From research: Routes use prefix `dashboard/mokey`, name prefix `dashboard.mokey.`. Use resource routing where possible.

## Requirements
### Functional
- Web routes for all CRUD operations
- Dashboard route as module entry point
- API routes for async operations
- Sidebar navigation integration

### Non-functional
- Routes protected by auth and verified middleware
- Follow existing module conventions

## Related Code Files
### Files to Create
- `Modules/Mokey/routes/web.php`
- `Modules/Mokey/routes/api.php`

### Files to Modify
- `resources/js/lib/sidebar-list.ts` (add Mokey navigation)

## Implementation Steps

### 1. Create web routes
```php
// Modules/Mokey/routes/web.php
<?php

use Illuminate\Support\Facades\Route;
use Modules\Mokey\Http\Controllers\DashboardController;
use Modules\Mokey\Http\Controllers\AccountController;
use Modules\Mokey\Http\Controllers\TransactionController;
use Modules\Mokey\Http\Controllers\BudgetController;
use Modules\Mokey\Http\Controllers\GoalController;
use Modules\Mokey\Http\Controllers\CategoryController;

Route::middleware(['auth', 'verified'])->prefix('dashboard/mokey')
    ->name('dashboard.mokey.')
    ->group(function () {

        // Dashboard
        Route::get('/', [DashboardController::class, 'index'])->name('index');

        // Accounts
        Route::resource('accounts', AccountController::class);

        // Transactions
        Route::resource('transactions', TransactionController::class);
        Route::post('transactions/{transaction}/reconcile', [TransactionController::class, 'reconcile'])
            ->name('transactions.reconcile');

        // Budgets
        Route::resource('budgets', BudgetController::class);
        Route::get('budgets/{budget}/variance', [BudgetController::class, 'variance'])
            ->name('budgets.variance');

        // Goals
        Route::resource('goals', GoalController::class);
        Route::post('goals/{goal}/progress', [GoalController::class, 'updateProgress'])
            ->name('goals.update-progress');
        Route::post('goals/{goal}/snapshot', [GoalController::class, 'createSnapshot'])
            ->name('goals.snapshot');
        Route::post('goals/{goal}/complete', [GoalController::class, 'complete'])
            ->name('goals.complete');

        // Categories
        Route::resource('categories', CategoryController::class);
    });
```

### 2. Create API routes
```php
// Modules/Mokey/routes/api.php
<?php

use Illuminate\Support\Facades\Route;
use Modules\Mokey\Http\Controllers\Api\AccountController;
use Modules\Mokey\Http\Controllers\Api\TransactionController;
use Modules\Mokey\Http\Controllers\Api\ExchangeRateController;

Route::middleware(['auth:sanctum'])->prefix('mokey')
    ->name('api.mokey.')
    ->group(function () {

        // Account balance
        Route::get('accounts/{account}/balance', [AccountController::class, 'balance'])
            ->name('accounts.balance');

        // Transaction stats
        Route::get('transactions/stats', [TransactionController::class, 'stats'])
            ->name('transactions.stats');

        // Exchange rates
        Route::get('exchange-rates/{from}/{to}', [ExchangeRateController::class, 'getRate'])
            ->name('exchange-rates.get');
        Route::post('exchange-rates', [ExchangeRateController::class, 'store'])
            ->name('exchange-rates.store');

        // Quick transaction create (for mobile/shortcuts)
        Route::post('transactions/quick', [TransactionController::class, 'quickStore'])
            ->name('transactions.quick');
    });
```

### 3. Update RouteServiceProvider
```php
// Modules/Mokey/Providers/RouteServiceProvider.php
namespace Modules\Mokey\Providers;

use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Route;

class RouteServiceProvider extends ServiceProvider
{
    protected string $name = 'Mokey';

    public function boot(): void
    {
        parent::boot();
    }

    public function map(): void
    {
        $this->mapApiRoutes();
        $this->mapWebRoutes();
    }

    protected function mapWebRoutes(): void
    {
        Route::middleware('web')->group(module_path($this->name, '/routes/web.php'));
    }

    protected function mapApiRoutes(): void
    {
        Route::middleware('api')
            ->prefix('api')
            ->name('api.')
            ->group(module_path($this->name, '/routes/api.php'));
    }
}
```

### 4. Add sidebar navigation
```typescript
// Add to resources/js/lib/sidebar-list.ts
// Under existing navigation items, add:

{
  title: 'Mokey Finance',
  items: [
    {
      title: 'Dashboard',
      url: '/dashboard/mokey',
      icon: 'IconChartBar',
    },
    {
      title: 'Accounts',
      url: '/dashboard/mokey/accounts',
      icon: 'IconWallet',
    },
    {
      title: 'Transactions',
      url: '/dashboard/mokey/transactions',
      icon: 'IconArrowsExchange',
    },
    {
      title: 'Budgets',
      url: '/dashboard/mokey/budgets',
      icon: 'IconCalculator',
    },
    {
      title: 'Goals',
      url: '/dashboard/mokey/goals',
      icon: 'IconTarget',
    },
    {
      title: 'Categories',
      url: '/dashboard/mokey/categories',
      icon: 'IconCategory',
    },
  ],
}
```

### 5. Create TypeScript route helper types
```typescript
// Add to resources/js/types/routes.ts or ziggy.ts
declare module '@inertiajs/core' {
  interface RouteList {
    'dashboard.mokey.index': {};
    'dashboard.mokey.accounts.index': {};
    'dashboard.mokey.accounts.create': {};
    'dashboard.mokey.accounts.store': {};
    'dashboard.mokey.accounts.show': { account: number | string };
    'dashboard.mokey.accounts.edit': { account: number | string };
    'dashboard.mokey.accounts.update': { account: number | string };
    'dashboard.mokey.accounts.destroy': { account: number | string };
    // ... same pattern for transactions, budgets, goals, categories
  }
}
```

### 6. Route verification commands
```bash
# List all mokey routes
php artisan route:list --name=mokey

# Verify routes registered
php artisan route:list | grep mokey
```

## Todo List
- [ ] Create `Modules/Mokey/routes/web.php` with all resources
- [ ] Create `Modules/Mokey/routes/api.php` for async endpoints
- [ ] Update RouteServiceProvider to load routes
- [ ] Add Mokey section to sidebar navigation
- [ ] Add TypeScript route types for type safety
- [ ] Verify routes with `php artisan route:list`
- [ ] Test route access with auth middleware

## Success Criteria
- [ ] All CRUD routes accessible at `/dashboard/mokey/*`
- [ ] API routes protected by Sanctum middleware
- [ ] Sidebar shows Mokey navigation section
- [ ] Unauthenticated access redirects to login

## Risk Assessment
- **Risk:** Route name collision with other modules. **Mitigation:** Use `dashboard.mokey.` prefix consistently.
- **Risk:** Missing auth middleware. **Mitigation:** Group all routes under auth middleware.

## Security Considerations
- All routes require authentication
- API routes use Sanctum for stateless auth
- Route model binding respects soft deletes

## Next Steps
Proceed to Phase 09: Frontend - Dashboard & Charts
