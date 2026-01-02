import { Link } from '@inertiajs/react'
import { AuthenticatedLayout } from '@/layouts'
import { Main } from '@/components/layout/main'
import {
  Card,
  CardContent,
  CardHeader,
  CardTitle,
} from '@/components/ui/card'
import {
  ArrowDownLeft,
  ArrowUpRight,
  TrendingUp,
  TrendingDown,
  ArrowLeft,
} from 'lucide-react'
import { DateRangePicker } from './components/date-range-picker'
import { IncomeExpenseTrend } from './components/income-expense-trend'
import { CategoryBreakdown } from './components/category-breakdown'
import { AccountDistribution } from './components/account-distribution'
import type {
  ReportFilters,
  IncomeExpensePoint,
  CategoryBreakdownItem,
  AccountTypeBreakdown,
  ReportSummary,
} from '@modules/Finance/types/finance'

interface Props {
  filters: ReportFilters
  incomeExpenseTrend: IncomeExpensePoint[]
  categoryBreakdown: CategoryBreakdownItem[]
  accountDistribution: AccountTypeBreakdown[]
  summary: ReportSummary
  currencyCode: string
}

function formatMoney(amount: number, currencyCode = 'VND'): string {
  return new Intl.NumberFormat('vi-VN', {
    style: 'currency',
    currency: currencyCode,
  }).format(amount)
}

function SummaryCards({ summary, currencyCode }: { summary: ReportSummary; currencyCode: string }) {
  const isPositiveChange = summary.previousPeriodChange >= 0

  return (
    <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
      <Card>
        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
          <CardTitle className="text-sm font-medium">Period Income</CardTitle>
          <ArrowDownLeft className="h-4 w-4 text-green-600" />
        </CardHeader>
        <CardContent>
          <div className="text-2xl font-bold text-green-600">
            {formatMoney(summary.totalIncome, currencyCode)}
          </div>
          <p className="text-xs text-muted-foreground">
            Total income for selected period
          </p>
        </CardContent>
      </Card>

      <Card>
        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
          <CardTitle className="text-sm font-medium">Period Expense</CardTitle>
          <ArrowUpRight className="h-4 w-4 text-red-600" />
        </CardHeader>
        <CardContent>
          <div className="text-2xl font-bold text-red-600">
            {formatMoney(summary.totalExpense, currencyCode)}
          </div>
          <p className="text-xs text-muted-foreground">
            Total expenses for selected period
          </p>
        </CardContent>
      </Card>

      <Card>
        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
          <CardTitle className="text-sm font-medium">Net Change</CardTitle>
          {summary.netChange >= 0 ? (
            <TrendingUp className="h-4 w-4 text-green-600" />
          ) : (
            <TrendingDown className="h-4 w-4 text-red-600" />
          )}
        </CardHeader>
        <CardContent>
          <div className={`text-2xl font-bold ${summary.netChange >= 0 ? 'text-green-600' : 'text-red-600'}`}>
            {summary.netChange >= 0 ? '+' : ''}{formatMoney(summary.netChange, currencyCode)}
          </div>
          <p className="text-xs text-muted-foreground">
            Income minus expenses
          </p>
        </CardContent>
      </Card>

      <Card>
        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
          <CardTitle className="text-sm font-medium">vs Previous Period</CardTitle>
          {isPositiveChange ? (
            <TrendingUp className="h-4 w-4 text-green-600" />
          ) : (
            <TrendingDown className="h-4 w-4 text-red-600" />
          )}
        </CardHeader>
        <CardContent>
          <div className={`text-2xl font-bold ${isPositiveChange ? 'text-green-600' : 'text-red-600'}`}>
            {isPositiveChange ? '+' : ''}{summary.previousPeriodChange}%
          </div>
          <p className="text-xs text-muted-foreground">
            Compared to previous period
          </p>
        </CardContent>
      </Card>
    </div>
  )
}

export default function FinanceReports({
  filters,
  incomeExpenseTrend,
  categoryBreakdown,
  accountDistribution,
  summary,
  currencyCode,
}: Props) {
  return (
    <AuthenticatedLayout title="Financial Reports">
      <Main>
        <div className="mb-4">
          <Link
            href={route('dashboard.finance.index')}
            className="inline-flex items-center text-sm text-muted-foreground hover:text-foreground"
          >
            <ArrowLeft className="mr-2 h-4 w-4" />
            Back to Dashboard
          </Link>
        </div>

        <div className="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
          <div>
            <h1 className="text-2xl font-bold tracking-tight">Financial Reports</h1>
            <p className="text-muted-foreground">
              Analyze your income, expenses, and account distribution
            </p>
          </div>
          <DateRangePicker filters={filters} />
        </div>

        <div className="space-y-4">
          <SummaryCards summary={summary} currencyCode={currencyCode} />

          <IncomeExpenseTrend data={incomeExpenseTrend} currencyCode={currencyCode} />

          <div className="grid gap-4 lg:grid-cols-2">
            <CategoryBreakdown data={categoryBreakdown} currencyCode={currencyCode} />
            <AccountDistribution data={accountDistribution} currencyCode={currencyCode} />
          </div>
        </div>
      </Main>
    </AuthenticatedLayout>
  )
}
