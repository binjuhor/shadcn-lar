import { Link } from '@inertiajs/react'
import { useTranslation } from 'react-i18next'
import { formatDateDisplay } from '@/lib/date-utils'
import { AuthenticatedLayout } from '@/layouts'
import { Main } from '@/components/layout/main'
import { Button } from '@/components/ui/button'
import {
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from '@/components/ui/card'
import { Progress } from '@/components/ui/progress'
import { Badge } from '@/components/ui/badge'
import {
  ArrowDownLeft,
  ArrowUpRight,
  Wallet,
  CreditCard,
  TrendingUp,
  Plus,
  ArrowRight,
  PiggyBank,
  BarChart3,
  RefreshCw,
  Target,
} from 'lucide-react'
import type {
  FinanceDashboardData,
  Transaction,
  Budget,
  AccountSummary,
  MonthlyProjection,
} from '@modules/Finance/types/finance'
import {IconSparkles} from "@tabler/icons-react";

interface UpcomingRecurring {
  id: number
  name: string
  transaction_type: 'income' | 'expense'
  amount: number
  currency_code: string
  frequency: string
  next_run_date: string
  category?: {
    name: string
    color?: string
    is_passive: boolean
  }
}

interface Props {
  summary: AccountSummary
  recentTransactions: Transaction[]
  budgets: Budget[]
  spendingTrend: { date: string; amount: number }[]
  recurringProjection: MonthlyProjection
  upcomingRecurrings: UpcomingRecurring[]
}

function formatMoney(amount: number, currencyCode = 'VND'): string {
  return new Intl.NumberFormat('vi-VN', {
    style: 'currency',
    currency: currencyCode,
  }).format(amount)
}

function SummaryCards({ summary }: { summary: AccountSummary }) {
  const { t } = useTranslation()
  return (
    <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
      <Card>
        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
          <CardTitle className="text-sm font-medium">{t('page.finance_dashboard.net_worth')}</CardTitle>
          <PiggyBank className="h-4 w-4 text-muted-foreground" />
        </CardHeader>
        <CardContent>
          <div className="text-2xl font-bold">
            {formatMoney(summary.net_worth, summary.currency_code)}
          </div>
          <p className="text-xs text-muted-foreground">
            {t('page.finance_dashboard.accounts_count', { count: summary.accounts_count })}
          </p>
        </CardContent>
      </Card>
      <Card>
        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
          <CardTitle className="text-sm font-medium">{t('page.finance_dashboard.total_assets')}</CardTitle>
          <TrendingUp className="h-4 w-4 text-green-600" />
        </CardHeader>
        <CardContent>
          <div className="text-2xl font-bold text-green-600">
            {formatMoney(summary.total_assets, summary.currency_code)}
          </div>
          <p className="text-xs text-muted-foreground">
            {t('page.finance_dashboard.bank_investment_cash')}
          </p>
        </CardContent>
      </Card>
      <Card>
        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
          <CardTitle className="text-sm font-medium">{t('page.finance_dashboard.total_liabilities')}</CardTitle>
          <CreditCard className="h-4 w-4 text-red-600" />
        </CardHeader>
        <CardContent>
          <div className="text-2xl font-bold text-red-600">
            {formatMoney(summary.total_liabilities, summary.currency_code)}
          </div>
          <p className="text-xs text-muted-foreground">
            {t('page.finance_dashboard.credit_cards_loans')}
          </p>
        </CardContent>
      </Card>
      <Card>
        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
          <CardTitle className="text-sm font-medium">{t('page.finance_dashboard.total_balance')}</CardTitle>
          <Wallet className="h-4 w-4 text-muted-foreground" />
        </CardHeader>
        <CardContent>
          <div className="text-2xl font-bold">
            {formatMoney(summary.total_balance, summary.currency_code)}
          </div>
          <p className="text-xs text-muted-foreground">
            {t('page.finance_dashboard.all_accounts_combined')}
          </p>
        </CardContent>
      </Card>
    </div>
  )
}

function RecentTransactions({ transactions }: { transactions: Transaction[] }) {
  const { t } = useTranslation()
  if (!transactions || transactions.length === 0) {
    return (
      <Card>
        <CardHeader>
          <CardTitle>{t('page.finance_dashboard.recent_transactions')}</CardTitle>
          <CardDescription>{t('page.finance_dashboard.no_transactions')}</CardDescription>
        </CardHeader>
        <CardContent className="flex flex-col items-center justify-center py-8">
          <p className="text-muted-foreground mb-4">
            {t('page.finance_dashboard.start_tracking')}
          </p>
          <Link href={route('dashboard.finance.transactions.create')}>
            <Button>
              <Plus className="mr-2 h-4 w-4" />
              {t('page.finance_dashboard.add_transaction')}
            </Button>
          </Link>
        </CardContent>
      </Card>
    )
  }

  return (
    <Card>
      <CardHeader className="flex flex-row items-center justify-between">
        <div>
          <CardTitle>{t('page.finance_dashboard.recent_transactions')}</CardTitle>
          <CardDescription>{t('page.finance_dashboard.latest_activities')}</CardDescription>
        </div>
        <Link href={route('dashboard.finance.transactions.index')}>
          <Button variant="ghost" size="sm">
            {t('page.finance_dashboard.view_all')} <ArrowRight className="ml-1 h-4 w-4" />
          </Button>
        </Link>
      </CardHeader>
      <CardContent>
        <div className="space-y-4">
          {transactions.slice(0, 5).map((transaction) => (
            <div
              key={transaction.id}
              className="flex items-center justify-between"
            >
              <div className="flex items-center gap-3">
                <div
                  className={`rounded-full p-2 ${
                    transaction.type === 'income'
                      ? 'bg-green-100 text-green-600'
                      : transaction.type === 'expense'
                        ? 'bg-red-100 text-red-600'
                        : 'bg-blue-100 text-blue-600'
                  }`}
                >
                  {transaction.type === 'income' ? (
                    <ArrowDownLeft className="h-4 w-4" />
                  ) : transaction.type === 'expense' ? (
                    <ArrowUpRight className="h-4 w-4" />
                  ) : (
                    <ArrowRight className="h-4 w-4" />
                  )}
                </div>
                <div>
                  <p className="font-medium">
                    {transaction.description || transaction.category?.name || 'Uncategorized'}
                  </p>
                  <p className="text-xs text-muted-foreground">
                    {transaction.account?.name} • {formatDateDisplay(transaction.transaction_date)}
                  </p>
                </div>
              </div>
              <div
                className={`font-semibold ${
                  transaction.type === 'income'
                    ? 'text-green-600'
                    : transaction.type === 'expense'
                      ? 'text-red-600'
                      : ''
                }`}
              >
                {transaction.type === 'income' ? '+' : transaction.type === 'expense' ? '-' : ''}
                {formatMoney(transaction.amount, transaction.currency_code)}
              </div>
            </div>
          ))}
        </div>
      </CardContent>
    </Card>
  )
}

function BudgetStatus({ budgets }: { budgets: Budget[] }) {
  const { t } = useTranslation()
  const now = new Date()
  const currentBudgets = budgets.filter((budget) => {
    const start = new Date(budget.start_date)
    const end = new Date(budget.end_date)
    return start <= now && now <= end && budget.is_active
  })

  if (currentBudgets.length === 0) {
    return (
      <Card>
        <CardHeader>
          <CardTitle>{t('page.finance_dashboard.budget_status')}</CardTitle>
          <CardDescription>{t('page.finance_dashboard.no_active_budgets')}</CardDescription>
        </CardHeader>
        <CardContent className="flex flex-col items-center justify-center py-8">
          <p className="text-muted-foreground mb-4 text-center">
            {t('page.finance_dashboard.create_budgets_prompt')}
          </p>
          <Link href={route('dashboard.finance.budgets.create')}>
            <Button>
              <Plus className="mr-2 h-4 w-4" />
              {t('page.finance_dashboard.create_budget')}
            </Button>
          </Link>
        </CardContent>
      </Card>
    )
  }

  return (
    <Card>
      <CardHeader className="flex flex-row items-center justify-between">
        <div>
          <CardTitle>{t('page.finance_dashboard.budget_status')}</CardTitle>
          <CardDescription>{t('page.finance_dashboard.current_period')}</CardDescription>
        </div>
        <Link href={route('dashboard.finance.budgets.index')}>
          <Button variant="ghost" size="sm">
            {t('page.finance_dashboard.view_all')} <ArrowRight className="ml-1 h-4 w-4" />
          </Button>
        </Link>
      </CardHeader>
      <CardContent>
        <div className="space-y-4">
          {currentBudgets.slice(0, 4).map((budget) => {
            const spentPercent = budget.amount > 0
              ? Math.min((budget.spent / budget.amount) * 100, 100)
              : 0
            const isOverBudget = budget.spent > budget.amount

            return (
              <div key={budget.id} className="space-y-2">
                <div className="flex items-center justify-between text-sm">
                  <span className="font-medium">{budget.name}</span>
                  <span className={isOverBudget ? 'text-red-600' : 'text-muted-foreground'}>
                    {formatMoney(budget.spent, budget.currency_code)} / {formatMoney(budget.amount, budget.currency_code)}
                  </span>
                </div>
                <Progress
                  value={spentPercent}
                  className={isOverBudget ? '[&>div]:bg-red-600' : ''}
                />
              </div>
            )
          })}
        </div>
      </CardContent>
    </Card>
  )
}

function RecurringOverview({
  projection,
  upcoming,
}: {
  projection: MonthlyProjection
  upcoming: UpcomingRecurring[]
}) {
  const { t } = useTranslation()
  return (
    <Card>
      <CardHeader className="pb-2">
        <div className="flex items-center justify-between">
          <div className="flex items-center gap-2">
            <RefreshCw className="h-4 w-4 text-muted-foreground" />
            <CardTitle className="text-base">{t('page.finance_dashboard.recurring_overview')}</CardTitle>
          </div>
          <Link href={route('dashboard.finance.recurring-transactions.index')}>
            <Button variant="ghost" size="sm">
              {t('page.finance_dashboard.manage')} <ArrowRight className="ml-1 h-4 w-4" />
            </Button>
          </Link>
        </div>
      </CardHeader>
      <CardContent className="space-y-4">
        <div className="grid grid-cols-2 gap-3">
          <div>
            <p className="text-xs text-muted-foreground">{t('page.finance_dashboard.monthly_income')}</p>
            <p className="text-lg font-semibold text-green-600">
              {formatMoney(projection.monthly_income, projection.currency_code)}
            </p>
          </div>
          <div>
            <p className="text-xs text-muted-foreground">{t('page.finance_dashboard.monthly_expense')}</p>
            <p className="text-lg font-semibold text-red-600">
              {formatMoney(projection.monthly_expense, projection.currency_code)}
            </p>
          </div>
          <div>
            <p className="text-xs text-muted-foreground">{t('page.finance_dashboard.net_monthly')}</p>
            <p className={`text-lg font-semibold ${projection.monthly_net >= 0 ? 'text-green-600' : 'text-red-600'}`}>
              {projection.monthly_net >= 0 ? '+' : ''}{formatMoney(projection.monthly_net, projection.currency_code)}
            </p>
          </div>
          <div>
            <p className="text-xs text-muted-foreground flex items-center gap-1">
              <Target className="h-3 w-3" /> {t('page.finance_dashboard.passive_coverage')}
            </p>
            <p className="text-lg font-semibold text-purple-600">
              {projection.passive_coverage}%
            </p>
          </div>
        </div>

        {upcoming.length > 0 && (
          <div className="pt-3 border-t">
            <p className="text-xs text-muted-foreground mb-2">{t('page.finance_dashboard.upcoming_7_days')}</p>
            <div className="space-y-1">
              {upcoming.slice(0, 3).map((r) => (
                <div key={r.id} className="flex items-center justify-between text-sm">
                  <span className="truncate">{r.name}</span>
                  <Badge
                    variant="outline"
                    className={`text-xs ${r.transaction_type === 'income' ? 'border-green-200 bg-green-50 text-green-700' : 'border-red-200 bg-red-50 text-red-700'}`}
                  >
                    {r.transaction_type === 'income' ? '+' : '-'}{formatMoney(r.amount, r.currency_code)}
                  </Badge>
                </div>
              ))}
            </div>
          </div>
        )}
      </CardContent>
    </Card>
  )
}

function EmptyDashboard() {
  const { t } = useTranslation()
  return (
    <Card className="col-span-full">
      <CardContent className="flex flex-col items-center justify-center py-16">
        <Wallet className="h-16 w-16 text-muted-foreground mb-4" />
        <h3 className="text-xl font-semibold mb-2">{t('page.finance_dashboard.welcome')}</h3>
        <p className="text-muted-foreground text-center mb-6 max-w-md">
          {t('page.finance_dashboard.get_started')}
        </p>
        <div className="flex gap-3">
          <Link href={route('dashboard.finance.accounts.create')}>
            <Button>
              <Wallet className="mr-2 h-4 w-4" />
              {t('page.finance_dashboard.create_account')}
            </Button>
          </Link>
          <Link href={route('dashboard.finance.budgets.create')}>
            <Button variant="outline">
              <PiggyBank className="mr-2 h-4 w-4" />
              {t('page.finance_dashboard.set_budget')}
            </Button>
          </Link>
        </div>
      </CardContent>
    </Card>
  )
}

export default function FinanceDashboard({
  summary,
  recentTransactions,
  budgets,
  recurringProjection,
  upcomingRecurrings,
}: Props) {
  const { t } = useTranslation()
  const hasData =
    recentTransactions?.length > 0 ||
    budgets?.length > 0 ||
    summary?.accounts_count > 0

  return (
    <AuthenticatedLayout title={t('page.finance_dashboard.title')}>
      <Main>
        <div className="mb-4 md:flex items-center justify-between">
          <div className="mb-4">
            <h1 className="text-2xl font-bold tracking-tight">{t('page.finance_dashboard.title')}</h1>
            <p className="text-muted-foreground">
              {t('page.finance_dashboard.description')}
            </p>
          </div>
          <div className="flex gap-2">
            <Link href={route('dashboard.finance.reports')}>
              <Button variant="outline">
                <BarChart3 className="mr-2 h-4 w-4" />
                {t('page.finance_dashboard.reports')}
              </Button>
            </Link>
            <Link className="hidden md:block" href={route('dashboard.finance.transactions.create')}>
              <Button>
                <Plus className="mr-2 h-4 w-4" />
                {t('page.finance_dashboard.add_transaction')}
              </Button>
            </Link>
            <Link className="md:hidden" href={route('dashboard.finance.smart-input')}>
              <Button>
                <IconSparkles className="mr-2 h-4 w-4" />
                {t('page.finance_dashboard.smart-input')}
              </Button>
            </Link>
          </div>
        </div>

        {!hasData ? (
          <EmptyDashboard />
        ) : (
          <div className="space-y-4">
            <SummaryCards summary={summary} />

            <div className="grid grid-cols-1 gap-4 lg:grid-cols-7">
              <div className="lg:col-span-4">
                <RecentTransactions transactions={recentTransactions} />
              </div>
              <div className="lg:col-span-3 space-y-4">
                <RecurringOverview projection={recurringProjection} upcoming={upcomingRecurrings} />
                <BudgetStatus budgets={budgets} />
              </div>
            </div>
          </div>
        )}
      </Main>
    </AuthenticatedLayout>
  )
}
