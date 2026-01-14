import * as React from 'react'
import { Bar, BarChart, CartesianGrid, XAxis, YAxis, Legend } from 'recharts'
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
  ChartTooltip,
  ChartTooltipContent,
} from '@/components/ui/chart'
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select'
import { TrendingUp, TrendingDown, BarChart3, Calendar, Hash } from 'lucide-react'
import type { Category, CategoryTrendAnalysis } from '@modules/Finance/types/finance'

interface CategoryTrendChartProps {
  categories: Category[]
  incomeTrendData: CategoryTrendAnalysis | null
  expenseTrendData: CategoryTrendAnalysis | null
  currencyCode: string
  selectedIncomeCategoryId: number | null
  selectedExpenseCategoryId: number | null
  onIncomeCategoryChange: (categoryId: number | null) => void
  onExpenseCategoryChange: (categoryId: number | null) => void
}

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

function getTrendColor(trend: number, isIncome: boolean) {
  // For income: positive trend is good (green), negative is bad (red)
  // For expense: positive trend is bad (red), negative is good (green)
  if (isIncome) {
    if (trend > 0) return 'text-green-600'
    if (trend < 0) return 'text-red-600'
  } else {
    if (trend > 0) return 'text-red-600'
    if (trend < 0) return 'text-green-600'
  }
  return 'text-muted-foreground'
}

function getTrendIcon(trend: number, isIncome: boolean) {
  const isPositive = isIncome ? trend > 0 : trend < 0
  if (trend === 0) return null
  if (isPositive) return <TrendingUp className="h-4 w-4 text-green-600" />
  return <TrendingDown className="h-4 w-4 text-red-600" />
}

interface CategorySelectorProps {
  label: string
  categories: Category[]
  selectedId: number | null
  onChange: (id: number | null) => void
  placeholder: string
}

function CategorySelector({ label, categories, selectedId, onChange, placeholder }: CategorySelectorProps) {
  const handleChange = (value: string) => {
    if (value === '' || value === 'none') {
      onChange(null)
    } else {
      onChange(parseInt(value))
    }
  }

  return (
    <div className="space-y-1">
      <label className="text-xs font-medium text-muted-foreground">{label}</label>
      <Select
        value={selectedId?.toString() || 'none'}
        onValueChange={handleChange}
      >
        <SelectTrigger className="w-full">
          <SelectValue placeholder={placeholder} />
        </SelectTrigger>
        <SelectContent>
          <SelectItem value="none">
            <span className="text-muted-foreground">None</span>
          </SelectItem>
          {categories.map((cat) => (
            <SelectItem key={cat.id} value={cat.id.toString()}>
              <div className="flex items-center gap-2">
                <div
                  className="h-2 w-2 rounded-full"
                  style={{ backgroundColor: cat.color || '#6b7280' }}
                />
                {cat.name}
                {cat.is_passive && (
                  <span className="ml-1 text-xs text-muted-foreground">(Passive)</span>
                )}
              </div>
            </SelectItem>
          ))}
        </SelectContent>
      </Select>
    </div>
  )
}

interface StatCardProps {
  label: string
  value: string
  icon: React.ReactNode
  color?: string
  subValue?: string
  trend?: number
  isIncome?: boolean
}

function StatCard({ label, value, icon, color, subValue, trend, isIncome }: StatCardProps) {
  return (
    <div className="text-center">
      <div className="flex items-center justify-center gap-1 text-xs text-muted-foreground">
        {icon}
        {label}
      </div>
      <div className="text-lg font-bold" style={color ? { color } : undefined}>
        {value}
      </div>
      {subValue && (
        <div className="text-xs text-muted-foreground">{subValue}</div>
      )}
      {trend !== undefined && (
        <div className={`flex items-center justify-center gap-1 text-xs ${getTrendColor(trend, isIncome ?? true)}`}>
          {getTrendIcon(trend, isIncome ?? true)}
          {trend > 0 ? '+' : ''}{trend.toFixed(1)}%
        </div>
      )}
    </div>
  )
}

export function CategoryTrendChart({
  categories,
  incomeTrendData,
  expenseTrendData,
  currencyCode,
  selectedIncomeCategoryId,
  selectedExpenseCategoryId,
  onIncomeCategoryChange,
  onExpenseCategoryChange,
}: CategoryTrendChartProps) {
  const incomeCategories = categories.filter((c) => c.type === 'income' || c.type === 'both')
  const expenseCategories = categories.filter((c) => c.type === 'expense' || c.type === 'both')

  const hasData = incomeTrendData || expenseTrendData

  const chartConfig = {
    income: {
      label: incomeTrendData?.category?.name || 'Income',
      color: incomeTrendData?.category?.color || 'hsl(142, 76%, 36%)',
    },
    expense: {
      label: expenseTrendData?.category?.name || 'Expense',
      color: expenseTrendData?.category?.color || 'hsl(0, 84%, 60%)',
    },
  } satisfies ChartConfig

  const chartData = React.useMemo(() => {
    if (!hasData) return []

    // Build periods from whichever data is available
    const periods = incomeTrendData?.monthlyData || expenseTrendData?.monthlyData || []

    return periods.map((point, idx) => ({
      period: point.period,
      label: point.label,
      income: incomeTrendData?.monthlyData[idx]?.amount || 0,
      expense: expenseTrendData?.monthlyData[idx]?.amount || 0,
    }))
  }, [incomeTrendData, expenseTrendData, hasData])

  return (
    <Card>
      <CardHeader className="pb-2">
        <div className="flex flex-col gap-4">
          <div>
            <CardTitle className="flex items-center gap-2">
              <BarChart3 className="h-5 w-5" />
              Category Comparison
            </CardTitle>
            <CardDescription>
              Compare income vs expense category performance over 12 months
            </CardDescription>
          </div>
          <div className="grid grid-cols-2 gap-4">
            <CategorySelector
              label="Income Category"
              categories={incomeCategories}
              selectedId={selectedIncomeCategoryId}
              onChange={onIncomeCategoryChange}
              placeholder="Select income..."
            />
            <CategorySelector
              label="Expense Category"
              categories={expenseCategories}
              selectedId={selectedExpenseCategoryId}
              onChange={onExpenseCategoryChange}
              placeholder="Select expense..."
            />
          </div>
        </div>
      </CardHeader>

      <CardContent className="space-y-6">
        {!hasData ? (
          <div className="flex h-[300px] items-center justify-center rounded-lg border border-dashed">
            <div className="text-center text-muted-foreground">
              <BarChart3 className="mx-auto h-12 w-12 opacity-50" />
              <p className="mt-2">Select categories to compare their performance</p>
            </div>
          </div>
        ) : (
          <>
            {/* Summary Stats - Two columns */}
            <div className="grid grid-cols-2 gap-4">
              {/* Income Stats - always first column */}
              {incomeTrendData ? (
                <div className="rounded-lg border border-green-200 bg-green-50/50 p-3 dark:border-green-900 dark:bg-green-950/20">
                  <div className="mb-2 flex items-center gap-2 text-sm font-medium text-green-700 dark:text-green-400">
                    <div
                      className="h-3 w-3 rounded-full"
                      style={{ backgroundColor: incomeTrendData.category?.color || '#22c55e' }}
                    />
                    {incomeTrendData.category?.name}
                  </div>
                  <div className="grid grid-cols-2 gap-2 text-xs">
                    <div>
                      <span className="text-muted-foreground">Total:</span>
                      <div className="font-semibold">
                        {formatCurrency(incomeTrendData.totalAmount, currencyCode)}
                      </div>
                    </div>
                    <div>
                      <span className="text-muted-foreground">Monthly Avg:</span>
                      <div className="font-semibold">
                        {formatCurrency(incomeTrendData.averageAmount, currencyCode)}
                      </div>
                    </div>
                    <div>
                      <span className="text-muted-foreground">Transactions:</span>
                      <div className="font-semibold">{incomeTrendData.transactionCount}</div>
                    </div>
                    <div>
                      <span className="text-muted-foreground">Trend:</span>
                      <div className={`font-semibold ${getTrendColor(incomeTrendData.trend, true)}`}>
                        {incomeTrendData.trend > 0 ? '+' : ''}{incomeTrendData.trend.toFixed(1)}%
                      </div>
                    </div>
                  </div>
                  {incomeTrendData.bestMonth && (
                    <div className="mt-2 border-t border-green-200 pt-2 text-xs dark:border-green-900">
                      <span className="text-muted-foreground">Best: </span>
                      <span className="font-medium">{incomeTrendData.bestMonth.period}</span>
                      <span className="text-muted-foreground"> - </span>
                      <span>{formatCurrency(incomeTrendData.bestMonth.amount, currencyCode)}</span>
                    </div>
                  )}
                </div>
              ) : (
                <div className="flex items-center justify-center rounded-lg border border-dashed p-3 min-h-[120px]">
                  <span className="text-xs text-muted-foreground">Select an income category</span>
                </div>
              )}

              {/* Expense Stats - always second column */}
              {expenseTrendData ? (
                <div className="rounded-lg border border-red-200 bg-red-50/50 p-3 dark:border-red-900 dark:bg-red-950/20">
                  <div className="mb-2 flex items-center gap-2 text-sm font-medium text-red-700 dark:text-red-400">
                    <div
                      className="h-3 w-3 rounded-full"
                      style={{ backgroundColor: expenseTrendData.category?.color || '#ef4444' }}
                    />
                    {expenseTrendData.category?.name}
                  </div>
                  <div className="grid grid-cols-2 gap-2 text-xs">
                    <div>
                      <span className="text-muted-foreground">Total:</span>
                      <div className="font-semibold">
                        {formatCurrency(expenseTrendData.totalAmount, currencyCode)}
                      </div>
                    </div>
                    <div>
                      <span className="text-muted-foreground">Monthly Avg:</span>
                      <div className="font-semibold">
                        {formatCurrency(expenseTrendData.averageAmount, currencyCode)}
                      </div>
                    </div>
                    <div>
                      <span className="text-muted-foreground">Transactions:</span>
                      <div className="font-semibold">{expenseTrendData.transactionCount}</div>
                    </div>
                    <div>
                      <span className="text-muted-foreground">Trend:</span>
                      <div className={`font-semibold ${getTrendColor(expenseTrendData.trend, false)}`}>
                        {expenseTrendData.trend > 0 ? '+' : ''}{expenseTrendData.trend.toFixed(1)}%
                      </div>
                    </div>
                  </div>
                  {expenseTrendData.bestMonth && (
                    <div className="mt-2 border-t border-red-200 pt-2 text-xs dark:border-red-900">
                      <span className="text-muted-foreground">Highest: </span>
                      <span className="font-medium">{expenseTrendData.bestMonth.period}</span>
                      <span className="text-muted-foreground"> - </span>
                      <span>{formatCurrency(expenseTrendData.bestMonth.amount, currencyCode)}</span>
                    </div>
                  )}
                </div>
              ) : (
                <div className="flex items-center justify-center rounded-lg border border-dashed p-3 min-h-[120px]">
                  <span className="text-xs text-muted-foreground">Select an expense category</span>
                </div>
              )}
            </div>

            {/* Net Income Summary - shown when both categories selected */}
            {incomeTrendData && expenseTrendData && (
              <div className="rounded-lg border border-blue-200 bg-blue-50/50 p-4 dark:border-blue-900 dark:bg-blue-950/20">
                <div className="mb-3 text-sm font-medium text-blue-700 dark:text-blue-400">
                  Net Income Analysis
                </div>
                <div className="grid grid-cols-2 gap-4 sm:grid-cols-4">
                  <div className="text-center">
                    <div className="text-xs text-muted-foreground">Total Net</div>
                    <div className={`text-lg font-bold ${
                      incomeTrendData.totalAmount - expenseTrendData.totalAmount >= 0
                        ? 'text-green-600'
                        : 'text-red-600'
                    }`}>
                      {incomeTrendData.totalAmount - expenseTrendData.totalAmount >= 0 ? '+' : ''}
                      {formatCurrency(incomeTrendData.totalAmount - expenseTrendData.totalAmount, currencyCode)}
                    </div>
                  </div>
                  <div className="text-center">
                    <div className="text-xs text-muted-foreground">Monthly Avg Net</div>
                    <div className={`text-lg font-bold ${
                      incomeTrendData.averageAmount - expenseTrendData.averageAmount >= 0
                        ? 'text-green-600'
                        : 'text-red-600'
                    }`}>
                      {incomeTrendData.averageAmount - expenseTrendData.averageAmount >= 0 ? '+' : ''}
                      {formatCurrency(incomeTrendData.averageAmount - expenseTrendData.averageAmount, currencyCode)}
                    </div>
                  </div>
                  <div className="text-center">
                    <div className="text-xs text-muted-foreground">Coverage Ratio</div>
                    <div className={`text-lg font-bold ${
                      incomeTrendData.totalAmount >= expenseTrendData.totalAmount
                        ? 'text-green-600'
                        : 'text-red-600'
                    }`}>
                      {expenseTrendData.totalAmount > 0
                        ? ((incomeTrendData.totalAmount / expenseTrendData.totalAmount) * 100).toFixed(0)
                        : '∞'}%
                    </div>
                  </div>
                  <div className="text-center">
                    <div className="text-xs text-muted-foreground">Status</div>
                    <div className={`text-lg font-bold ${
                      incomeTrendData.totalAmount >= expenseTrendData.totalAmount
                        ? 'text-green-600'
                        : 'text-red-600'
                    }`}>
                      {incomeTrendData.totalAmount >= expenseTrendData.totalAmount ? 'Surplus' : 'Deficit'}
                    </div>
                  </div>
                </div>
              </div>
            )}

            {/* Comparison Chart */}
            <ChartContainer config={chartConfig} className="aspect-auto h-[300px] w-full">
              <BarChart
                data={chartData}
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
                  tickFormatter={(value) => formatCurrency(value, currencyCode)}
                  tickLine={false}
                  axisLine={false}
                  width={70}
                />
                <ChartTooltip
                  content={
                    <ChartTooltipContent
                      className="w-[200px]"
                      formatter={(value, name) => {
                        const label = name === 'income'
                          ? incomeTrendData?.category?.name || 'Income'
                          : expenseTrendData?.category?.name || 'Expense'
                        return (
                          <div className="flex items-center justify-between gap-2">
                            <span className="text-muted-foreground">{label}:</span>
                            <span className="font-mono font-medium">
                              {formatCurrency(value as number, currencyCode)}
                            </span>
                          </div>
                        )
                      }}
                    />
                  }
                />
                <Legend />
                {incomeTrendData && (
                  <Bar
                    dataKey="income"
                    name={incomeTrendData.category?.name || 'Income'}
                    fill={incomeTrendData.category?.color || 'hsl(142, 76%, 36%)'}
                    radius={[4, 4, 0, 0]}
                  />
                )}
                {expenseTrendData && (
                  <Bar
                    dataKey="expense"
                    name={expenseTrendData.category?.name || 'Expense'}
                    fill={expenseTrendData.category?.color || 'hsl(0, 84%, 60%)'}
                    radius={[4, 4, 0, 0]}
                  />
                )}
              </BarChart>
            </ChartContainer>

            <div className="rounded-lg bg-muted/50 p-3 text-xs text-muted-foreground">
              <p>
                <strong>Tip:</strong> Compare income and expense categories to understand your financial patterns.
                Ideally, income categories should show growth while expense categories remain stable or decrease.
              </p>
            </div>
          </>
        )}
      </CardContent>
    </Card>
  )
}
