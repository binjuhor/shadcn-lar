# Laravel Modules v12: Finance/Mokey Module Research

## Executive Summary
nwidart/laravel-modules v12 provides modular Laravel architecture via self-contained units with controllers, models, migrations, service providers, and routes. Your codebase (Blog, Ecommerce, Permission modules) follows established patterns suitable for extending into Finance/Mokey.

---

## 1. Module Creation & Structure Best Practices

**Creation Command:**
```bash
php artisan module:make Mokey
```

Generates structure:
- `Modules/Mokey/Models/` - Domain entities (Account, Transaction, Budget, Goal)
- `Modules/Mokey/Policies/` - Authorization rules per model
- `Modules/Mokey/Controllers/` - API/Web controllers
- `Modules/Mokey/Providers/` - Service bootstrap
- `Modules/Mokey/database/migrations/` - Schema definition
- `Modules/Mokey/database/seeders/` - Sample data
- `Modules/Mokey/Routes/` - api.php, web.php
- `Modules/Mokey/Tests/` - Feature/Unit tests

**Key Pattern:** Use PSR-4 autoloading via composer merge-plugin. Run `composer dump-autoload` after module creation.

---

## 2. Service Provider Registration Pattern

**Observed from Blog/Permission modules:**

```php
class MokeyServiceProvider extends ServiceProvider
{
    use PathNamespace;

    protected string $name = 'Mokey';
    protected string $nameLower = 'mokey';

    public function boot(): void
    {
        $this->registerPolicies();      // Gate::policy() for each model
        $this->registerTranslations();
        $this->registerConfig();
        $this->registerViews();
        $this->loadMigrationsFrom(module_path($this->name, 'database/migrations'));
    }

    public function register(): void
    {
        $this->app->register(EventServiceProvider::class);
        $this->app->register(RouteServiceProvider::class);
    }

    protected function registerPolicies(): void
    {
        Gate::policy(Account::class, AccountPolicy::class);
        Gate::policy(Transaction::class, TransactionPolicy::class);
        Gate::policy(Budget::class, BudgetPolicy::class);
        Gate::policy(Goal::class, GoalPolicy::class);
    }
}
```

**Important:** Boot policies BEFORE migrations. Use `module_path()` helper for relative asset loading.

---

## 3. Migration Handling

**Pattern:** Module migrations auto-loaded via `loadMigrationsFrom()` in ServiceProvider boot().

```php
$this->loadMigrationsFrom(module_path($this->name, 'database/migrations'));
```

**Best Practice:**
- Create migrations in `Modules/Mokey/database/migrations/`
- Use timestamp naming: `2025_12_19_000000_create_accounts_table.php`
- Run migrations normally: `php artisan migrate`
- Seeders in `Modules/Mokey/database/seeders/`

**Multi-Currency Consideration:** Add migration for currency pivot table supporting multiple base currencies per account.

---

## 4. Policies & Authorization Patterns

**Observed implementation (Blog example):**

```php
// In ServiceProvider boot()
protected function registerPolicies(): void
{
    Gate::policy(Post::class, PostPolicy::class);
    Gate::policy(Category::class, CategoryPolicy::class);
    Gate::policy(Tag::class, TagPolicy::class);
}
```

**For Mokey, create policies:**
- `AccountPolicy` - User can manage own/shared accounts
- `TransactionPolicy` - Record-level permissions
- `BudgetPolicy` - Budget ownership/sharing rules
- `GoalPolicy` - Goal-based access control

Use Permission module's Role integration for granular access (e.g., Finance Manager role can approve transactions).

---

## 5. User Model Extension (Critical)

**Approach:** Do NOT extend User model directly from module. Instead:

1. **In Mokey module, create polymorphic relationship:**
```php
// Modules/Mokey/Models/Account.php
class Account extends Model
{
    protected $fillable = ['user_id', 'currency', 'balance'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
```

2. **Register User scopes in EventServiceProvider:**
```php
// Modules/Mokey/Providers/EventServiceProvider.php
User::resolveRelationUsing('accounts', function ($userModel) {
    return $userModel->hasMany(Account::class);
});
```

**Alternative (cleaner):** Add macros in ServiceProvider:
```php
public function boot(): void
{
    User::macro('accounts', function () {
        return $this->hasMany(Account::class);
    });
}
```

This keeps User model clean and module-agnostic.

---

## 6. Multi-Currency Handling Recommendations

**Three approaches:**

### A. Currency Column + Exchange Rates Table
```php
// accounts table: currency (ISO-4217 code)
// Create separate rates table for daily forex updates
Schema::create('exchange_rates', function (Blueprint $table) {
    $table->id();
    $table->string('from_currency');
    $table->string('to_currency');
    $table->decimal('rate', 10, 6);
    $table->date('date');
    $table->timestamps();
    $table->unique(['from_currency', 'to_currency', 'date']);
});
```

### B. Use Laravel Money Package
```bash
composer require akaunting/laravel-money
# or
composer require moneyphp/money
```
Provides Money value objects with automatic conversion logic.

### C. Store as Integers (Cents/Smallest Units)
```php
// All amounts in cents: amount = 15000 = $150.00
// Conversion: display = $amount / 100
```

**Recommendation:** Use approach B (Laravel Money) for simplicity + type safety. Integrate in Mokey config:

```php
// Modules/Mokey/config/mokey.php
return [
    'default_currency' => env('MOKEY_DEFAULT_CURRENCY', 'USD'),
    'supported_currencies' => ['USD', 'EUR', 'GBP', 'JPY'],
];
```

---

## 7. Key Packages to Consider

| Package | Purpose | Integration |
|---------|---------|-------------|
| `akaunting/laravel-money` | Money value objects | Cast columns in Transaction/Account models |
| `spatie/laravel-package-tools` | Service provider boilerplate | Already used in Permission module |
| `laravel/passport` | API auth tokens | For Finance API endpoints |
| `spatie/laravel-activitylog` | Audit trails | Track all transaction modifications |
| `moneyphp/money` | ISO-4217 currency handling | Alternative to akaunting |

**Audit Trail Pattern (Finance Critical):**
```php
// In Transaction model
use Spatie\Activitylog\Traits\LogsActivity;

class Transaction extends Model
{
    use LogsActivity;

    protected static $logName = 'transactions';
    protected static $logOnlyDirty = true;
    protected static $logAttributesToIgnore = ['created_at', 'updated_at'];
}
```

---

## 8. Testing Strategy

**Structure:** `Modules/Mokey/Tests/Feature/` and `Modules/Mokey/Tests/Unit/`

```php
// Feature test example
class TransactionTest extends TestCase
{
    public function test_user_can_create_transaction()
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create();

        $this->actingAs($user)
             ->post('/mokey/transactions', [
                 'account_id' => $account->id,
                 'amount' => 100,
                 'description' => 'Test',
             ])
             ->assertRedirect();
    }
}
```

Use module's test bootstrap in `phpunit.xml` to auto-discover tests.

---

## 9. Configuration Best Practice

Store config in `Modules/Mokey/config/mokey.php`:

```php
return [
    'table_prefix' => 'mokey_',
    'default_currency' => 'USD',
    'transaction_approval_required' => false,
    'audit_trail_enabled' => true,
    'max_account_per_user' => 10,
];
```

Publish via ServiceProvider: `$this->publishes([...], 'config')` allowing override in app/config/mokey.php.

---

## Key Takeaways

1. **Use ServiceProvider pattern** - Boot, register policies, load migrations in order
2. **Don't extend User** - Use relationships + macros to extend functionality
3. **Implement Policies** - Gate::policy() for all financial models
4. **Multi-currency:** Use akaunting/laravel-money + exchange_rates table
5. **Audit everything** - spatie/laravel-activitylog for transaction history
6. **Follow module structure** - Models, Controllers, Policies organized per entity
7. **Test thoroughly** - Feature tests for authorization, Unit tests for money calculations

---

## Unresolved Questions

- Should Mokey use event broadcasting for real-time balance updates?
- Webhook handling for external bank integrations (future requirement)?
- Pagination strategy for transaction history (potentially thousands per account)?
