# Brainstorm: Financial Planning Module

**Date:** 2026-01-04
**Status:** Agreed
**Approach:** Financial Forecast Module (Approach 2)

## Problem Statement

User needs long-term financial planning capability (1-5 years) to:
- Plan expected income and expenses
- Set financial targets by category
- Track planned vs actual performance
- Visualize financial roadmap

## Agreed Solution: Financial Forecast Module

### Data Model

```
FinancialPlan (main plan entity)
├── id, user_id, name, description
├── start_year, end_year (e.g., 2026-2030)
├── currency_code
├── status: draft | active | archived
├── created_at, updated_at

PlanPeriod (yearly breakdown)
├── id, financial_plan_id
├── year (2026, 2027, etc.)
├── planned_income, planned_expense
├── notes
├── created_at, updated_at

PlanItem (line items per period)
├── id, plan_period_id
├── category_id (nullable, links to finance_categories)
├── name (custom name if no category)
├── type: income | expense
├── planned_amount
├── recurrence: one_time | monthly | quarterly | yearly
├── notes
├── created_at, updated_at
```

### Key Features

**Phase 1 (MVP)**
- Create/edit financial plans (1-5 year span)
- Add yearly periods with income/expense items
- Link items to existing categories (optional)
- View plan summary with yearly totals
- Basic planned vs actual comparison (from transactions)

**Phase 2 (Future)**
- Monthly breakdown within years
- Recurring item templates
- Multiple scenarios (optimistic/conservative)
- Variance alerts and notifications
- Export to PDF/Excel

### UI Structure

```
/dashboard/finance/plans           → List of financial plans
/dashboard/finance/plans/create    → Create new plan
/dashboard/finance/plans/{id}      → View/Edit plan details
/dashboard/finance/plans/{id}/compare → Planned vs Actual comparison
```

### Integration with Existing Finance Module

- Reuse `finance_categories` for categorization
- Query `finance_transactions` for actual amounts
- Use existing `ExchangeRateService` for multi-currency
- Follow same UI patterns (cards, forms, charts)

## Implementation Considerations

### Database
- New tables: `finance_plans`, `finance_plan_periods`, `finance_plan_items`
- Foreign key to `finance_categories` (nullable)
- Indexes on user_id, year for performance

### Backend
- `FinancialPlanController` with CRUD operations
- `PlanComparisonService` for planned vs actual calculation
- Use existing model patterns from Finance module

### Frontend
- Year-by-year accordion or tab view
- Income/expense sections with item lists
- Summary cards showing totals per year
- Comparison charts (bar chart: planned vs actual)

## Risks & Mitigations

| Risk | Mitigation |
|------|------------|
| Complex UI for multi-year planning | Start with yearly view only, add monthly later |
| Performance with large data | Aggregate at query level, cache summaries |
| Category changes breaking links | Soft delete categories, handle gracefully |

## Success Metrics

- User can create a 5-year financial plan in < 5 minutes
- Planned vs actual comparison shows within 2 seconds
- Clear visualization of financial trajectory

## Next Steps

1. Create database migrations for plan tables
2. Create Eloquent models with relationships
3. Build controller with CRUD operations
4. Create frontend pages (list, create, view)
5. Add comparison feature with charts
6. Write tests for plan calculations

## Dependencies

- Existing Finance module (categories, transactions)
- Chart components (recharts via shadcn/ui)
- Date handling (Carbon, date-fns)
