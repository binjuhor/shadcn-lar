import * as React from 'react'
import { Bar, BarChart, CartesianGrid, XAxis, YAxis } from 'recharts'
import {
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from '@/components/ui/card'
import {
  ChartConfig,
  ChartContainer,
  ChartLegend,
  ChartLegendContent,
  ChartTooltip,
  ChartTooltipContent,
} from '@/components/ui/chart'
import type { IncomeExpensePoint } from '@modules/Finance/types/finance'

interface IncomeExpenseTrendProps {
  data: IncomeExpensePoint[]
  currencyCode: string
}

const chartConfig = {
  income: {
    label: 'Income',
    color: 'hsl(142, 76%, 36%)',
  },
  expense: {
    label: 'Expense',
    color: 'hsl(0, 84%, 60%)',
  },
} satisfies ChartConfig

function formatCurrency(value: number, code: string): string {
  return new Intl.NumberFormat('vi-VN', {
    style: 'currency',
    currency: code,
    notation: 'compact',
    maximumFractionDigits: 1,
  }).format(value)
}

function formatFullCurrency(value: number, code: string): string {
  return new Intl.NumberFormat('vi-VN', {
    style: 'currency',
    currency: code,
  }).format(value)
}

function formatPeriod(period: string): string {
  if (period.length === 7) {
    const [year, month] = period.split('-')
    const date = new Date(parseInt(year), parseInt(month) - 1)
    return date.toLocaleDateString('en-US', { month: 'short', year: '2-digit' })
  }
  const date = new Date(period)
  return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric' })
}

export function IncomeExpenseTrend({ data, currencyCode }: IncomeExpenseTrendProps) {
  const formattedData = data.map((item) => ({
    ...item,
    periodLabel: formatPeriod(item.period),
  }))

  const totals = React.useMemo(() => ({
    income: data.reduce((acc, curr) => acc + curr.income, 0),
    expense: data.reduce((acc, curr) => acc + curr.expense, 0),
  }), [data])

  const netChange = totals.income - totals.expense

  return (
    <Card>
      <CardHeader className="flex flex-col items-stretch space-y-0 border-b p-0 sm:flex-row">
        <div className="flex flex-1 flex-col justify-center gap-1 px-6 py-4">
          <CardTitle>Income vs Expense</CardTitle>
          <CardDescription>
            Track your income and expenses over time
          </CardDescription>
        </div>
        <div className="flex">
          <div className="flex flex-1 flex-col justify-center gap-1 border-t px-4 py-3 text-left even:border-l sm:border-l sm:border-t-0 sm:px-6 sm:py-4">
            <span className="text-xs text-muted-foreground">Net</span>
            <span className={`text-sm font-bold leading-none ${netChange >= 0 ? 'text-green-600' : 'text-red-600'}`}>
              {netChange >= 0 ? '+' : ''}{formatFullCurrency(netChange, currencyCode)}
            </span>
          </div>
          <div className="flex flex-1 flex-col justify-center gap-1 border-t px-4 py-3 text-left even:border-l sm:border-l sm:border-t-0 sm:px-6 sm:py-4">
            <span className="text-xs text-muted-foreground">Income</span>
            <span className="text-sm font-bold leading-none text-green-600">
              {formatFullCurrency(totals.income, currencyCode)}
            </span>
          </div>
          <div className="flex flex-1 flex-col justify-center gap-1 border-t px-4 py-3 text-left even:border-l sm:border-l sm:border-t-0 sm:px-6 sm:py-4">
            <span className="text-xs text-muted-foreground">Expense</span>
            <span className="text-sm font-bold leading-none text-red-600">
              {formatFullCurrency(totals.expense, currencyCode)}
            </span>
          </div>
        </div>
      </CardHeader>
      <CardContent className="px-2 sm:p-6">
        <ChartContainer config={chartConfig} className="aspect-auto h-[350px] w-full">
          <BarChart
            accessibilityLayer
            data={formattedData}
            margin={{ left: 12, right: 12, top: 12, bottom: 12 }}
          >
            <CartesianGrid vertical={false} />
            <XAxis
              dataKey="periodLabel"
              tickLine={false}
              axisLine={false}
              tickMargin={8}
              minTickGap={32}
            />
            <YAxis
              tickFormatter={(value) => formatCurrency(value, currencyCode)}
              tickLine={false}
              axisLine={false}
              width={70}
            />
            <ChartTooltip
              content={
                <ChartTooltipContent
                  className="w-[180px]"
                  formatter={(value, name) => (
                    <div className="flex items-center gap-2">
                      <span className="text-muted-foreground">
                        {name === 'income' ? 'Income' : 'Expense'}:
                      </span>
                      <span className="font-mono font-medium">
                        {formatCurrency(value as number, currencyCode)}
                      </span>
                    </div>
                  )}
                />
              }
            />
            <ChartLegend content={<ChartLegendContent />} />
            <Bar
              dataKey="income"
              fill="var(--color-income)"
              radius={[4, 4, 0, 0]}
            />
            <Bar
              dataKey="expense"
              fill="var(--color-expense)"
              radius={[4, 4, 0, 0]}
            />
          </BarChart>
        </ChartContainer>
      </CardContent>
    </Card>
  )
}
