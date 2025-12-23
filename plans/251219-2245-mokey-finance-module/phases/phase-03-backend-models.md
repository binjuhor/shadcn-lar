# Phase 03: Backend Models & Relationships

## Context
- Parent plan: [plan.md](../plan.md)
- Dependencies: Phase 02 (tables must exist)

## Overview
- Priority: high
- Status: pending
- Description: Create Eloquent models with relationships, casts, scopes. Port Money value object. Setup audit trail. Register User relationship macros.

## Key Insights
From mokeyv2: Models use typed properties, HasFactory, SoftDeletes. Money value object handles amount display. Auditing trait tracks changes.

## Requirements
### Functional
- 9 models matching database schema
- Proper BelongsTo/HasMany relationships
- Amount casts to Money value object for display
- Audit logging on Transaction, Budget, Goal

### Non-functional
- Use typed properties, not docblocks
- Follow PSR-12 standards

## Related Code Files
### Files to Create
```
Modules/Mokey/Models/
├── Currency.php
├── ExchangeRate.php
├── Category.php
├── Account.php
├── Transaction.php
├── AccountBalance.php
├── Budget.php
├── Goal.php
└── GoalProgressSnapshot.php

Modules/Mokey/ValueObjects/Money.php
Modules/Mokey/Casts/MoneyCast.php
```

### Files to Modify
- `Modules/Mokey/Providers/MokeyServiceProvider.php` (add User macros)

## Implementation Steps

### 1. Create Money Value Object
```php
// Modules/Mokey/ValueObjects/Money.php
namespace Modules\Mokey\ValueObjects;

class Money
{
    public function __construct(
        public readonly int $amount, // in cents
        public readonly string $currency = 'USD'
    ) {}

    public function formatted(): string
    {
        $symbol = match($this->currency) {
            'USD' => '$', 'EUR' => '€', 'GBP' => '£',
            'VND' => '₫', 'JPY' => '¥', default => $this->currency
        };
        $decimal = $this->currency === 'VND' || $this->currency === 'JPY' ? 0 : 2;
        return $symbol . number_format($this->amount / pow(10, $decimal), $decimal);
    }

    public function toFloat(): float
    {
        $decimal = $this->currency === 'VND' || $this->currency === 'JPY' ? 0 : 2;
        return $this->amount / pow(10, $decimal);
    }

    public static function fromFloat(float $amount, string $currency = 'USD'): self
    {
        $decimal = $currency === 'VND' || $currency === 'JPY' ? 0 : 2;
        return new self((int) round($amount * pow(10, $decimal)), $currency);
    }
}
```

### 2. Create Account Model
```php
// Modules/Mokey/Models/Account.php
namespace Modules\Mokey\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use OwenIt\Auditing\Contracts\Auditable;

class Account extends Model implements Auditable
{
    use HasFactory, SoftDeletes, \OwenIt\Auditing\Auditable;

    protected $table = 'mokey_accounts';

    protected $fillable = [
        'user_id', 'account_type', 'name', 'currency_code',
        'account_number', 'institution_name', 'current_balance',
        'initial_balance', 'is_active', 'include_in_net_worth',
    ];

    protected $casts = [
        'current_balance' => 'integer',
        'initial_balance' => 'integer',
        'is_active' => 'boolean',
        'include_in_net_worth' => 'boolean',
        'account_number' => 'encrypted',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    public function balanceSnapshots(): HasMany
    {
        return $this->hasMany(AccountBalance::class);
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class, 'currency_code', 'code');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }
}
```

### 3. Create Transaction Model
```php
// Modules/Mokey/Models/Transaction.php
namespace Modules\Mokey\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use OwenIt\Auditing\Contracts\Auditable;

class Transaction extends Model implements Auditable
{
    use HasFactory, SoftDeletes, \OwenIt\Auditing\Auditable;

    protected $table = 'mokey_transactions';

    protected $fillable = [
        'user_id', 'account_id', 'category_id', 'transfer_account_id',
        'transaction_type', 'amount', 'currency_code', 'exchange_rate',
        'base_amount', 'description', 'notes', 'transaction_date',
        'reconciled_at', 'reference_number',
    ];

    protected $casts = [
        'amount' => 'integer',
        'base_amount' => 'integer',
        'exchange_rate' => 'decimal:8',
        'transaction_date' => 'date',
        'reconciled_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class);
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function transferAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'transfer_account_id');
    }

    public function scopeIncome($query)
    {
        return $query->where('transaction_type', 'income');
    }

    public function scopeExpense($query)
    {
        return $query->where('transaction_type', 'expense');
    }

    public function scopeTransfer($query)
    {
        return $query->where('transaction_type', 'transfer');
    }

    public function scopeDateRange($query, $start, $end)
    {
        return $query->whereBetween('transaction_date', [$start, $end]);
    }
}
```

### 4. Create remaining models (Category, Budget, Goal, etc.)
Follow same pattern: typed properties, relationships, scopes.

### 5. Register User macros in ServiceProvider
```php
// In MokeyServiceProvider::boot()
\App\Models\User::macro('mokeyAccounts', function () {
    return $this->hasMany(\Modules\Mokey\Models\Account::class);
});

\App\Models\User::macro('mokeyTransactions', function () {
    return $this->hasMany(\Modules\Mokey\Models\Transaction::class);
});

\App\Models\User::macro('mokeyBudgets', function () {
    return $this->hasMany(\Modules\Mokey\Models\Budget::class);
});

\App\Models\User::macro('mokeyGoals', function () {
    return $this->hasMany(\Modules\Mokey\Models\Goal::class);
});
```

### 6. Create model factories
```php
// Modules/Mokey/database/factories/AccountFactory.php
namespace Modules\Mokey\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Mokey\Models\Account;

class AccountFactory extends Factory
{
    protected $model = Account::class;

    public function definition(): array
    {
        return [
            'user_id' => \App\Models\User::factory(),
            'account_type' => fake()->randomElement(['checking', 'savings', 'credit_card', 'cash']),
            'name' => fake()->words(2, true),
            'currency_code' => 'USD',
            'current_balance' => fake()->numberBetween(10000, 1000000), // cents
            'initial_balance' => fake()->numberBetween(0, 10000),
            'is_active' => true,
        ];
    }
}
```

## Todo List
- [ ] Create Money value object
- [ ] Create Currency, ExchangeRate models
- [ ] Create Category model with self-referencing parent
- [ ] Create Account model with encryption cast
- [ ] Create Transaction model with scopes
- [ ] Create AccountBalance model
- [ ] Create Budget model with period handling
- [ ] Create Goal, GoalProgressSnapshot models
- [ ] Add User macros in ServiceProvider
- [ ] Create factories for all models

## Success Criteria
- [ ] All models loadable without errors
- [ ] Relationships return correct related models
- [ ] `$user->mokeyAccounts()` works after macro registration
- [ ] Audit logs created on model changes

## Risk Assessment
- **Risk:** Circular dependency between models. **Mitigation:** Use string-based foreign key references.
- **Risk:** Encryption key not set. **Mitigation:** Verify APP_KEY in .env.

## Security Considerations
- account_number encrypted at rest
- User-scoped queries prevent data leakage
- Audit trail provides accountability

## Next Steps
Proceed to Phase 04: Services & Business Logic
