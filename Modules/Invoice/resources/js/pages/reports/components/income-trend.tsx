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

interface IncomeTrendPoint {
  period: string
  total: number
  paid: number
  pending: number
  count: number
}

interface IncomeTrendProps {
  data: IncomeTrendPoint[]
}

const chartConfig = {
  paid: {
    label: 'Paid',
    color: 'hsl(142, 76%, 36%)',
  },
  pending: {
    label: 'Pending',
    color: 'hsl(43, 96%, 56%)',
  },
} satisfies ChartConfig

function formatCurrency(value: number): string {
  return new Intl.NumberFormat('vi-VN', {
    style: 'currency',
    currency: 'VND',
    notation: 'compact',
    maximumFractionDigits: 1,
  }).format(value)
}

function formatFullCurrency(value: number): string {
  return new Intl.NumberFormat('vi-VN', {
    style: 'currency',
    currency: 'VND',
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

export function IncomeTrend({ data }: IncomeTrendProps) {
  const formattedData = data.map((item) => ({
    ...item,
    periodLabel: formatPeriod(item.period),
  }))

  const totals = React.useMemo(() => ({
    total: data.reduce((acc, curr) => acc + curr.total, 0),
    paid: data.reduce((acc, curr) => acc + curr.paid, 0),
    pending: data.reduce((acc, curr) => acc + curr.pending, 0),
    count: data.reduce((acc, curr) => acc + curr.count, 0),
  }), [data])

  return (
    <Card>
      <CardHeader className="flex flex-col items-stretch space-y-0 border-b p-0 sm:flex-row">
        <div className="flex flex-1 flex-col justify-center gap-1 px-6 py-4">
          <CardTitle>Invoice Income Trend</CardTitle>
          <CardDescription>
            Track your invoice income over time
          </CardDescription>
        </div>
        <div className="flex">
          <div className="flex flex-1 flex-col justify-center gap-1 border-t px-4 py-3 text-left even:border-l sm:border-l sm:border-t-0 sm:px-6 sm:py-4">
            <span className="text-xs text-muted-foreground">Total</span>
            <span className="text-sm font-bold leading-none">
              {formatFullCurrency(totals.total)}
            </span>
          </div>
          <div className="flex flex-1 flex-col justify-center gap-1 border-t px-4 py-3 text-left even:border-l sm:border-l sm:border-t-0 sm:px-6 sm:py-4">
            <span className="text-xs text-muted-foreground">Paid</span>
            <span className="text-sm font-bold leading-none text-green-600">
              {formatFullCurrency(totals.paid)}
            </span>
          </div>
          <div className="flex flex-1 flex-col justify-center gap-1 border-t px-4 py-3 text-left even:border-l sm:border-l sm:border-t-0 sm:px-6 sm:py-4">
            <span className="text-xs text-muted-foreground">Pending</span>
            <span className="text-sm font-bold leading-none text-yellow-600">
              {formatFullCurrency(totals.pending)}
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
              tickFormatter={(value) => formatCurrency(value)}
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
                        {name === 'paid' ? 'Paid' : 'Pending'}:
                      </span>
                      <span className="font-mono font-medium">
                        {formatCurrency(value as number)}
                      </span>
                    </div>
                  )}
                />
              }
            />
            <ChartLegend content={<ChartLegendContent />} />
            <Bar
              dataKey="paid"
              fill="var(--color-paid)"
              stackId="a"
              radius={[0, 0, 0, 0]}
            />
            <Bar
              dataKey="pending"
              fill="var(--color-pending)"
              stackId="a"
              radius={[4, 4, 0, 0]}
            />
          </BarChart>
        </ChartContainer>
      </CardContent>
    </Card>
  )
}
