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

export function AccountDistribution({ data, currencyCode }: AccountDistributionProps) {
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
      <Card>
        <CardHeader>
          <CardTitle>Account Distribution</CardTitle>
          <CardDescription>No active accounts found</CardDescription>
        </CardHeader>
        <CardContent className="flex h-[300px] items-center justify-center">
          <p className="text-muted-foreground">No data available</p>
        </CardContent>
      </Card>
    )
  }

  return (
    <Card>
      <CardHeader>
        <CardTitle>Account Distribution</CardTitle>
        <CardDescription>
          Balance breakdown by account type
        </CardDescription>
      </CardHeader>
      <CardContent>
        <ChartContainer config={chartConfig} className="aspect-auto h-[300px] w-full">
          <BarChart
            accessibilityLayer
            data={data}
            layout="vertical"
            margin={{ left: 0, right: 12, top: 12, bottom: 12 }}
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
              width={100}
              tickMargin={8}
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
                          {item.count} account{item.count !== 1 ? 's' : ''}
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

        <div className="mt-4 flex justify-between border-t pt-4">
          <div>
            <p className="text-sm text-muted-foreground">Total Assets</p>
            <p className="text-lg font-semibold text-green-600">
              {formatCurrency(totalAssets, currencyCode)}
            </p>
          </div>
          <div className="text-right">
            <p className="text-sm text-muted-foreground">Total Liabilities</p>
            <p className="text-lg font-semibold text-red-600">
              {formatCurrency(totalLiabilities, currencyCode)}
            </p>
          </div>
        </div>
      </CardContent>
    </Card>
  )
}
