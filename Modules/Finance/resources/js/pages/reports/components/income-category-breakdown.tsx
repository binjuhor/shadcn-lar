import { Pie, PieChart, Cell, Label } from 'recharts'
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
import type { CategoryBreakdownItem } from '@modules/Finance/types/finance'

interface IncomeCategoryBreakdownProps {
  data: CategoryBreakdownItem[]
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

export function IncomeCategoryBreakdown({ data, currencyCode }: IncomeCategoryBreakdownProps) {
  const total = data.reduce((sum, item) => sum + item.amount, 0)

  const chartConfig = data.reduce((config, item) => {
    config[item.name] = {
      label: item.name,
      color: item.color,
    }
    return config
  }, {} as ChartConfig)

  if (data.length === 0) {
    return (
      <Card className="h-full">
        <CardHeader className="pb-2">
          <CardTitle className="text-base">Income by Category</CardTitle>
          <CardDescription>No income data for this period</CardDescription>
        </CardHeader>
        <CardContent className="flex h-[200px] items-center justify-center">
          <p className="text-muted-foreground">No data available</p>
        </CardContent>
      </Card>
    )
  }

  return (
    <Card className="h-full">
      <CardHeader className="pb-2">
        <CardTitle className="text-base">Income by Category</CardTitle>
        <CardDescription>
          Top income sources
        </CardDescription>
      </CardHeader>
      <CardContent className="pb-4">
        <ChartContainer config={chartConfig} className="mx-auto aspect-square h-[220px]">
          <PieChart>
            <ChartTooltip
              content={
                <ChartTooltipContent
                  formatter={(value, name) => (
                    <div className="flex flex-col gap-0.5">
                      <span className="font-medium">{name}</span>
                      <span className="font-mono">
                        {formatCurrency(value as number, currencyCode)}
                      </span>
                    </div>
                  )}
                  hideLabel
                />
              }
            />
            <Pie
              data={data}
              dataKey="amount"
              nameKey="name"
              innerRadius={50}
              outerRadius={85}
              paddingAngle={2}
            >
              {data.map((entry) => (
                <Cell key={entry.id} fill={entry.color} />
              ))}
              <Label
                content={({ viewBox }) => {
                  if (viewBox && 'cx' in viewBox && 'cy' in viewBox) {
                    return (
                      <text
                        x={viewBox.cx}
                        y={viewBox.cy}
                        textAnchor="middle"
                        dominantBaseline="middle"
                      >
                        <tspan
                          x={viewBox.cx}
                          y={viewBox.cy}
                          className="fill-foreground text-lg font-bold"
                        >
                          {formatCurrency(total, currencyCode)}
                        </tspan>
                        <tspan
                          x={viewBox.cx}
                          y={(viewBox.cy || 0) + 16}
                          className="fill-muted-foreground text-xs"
                        >
                          Total
                        </tspan>
                      </text>
                    )
                  }
                }}
              />
            </Pie>
          </PieChart>
        </ChartContainer>

        <div className="grid grid-cols-2 gap-x-4 gap-y-1">
          {data.slice(0, 6).map((item) => (
            <div key={item.id} className="flex items-center gap-2 text-xs">
              <div
                className="h-2 w-2 shrink-0 rounded-full"
                style={{ backgroundColor: item.color }}
              />
              <span className="truncate text-muted-foreground">{item.name}</span>
              <span className="ml-auto font-medium">{item.percentage}%</span>
            </div>
          ))}
        </div>
      </CardContent>
    </Card>
  )
}
