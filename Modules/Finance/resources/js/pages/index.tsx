import { Link } from '@inertiajs/react'
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
import {
  ArrowDownLeft,
  ArrowUpRight,
  Wallet,
  CreditCard,
  TrendingUp,
  Plus,
  ArrowRight,
  PiggyBank,
} from 'lucide-react'
import type {
  FinanceDashboardData,
  Transaction,
  Budget,
  AccountSummary,
} from '@modules/Finance/types/finance'

interface Props {
  summary: AccountSummary
  recentTransactions: Transaction[]
  budgets: Budget[]
  spendingTrend: { date: string; amount: number }[]
}

function formatMoney(amount: number, currencyCode = 'VND'): string {
  return new Intl.NumberFormat('vi-VN', {
    style: 'currency',
    currency: currencyCode,
  }).format(amount)
}

function SummaryCards({ summary }: { summary: AccountSummary }) {
  return (
    <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
      <Card>
        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
          <CardTitle className="text-sm font-medium">Net Worth</CardTitle>
          <PiggyBank className="h-4 w-4 text-muted-foreground" />
        </CardHeader>
        <CardContent>
          <div className="text-2xl font-bold">
            {formatMoney(summary.net_worth, summary.currency_code)}
          </div>
          <p className="text-xs text-muted-foreground">
            {summary.accounts_count} accounts
          </p>
        </CardContent>
      </Card>
      <Card>
        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
          <CardTitle className="text-sm font-medium">Total Assets</CardTitle>
          <TrendingUp className="h-4 w-4 text-green-600" />
        </CardHeader>
        <CardContent>
          <div className="text-2xl font-bold text-green-600">
            {formatMoney(summary.total_assets, summary.currency_code)}
          </div>
          <p className="text-xs text-muted-foreground">
            Bank + Investment + Cash
          </p>
        </CardContent>
      </Card>
      <Card>
        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
          <CardTitle className="text-sm font-medium">Total Liabilities</CardTitle>
          <CreditCard className="h-4 w-4 text-red-600" />
        </CardHeader>
        <CardContent>
          <div className="text-2xl font-bold text-red-600">
            {formatMoney(summary.total_liabilities, summary.currency_code)}
          </div>
          <p className="text-xs text-muted-foreground">
            Credit Cards + Loans
          </p>
        </CardContent>
      </Card>
      <Card>
        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
          <CardTitle className="text-sm font-medium">Total Balance</CardTitle>
          <Wallet className="h-4 w-4 text-muted-foreground" />
        </CardHeader>
        <CardContent>
          <div className="text-2xl font-bold">
            {formatMoney(summary.total_balance, summary.currency_code)}
          </div>
          <p className="text-xs text-muted-foreground">
            All accounts combined
          </p>
        </CardContent>
      </Card>
    </div>
  )
}

function RecentTransactions({ transactions }: { transactions: Transaction[] }) {
  if (!transactions || transactions.length === 0) {
    return (
      <Card>
        <CardHeader>
          <CardTitle>Recent Transactions</CardTitle>
          <CardDescription>No transactions yet</CardDescription>
        </CardHeader>
        <CardContent className="flex flex-col items-center justify-center py-8">
          <p className="text-muted-foreground mb-4">
            Start tracking your finances by recording your first transaction
          </p>
          <Link href={route('dashboard.finance.transactions.create')}>
            <Button>
              <Plus className="mr-2 h-4 w-4" />
              Add Transaction
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
          <CardTitle>Recent Transactions</CardTitle>
          <CardDescription>Your latest financial activities</CardDescription>
        </div>
        <Link href={route('dashboard.finance.transactions.index')}>
          <Button variant="ghost" size="sm">
            View All <ArrowRight className="ml-1 h-4 w-4" />
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
                    {transaction.account?.name} • {new Date(transaction.transaction_date).toLocaleDateString()}
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
          <CardTitle>Budget Status</CardTitle>
          <CardDescription>No active budgets</CardDescription>
        </CardHeader>
        <CardContent className="flex flex-col items-center justify-center py-8">
          <p className="text-muted-foreground mb-4 text-center">
            Create budgets to track your spending
          </p>
          <Link href={route('dashboard.finance.budgets.create')}>
            <Button>
              <Plus className="mr-2 h-4 w-4" />
              Create Budget
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
          <CardTitle>Budget Status</CardTitle>
          <CardDescription>Current period budgets</CardDescription>
        </div>
        <Link href={route('dashboard.finance.budgets.index')}>
          <Button variant="ghost" size="sm">
            View All <ArrowRight className="ml-1 h-4 w-4" />
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

function EmptyDashboard() {
  return (
    <Card className="col-span-full">
      <CardContent className="flex flex-col items-center justify-center py-16">
        <Wallet className="h-16 w-16 text-muted-foreground mb-4" />
        <h3 className="text-xl font-semibold mb-2">Welcome to Finance!</h3>
        <p className="text-muted-foreground text-center mb-6 max-w-md">
          Get started by creating your first account and tracking your finances.
        </p>
        <div className="flex gap-3">
          <Link href={route('dashboard.finance.accounts.create')}>
            <Button>
              <Wallet className="mr-2 h-4 w-4" />
              Create Account
            </Button>
          </Link>
          <Link href={route('dashboard.finance.budgets.create')}>
            <Button variant="outline">
              <PiggyBank className="mr-2 h-4 w-4" />
              Set Budget
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
}: Props) {
  const hasData =
    recentTransactions?.length > 0 ||
    budgets?.length > 0 ||
    summary?.accounts_count > 0

  return (
    <AuthenticatedLayout title="Finance">
      <Main>
        <div className="mb-4 flex items-center justify-between">
          <div>
            <h1 className="text-2xl font-bold tracking-tight">Finance Dashboard</h1>
            <p className="text-muted-foreground">
              Track your personal finances and budgets
            </p>
          </div>
          <Link href={route('dashboard.finance.transactions.create')}>
            <Button>
              <Plus className="mr-2 h-4 w-4" />
              Add Transaction
            </Button>
          </Link>
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
              <div className="lg:col-span-3">
                <BudgetStatus budgets={budgets} />
              </div>
            </div>
          </div>
        )}
      </Main>
    </AuthenticatedLayout>
  )
}
