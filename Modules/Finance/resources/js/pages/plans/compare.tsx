import { Link } from '@inertiajs/react'
import { useTranslation } from 'react-i18next'
import { AuthenticatedLayout } from '@/layouts'
import { Main } from '@/components/layout/main'
import { Button } from '@/components/ui/button'
import { Badge } from '@/components/ui/badge'
import {
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from '@/components/ui/card'
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table'
import {
  Bar,
  BarChart,
  CartesianGrid,
  Legend,
  ResponsiveContainer,
  Tooltip,
  XAxis,
  YAxis,
} from 'recharts'
import { ArrowLeft, TrendingUp, TrendingDown, Target } from 'lucide-react'
import type { FinancialPlan, PlanComparison } from '@modules/Finance/types/finance'

interface Props {
  plan: FinancialPlan
  comparison: PlanComparison[]
}

function formatMoney(amount: number, currencyCode = 'VND'): string {
  return new Intl.NumberFormat('vi-VN', {
    style: 'currency',
    currency: currencyCode,
    maximumFractionDigits: 0,
  }).format(amount)
}

function formatPercent(value: number): string {
  const sign = value >= 0 ? '+' : ''
  return `${sign}${value.toFixed(1)}%`
}

export default function PlansCompare({ plan, comparison }: Props) {
  const { t } = useTranslation()

  const chartData = comparison.map((c) => ({
    year: String(c.year),
    [t('page.plans.compare.planned_income')]: c.planned_income,
    [t('page.plans.compare.actual_income')]: c.actual_income,
    [t('page.plans.compare.planned_expense')]: c.planned_expense,
    [t('page.plans.compare.actual_expense')]: c.actual_expense,
  }))

  const totals = comparison.reduce(
    (acc, c) => ({
      planned_income: acc.planned_income + c.planned_income,
      actual_income: acc.actual_income + c.actual_income,
      planned_expense: acc.planned_expense + c.planned_expense,
      actual_expense: acc.actual_expense + c.actual_expense,
    }),
    { planned_income: 0, actual_income: 0, planned_expense: 0, actual_expense: 0 }
  )

  const incomeVariance = totals.actual_income - totals.planned_income
  const expenseVariance = totals.actual_expense - totals.planned_expense
  const netPlanned = totals.planned_income - totals.planned_expense
  const netActual = totals.actual_income - totals.actual_expense

  return (
    <AuthenticatedLayout title={`Compare: ${plan.name}`}>
      <Main>
        <div className="mb-6 flex items-center justify-between">
          <div>
            <div className="flex items-center gap-3 mb-2">
              <Button variant="ghost" size="icon" asChild>
                <Link href={route('dashboard.finance.plans.show', plan.id)}>
                  <ArrowLeft className="h-4 w-4" />
                </Link>
              </Button>
              <h1 className="text-2xl font-bold tracking-tight">{plan.name}</h1>
              <Badge variant="outline">
                {plan.start_year} - {plan.end_year}
              </Badge>
            </div>
            <p className="text-muted-foreground">
              {t('page.plans.compare.description')}
            </p>
          </div>
        </div>

        {/* Summary Cards */}
        <div className="grid grid-cols-1 gap-4 md:grid-cols-4 mb-6">
          <Card>
            <CardHeader className="pb-2">
              <CardDescription className="flex items-center gap-2">
                <TrendingUp className="h-4 w-4 text-green-600" />
                {t('page.plans.compare.total_income')}
              </CardDescription>
            </CardHeader>
            <CardContent>
              <div className="space-y-1">
                <p className="text-sm text-muted-foreground">
                  {t('page.plans.compare.planned')}: {formatMoney(totals.planned_income, plan.currency_code)}
                </p>
                <p className="text-xl font-bold text-green-600">
                  {t('page.plans.compare.actual')}: {formatMoney(totals.actual_income, plan.currency_code)}
                </p>
                <p
                  className={`text-sm ${
                    incomeVariance >= 0 ? 'text-green-600' : 'text-red-600'
                  }`}
                >
                  {formatMoney(Math.abs(incomeVariance), plan.currency_code)}{' '}
                  {incomeVariance >= 0 ? t('page.plans.compare.over_plan') : t('page.plans.compare.under_plan')}
                </p>
              </div>
            </CardContent>
          </Card>

          <Card>
            <CardHeader className="pb-2">
              <CardDescription className="flex items-center gap-2">
                <TrendingDown className="h-4 w-4 text-red-600" />
                {t('page.plans.compare.total_expense')}
              </CardDescription>
            </CardHeader>
            <CardContent>
              <div className="space-y-1">
                <p className="text-sm text-muted-foreground">
                  {t('page.plans.compare.planned')}: {formatMoney(totals.planned_expense, plan.currency_code)}
                </p>
                <p className="text-xl font-bold text-red-600">
                  {t('page.plans.compare.actual')}: {formatMoney(totals.actual_expense, plan.currency_code)}
                </p>
                <p
                  className={`text-sm ${
                    expenseVariance <= 0 ? 'text-green-600' : 'text-red-600'
                  }`}
                >
                  {formatMoney(Math.abs(expenseVariance), plan.currency_code)}{' '}
                  {expenseVariance <= 0 ? t('page.plans.compare.under_plan') : t('page.plans.compare.over_plan')}
                </p>
              </div>
            </CardContent>
          </Card>

          <Card>
            <CardHeader className="pb-2">
              <CardDescription className="flex items-center gap-2">
                <Target className="h-4 w-4" />
                {t('page.plans.compare.net_savings_planned')}
              </CardDescription>
            </CardHeader>
            <CardContent>
              <p
                className={`text-2xl font-bold ${
                  netPlanned >= 0 ? 'text-green-600' : 'text-red-600'
                }`}
              >
                {formatMoney(netPlanned, plan.currency_code)}
              </p>
            </CardContent>
          </Card>

          <Card>
            <CardHeader className="pb-2">
              <CardDescription className="flex items-center gap-2">
                <Target className="h-4 w-4" />
                {t('page.plans.compare.net_savings_actual')}
              </CardDescription>
            </CardHeader>
            <CardContent>
              <p
                className={`text-2xl font-bold ${
                  netActual >= 0 ? 'text-green-600' : 'text-red-600'
                }`}
              >
                {formatMoney(netActual, plan.currency_code)}
              </p>
            </CardContent>
          </Card>
        </div>

        {/* Chart */}
        <Card className="mb-6">
          <CardHeader>
            <CardTitle>{t('page.plans.compare.chart_title')}</CardTitle>
          </CardHeader>
          <CardContent>
            <div className="h-[400px]">
              <ResponsiveContainer width="100%" height="100%">
                <BarChart data={chartData} barGap={0}>
                  <CartesianGrid strokeDasharray="3 3" className="stroke-muted" />
                  <XAxis
                    dataKey="year"
                    tick={{ fontSize: 12 }}
                    className="text-muted-foreground"
                  />
                  <YAxis
                    tick={{ fontSize: 12 }}
                    className="text-muted-foreground"
                    tickFormatter={(value) =>
                      new Intl.NumberFormat('en', {
                        notation: 'compact',
                        maximumFractionDigits: 1,
                      }).format(value)
                    }
                  />
                  <Tooltip
                    formatter={(value: number) =>
                      formatMoney(value, plan.currency_code)
                    }
                    contentStyle={{
                      backgroundColor: 'hsl(var(--card))',
                      border: '1px solid hsl(var(--border))',
                      borderRadius: '8px',
                    }}
                  />
                  <Legend />
                  <Bar
                    dataKey={t('page.plans.compare.planned_income')}
                    fill="#86efac"
                    radius={[4, 4, 0, 0]}
                  />
                  <Bar
                    dataKey={t('page.plans.compare.actual_income')}
                    fill="#22c55e"
                    radius={[4, 4, 0, 0]}
                  />
                  <Bar
                    dataKey={t('page.plans.compare.planned_expense')}
                    fill="#fca5a5"
                    radius={[4, 4, 0, 0]}
                  />
                  <Bar
                    dataKey={t('page.plans.compare.actual_expense')}
                    fill="#ef4444"
                    radius={[4, 4, 0, 0]}
                  />
                </BarChart>
              </ResponsiveContainer>
            </div>
          </CardContent>
        </Card>

        {/* Detailed Table */}
        <Card>
          <CardHeader>
            <CardTitle>{t('page.plans.compare.table_title')}</CardTitle>
          </CardHeader>
          <CardContent>
            <Table>
              <TableHeader>
                <TableRow>
                  <TableHead>{t('page.plans.compare.year')}</TableHead>
                  <TableHead className="text-right">{t('page.plans.compare.planned_income')}</TableHead>
                  <TableHead className="text-right">{t('page.plans.compare.actual_income')}</TableHead>
                  <TableHead className="text-right">{t('page.plans.compare.variance')}</TableHead>
                  <TableHead className="text-right">{t('page.plans.compare.planned_expense')}</TableHead>
                  <TableHead className="text-right">{t('page.plans.compare.actual_expense')}</TableHead>
                  <TableHead className="text-right">{t('page.plans.compare.variance')}</TableHead>
                </TableRow>
              </TableHeader>
              <TableBody>
                {comparison.map((c) => (
                  <TableRow key={c.year}>
                    <TableCell className="font-medium">{c.year}</TableCell>
                    <TableCell className="text-right">
                      {formatMoney(c.planned_income, plan.currency_code)}
                    </TableCell>
                    <TableCell className="text-right text-green-600">
                      {formatMoney(c.actual_income, plan.currency_code)}
                    </TableCell>
                    <TableCell
                      className={`text-right ${
                        c.income_variance >= 0 ? 'text-green-600' : 'text-red-600'
                      }`}
                    >
                      {formatPercent(c.income_variance_percent)}
                    </TableCell>
                    <TableCell className="text-right">
                      {formatMoney(c.planned_expense, plan.currency_code)}
                    </TableCell>
                    <TableCell className="text-right text-red-600">
                      {formatMoney(c.actual_expense, plan.currency_code)}
                    </TableCell>
                    <TableCell
                      className={`text-right ${
                        c.expense_variance <= 0 ? 'text-green-600' : 'text-red-600'
                      }`}
                    >
                      {formatPercent(c.expense_variance_percent)}
                    </TableCell>
                  </TableRow>
                ))}
              </TableBody>
            </Table>
          </CardContent>
        </Card>
      </Main>
    </AuthenticatedLayout>
  )
}
