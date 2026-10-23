interface AdvisorChartBlock {
  type: 'bar' | 'line'
  title: string
  currency: string
  data: Array<Record<string, string | number>>
  series: Record<string, string>
}

interface AdvisorChartProps {
  chart: AdvisorChartBlock
}

const SERIES_COLORS = ['#2563eb', '#dc2626', '#16a34a', '#9333ea', '#d97706']
const WIDTH = 720
const HEIGHT = 320
const PADDING = { top: 20, right: 24, bottom: 70, left: 86 }

function formatCurrency(value: number, code: string): string {
  return new Intl.NumberFormat('vi-VN', {
    style: 'currency',
    currency: code,
    notation: 'compact',
    maximumFractionDigits: 1,
  }).format(value)
}

function formatTick(value: string | number, maxLength = 12): string {
  const label = String(value)

  if (label.length <= maxLength) {
    return label
  }

  return `${label.slice(0, maxLength - 1)}...`
}

function numericValue(value: string | number | undefined): number {
  return typeof value === 'number' && Number.isFinite(value) ? value : 0
}

function buildLinePath(points: Array<{ x: number; y: number }>): string {
  return points
    .map((point, index) => `${index === 0 ? 'M' : 'L'} ${point.x} ${point.y}`)
    .join(' ')
}

export function AdvisorChart({ chart }: AdvisorChartProps) {
  const seriesEntries = Object.entries(chart.series)

  if (!chart.data.length || !seriesEntries.length) {
    return null
  }

  const plotWidth = WIDTH - PADDING.left - PADDING.right
  const plotHeight = HEIGHT - PADDING.top - PADDING.bottom
  const maxValue = Math.max(
    1,
    ...chart.data.flatMap((row) =>
      seriesEntries.map(([key]) => numericValue(row[key]))
    )
  )
  const yTicks = [0, 0.25, 0.5, 0.75, 1].map((ratio) => maxValue * ratio)
  const rowWidth = plotWidth / chart.data.length
  const barGap = 4
  const groupPadding = 12
  const barWidth = Math.max(
    5,
    (rowWidth - groupPadding * 2 - barGap * (seriesEntries.length - 1)) /
      seriesEntries.length
  )

  const xForIndex = (index: number) => PADDING.left + rowWidth * index + rowWidth / 2
  const yForValue = (value: number) =>
    PADDING.top + plotHeight - (Math.max(0, value) / maxValue) * plotHeight

  return (
    <div className="mt-3 w-full rounded-lg border bg-background p-3 text-foreground">
      <div className="mb-2 text-sm font-semibold">{chart.title}</div>
      <svg
        viewBox={`0 0 ${WIDTH} ${HEIGHT}`}
        role="img"
        aria-label={chart.title}
        className="h-64 w-full overflow-visible"
      >
        <title>{chart.title}</title>
        <rect width={WIDTH} height={HEIGHT} fill="transparent" />

        {yTicks.map((tick) => {
          const y = yForValue(tick)

          return (
            <g key={tick}>
              <line
                x1={PADDING.left}
                x2={WIDTH - PADDING.right}
                y1={y}
                y2={y}
                stroke="currentColor"
                strokeOpacity={0.12}
              />
              <text
                x={PADDING.left - 10}
                y={y + 4}
                textAnchor="end"
                className="fill-muted-foreground text-[11px]"
              >
                {formatCurrency(tick, chart.currency)}
              </text>
            </g>
          )
        })}

        <line
          x1={PADDING.left}
          x2={WIDTH - PADDING.right}
          y1={PADDING.top + plotHeight}
          y2={PADDING.top + plotHeight}
          stroke="currentColor"
          strokeOpacity={0.24}
        />

        {chart.type === 'line'
          ? seriesEntries.map(([key, label], seriesIndex) => {
              const points = chart.data.map((row, rowIndex) => ({
                x: xForIndex(rowIndex),
                y: yForValue(numericValue(row[key])),
              }))

              return (
                <g key={key}>
                  <path
                    d={buildLinePath(points)}
                    fill="none"
                    stroke={SERIES_COLORS[seriesIndex % SERIES_COLORS.length]}
                    strokeWidth={3}
                    strokeLinejoin="round"
                    strokeLinecap="round"
                  />
                  {points.map((point, pointIndex) => (
                    <circle
                      key={`${key}-${pointIndex}`}
                      cx={point.x}
                      cy={point.y}
                      r={3}
                      fill={SERIES_COLORS[seriesIndex % SERIES_COLORS.length]}
                    >
                      <title>
                        {`${chart.data[pointIndex].label}: ${label} ${formatCurrency(
                          numericValue(chart.data[pointIndex][key]),
                          chart.currency
                        )}`}
                      </title>
                    </circle>
                  ))}
                </g>
              )
            })
          : chart.data.flatMap((row, rowIndex) =>
              seriesEntries.map(([key, label], seriesIndex) => {
                const value = numericValue(row[key])
                const x =
                  PADDING.left +
                  rowWidth * rowIndex +
                  groupPadding +
                  seriesIndex * (barWidth + barGap)
                const y = yForValue(value)
                const height = PADDING.top + plotHeight - y

                return (
                  <rect
                    key={`${rowIndex}-${key}`}
                    x={x}
                    y={y}
                    width={barWidth}
                    height={height}
                    rx={4}
                    fill={SERIES_COLORS[seriesIndex % SERIES_COLORS.length]}
                  >
                    <title>
                      {`${row.label}: ${label} ${formatCurrency(value, chart.currency)}`}
                    </title>
                  </rect>
                )
              })
            )}

        {chart.data.map((row, index) => (
          <text
            key={`${row.label}-${index}`}
            x={xForIndex(index)}
            y={HEIGHT - 34}
            textAnchor="end"
            transform={`rotate(-35 ${xForIndex(index)} ${HEIGHT - 34})`}
            className="fill-muted-foreground text-[11px]"
          >
            {formatTick(row.label)}
          </text>
        ))}

        <g transform={`translate(${PADDING.left}, ${HEIGHT - 16})`}>
          {seriesEntries.map(([key, label], index) => (
            <g key={key} transform={`translate(${index * 140}, 0)`}>
              <rect
                width={10}
                height={10}
                y={-9}
                rx={2}
                fill={SERIES_COLORS[index % SERIES_COLORS.length]}
              />
              <text x={16} y={0} className="fill-muted-foreground text-[11px]">
                {label}
              </text>
            </g>
          ))}
        </g>
      </svg>
    </div>
  )
}

export type { AdvisorChartBlock }
