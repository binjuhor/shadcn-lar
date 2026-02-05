import * as React from 'react'
import { useTranslation } from 'react-i18next'
import { Cell, Pie, PieChart, ResponsiveContainer } from 'recharts'
import {
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from '@/components/ui/card'
import {
  ChartContainer,
  ChartTooltip,
  ChartTooltipContent,
} from '@/components/ui/chart'

interface StatusBreakdownItem {
  status: string
  label: string
  color: string
  amount: number
  count: number
  percentage: number
}

interface StatusBreakdownProps {
  data: StatusBreakdownItem[]
  currency: string
}

function formatCurrency(value: number, currency: string): string {
  return new Intl.NumberFormat('vi-VN', {
    style: 'currency',
    currency,
    notation: 'compact',
    maximumFractionDigits: 1,
  }).format(value)
}

function formatFullCurrency(value: number, currency: string): string {
  return new Intl.NumberFormat('vi-VN', {
    style: 'currency',
    currency,
  }).format(value)
}

export function StatusBreakdown({ data, currency }: StatusBreakdownProps) {
  const { t } = useTranslation()
  const chartConfig = React.useMemo(() => {
    return data.reduce((acc, item) => {
      acc[item.status] = {
        label: item.label,
        color: item.color,
      }
      return acc
    }, {} as Record<string, { label: string; color: string }>)
  }, [data])

  const totalAmount = data.reduce((acc, item) => acc + item.amount, 0)

  return (
    <Card>
      <CardHeader>
        <CardTitle>{t('page.invoices.invoice_status')}</CardTitle>
        <CardDescription>
          {t('page.invoices.breakdown_by_status')}
        </CardDescription>
      </CardHeader>
      <CardContent>
        <ChartContainer config={chartConfig} className="mx-auto aspect-square max-h-[250px]">
          <ResponsiveContainer width="100%" height="100%">
            <PieChart>
              <ChartTooltip
                content={
                  <ChartTooltipContent
                    formatter={(value, name) => (
                      <div className="flex items-center gap-2">
                        <span className="text-muted-foreground">{name}:</span>
                        <span className="font-mono font-medium">
                          {formatCurrency(value as number, currency)}
                        </span>
                      </div>
                    )}
                  />
                }
              />
              <Pie
                data={data}
                dataKey="amount"
                nameKey="label"
                cx="50%"
                cy="50%"
                innerRadius={60}
                outerRadius={80}
                paddingAngle={2}
              >
                {data.map((entry, index) => (
                  <Cell key={`cell-${index}`} fill={entry.color} />
                ))}
              </Pie>
            </PieChart>
          </ResponsiveContainer>
        </ChartContainer>

        <div className="mt-4 space-y-2">
          {data.map((item) => (
            <div key={item.status} className="flex items-center justify-between text-sm">
              <div className="flex items-center gap-2">
                <div
                  className="h-3 w-3 rounded-full"
                  style={{ backgroundColor: item.color }}
                />
                <span>{item.label}</span>
                <span className="text-muted-foreground">({item.count})</span>
              </div>
              <div className="flex items-center gap-2">
                <span className="font-medium">{formatFullCurrency(item.amount, currency)}</span>
                <span className="text-muted-foreground">{item.percentage}%</span>
              </div>
            </div>
          ))}
        </div>

        <div className="mt-4 border-t pt-4">
          <div className="flex items-center justify-between text-sm font-medium">
            <span>{t('common.total')}</span>
            <span>{formatFullCurrency(totalAmount, currency)}</span>
          </div>
        </div>
      </CardContent>
    </Card>
  )
}
