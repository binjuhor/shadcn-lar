import { Link } from '@inertiajs/react'
import { AuthenticatedLayout } from '@/layouts'
import { Main } from '@/components/layout/main'
import { Card } from '@/components/ui/card'
import {
  ArrowLeft,
  FileText,
  DollarSign,
  Clock,
  TrendingUp,
  TrendingDown,
} from 'lucide-react'
import { DateRangePicker } from './components/date-range-picker'
import { IncomeTrend } from './components/income-trend'
import { StatusBreakdown } from './components/status-breakdown'
import { ClientBreakdown } from './components/client-breakdown'

type DateRangePreset = '30d' | '6m' | '12m' | 'ytd' | 'custom'

interface ReportFilters {
  range: DateRangePreset
  startDate: string
  endDate: string
}

interface IncomeTrendPoint {
  period: string
  total: number
  paid: number
  pending: number
  count: number
}

interface StatusBreakdownItem {
  status: string
  label: string
  color: string
  amount: number
  count: number
  percentage: number
}

interface ClientBreakdownItem {
  name: string
  color: string
  amount: number
  count: number
  percentage: number
}

interface ReportSummary {
  totalInvoiced: number
  totalPaid: number
  totalPending: number
  invoiceCount: number
  previousPeriodChange: number
}

interface Props {
  filters: ReportFilters
  currency: string
  incomeTrend: IncomeTrendPoint[]
  statusBreakdown: StatusBreakdownItem[]
  clientBreakdown: ClientBreakdownItem[]
  summary: ReportSummary
}

function formatMoney(amount: number, currency: string): string {
  return new Intl.NumberFormat('vi-VN', {
    style: 'currency',
    currency,
  }).format(amount)
}

function SummaryCards({ summary, currency }: { summary: ReportSummary; currency: string }) {
  const isPositiveChange = summary.previousPeriodChange >= 0

  return (
    <div className="grid gap-3 grid-cols-2 lg:grid-cols-4">
      <Card className="p-3">
        <div className="flex items-center justify-between">
          <span className="text-xs text-muted-foreground">Total Invoiced</span>
          <FileText className="h-3 w-3 text-blue-600" />
        </div>
        <div className="text-lg font-bold mt-1">
          {formatMoney(summary.totalInvoiced, currency)}
        </div>
        <div className="text-xs text-muted-foreground mt-1">
          {summary.invoiceCount} invoices
        </div>
      </Card>

      <Card className="p-3">
        <div className="flex items-center justify-between">
          <span className="text-xs text-muted-foreground">Paid</span>
          <DollarSign className="h-3 w-3 text-green-600" />
        </div>
        <div className="text-lg font-bold text-green-600 mt-1">
          {formatMoney(summary.totalPaid, currency)}
        </div>
      </Card>

      <Card className="p-3">
        <div className="flex items-center justify-between">
          <span className="text-xs text-muted-foreground">Pending</span>
          <Clock className="h-3 w-3 text-yellow-600" />
        </div>
        <div className="text-lg font-bold text-yellow-600 mt-1">
          {formatMoney(summary.totalPending, currency)}
        </div>
      </Card>

      <Card className="p-3">
        <div className="flex items-center justify-between">
          <span className="text-xs text-muted-foreground">vs Previous</span>
          {isPositiveChange ? (
            <TrendingUp className="h-3 w-3 text-green-600" />
          ) : (
            <TrendingDown className="h-3 w-3 text-red-600" />
          )}
        </div>
        <div className={`text-lg font-bold mt-1 ${isPositiveChange ? 'text-green-600' : 'text-red-600'}`}>
          {isPositiveChange ? '+' : ''}{summary.previousPeriodChange}%
        </div>
      </Card>
    </div>
  )
}

export default function InvoiceReports({
  filters,
  currency,
  incomeTrend,
  statusBreakdown,
  clientBreakdown,
  summary,
}: Props) {
  return (
    <AuthenticatedLayout title="Invoice Reports">
      <Main>
        <div className="mb-4">
          <Link
            href={route('dashboard.invoices.index')}
            className="inline-flex items-center text-sm text-muted-foreground hover:text-foreground"
          >
            <ArrowLeft className="mr-2 h-4 w-4" />
            Back to Invoices
          </Link>
        </div>

        <div className="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
          <div>
            <h1 className="text-2xl font-bold tracking-tight">Invoice Reports</h1>
            <p className="text-muted-foreground">
              Analyze your invoice income and client breakdown
            </p>
          </div>
          <DateRangePicker filters={filters} />
        </div>

        <div className="space-y-4">
          <SummaryCards summary={summary} currency={currency} />

          <IncomeTrend data={incomeTrend} currency={currency} />

          <div className="grid gap-4 md:grid-cols-2">
            <StatusBreakdown data={statusBreakdown} currency={currency} />
            <ClientBreakdown data={clientBreakdown} currency={currency} />
          </div>
        </div>
      </Main>
    </AuthenticatedLayout>
  )
}
