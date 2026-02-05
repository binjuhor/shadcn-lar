import * as React from 'react'
import { useTranslation } from 'react-i18next'
import { Bar, BarChart, XAxis, YAxis, CartesianGrid } from 'recharts'
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

interface ClientBreakdownItem {
  name: string
  color: string
  amount: number
  count: number
  percentage: number
}

interface ClientBreakdownProps {
  data: ClientBreakdownItem[]
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

export function ClientBreakdown({ data, currency }: ClientBreakdownProps) {
  const { t } = useTranslation()

  const chartConfig = React.useMemo(() => {
    return {
      amount: {
        label: t('form.amount'),
        color: 'hsl(199, 89%, 48%)',
      },
    }
  }, [t])

  const chartData = data.map((item) => ({
    ...item,
    shortName: item.name.length > 15 ? item.name.substring(0, 15) + '...' : item.name,
  }))

  if (data.length === 0) {
    return (
      <Card>
        <CardHeader>
          <CardTitle>{t('page.invoice_reports.top_clients')}</CardTitle>
          <CardDescription>
            {t('page.invoice_reports.revenue_by_client')}
          </CardDescription>
        </CardHeader>
        <CardContent>
          <div className="flex h-[300px] items-center justify-center text-muted-foreground">
            {t('page.invoice_reports.no_data_available')}
          </div>
        </CardContent>
      </Card>
    )
  }

  return (
    <Card>
      <CardHeader>
        <CardTitle>{t('page.invoice_reports.top_clients')}</CardTitle>
        <CardDescription>
          {t('page.invoice_reports.revenue_by_client_top_10')}
        </CardDescription>
      </CardHeader>
      <CardContent>
        <ChartContainer config={chartConfig} className="aspect-auto h-[300px] w-full">
          <BarChart
            accessibilityLayer
            data={chartData}
            layout="vertical"
            margin={{ left: 0, right: 12 }}
          >
            <CartesianGrid horizontal={false} />
            <XAxis
              type="number"
              tickFormatter={(value) => formatCurrency(value, currency)}
              tickLine={false}
              axisLine={false}
            />
            <YAxis
              type="category"
              dataKey="shortName"
              tickLine={false}
              axisLine={false}
              width={120}
              tick={{ fontSize: 12 }}
            />
            <ChartTooltip
              content={
                <ChartTooltipContent
                  formatter={(value, _name, props) => (
                    <div className="flex flex-col gap-1">
                      <span className="font-medium">{props.payload.name}</span>
                      <div className="flex items-center gap-2">
                        <span className="text-muted-foreground">Amount:</span>
                        <span className="font-mono">{formatFullCurrency(value as number, currency)}</span>
                      </div>
                      <div className="flex items-center gap-2">
                        <span className="text-muted-foreground">Invoices:</span>
                        <span className="font-mono">{props.payload.count}</span>
                      </div>
                    </div>
                  )}
                />
              }
            />
            <Bar
              dataKey="amount"
              fill="var(--color-amount)"
              radius={[0, 4, 4, 0]}
            />
          </BarChart>
        </ChartContainer>

        <div className="mt-4 space-y-2">
          {data.slice(0, 5).map((item) => (
            <div key={item.name} className="flex items-center justify-between text-sm">
              <div className="flex items-center gap-2">
                <div
                  className="h-3 w-3 rounded-full"
                  style={{ backgroundColor: item.color }}
                />
                <span className="truncate max-w-[150px]">{item.name}</span>
              </div>
              <div className="flex items-center gap-2">
                <span className="font-medium">{formatFullCurrency(item.amount, currency)}</span>
                <span className="text-muted-foreground">{item.percentage}%</span>
              </div>
            </div>
          ))}
        </div>
      </CardContent>
    </Card>
  )
}
