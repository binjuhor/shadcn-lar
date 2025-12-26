# Phase 2: Convert Frontend (Vue→React)

**Parent Plan:** [plan.md](./plan.md)
**Source:** `~/Development/mokeyv2/resources/js`
**Effort:** ~2 hours

## Overview

Convert Vue.js components from mokeyv2 to React/TypeScript for shadcn-admin.

**Status:** Pending

## Directory Structure

```
resources/js/pages/finance/
├── index.tsx                    # Dashboard (from Dashboard.vue)
├── accounts/
│   ├── index.tsx               # Account list
│   └── components/
│       ├── account-form.tsx
│       └── accounts-table.tsx
├── transactions/
│   ├── index.tsx               # Transaction list (from Transactions/Index.vue)
│   └── components/
│       ├── transaction-form.tsx
│       ├── transaction-list.tsx
│       └── transaction-filters.tsx
├── budgets/
│   ├── index.tsx
│   └── components/
│       ├── budget-form.tsx
│       └── budget-progress.tsx
├── components/
│   ├── money-display.tsx       # Format money from cents
│   └── category-select.tsx
└── data/
    └── schema.ts               # TypeScript types
```

## Vue→React Conversion Guide

### Vue Composition API → React Hooks

```vue
<!-- Vue -->
<script setup>
import { ref } from 'vue';
const showForm = ref(false);
const transactions = defineProps(['transactions']);
</script>
```

```tsx
// React
import { useState } from 'react';
interface Props { transactions: Transaction[] }
export default function TransactionsIndex({ transactions }: Props) {
  const [showForm, setShowForm] = useState(false);
}
```

### Inertia Vue → Inertia React

```vue
<!-- Vue -->
import { router } from '@inertiajs/vue3';
router.delete(`/transactions/${id}`);
```

```tsx
// React
import { router } from '@inertiajs/react';
router.delete(`/transactions/${id}`);
```

### v-if/v-for → JSX

```vue
<!-- Vue -->
<div v-if="showFilters">...</div>
<div v-for="tx in transactions" :key="tx.id">...</div>
```

```tsx
// React
{showFilters && <div>...</div>}
{transactions.map(tx => <div key={tx.id}>...</div>)}
```

## TypeScript Types

Create `data/schema.ts`:

```typescript
export type AccountType = 'bank' | 'investment' | 'cash' | 'credit_card';
export type TransactionType = 'income' | 'expense' | 'transfer';
export type CategoryType = 'income' | 'expense' | 'both';
export type BudgetPeriod = 'weekly' | 'monthly' | 'yearly';

export interface Currency {
  code: string;
  name: string;
  symbol: string;
  decimal_places: number;
}

export interface Account {
  id: number;
  name: string;
  account_type: AccountType;
  currency_code: string;
  current_balance: number; // in cents
  is_active: boolean;
}

export interface Category {
  id: number;
  name: string;
  type: CategoryType;
  icon: string | null;
  color: string;
  parent_id: number | null;
  children?: Category[];
}

export interface Transaction {
  id: number;
  account: Account;
  category: Category | null;
  transaction_type: TransactionType;
  amount: number; // in cents
  currency_code: string;
  description: string | null;
  transaction_date: string;
  reconciled_at: string | null;
}

export interface Budget {
  id: number;
  category: Category;
  period_type: BudgetPeriod;
  allocated_amount: number;
  spent_amount: number;
  currency_code: string;
  start_date: string;
  end_date: string;
}

export interface FinanceSummary {
  totalAssets: number;
  totalLiabilities: number;
  netWorth: number;
}
```

## Components to Convert

### 1. Dashboard (Dashboard.vue → index.tsx)

Source: `~/Development/mokeyv2/resources/js/pages/Dashboard.vue`

Key elements:
- Summary cards (net worth, assets, liabilities)
- Recent transactions list
- Budget progress bars
- Spending trend chart

### 2. Transactions (Transactions/Index.vue → transactions/index.tsx)

Source: `~/Development/mokeyv2/resources/js/pages/Transactions/Index.vue`

Key elements:
- Transaction list with pagination
- Filters (account, category, type, date range)
- Create transaction modal
- Delete confirmation dialog

### 3. Transaction Form Component

Source: `~/Development/mokeyv2/resources/js/Components/Transactions/TransactionForm.vue`

Convert to React with Shadcn UI:
- Type selector (income/expense/transfer)
- Account select
- Amount input (cents → display as dollars)
- Category select
- Date picker
- Description textarea

### 4. Money Display Component

```tsx
// components/money-display.tsx
import { Currency } from '../data/schema';

interface MoneyDisplayProps {
  amount: number; // in cents
  currencyCode: string;
  showSign?: boolean;
}

export function MoneyDisplay({ amount, currencyCode, showSign = false }: MoneyDisplayProps) {
  const formatted = new Intl.NumberFormat('en-US', {
    style: 'currency',
    currency: currencyCode,
  }).format(amount / 100);

  const isNegative = amount < 0;
  const color = isNegative ? 'text-red-500' : 'text-green-500';

  return (
    <span className={showSign ? color : ''}>
      {showSign && amount > 0 ? '+' : ''}{formatted}
    </span>
  );
}
```

## Steps

1. [ ] Create TypeScript types in `data/schema.ts`
2. [ ] Create shared components:
   - [ ] `money-display.tsx`
   - [ ] `category-select.tsx`
3. [ ] Convert Dashboard page
4. [ ] Convert Transactions pages:
   - [ ] `transactions/index.tsx`
   - [ ] `transactions/components/transaction-form.tsx`
   - [ ] `transactions/components/transaction-list.tsx`
   - [ ] `transactions/components/transaction-filters.tsx`
5. [ ] Convert Accounts pages:
   - [ ] `accounts/index.tsx`
   - [ ] `accounts/components/account-form.tsx`
6. [ ] Convert Budgets pages:
   - [ ] `budgets/index.tsx`
   - [ ] `budgets/components/budget-form.tsx`
   - [ ] `budgets/components/budget-progress.tsx`
7. [ ] Add Finance to sidebar navigation
8. [ ] Test all pages render correctly

## Shadcn UI Components to Use

- `Button`, `Input`, `Textarea` - Forms
- `Select`, `SelectContent`, `SelectItem` - Dropdowns
- `Card`, `CardHeader`, `CardContent` - Layout
- `Table`, `TableHeader`, `TableBody`, `TableRow`, `TableCell` - Lists
- `Dialog`, `DialogContent`, `DialogHeader` - Modals
- `Calendar`, `Popover` - Date picker
- `Progress` - Budget bars
- `Badge` - Transaction type labels

## Success Criteria

- [ ] All pages render without errors
- [ ] Transaction CRUD operations work
- [ ] Money displays correctly (cents → formatted)
- [ ] Filters update transaction list
- [ ] Forms submit to correct endpoints
- [ ] Navigation works between finance pages

## Next Phase

→ [phase-03-integration.md](./phase-03-integration.md) - Integration & Tests
