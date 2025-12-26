# Finance Module Implementation Plan (Updated)

**Date:** 2025-12-26
**Status:** Planning
**Priority:** High
**Source:** Adapted from `~/Development/mokeyv2`

## Overview

Personal finance management module - adapting existing mokeyv2 implementation to shadcn-admin modular architecture. Backend mostly copy/adapt, frontend needs Vue→React conversion.

## Source Files (mokeyv2)

### Backend (Copy & Adapt)
| Type | Source Path | Target |
|------|-------------|--------|
| Models | `app/Models/{Account,Transaction,Category,Budget}.php` | `Modules/Finance/Models/` |
| Controllers | `app/Http/Controllers/{Account,Transaction,Budget,Dashboard}Controller.php` | `Modules/Finance/Http/Controllers/` |
| Services | `app/Services/{Transaction,Budget}Service.php` | `Modules/Finance/Services/` |
| Requests | `app/Http/Requests/StoreTransactionRequest.php` | `Modules/Finance/Http/Requests/` |
| Policies | `app/Policies/{Account,Transaction,Budget}Policy.php` | `Modules/Finance/Policies/` |
| Migrations | `database/migrations/*_{accounts,transactions,categories,budgets}_table.php` | `Modules/Finance/database/migrations/` |
| Factories | `database/factories/{Account,Transaction,Category,Budget}Factory.php` | `Modules/Finance/database/factories/` |
| ValueObjects | `app/ValueObjects/Money.php` | `Modules/Finance/ValueObjects/` |
| Events | `app/Events/TransactionCreated.php` | `Modules/Finance/Events/` |
| Listeners | `app/Listeners/UpdateAccountBalance.php` | `Modules/Finance/Listeners/` |

### Frontend (Vue→React Conversion)
| Vue Component | React Target |
|---------------|--------------|
| `pages/Dashboard.vue` | `pages/finance/index.tsx` |
| `pages/Transactions/Index.vue` | `pages/finance/transactions/index.tsx` |
| `pages/Accounts/Index.vue` | `pages/finance/accounts/index.tsx` |
| `pages/Budgets/Index.vue` | `pages/finance/budgets/index.tsx` |
| `Components/Transactions/*` | `pages/finance/transactions/components/*` |

## Packages to Install

```bash
composer require brick/money owen-it/laravel-auditing
```

## Phases (Simplified)

| Phase | Description | Status | Effort | File |
|-------|-------------|--------|--------|------|
| 1 | Create Module + Copy Backend | Pending | ~1hr | [phase-01-backend.md](./phase-01-backend.md) |
| 2 | Convert Frontend (Vue→React) | Pending | ~2hr | [phase-02-frontend.md](./phase-02-frontend.md) |
| 3 | Integration + Tests | Pending | ~1hr | [phase-03-integration.md](./phase-03-integration.md) |

## Key Architecture (from mokeyv2)

- **Money:** Integer cents with `brick/money` ValueObject
- **Multi-currency:** Currency + ExchangeRate tables
- **Audit:** `owen-it/laravel-auditing` for transaction history
- **Balance Updates:** Event-driven via `TransactionCreated` event
- **Categories:** Nested set with `kalnoy/nestedset`

## Database Schema (from mokeyv2)

```
currencies (code, name, symbol, decimal_places)
    ↓
accounts (user_id, account_type, name, currency_code, current_balance)
    ↓
transactions (user_id, account_id, category_id, transaction_type, amount, currency_code)

categories (user_id, parent_id, name, type, icon, color) [nested set]
    ↓
budgets (user_id, category_id, period_type, allocated_amount, spent_amount)
```

## Excluded (Simplify)

- Goals feature (can add later)
- Exchange rate auto-sync (manual entry first)
- Account reconciliation (can add later)

---

**Research:** [researcher-01-finance-patterns.md](./research/researcher-01-finance-patterns.md)
**Scout:** [scout-01-codebase-patterns.md](./scout/scout-01-codebase-patterns.md)
