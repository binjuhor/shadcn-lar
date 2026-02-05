import { useState, useEffect, useRef } from 'react'
import { router } from '@inertiajs/react'
import { format } from 'date-fns'
import { useTranslation } from 'react-i18next'
import { AuthenticatedLayout } from '@/layouts'
import { Main } from '@/components/layout/main'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { DatePicker } from '@/components/ui/date-picker'
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select'
import { PageProps } from '@/types'
import { getColumns } from './components/invoices-columns'
import { InvoicesDialogs } from './components/invoices-dialogs'
import { InvoicesPrimaryButtons } from './components/invoices-primary-buttons'
import { InvoicesTable } from './components/invoices-table'
import { ServerPagination } from './components/server-pagination'
import InvoicesProvider from './context/invoices-context'
import { Invoice } from './data/schema'
import { IconFilter, IconSearch, IconX } from '@tabler/icons-react'

interface PaginationMeta {
  current_page: number
  last_page: number
  per_page: number
  total: number
  from: number | null
  to: number | null
}

interface PaginatedInvoices extends PaginationMeta {
  data: Invoice[]
}

interface Filters {
  search?: string
  status?: string
  date_from?: string
  date_to?: string
  amount_from?: string
  amount_to?: string
  per_page?: number
}

interface Totals {
  count: number
  total_amount: number
  paid_amount: number
  pending_amount: number
}

interface InvoicesPageProps extends PageProps {
  invoices: PaginatedInvoices
  filters: Filters
  totals: Totals
  currency: string
}

const defaultPagination: PaginatedInvoices = {
  data: [],
  current_page: 1,
  last_page: 1,
  per_page: 25,
  total: 0,
  from: null,
  to: null,
}

const defaultFilters: Filters = {}

const defaultTotals: Totals = {
  count: 0,
  total_amount: 0,
  paid_amount: 0,
  pending_amount: 0,
}

function formatMoney(amount: number, currency: string): string {
  const locale = currency === 'VND' ? 'vi-VN' : 'en-US'
  return new Intl.NumberFormat(locale, {
    style: 'currency',
    currency: currency,
  }).format(amount)
}

export default function Invoices({
  invoices = defaultPagination,
  filters = defaultFilters,
  totals = defaultTotals,
  currency = 'USD',
}: InvoicesPageProps) {
  const { t } = useTranslation()
  const [showFilters, setShowFilters] = useState(false)
  const [searchQuery, setSearchQuery] = useState(filters.search || '')
  const searchTimeoutRef = useRef<NodeJS.Timeout | null>(null)

  const safeInvoices: PaginatedInvoices = {
    data: invoices?.data ?? [],
    current_page: invoices?.current_page ?? 1,
    last_page: invoices?.last_page ?? 1,
    per_page: invoices?.per_page ?? 25,
    total: invoices?.total ?? 0,
    from: invoices?.from ?? null,
    to: invoices?.to ?? null,
  }

  const paginationMeta: PaginationMeta = {
    current_page: safeInvoices.current_page,
    last_page: safeInvoices.last_page,
    per_page: safeInvoices.per_page,
    total: safeInvoices.total,
    from: safeInvoices.from,
    to: safeInvoices.to,
  }

  // Debounced search effect
  useEffect(() => {
    if (searchTimeoutRef.current) {
      clearTimeout(searchTimeoutRef.current)
    }

    // Skip if search matches current filter (initial load)
    if (searchQuery === (filters.search || '')) return

    searchTimeoutRef.current = setTimeout(() => {
      router.get(
        route('dashboard.invoices.index'),
        {
          ...filters,
          search: searchQuery || undefined,
          page: 1,
        },
        {
          preserveState: true,
          preserveScroll: true,
        }
      )
    }, 500)

    return () => {
      if (searchTimeoutRef.current) {
        clearTimeout(searchTimeoutRef.current)
      }
    }
  }, [searchQuery])

  const handleFilterChange = (key: string, value: string) => {
    router.get(
      route('dashboard.invoices.index'),
      {
        ...filters,
        [key]: value === 'all' ? undefined : value,
        page: 1,
      },
      {
        preserveState: true,
        preserveScroll: true,
      }
    )
  }

  const clearFilters = () => {
    router.get(
      route('dashboard.invoices.index'),
      { search: filters.search },
      { preserveState: true, preserveScroll: true }
    )
  }

  const hasActiveFilters = filters.status || filters.date_from || filters.date_to || filters.amount_from || filters.amount_to
  const hasAnyFilters = hasActiveFilters || filters.search

  return (
    <InvoicesProvider>
      <AuthenticatedLayout title={t('page.invoices.title')}>
        <Main>
          <div className="mb-4 flex items-center justify-between space-y-2 flex-wrap">
            <div>
              <h2 className="text-2xl font-bold tracking-tight">{t('page.invoices.title')}</h2>
              <p className="text-muted-foreground">
                {t('page.invoices.description')}
              </p>
            </div>
            <div className="flex gap-2">
              <Button
                variant="outline"
                onClick={() => setShowFilters(!showFilters)}
              >
                <IconFilter className="mr-2 h-4 w-4" />
                {showFilters ? t('filter.hide') : t('filter.show')} {t('filter.filters')}
              </Button>
              <InvoicesPrimaryButtons />
            </div>
          </div>

          {/* Search */}
          <div className="relative mb-4">
            <IconSearch className="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-muted-foreground" />
            <Input
              placeholder={t('page.invoices.search_placeholder')}
              value={searchQuery}
              onChange={(e) => setSearchQuery(e.target.value)}
              className="pl-10"
            />
          </div>

          {/* Filters */}
          {showFilters && (
            <div className="flex gap-4 flex-wrap mb-6 p-4 border rounded-lg bg-muted/50">
              <div className="flex flex-col gap-1.5">
                <label className="text-sm font-medium">{t('table.status')}</label>
                <Select
                  value={filters.status || 'all'}
                  onValueChange={(value) => handleFilterChange('status', value)}
                >
                  <SelectTrigger className="w-36">
                    <SelectValue placeholder={t('page.invoices.status.all')} />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem value="all">{t('page.invoices.status.all')}</SelectItem>
                    <SelectItem value="draft">{t('page.invoices.status.draft')}</SelectItem>
                    <SelectItem value="sent">{t('page.invoices.status.sent')}</SelectItem>
                    <SelectItem value="paid">{t('page.invoices.status.paid')}</SelectItem>
                    <SelectItem value="overdue">{t('page.invoices.status.overdue')}</SelectItem>
                    <SelectItem value="cancelled">{t('page.invoices.status.cancelled')}</SelectItem>
                  </SelectContent>
                </Select>
              </div>

              <div className="flex flex-col gap-1.5">
                <label className="text-sm font-medium">{t('filter.from_date')}</label>
                <DatePicker
                  value={filters.date_from || undefined}
                  onChange={(date) => handleFilterChange('date_from', date ? format(date, 'yyyy-MM-dd') : 'all')}
                  placeholder={t('filter.select_date')}
                  dateFormat="dd/MM/yyyy"
                  className="w-40"
                />
              </div>

              <div className="flex flex-col gap-1.5">
                <label className="text-sm font-medium">{t('filter.to_date')}</label>
                <DatePicker
                  value={filters.date_to || undefined}
                  onChange={(date) => handleFilterChange('date_to', date ? format(date, 'yyyy-MM-dd') : 'all')}
                  placeholder={t('filter.select_date')}
                  dateFormat="dd/MM/yyyy"
                  className="w-40"
                />
              </div>

              <div className="flex flex-col gap-1.5">
                <label className="text-sm font-medium">{t('filter.amount_from')}</label>
                <Input
                  type="number"
                  min="0"
                  placeholder={t('filter.min_amount')}
                  value={filters.amount_from || ''}
                  onChange={(e) => handleFilterChange('amount_from', e.target.value || 'all')}
                  className="w-36"
                />
              </div>

              <div className="flex flex-col gap-1.5">
                <label className="text-sm font-medium">{t('filter.amount_to')}</label>
                <Input
                  type="number"
                  min="0"
                  placeholder={t('filter.max_amount')}
                  value={filters.amount_to || ''}
                  onChange={(e) => handleFilterChange('amount_to', e.target.value || 'all')}
                  className="w-36"
                />
              </div>

              {hasActiveFilters && (
                <div className="flex flex-col gap-1.5 justify-end">
                  <Button variant="ghost" size="sm" onClick={clearFilters}>
                    <IconX className="mr-2 h-4 w-4" />
                    {t('filter.clear')}
                  </Button>
                </div>
              )}
            </div>
          )}

          {/* Totals Summary - show when filters are active */}
          {hasAnyFilters && (
            <div className="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
              <div className="p-4 border rounded-lg bg-background">
                <p className="text-sm text-muted-foreground">{t('page.invoices.total_invoices')}</p>
                <p className="text-2xl font-bold">{totals.count.toLocaleString()}</p>
              </div>
              <div className="p-4 border rounded-lg bg-background">
                <p className="text-sm text-muted-foreground">{t('page.invoices.total_amount')}</p>
                <p className="text-2xl font-bold">{formatMoney(totals.total_amount, currency)}</p>
              </div>
              <div className="p-4 border rounded-lg bg-background">
                <p className="text-sm text-muted-foreground">{t('page.invoices.paid')}</p>
                <p className="text-2xl font-bold text-green-600">{formatMoney(totals.paid_amount, currency)}</p>
              </div>
              <div className="p-4 border rounded-lg bg-background">
                <p className="text-sm text-muted-foreground">{t('page.invoices.pending')}</p>
                <p className="text-2xl font-bold text-amber-600">{formatMoney(totals.pending_amount, currency)}</p>
              </div>
            </div>
          )}

          <div className="space-y-4">
            <InvoicesTable data={safeInvoices.data} columns={getColumns(t)} />
            <ServerPagination meta={paginationMeta} filters={filters} />
          </div>
        </Main>

        <InvoicesDialogs />
      </AuthenticatedLayout>
    </InvoicesProvider>
  )
}
