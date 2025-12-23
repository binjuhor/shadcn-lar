# Mokey Finance Module Implementation Plan

## Overview
Integrate mokeyv2 personal finance tracking into shadcn-admin as a self-contained Laravel module following nwidart/laravel-modules v12 patterns. Core features: multi-currency accounts, transactions (income/expense/transfer), budgets, and financial goals.

## Key Dependencies
- nwidart/laravel-modules v12 (existing)
- spatie/laravel-permission (existing)
- owen-it/laravel-auditing (new - audit trail)
- Recharts (existing - charts)
- @tanstack/react-table (existing - data tables)
- react-hook-form + zod (existing - forms)

## Phases

| Phase | Name | Status | File |
|-------|------|--------|------|
| 01 | Module Setup & Configuration | pending | [phase-01-module-setup.md](./phases/phase-01-module-setup.md) |
| 02 | Database Schema & Migrations | pending | [phase-02-database-schema.md](./phases/phase-02-database-schema.md) |
| 03 | Backend Models & Relationships | pending | [phase-03-backend-models.md](./phases/phase-03-backend-models.md) |
| 04 | Services & Business Logic | pending | [phase-04-services-business-logic.md](./phases/phase-04-services-business-logic.md) |
| 05 | Controllers & API Resources | pending | [phase-05-controllers-resources.md](./phases/phase-05-controllers-resources.md) |
| 06 | Policies & Authorization | pending | [phase-06-policies-authorization.md](./phases/phase-06-policies-authorization.md) |
| 07 | Form Requests & Validation | pending | [phase-07-form-requests.md](./phases/phase-07-form-requests.md) |
| 08 | Routes & Navigation | pending | [phase-08-routes-navigation.md](./phases/phase-08-routes-navigation.md) |
| 09 | Frontend - Dashboard & Charts | pending | [phase-09-frontend-dashboard.md](./phases/phase-09-frontend-dashboard.md) |
| 10 | Frontend - Accounts Module | pending | [phase-10-frontend-accounts.md](./phases/phase-10-frontend-accounts.md) |
| 11 | Frontend - Transactions Module | pending | [phase-11-frontend-transactions.md](./phases/phase-11-frontend-transactions.md) |
| 12 | Frontend - Budgets Module | pending | [phase-12-frontend-budgets.md](./phases/phase-12-frontend-budgets.md) |
| 13 | Frontend - Goals Module | pending | [phase-13-frontend-goals.md](./phases/phase-13-frontend-goals.md) |
| 14 | Frontend - Categories Management | pending | [phase-14-frontend-categories.md](./phases/phase-14-frontend-categories.md) |
| 15 | Testing & Documentation | pending | [phase-15-testing-documentation.md](./phases/phase-15-testing-documentation.md) |

## Critical Architecture Decisions
1. **User extension via macros** - Don't modify User model directly
2. **Amounts as integers** - Store all amounts in cents/smallest currency unit
3. **Vue to React rewrite** - Complete frontend rebuild required (no migration)
4. **Module isolation** - All Mokey code in `Modules/Mokey/`

## Success Criteria
- [ ] Module scaffolded and autoloaded correctly
- [ ] All 9 database tables migrated with proper indexes
- [ ] CRUD operations for accounts, transactions, budgets, goals
- [ ] Multi-currency support with exchange rates
- [ ] Dashboard with Recharts visualizations
- [ ] Authorization via policies integrated with Permission module
- [ ] Feature tests achieving 80%+ coverage
- [ ] All frontend pages responsive and accessible

## Research Reports
- [Laravel Modules Patterns](./research/researcher-01-laravel-modules-patterns.md)
- [Vue to React Migration](./research/researcher-02-vue-to-react-migration.md)
