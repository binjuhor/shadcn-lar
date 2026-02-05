import * as React from 'react'
import { Link } from '@inertiajs/react'
import { useTranslation } from 'react-i18next'
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
import { IncomeCategoryBreakdown } from './components/income-category-breakdown'
import { AccountDistribution } from './components/account-distribution'
import { CashflowChart } from './components/cashflow-chart'
import { CategoryTrendChart } from './components/category-trend-chart'
import type {
  ReportFilters,
  IncomeExpensePoint,
  CategoryBreakdownItem,
  AccountTypeBreakdown,
  ReportSummary,
  CashflowAnalysis,
  Category,
  CategoryTrendAnalysis,
} from '@modules/Finance/types/finance'

interface Props {
  filters: ReportFilters
  incomeExpenseTrend: IncomeExpensePoint[]
  categoryBreakdown: CategoryBreakdownItem[]
  incomeCategoryBreakdown: CategoryBreakdownItem[]
  accountDistribution: AccountTypeBreakdown[]
  cashflowAnalysis: CashflowAnalysis
  summary: ReportSummary
  categories: Category[]
  currencyCode: string
}

function formatMoney(amount: number, currencyCode = 'VND'): string {
  return new Intl.NumberFormat('vi-VN', {
    style: 'currency',
    currency: currencyCode,
  }).format(amount)
}

function SummaryCards({ summary, currencyCode }: { summary: ReportSummary; currencyCode: string }) {
  const { t } = useTranslation()
  const isPositiveChange = summary.previousPeriodChange >= 0

  return (
    <div className="grid gap-3 grid-cols-2 lg:grid-cols-4">
      <Card className="p-3">
        <div className="flex items-center justify-between">
          <span className="text-xs text-muted-foreground">{t('filter.income')}</span>
          <ArrowDownLeft className="h-3 w-3 text-green-600" />
        </div>
        <div className="text-lg font-bold text-green-600 mt-1">
          {formatMoney(summary.totalIncome, currencyCode)}
        </div>
      </Card>

      <Card className="p-3">
        <div className="flex items-center justify-between">
          <span className="text-xs text-muted-foreground">{t('filter.expense')}</span>
          <ArrowUpRight className="h-3 w-3 text-red-600" />
        </div>
        <div className="text-lg font-bold text-red-600 mt-1">
          {formatMoney(summary.totalExpense, currencyCode)}
        </div>
      </Card>

      <Card className="p-3">
        <div className="flex items-center justify-between">
          <span className="text-xs text-muted-foreground">{t('page.reports.net_change')}</span>
          {summary.netChange >= 0 ? (
            <TrendingUp className="h-3 w-3 text-green-600" />
          ) : (
            <TrendingDown className="h-3 w-3 text-red-600" />
          )}
        </div>
        <div className={`text-lg font-bold mt-1 ${summary.netChange >= 0 ? 'text-green-600' : 'text-red-600'}`}>
          {summary.netChange >= 0 ? '+' : ''}{formatMoney(summary.netChange, currencyCode)}
        </div>
      </Card>

      <Card className="p-3">
        <div className="flex items-center justify-between">
          <span className="text-xs text-muted-foreground">{t('page.reports.vs_previous')}</span>
          {isPositiveChange ? (
            <TrendingUp className="h-3 w-3 text-green-600" />
          ) : (
            <TrendingDown className="h-3 w-3 text-red-600" />
          )}
        </div>
        <div className={`text-lg font-bold mt-1 ${isPositiveChange ? 'text-green-600' : 'text-red-600'}`}>
          {isPositiveChange ? '+' : ''}{summary.previousPeriodChange}%
        </div>
      </Card>
    </div>
  )
}

export default function FinanceReports({
  filters,
  incomeExpenseTrend,
  categoryBreakdown,
  incomeCategoryBreakdown,
  accountDistribution,
  cashflowAnalysis,
  summary,
  categories,
  currencyCode,
}: Props) {
  const { t } = useTranslation()
  const [selectedIncomeCategoryId, setSelectedIncomeCategoryId] = React.useState<number | null>(null)
  const [selectedExpenseCategoryId, setSelectedExpenseCategoryId] = React.useState<number | null>(null)
  const [incomeTrendData, setIncomeTrendData] = React.useState<CategoryTrendAnalysis | null>(null)
  const [expenseTrendData, setExpenseTrendData] = React.useState<CategoryTrendAnalysis | null>(null)

  // Build query params from filters
  const buildFilterParams = React.useCallback(() => {
    const params = new URLSearchParams()
    params.set('range', filters.range)
    if (filters.range === 'custom') {
      params.set('start', filters.startDate)
      params.set('end', filters.endDate)
    }
    return params.toString()
  }, [filters])

  const fetchCategoryTrend = React.useCallback(async (categoryId: number | null): Promise<CategoryTrendAnalysis | null> => {
    if (!categoryId) return null

    try {
      const filterParams = buildFilterParams()
      const response = await fetch(`/dashboard/finance/reports/category-trend?category_id=${categoryId}&${filterParams}`, {
        headers: {
          'Accept': 'application/json',
        },
      })
      const result = await response.json()
      return result.data
    } catch (error) {
      console.error('Failed to fetch category trend:', error)
      return null
    }
  }, [buildFilterParams])

  const handleIncomeCategoryChange = React.useCallback(async (categoryId: number | null) => {
    setSelectedIncomeCategoryId(categoryId)
    const data = await fetchCategoryTrend(categoryId)
    setIncomeTrendData(data)
  }, [fetchCategoryTrend])

  const handleExpenseCategoryChange = React.useCallback(async (categoryId: number | null) => {
    setSelectedExpenseCategoryId(categoryId)
    const data = await fetchCategoryTrend(categoryId)
    setExpenseTrendData(data)
  }, [fetchCategoryTrend])

  // Refetch when filters change (if categories are selected)
  React.useEffect(() => {
    if (selectedIncomeCategoryId) {
      fetchCategoryTrend(selectedIncomeCategoryId).then(setIncomeTrendData)
    }
    if (selectedExpenseCategoryId) {
      fetchCategoryTrend(selectedExpenseCategoryId).then(setExpenseTrendData)
    }
  }, [filters, selectedIncomeCategoryId, selectedExpenseCategoryId, fetchCategoryTrend])

  return (
    <AuthenticatedLayout title={t('page.reports.title')}>
      <Main>
        <div className="mb-4">
          <Link
            href={route('dashboard.finance.index')}
            className="inline-flex items-center text-sm text-muted-foreground hover:text-foreground"
          >
            <ArrowLeft className="mr-2 h-4 w-4" />
            {t('page.reports.back_to_dashboard')}
          </Link>
        </div>

        <div className="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
          <div>
            <h1 className="text-2xl font-bold tracking-tight">{t('page.reports.title')}</h1>
            <p className="text-muted-foreground">
              {t('page.reports.description')}
            </p>
          </div>
          <DateRangePicker filters={filters} />
        </div>

        <div className="space-y-4">
          <SummaryCards summary={summary} currencyCode={currencyCode} />

          <IncomeExpenseTrend data={incomeExpenseTrend} currencyCode={currencyCode} />

          <CashflowChart data={cashflowAnalysis} currencyCode={currencyCode} />

          <CategoryTrendChart
            categories={categories}
            incomeTrendData={incomeTrendData}
            expenseTrendData={expenseTrendData}
            currencyCode={currencyCode}
            selectedIncomeCategoryId={selectedIncomeCategoryId}
            selectedExpenseCategoryId={selectedExpenseCategoryId}
            onIncomeCategoryChange={handleIncomeCategoryChange}
            onExpenseCategoryChange={handleExpenseCategoryChange}
          />

          <div className="grid gap-4 md:grid-cols-2">
            <IncomeCategoryBreakdown data={incomeCategoryBreakdown} currencyCode={currencyCode} />
            <CategoryBreakdown data={categoryBreakdown} currencyCode={currencyCode} />
          </div>

          <AccountDistribution data={accountDistribution} currencyCode={currencyCode} />
        </div>
      </Main>
    </AuthenticatedLayout>
  )
}
