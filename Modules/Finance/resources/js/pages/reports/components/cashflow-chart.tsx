import * as React from 'react'
import { Bar, BarChart, CartesianGrid, XAxis, YAxis, Line, ComposedChart, ReferenceLine } from 'recharts'
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
import { Progress } from '@/components/ui/progress'
import { Target, TrendingUp, Wallet } from 'lucide-react'
import type { CashflowAnalysis } from '@modules/Finance/types/finance'

interface CashflowChartProps {
  data: CashflowAnalysis
  currencyCode: string
}

const chartConfig = {
  passiveIncome: {
    label: 'Passive Income',
    color: 'hsl(142, 76%, 36%)',
  },
  essentialExpense: {
    label: 'Essential Expense',
    color: 'hsl(0, 84%, 60%)',
  },
  expense: {
    label: 'Total Expense',
    color: 'hsl(0, 60%, 75%)',
  },
  passiveCoverage: {
    label: 'Coverage %',
    color: 'hsl(199, 89%, 48%)',
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

export function CashflowChart({ data, currencyCode }: CashflowChartProps) {
  const { monthlyData, averages, financialFreedomProgress } = data

  const maxCoverage = Math.max(...monthlyData.map((d) => d.passiveCoverage), 100)
  const coverageAxisMax = Math.ceil(maxCoverage / 25) * 25

  const getProgressColor = (progress: number) => {
    if (progress >= 100) return 'bg-green-500'
    if (progress >= 75) return 'bg-emerald-500'
    if (progress >= 50) return 'bg-yellow-500'
    if (progress >= 25) return 'bg-orange-500'
    return 'bg-red-500'
  }

  const getProgressLabel = (progress: number) => {
    if (progress >= 100) return 'Financial Freedom!'
    if (progress >= 75) return 'Almost There'
    if (progress >= 50) return 'Halfway'
    if (progress >= 25) return 'Getting Started'
    return 'Building Foundation'
  }

  return (
    <Card>
      <CardHeader className="pb-2">
        <div className="flex items-center justify-between">
          <div>
            <CardTitle className="flex items-center gap-2">
              <Target className="h-5 w-5" />
              Financial Freedom Progress
            </CardTitle>
            <CardDescription>
              Passive income vs expenses - Track your path to financial independence
            </CardDescription>
          </div>
        </div>

        <div className="mt-4 space-y-2">
          <div className="flex items-center justify-between text-sm">
            <span className="text-muted-foreground">{getProgressLabel(financialFreedomProgress)}</span>
            <span className="font-bold">{financialFreedomProgress.toFixed(1)}%</span>
          </div>
          <Progress
            value={Math.min(financialFreedomProgress, 100)}
            className="h-3"
            indicatorClassName={getProgressColor(financialFreedomProgress)}
          />
          <div className="flex justify-between text-xs text-muted-foreground">
            <span>0%</span>
            <span>25%</span>
            <span>50%</span>
            <span>75%</span>
            <span>100%</span>
          </div>
        </div>
      </CardHeader>

      <CardContent className="space-y-6">
        <div className="grid grid-cols-2 md:grid-cols-4 gap-4 rounded-lg border p-3">
          <div className="text-center">
            <div className="flex items-center justify-center gap-1 text-xs text-muted-foreground">
              <TrendingUp className="h-3 w-3 text-green-600" />
              Avg Passive Income
            </div>
            <div className="text-lg font-bold text-green-600">
              {formatFullCurrency(averages.passiveIncome, currencyCode)}
            </div>
          </div>
          <div className="text-center">
            <div className="flex items-center justify-center gap-1 text-xs text-muted-foreground">
              <Wallet className="h-3 w-3 text-red-600" />
              Avg Essential Expense
            </div>
            <div className="text-lg font-bold text-red-600">
              {formatFullCurrency(averages.essentialExpense, currencyCode)}
            </div>
          </div>
          <div className="text-center">
            <div className="flex items-center justify-center gap-1 text-xs text-muted-foreground">
              <Wallet className="h-3 w-3 text-red-400" />
              Avg Total Expense
            </div>
            <div className="text-lg font-bold text-red-400">
              {formatFullCurrency(averages.expense, currencyCode)}
            </div>
          </div>
          <div className="text-center">
            <div className="flex items-center justify-center gap-1 text-xs text-muted-foreground">
              <Target className="h-3 w-3 text-blue-600" />
              Avg Coverage
            </div>
            <div className="text-lg font-bold text-blue-600">
              {averages.coverage}%
            </div>
          </div>
        </div>

        <ChartContainer config={chartConfig} className="aspect-auto h-[300px] w-full">
          <ComposedChart
            data={monthlyData}
            margin={{ left: 12, right: 12, top: 12, bottom: 12 }}
          >
            <CartesianGrid vertical={false} strokeDasharray="3 3" />
            <XAxis
              dataKey="label"
              tickLine={false}
              axisLine={false}
              tickMargin={8}
              minTickGap={32}
              tick={{ fontSize: 11 }}
            />
            <YAxis
              yAxisId="left"
              tickFormatter={(value) => formatCurrency(value, currencyCode)}
              tickLine={false}
              axisLine={false}
              width={70}
            />
            <YAxis
              yAxisId="right"
              orientation="right"
              tickFormatter={(value) => `${value}%`}
              tickLine={false}
              axisLine={false}
              width={45}
              domain={[0, coverageAxisMax]}
            />
            <ReferenceLine
              yAxisId="right"
              y={100}
              stroke="hsl(142, 76%, 36%)"
              strokeDasharray="5 5"
              label={{ value: '100% Coverage', position: 'right', fontSize: 10, fill: 'hsl(142, 76%, 36%)' }}
            />
            <ChartTooltip
              content={
                <ChartTooltipContent
                  className="w-[220px]"
                  formatter={(value, name) => {
                    if (name === 'passiveCoverage') {
                      return (
                        <div className="flex items-center gap-2">
                          <span className="text-muted-foreground">Coverage:</span>
                          <span className="font-mono font-medium">{value}%</span>
                        </div>
                      )
                    }
                    const labels: Record<string, string> = {
                      passiveIncome: 'Passive:',
                      essentialExpense: 'Essential:',
                      expense: 'Total:',
                    }
                    return (
                      <div className="flex items-center gap-2">
                        <span className="text-muted-foreground">
                          {labels[name as string] || name}
                        </span>
                        <span className="font-mono font-medium">
                          {formatCurrency(value as number, currencyCode)}
                        </span>
                      </div>
                    )
                  }}
                />
              }
            />
            <ChartLegend content={<ChartLegendContent />} />
            <Bar
              yAxisId="left"
              dataKey="passiveIncome"
              fill="var(--color-passiveIncome)"
              radius={[4, 4, 0, 0]}
              barSize={16}
            />
            <Bar
              yAxisId="left"
              dataKey="essentialExpense"
              fill="var(--color-essentialExpense)"
              radius={[4, 4, 0, 0]}
              barSize={16}
            />
            <Line
              yAxisId="right"
              type="monotone"
              dataKey="passiveCoverage"
              stroke="var(--color-passiveCoverage)"
              strokeWidth={2}
              dot={{ fill: 'var(--color-passiveCoverage)', r: 3 }}
            />
          </ComposedChart>
        </ChartContainer>

        <div className="rounded-lg bg-muted/50 p-3 text-xs text-muted-foreground">
          <p>
            <strong>Financial Freedom Goal:</strong> When your passive income covers 100% of your essential expenses (needs),
            you've achieved financial independence. Essential expenses are categorized as "needs" like housing, food, utilities,
            healthcare, and insurance. The coverage % is calculated as Passive Income / Essential Expenses.
          </p>
        </div>
      </CardContent>
    </Card>
  )
}
