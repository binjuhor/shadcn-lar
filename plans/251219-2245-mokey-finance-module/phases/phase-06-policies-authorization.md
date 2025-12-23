# Phase 06: Policies & Authorization

## Context
- Parent plan: [plan.md](../plan.md)
- Dependencies: Phase 03 (models must exist)

## Overview
- Priority: high
- Status: pending
- Description: Create policies for all Mokey models. Integrate with spatie/laravel-permission. Create permission seeder.

## Key Insights
From research: Policies registered in ServiceProvider via Gate::policy(). Blog module pattern shows policy structure.

## Requirements
### Functional
- Policy for each model: Account, Transaction, Budget, Goal, Category
- User ownership checks for personal data
- Permission-based access for admin operations

### Non-functional
- Policies use typed parameters
- viewAny always allowed for authenticated users

## Related Code Files
### Files to Create
```
Modules/Mokey/Policies/
├── AccountPolicy.php
├── TransactionPolicy.php
├── BudgetPolicy.php
├── GoalPolicy.php
└── CategoryPolicy.php

Modules/Mokey/database/seeders/PermissionSeeder.php
```

### Files to Modify
- `Modules/Mokey/Providers/MokeyServiceProvider.php` (register policies)

## Implementation Steps

### 1. Create AccountPolicy
```php
// Modules/Mokey/Policies/AccountPolicy.php
namespace Modules\Mokey\Policies;

use App\Models\User;
use Modules\Mokey\Models\Account;
use Illuminate\Auth\Access\HandlesAuthorization;

class AccountPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return true; // Authenticated users can view their accounts
    }

    public function view(User $user, Account $account): bool
    {
        return $user->id === $account->user_id;
    }

    public function create(User $user): bool
    {
        return true; // Any authenticated user can create accounts
    }

    public function update(User $user, Account $account): bool
    {
        return $user->id === $account->user_id;
    }

    public function delete(User $user, Account $account): bool
    {
        return $user->id === $account->user_id;
    }

    public function restore(User $user, Account $account): bool
    {
        return $user->id === $account->user_id;
    }

    public function forceDelete(User $user, Account $account): bool
    {
        return $user->id === $account->user_id
            && $user->hasPermissionTo('mokey.accounts.force-delete');
    }
}
```

### 2. Create TransactionPolicy
```php
// Modules/Mokey/Policies/TransactionPolicy.php
namespace Modules\Mokey\Policies;

use App\Models\User;
use Modules\Mokey\Models\Transaction;
use Illuminate\Auth\Access\HandlesAuthorization;

class TransactionPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Transaction $transaction): bool
    {
        return $user->id === $transaction->user_id;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Transaction $transaction): bool
    {
        // Can only update non-reconciled transactions
        if ($transaction->reconciled_at && !$user->hasPermissionTo('mokey.transactions.update-reconciled')) {
            return false;
        }

        return $user->id === $transaction->user_id;
    }

    public function delete(User $user, Transaction $transaction): bool
    {
        // Can only delete non-reconciled transactions
        if ($transaction->reconciled_at && !$user->hasPermissionTo('mokey.transactions.delete-reconciled')) {
            return false;
        }

        return $user->id === $transaction->user_id;
    }

    public function reconcile(User $user, Transaction $transaction): bool
    {
        return $user->id === $transaction->user_id;
    }
}
```

### 3. Create BudgetPolicy
```php
// Modules/Mokey/Policies/BudgetPolicy.php
namespace Modules\Mokey\Policies;

use App\Models\User;
use Modules\Mokey\Models\Budget;
use Illuminate\Auth\Access\HandlesAuthorization;

class BudgetPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Budget $budget): bool
    {
        return $user->id === $budget->user_id;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Budget $budget): bool
    {
        return $user->id === $budget->user_id;
    }

    public function delete(User $user, Budget $budget): bool
    {
        return $user->id === $budget->user_id;
    }
}
```

### 4. Create GoalPolicy
```php
// Modules/Mokey/Policies/GoalPolicy.php
namespace Modules\Mokey\Policies;

use App\Models\User;
use Modules\Mokey\Models\Goal;
use Illuminate\Auth\Access\HandlesAuthorization;

class GoalPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Goal $goal): bool
    {
        return $user->id === $goal->user_id;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Goal $goal): bool
    {
        return $user->id === $goal->user_id;
    }

    public function delete(User $user, Goal $goal): bool
    {
        return $user->id === $goal->user_id;
    }

    public function complete(User $user, Goal $goal): bool
    {
        return $user->id === $goal->user_id;
    }
}
```

### 5. Create CategoryPolicy (admin-only for create/update/delete)
```php
// Modules/Mokey/Policies/CategoryPolicy.php
namespace Modules\Mokey\Policies;

use App\Models\User;
use Modules\Mokey\Models\Category;
use Illuminate\Auth\Access\HandlesAuthorization;

class CategoryPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return true; // All users can view categories
    }

    public function view(User $user, Category $category): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('mokey.categories.create');
    }

    public function update(User $user, Category $category): bool
    {
        return $user->hasPermissionTo('mokey.categories.update');
    }

    public function delete(User $user, Category $category): bool
    {
        return $user->hasPermissionTo('mokey.categories.delete');
    }
}
```

### 6. Register policies in ServiceProvider
```php
// In MokeyServiceProvider::boot()
protected function registerPolicies(): void
{
    Gate::policy(Account::class, AccountPolicy::class);
    Gate::policy(Transaction::class, TransactionPolicy::class);
    Gate::policy(Budget::class, BudgetPolicy::class);
    Gate::policy(Goal::class, GoalPolicy::class);
    Gate::policy(Category::class, CategoryPolicy::class);
}
```

### 7. Create PermissionSeeder
```php
// Modules/Mokey/database/seeders/PermissionSeeder.php
namespace Modules\Mokey\Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class PermissionSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            // Category management (admin)
            'mokey.categories.create',
            'mokey.categories.update',
            'mokey.categories.delete',

            // Special transaction permissions
            'mokey.transactions.update-reconciled',
            'mokey.transactions.delete-reconciled',

            // Force delete
            'mokey.accounts.force-delete',
            'mokey.transactions.force-delete',
            'mokey.budgets.force-delete',
            'mokey.goals.force-delete',

            // Admin dashboard
            'mokey.admin.view-all-users',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }

        // Assign to admin role if exists
        $adminRole = Role::where('name', 'admin')->first();
        if ($adminRole) {
            $adminRole->givePermissionTo($permissions);
        }
    }
}
```

## Todo List
- [ ] Create AccountPolicy
- [ ] Create TransactionPolicy with reconcile check
- [ ] Create BudgetPolicy
- [ ] Create GoalPolicy
- [ ] Create CategoryPolicy (admin permissions)
- [ ] Register all policies in MokeyServiceProvider
- [ ] Create PermissionSeeder
- [ ] Run seeder: `php artisan db:seed --class=Modules\\Mokey\\Database\\Seeders\\PermissionSeeder`

## Success Criteria
- [ ] Unauthorized access returns 403
- [ ] Users can only access their own data
- [ ] Admin can manage categories
- [ ] Reconciled transactions protected from edit/delete

## Risk Assessment
- **Risk:** Permission not found error. **Mitigation:** Run seeder before testing policies.
- **Risk:** Policy not registered. **Mitigation:** Check ServiceProvider boot order.

## Security Considerations
- User ID compared with model's user_id, not request data
- Force delete requires explicit permission
- Reconciled transactions require special permission to modify

## Next Steps
Proceed to Phase 07: Form Requests & Validation
