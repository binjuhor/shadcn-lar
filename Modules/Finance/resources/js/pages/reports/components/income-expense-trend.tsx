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

  return (
    <Card>
      <CardHeader>
        <CardTitle>Income vs Expense</CardTitle>
        <CardDescription>
          Track your income and expenses over time
        </CardDescription>
      </CardHeader>
      <CardContent>
        <ChartContainer config={chartConfig} className="aspect-auto h-[300px] w-full">
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
            />
            <YAxis
              tickFormatter={(value) => formatCurrency(value, currencyCode)}
              tickLine={false}
              axisLine={false}
              width={80}
            />
            <ChartTooltip
              content={
                <ChartTooltipContent
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
