# Phase 09: Frontend - Dashboard & Charts

## Context
- Parent plan: [plan.md](../plan.md)
- Dependencies: Phase 08 (routes must exist)

## Overview
- Priority: high
- Status: pending
- Description: Build Mokey dashboard with Recharts visualizations. Display summary cards, monthly trends, expense breakdown, budget progress, and goal tracking.

## Key Insights
From research: Use Recharts composable components. Follow existing Overview.tsx pattern. UsePage hook for Inertia props.

## Requirements
### Functional
- Summary cards: Net worth, monthly income/expense/savings
- Bar chart: Monthly income vs expense (6 months)
- Pie chart: Expense by category
- Progress bars: Active budgets
- Goal progress cards
- Recent transactions list

### Non-functional
- Responsive layout with grid
- Loading states
- Dark mode compatible

## Related Code Files
### Files to Create
```
resources/js/pages/mokey/
├── dashboard.tsx
├── components/
│   ├── summary-cards.tsx
│   ├── monthly-trend-chart.tsx
│   ├── expense-category-chart.tsx
│   ├── budget-progress.tsx
│   ├── goal-cards.tsx
│   └── recent-transactions.tsx
└── types/
    └── mokey.ts
```

## Implementation Steps

### 1. Create TypeScript types
```typescript
// resources/js/pages/mokey/types/mokey.ts
export interface MoneySummary {
  net_worth: number
  monthly_income: number
  monthly_expenses: number
  monthly_savings: number
}

export interface MonthlyTrend {
  month: string
  income: number
  expense: number
}

export interface ExpenseCategory {
  category: string
  amount: number
}

export interface BudgetProgress {
  id: number
  category_name: string
  allocated: number
  spent: number
  remaining: number
  percent_used: number
  is_over_budget: boolean
}

export interface GoalProgress {
  id: number
  name: string
  target: number
  current: number
  percent: number
}

export interface RecentTransaction {
  id: number
  description: string
  amount: number
  amount_formatted: string
  transaction_type: 'income' | 'expense' | 'transfer'
  transaction_date: string
  account_name: string
  category_name: string | null
}
```

### 2. Create Summary Cards component
```tsx
// resources/js/pages/mokey/components/summary-cards.tsx
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { IconWallet, IconTrendingUp, IconTrendingDown, IconPigMoney } from '@tabler/icons-react'
import { MoneySummary } from '../types/mokey'

interface SummaryCardsProps {
  summary: MoneySummary
}

function formatMoney(cents: number): string {
  return new Intl.NumberFormat('en-US', {
    style: 'currency',
    currency: 'USD',
  }).format(cents / 100)
}

export function SummaryCards({ summary }: SummaryCardsProps) {
  return (
    <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
      <Card>
        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
          <CardTitle className="text-sm font-medium">Net Worth</CardTitle>
          <IconWallet className="h-4 w-4 text-muted-foreground" />
        </CardHeader>
        <CardContent>
          <div className="text-2xl font-bold">{formatMoney(summary.net_worth)}</div>
          <p className="text-xs text-muted-foreground">All accounts combined</p>
        </CardContent>
      </Card>

      <Card>
        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
          <CardTitle className="text-sm font-medium">Monthly Income</CardTitle>
          <IconTrendingUp className="h-4 w-4 text-green-500" />
        </CardHeader>
        <CardContent>
          <div className="text-2xl font-bold text-green-600">{formatMoney(summary.monthly_income)}</div>
          <p className="text-xs text-muted-foreground">This month</p>
        </CardContent>
      </Card>

      <Card>
        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
          <CardTitle className="text-sm font-medium">Monthly Expenses</CardTitle>
          <IconTrendingDown className="h-4 w-4 text-red-500" />
        </CardHeader>
        <CardContent>
          <div className="text-2xl font-bold text-red-600">{formatMoney(summary.monthly_expenses)}</div>
          <p className="text-xs text-muted-foreground">This month</p>
        </CardContent>
      </Card>

      <Card>
        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
          <CardTitle className="text-sm font-medium">Savings</CardTitle>
          <IconPigMoney className="h-4 w-4 text-blue-500" />
        </CardHeader>
        <CardContent>
          <div className={`text-2xl font-bold ${summary.monthly_savings >= 0 ? 'text-blue-600' : 'text-red-600'}`}>
            {formatMoney(summary.monthly_savings)}
          </div>
          <p className="text-xs text-muted-foreground">Income - Expenses</p>
        </CardContent>
      </Card>
    </div>
  )
}
```

### 3. Create Monthly Trend Chart
```tsx
// resources/js/pages/mokey/components/monthly-trend-chart.tsx
import { Bar, BarChart, ResponsiveContainer, XAxis, YAxis, Tooltip, Legend } from 'recharts'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { MonthlyTrend } from '../types/mokey'

interface MonthlyTrendChartProps {
  data: MonthlyTrend[]
}

export function MonthlyTrendChart({ data }: MonthlyTrendChartProps) {
  const chartData = data.map(item => ({
    ...item,
    income: item.income / 100,
    expense: item.expense / 100,
  }))

  return (
    <Card className="col-span-4">
      <CardHeader>
        <CardTitle>Income vs Expenses</CardTitle>
      </CardHeader>
      <CardContent className="pl-2">
        <ResponsiveContainer width="100%" height={350}>
          <BarChart data={chartData}>
            <XAxis dataKey="month" stroke="#888888" fontSize={12} tickLine={false} axisLine={false} />
            <YAxis stroke="#888888" fontSize={12} tickLine={false} axisLine={false} tickFormatter={(value) => `$${value}`} />
            <Tooltip formatter={(value: number) => `$${value.toFixed(2)}`} />
            <Legend />
            <Bar dataKey="income" name="Income" fill="#22c55e" radius={[4, 4, 0, 0]} />
            <Bar dataKey="expense" name="Expenses" fill="#ef4444" radius={[4, 4, 0, 0]} />
          </BarChart>
        </ResponsiveContainer>
      </CardContent>
    </Card>
  )
}
```

### 4. Create Expense Category Chart
```tsx
// resources/js/pages/mokey/components/expense-category-chart.tsx
import { PieChart, Pie, Cell, ResponsiveContainer, Tooltip, Legend } from 'recharts'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { ExpenseCategory } from '../types/mokey'

interface ExpenseCategoryChartProps {
  data: ExpenseCategory[]
}

const COLORS = ['#0088FE', '#00C49F', '#FFBB28', '#FF8042', '#8884D8', '#82CA9D', '#FFC658', '#8DD1E1']

export function ExpenseCategoryChart({ data }: ExpenseCategoryChartProps) {
  const chartData = data.map(item => ({
    ...item,
    amount: item.amount / 100,
  }))

  return (
    <Card className="col-span-3">
      <CardHeader>
        <CardTitle>Expenses by Category</CardTitle>
      </CardHeader>
      <CardContent>
        <ResponsiveContainer width="100%" height={300}>
          <PieChart>
            <Pie
              data={chartData}
              cx="50%"
              cy="50%"
              labelLine={false}
              label={({ category, percent }) => `${category} (${(percent * 100).toFixed(0)}%)`}
              outerRadius={80}
              dataKey="amount"
              nameKey="category"
            >
              {chartData.map((_, index) => (
                <Cell key={`cell-${index}`} fill={COLORS[index % COLORS.length]} />
              ))}
            </Pie>
            <Tooltip formatter={(value: number) => `$${value.toFixed(2)}`} />
            <Legend />
          </PieChart>
        </ResponsiveContainer>
      </CardContent>
    </Card>
  )
}
```

### 5. Create Budget Progress component
```tsx
// resources/js/pages/mokey/components/budget-progress.tsx
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Progress } from '@/components/ui/progress'
import { BudgetProgress as BudgetProgressType } from '../types/mokey'

interface BudgetProgressProps {
  budgets: BudgetProgressType[]
}

export function BudgetProgress({ budgets }: BudgetProgressProps) {
  return (
    <Card className="col-span-4">
      <CardHeader>
        <CardTitle>Budget Progress</CardTitle>
      </CardHeader>
      <CardContent className="space-y-4">
        {budgets.length === 0 ? (
          <p className="text-muted-foreground">No active budgets</p>
        ) : (
          budgets.map((budget) => (
            <div key={budget.id} className="space-y-2">
              <div className="flex items-center justify-between text-sm">
                <span className="font-medium">{budget.category_name}</span>
                <span className={budget.is_over_budget ? 'text-red-600' : 'text-muted-foreground'}>
                  ${(budget.spent / 100).toFixed(2)} / ${(budget.allocated / 100).toFixed(2)}
                </span>
              </div>
              <Progress
                value={Math.min(budget.percent_used, 100)}
                className={budget.is_over_budget ? 'bg-red-200' : ''}
              />
            </div>
          ))
        )}
      </CardContent>
    </Card>
  )
}
```

### 6. Create main Dashboard page
```tsx
// resources/js/pages/mokey/dashboard.tsx
import { AuthenticatedLayout } from '@/layouts'
import { Main } from '@/components/layout'
import { usePage } from '@inertiajs/react'
import { PageProps } from '@/types'
import { SummaryCards } from './components/summary-cards'
import { MonthlyTrendChart } from './components/monthly-trend-chart'
import { ExpenseCategoryChart } from './components/expense-category-chart'
import { BudgetProgress } from './components/budget-progress'
import { GoalCards } from './components/goal-cards'
import { RecentTransactions } from './components/recent-transactions'
import { MoneySummary, MonthlyTrend, ExpenseCategory, BudgetProgress as BudgetProgressType, GoalProgress, RecentTransaction } from './types/mokey'

interface MokeyDashboardProps extends PageProps {
  summary: MoneySummary
  monthly_trend: MonthlyTrend[]
  expense_by_category: ExpenseCategory[]
  active_budgets: BudgetProgressType[]
  active_goals: GoalProgress[]
  recent_transactions: RecentTransaction[]
}

export default function MokeyDashboard() {
  const { summary, monthly_trend, expense_by_category, active_budgets, active_goals, recent_transactions } =
    usePage<MokeyDashboardProps>().props

  return (
    <AuthenticatedLayout title="Mokey Finance">
      <Main>
        <div className="space-y-6">
          <div>
            <h2 className="text-3xl font-bold tracking-tight">Finance Dashboard</h2>
            <p className="text-muted-foreground">Your personal finance overview</p>
          </div>

          <SummaryCards summary={summary} />

          <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-7">
            <MonthlyTrendChart data={monthly_trend} />
            <ExpenseCategoryChart data={expense_by_category} />
          </div>

          <div className="grid gap-4 md:grid-cols-2">
            <BudgetProgress budgets={active_budgets} />
            <GoalCards goals={active_goals} />
          </div>

          <RecentTransactions transactions={recent_transactions} />
        </div>
      </Main>
    </AuthenticatedLayout>
  )
}
```

## Todo List
- [ ] Create mokey types file
- [ ] Create SummaryCards component
- [ ] Create MonthlyTrendChart with Recharts
- [ ] Create ExpenseCategoryChart (pie chart)
- [ ] Create BudgetProgress component
- [ ] Create GoalCards component
- [ ] Create RecentTransactions component
- [ ] Create main dashboard.tsx page
- [ ] Add Progress component if not exists
- [ ] Test responsive layout

## Success Criteria
- [ ] Dashboard renders without errors
- [ ] All charts display data correctly
- [ ] Responsive on mobile/tablet/desktop
- [ ] Dark mode colors work properly
- [ ] Loading states shown when data empty

## Risk Assessment
- **Risk:** Recharts not rendering in SSR. **Mitigation:** Use dynamic import with no-ssr.
- **Risk:** Large data sets slow charts. **Mitigation:** Limit to 6 months, 10 categories.

## Security Considerations
- No sensitive data (account numbers) displayed
- All data pre-filtered by user on backend

## Next Steps
Proceed to Phase 10: Frontend - Accounts Module
