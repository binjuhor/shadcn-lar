# Research Report: Personal Finance Management Module (Laravel + Inertia.js + React)

**Date:** 2025-12-26
**Focus Areas:** Database design, money handling, multi-user isolation, UX patterns, security

---

## Executive Summary

Building a personal finance module requires careful attention to three critical areas: (1) **monetary precision** - use DECIMAL/NUMERIC or integer cents, never floats, (2) **data isolation** - leverage Laravel's query scopes with tenant_id column for multi-user safety, (3) **intuitive UX** - financial dashboards need clear hierarchy, real-time updates, and memoized calculations.

---

## Database Schema Design

### Core Tables & Relationships

**Users**
```sql
CREATE TABLE users (
  id BIGINT PRIMARY KEY,
  email VARCHAR(255) UNIQUE,
  name VARCHAR(255)
);
```

**Bank Accounts** (1 user : many accounts)
```sql
CREATE TABLE accounts (
  id BIGINT PRIMARY KEY,
  user_id BIGINT REFERENCES users(id),
  name VARCHAR(255),           -- "Checking", "Savings"
  account_type ENUM('checking', 'savings', 'credit'),
  balance DECIMAL(15,2),       -- Store in major currency units
  currency VARCHAR(3),         -- ISO 4217: USD, EUR, etc.
  UNIQUE(user_id, name)
);
```

**Transaction Categories**
```sql
CREATE TABLE categories (
  id BIGINT PRIMARY KEY,
  user_id BIGINT REFERENCES users(id),
  name VARCHAR(100),           -- "Groceries", "Utilities"
  type ENUM('income', 'expense'),
  color VARCHAR(7),            -- Optional: #FF5733
  UNIQUE(user_id, name)
);
```

**Transactions** (1 account : many transactions)
```sql
CREATE TABLE transactions (
  id BIGINT PRIMARY KEY,
  account_id BIGINT REFERENCES accounts(id),
  category_id BIGINT REFERENCES categories(id),
  amount DECIMAL(15,2),        -- Precise decimal storage
  currency VARCHAR(3),
  description TEXT,
  transaction_date DATE,
  created_at TIMESTAMP,
  updated_at TIMESTAMP,
  INDEX(account_id, transaction_date)
);
```

**Budgets** (1 user : many budgets)
```sql
CREATE TABLE budgets (
  id BIGINT PRIMARY KEY,
  user_id BIGINT REFERENCES users(id),
  category_id BIGINT REFERENCES categories(id),
  limit_amount DECIMAL(15,2),
  period ENUM('monthly', 'yearly'),
  active_from DATE,
  active_until DATE NULL
);
```

### Key Principles

- **Tenant Isolation:** All tables have `user_id` or inherit it via FK chain. Use global scopes in Eloquent.
- **No Denormalization:** Calculate summaries in queries, not stored columns (avoid sync issues).
- **Indexes:** Add on `(user_id, created_at)` for dashboard queries; `(account_id, date)` for transaction lists.

---

## Money Handling: DECIMAL vs. Integer

### Recommendation: **Use DECIMAL(15,2)**

**Why NOT floats (FLOAT/DOUBLE):**
- Binary representation can't store decimal values exactly (19.99 becomes 19.990000000000002)
- Rounding errors accumulate in financial calculations
- Legal/compliance risk

**DECIMAL Advantages:**
- Precise decimal arithmetic
- Handles up to 28-29 significant digits
- Native database support: PostgreSQL NUMERIC, MySQL DECIMAL
- Easier application logic (no conversion to/from cents)

**Alternative: Integer Cents** (Stripe/Modern Treasury approach)
- Store `$12.34` as integer `1234`
- Fast CPU operations, minimal storage
- **Drawback:** Requires manual conversion logic throughout app
- Use only if performance critical (unlikely for personal finance)

**Best Practice:**
```php
// Eloquent Model - cast to decimal
class Transaction extends Model {
    protected $casts = [
        'amount' => 'decimal:2',      // Stored as DECIMAL(15,2)
        'currency' => 'string',
    ];
}

// When calculating: precise sum
$monthlyTotal = Transaction::where('user_id', $userId)
    ->where('transaction_date', '>=', $from)
    ->sum('amount');
```

**Multi-Currency:** Always store currency code (ISO 4217) alongside amount. Never assume USD.

---

## Multi-User Data Isolation

### Isolation Pattern: Single DB + Tenant ID + Global Scopes

**Recommended package:** `spatie/laravel-permission` + query scopes

**Implementation:**
```php
class Transaction extends Model {
    protected static function booted() {
        static::addGlobalScope('user', fn($q) =>
            $q->whereHas('account', fn($a) =>
                $a->where('user_id', auth()->id())
            )
        );
    }
}

class Account extends Model {
    protected static function booted() {
        static::addGlobalScope('user', fn($q) =>
            $q->where('user_id', auth()->id())
        );
    }
}
```

**Security Checklist:**
- ✓ Always validate `auth()->id()` == resource owner
- ✓ Use global scopes on all user-sensitive models
- ✓ Test that User A cannot access User B's transactions
- ✓ API tokens include user context; validate in middleware

---

## React UX Patterns

**Dashboard Layout:**
1. **Summary Cards** (top): Total balance, month-to-date income, expenses
2. **Charts** (middle): Spending by category (pie), income vs. expenses (line chart)
3. **Transaction List** (bottom): Latest 10 transactions, sortable, filterable

**Performance Optimization:**
- Memoize category totals calculations: `useMemo(() => calculateByCat(), [transactions])`
- Virtual rendering for large transaction lists (100+ items)
- Use TanStack React Charts for smooth real-time updates

**Key UX Principles:**
- Hierarchy: Net worth/savings goal most prominent
- Context: Show trends (month-over-month, year-over-year)
- Actionability: Highlight overspending categories
- Simplicity: 3-4 charts max per dashboard

---

## Security Considerations

### Encryption & Storage

| Data | Method | Reason |
|------|--------|--------|
| Account numbers | Laravel Crypt (AES-256) | PII |
| Passwords | Bcrypt hashing | Irreversible |
| Transaction data | DECIMAL precise storage | No encryption needed; access control sufficient |
| API tokens | APP_KEY rotation quarterly | Compromise requires re-key |

**Implementation:**
```php
class Account extends Model {
    protected $encrypted = ['account_number'];  // Auto-encrypt on save
}
```

### Access Control

- **Field-level:** Hide full account numbers in list views; only show last 4 digits
- **Query-level:** Global scopes + policy authorization
- **Logging:** Sanitize before logging; never log full account/card numbers

**Laravel Policy Example:**
```php
class TransactionPolicy {
    public function view(User $user, Transaction $t): bool {
        return $t->account->user_id === $user->id;
    }
}
```

### Compliance

- GDPR: Encrypt PII, implement data export/deletion endpoints
- No PCI DSS need: Don't store card numbers (use payment gateway tokens only)
- Audit trail: Log all account access, transfers (immutable)

---

## Recommended Packages

| Package | Purpose |
|---------|---------|
| `spatie/laravel-permission` | RBAC + multi-tenancy permission isolation |
| `laravel/cashier` | Payment subscription handling (if needed) |
| `inertiajs/inertia-laravel` | React bridge (already in stack) |
| `spatie/laravel-query-builder` | Filterable API endpoints |
| `nunomaduro/pest` | Testing (optional but recommended) |

---

## Implementation Roadmap

1. **Phase 1:** Schema + seeders, Account/Category CRUD
2. **Phase 2:** Transaction entry + list views (paginated)
3. **Phase 3:** Dashboard charts + summary calculations
4. **Phase 4:** Budget tracking, alerts, reports
5. **Phase 5:** Export (CSV/PDF), multi-currency

---

## Key Takeaways

- **Precision:** Use `DECIMAL(15,2)`, not floats
- **Isolation:** Tenant ID + global scopes + policy authorization
- **UX:** Memoized calculations, virtual rendering, clear hierarchy
- **Security:** Encrypt PII, never log sensitive data, audit all access
- **Testing:** Test multi-user isolation aggressively before production

---

## Unresolved Questions

1. Should recurring transactions (subscriptions) be auto-generated or manual?
2. Multi-currency conversion: Use real-time rates or user-set rates?
3. Export format preference: CSV, PDF, or both?
4. Notification system: Email alerts for budget overages?

---

### Sources

- [How to Design a Database for Financial Applications - GeeksforGeeks](https://www.geeksforgeeks.org/dbms/how-to-design-a-database-for-financial-applications/)
- [Storing currency values: data types, caveats, best practices](https://cardinalby.github.io/blog/post/best-practices/storing-currency-values-data-types/)
- [Floats Don't Work For Storing Cents - Modern Treasury](https://www.moderntreasury.com/journal/floats-dont-work-for-storing-cents)
- [Building Multi-Tenant SaaS Applications Using Laravel + React in 2025](https://cloudexistechnolabs.com/building-multi-tenant-saas-applications-using-laravel-react-in-2025/)
- [Implementing Role-Based Access Control (RBAC) in Laravel 12](https://needlaravelsite.com/blog/implementing-role-based-access-control-rbac-in-laravel-12)
- [React Financial Dashboard Design Patterns](https://olivertriunfo.com/react-financial-dashboard-design-patterns/)
- [Building a Personal Finance Dashboard with React](https://medium.com/@janeezy/building-a-personal-finance-dashboard-with-react-from-financial-concepts-to-code-69008c4778fe)
- [Laravel Cryptography: Secure Your Application with Built-in Encryption](https://medium.com/addweb-engineering/laravel-cryptography-secure-your-application-with-built-in-encryption-f0d3270aa82a)
