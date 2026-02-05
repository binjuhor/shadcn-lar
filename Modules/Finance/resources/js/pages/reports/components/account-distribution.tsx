import { useTranslation } from 'react-i18next'
import { Bar, BarChart, XAxis, YAxis, Cell } from 'recharts'
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
import type { AccountTypeBreakdown } from '@modules/Finance/types/finance'

interface AccountDistributionProps {
  data: AccountTypeBreakdown[]
  currencyCode: string
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

export function AccountDistribution({ data, currencyCode }: AccountDistributionProps) {
  const { t } = useTranslation()

  const chartConfig = data.reduce((config, item) => {
    config[item.type] = {
      label: item.label,
      color: item.color,
    }
    return config
  }, {} as ChartConfig)

  const totalAssets = data
    .filter((d) => !d.isLiability)
    .reduce((sum, d) => sum + d.balance, 0)

  const totalLiabilities = data
    .filter((d) => d.isLiability)
    .reduce((sum, d) => sum + Math.abs(d.balance), 0)

  if (data.length === 0) {
    return (
      <Card className="h-full">
        <CardHeader className="pb-2">
          <CardTitle className="text-base">{t('page.reports.account_distribution')}</CardTitle>
          <CardDescription>{t('page.reports.no_active_accounts')}</CardDescription>
        </CardHeader>
        <CardContent className="flex h-[200px] items-center justify-center">
          <p className="text-muted-foreground">{t('page.reports.no_data')}</p>
        </CardContent>
      </Card>
    )
  }

  return (
    <Card className="h-full">
      <CardHeader className="pb-2">
        <CardTitle className="text-base">{t('page.reports.account_distribution')}</CardTitle>
        <CardDescription>
          {t('page.reports.balance_by_account_type')}
        </CardDescription>
      </CardHeader>
      <CardContent className="pb-4">
        <ChartContainer config={chartConfig} className="aspect-auto h-[220px] w-full">
          <BarChart
            accessibilityLayer
            data={data}
            layout="vertical"
            margin={{ left: 0, right: 12, top: 8, bottom: 8 }}
          >
            <XAxis
              type="number"
              tickFormatter={(value) => formatCurrency(Math.abs(value), currencyCode)}
              tickLine={false}
              axisLine={false}
            />
            <YAxis
              dataKey="label"
              type="category"
              tickLine={false}
              axisLine={false}
              width={80}
              tickMargin={4}
              fontSize={12}
            />
            <ChartTooltip
              content={
                <ChartTooltipContent
                  formatter={(value, _name, props) => {
                    const item = props.payload as AccountTypeBreakdown
                    return (
                      <div className="flex flex-col gap-1">
                        <span className="font-medium">{item.label}</span>
                        <span className="font-mono">
                          {formatCurrency(Math.abs(value as number), currencyCode)}
                        </span>
                        <span className="text-xs text-muted-foreground">
                          {t('page.reports.accounts_count', { count: item.count })}
                        </span>
                      </div>
                    )
                  }}
                  hideLabel
                />
              }
            />
            <Bar dataKey="balance" radius={[0, 4, 4, 0]}>
              {data.map((entry) => (
                <Cell key={entry.type} fill={entry.color} />
              ))}
            </Bar>
          </BarChart>
        </ChartContainer>

        <div className="flex justify-between border-t pt-3">
          <div>
            <p className="text-xs text-muted-foreground">{t('page.reports.assets')}</p>
            <p className="text-sm font-semibold text-green-600">
              {formatFullCurrency(totalAssets, currencyCode)}
            </p>
          </div>
          <div className="text-right">
            <p className="text-xs text-muted-foreground">{t('page.reports.liabilities')}</p>
            <p className="text-sm font-semibold text-red-600">
              {formatFullCurrency(totalLiabilities, currencyCode)}
            </p>
          </div>
        </div>
      </CardContent>
    </Card>
  )
}
