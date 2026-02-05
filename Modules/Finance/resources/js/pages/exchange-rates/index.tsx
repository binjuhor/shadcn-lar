import { useState } from 'react'
import { router, Link } from '@inertiajs/react'
import { useTranslation } from 'react-i18next'
import { AuthenticatedLayout } from '@/layouts'
import { Main } from '@/components/layout/main'
import { Button } from '@/components/ui/button'
import {
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from '@/components/ui/card'
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table'
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select'
import {
  AlertDialog,
  AlertDialogAction,
  AlertDialogCancel,
  AlertDialogContent,
  AlertDialogDescription,
  AlertDialogFooter,
  AlertDialogHeader,
  AlertDialogTitle,
} from '@/components/ui/alert-dialog'
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu'
import { Badge } from '@/components/ui/badge'
import {
  Plus,
  RefreshCw,
  MoreHorizontal,
  Pencil,
  Trash2,
  ArrowRightLeft,
  TrendingUp,
  ChevronLeft,
  ChevronRight,
  ChevronsLeft,
  ChevronsRight,
} from 'lucide-react'
import type {
  ExchangeRate,
  Currency,
  PaginatedData,
} from '@modules/Finance/types/finance'

interface Props {
  rates: PaginatedData<ExchangeRate>
  currentRates: ExchangeRate[]
  currencies: Currency[]
  accountCurrencies: string[]
  filters: {
    base_currency?: string
    target_currency?: string
    source?: string
  }
  providers: string[]
}

function formatRate(rate: number): string {
  if (rate >= 1000) {
    return rate.toLocaleString('vi-VN', {
      minimumFractionDigits: 0,
      maximumFractionDigits: 2,
    })
  }
  return rate.toLocaleString('vi-VN', {
    minimumFractionDigits: 4,
    maximumFractionDigits: 10,
  })
}

function formatDateTime(dateStr: string): string {
  return new Date(dateStr).toLocaleString('vi-VN', {
    year: 'numeric',
    month: '2-digit',
    day: '2-digit',
    hour: '2-digit',
    minute: '2-digit',
  })
}

export default function ExchangeRatesIndex({
  rates,
  currentRates,
  currencies,
  filters,
  providers,
}: Props) {
  const { t } = useTranslation()
  const [showDeleteDialog, setShowDeleteDialog] = useState(false)
  const [selectedRate, setSelectedRate] = useState<ExchangeRate | null>(null)
  const [isFetching, setIsFetching] = useState(false)
  const [filterBase, setFilterBase] = useState(filters.base_currency || '__all__')
  const [filterTarget, setFilterTarget] = useState(filters.target_currency || '__all__')
  const [filterSource, setFilterSource] = useState(filters.source || '__all__')

  const handleFilter = () => {
    router.get(
      route('dashboard.finance.exchange-rates.index'),
      {
        base_currency: filterBase === '__all__' ? undefined : filterBase,
        target_currency: filterTarget === '__all__' ? undefined : filterTarget,
        source: filterSource === '__all__' ? undefined : filterSource,
      },
      { preserveState: true }
    )
  }

  const handleFetchRates = (provider: string) => {
    setIsFetching(true)
    router.post(
      route('dashboard.finance.exchange-rates.fetch'),
      { provider },
      {
        preserveState: true,
        preserveScroll: true,
        onFinish: () => setIsFetching(false),
      }
    )
  }

  const handleDelete = (rate: ExchangeRate) => {
    setSelectedRate(rate)
    setShowDeleteDialog(true)
  }

  const confirmDelete = () => {
    if (selectedRate) {
      router.delete(
        route('dashboard.finance.exchange-rates.destroy', selectedRate.id),
        {
          onSuccess: () => {
            setShowDeleteDialog(false)
            setSelectedRate(null)
          },
        }
      )
    }
  }

  return (
    <AuthenticatedLayout title={t('page.exchange_rates.title')}>
      <Main>
        <div className="mb-4 md:flex items-center justify-between">
          <div className="mb-4">
            <h1 className="text-2xl font-bold tracking-tight">{t('page.exchange_rates.title')}</h1>
            <p className="text-muted-foreground">
              {t('page.exchange_rates.description')}
            </p>
          </div>
          <div className="flex gap-2">
            <DropdownMenu>
              <DropdownMenuTrigger asChild>
                <Button variant="outline" disabled={isFetching}>
                  <RefreshCw
                    className={`mr-2 h-4 w-4 ${isFetching ? 'animate-spin' : ''}`}
                  />
                  {t('page.exchange_rates.fetch_rates')}
                </Button>
              </DropdownMenuTrigger>
              <DropdownMenuContent align="end">
                <DropdownMenuItem onClick={() => handleFetchRates('vietcombank')}>
                  {t('page.exchange_rates.provider.vietcombank')}
                </DropdownMenuItem>
                <DropdownMenuItem onClick={() => handleFetchRates('payoneer')}>
                  {t('page.exchange_rates.provider.payoneer')}
                </DropdownMenuItem>
                <DropdownMenuItem onClick={() => handleFetchRates('exchangerate_api')}>
                  {t('page.exchange_rates.provider.exchangerate_api')}
                </DropdownMenuItem>
                <DropdownMenuItem onClick={() => handleFetchRates('open_exchange_rates')}>
                  {t('page.exchange_rates.provider.open_exchange_rates')}
                </DropdownMenuItem>
                <DropdownMenuItem onClick={() => handleFetchRates('all')}>
                  {t('page.exchange_rates.provider.all')}
                </DropdownMenuItem>
              </DropdownMenuContent>
            </DropdownMenu>
            <Button asChild>
              <Link href={route('dashboard.finance.exchange-rates.create')}>
                <Plus className="mr-2 h-4 w-4" />
                {t('page.exchange_rates.add_rate')}
              </Link>
            </Button>
          </div>
        </div>

        {/* Current Rates Summary */}
        {currentRates.length > 0 && (
          <Card className="mb-6">
            <CardHeader>
              <CardTitle className="flex items-center gap-2">
                <TrendingUp className="h-5 w-5" />
                {t('page.exchange_rates.latest_rates')}
              </CardTitle>
              <CardDescription>
                {t('page.exchange_rates.latest_rates_description')}
              </CardDescription>
            </CardHeader>
            <CardContent>
              <div className="grid sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4">
                {currentRates.map((rate) => (
                  <div
                    key={rate.id}
                    className="p-3 border rounded-lg bg-muted/50"
                  >
                    <div className="flex items-center gap-2 text-sm font-medium mb-1">
                      <span>{rate.base_currency}</span>
                      <ArrowRightLeft className="h-3 w-3 text-muted-foreground" />
                      <span>{rate.target_currency}</span>
                    </div>
                    <div className="text-lg font-bold">{formatRate(rate.rate)}</div>
                    <div className="text-xs text-muted-foreground">
                      <Badge variant="outline" className="text-xs">
                        {rate.source}
                      </Badge>
                    </div>
                  </div>
                ))}
              </div>
            </CardContent>
          </Card>
        )}

        {/* Filters */}
        <Card className="mb-6">
          <CardContent className="pt-6">
            <div className="flex flex-wrap gap-4 items-end">
              <div className="flex-1 min-w-[150px]">
                <label className="text-sm font-medium mb-2 block">
                  {t('page.exchange_rates.filter.base_currency')}
                </label>
                <Select value={filterBase} onValueChange={setFilterBase}>
                  <SelectTrigger>
                    <SelectValue placeholder={t('common.all')} />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem value="__all__">{t('common.all')}</SelectItem>
                    {currencies.map((c) => (
                      <SelectItem key={c.code} value={c.code}>
                        {c.code} - {c.name}
                      </SelectItem>
                    ))}
                  </SelectContent>
                </Select>
              </div>
              <div className="flex-1 min-w-[150px]">
                <label className="text-sm font-medium mb-2 block">
                  {t('page.exchange_rates.filter.target_currency')}
                </label>
                <Select value={filterTarget} onValueChange={setFilterTarget}>
                  <SelectTrigger>
                    <SelectValue placeholder={t('common.all')} />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem value="__all__">{t('common.all')}</SelectItem>
                    {currencies.map((c) => (
                      <SelectItem key={c.code} value={c.code}>
                        {c.code} - {c.name}
                      </SelectItem>
                    ))}
                  </SelectContent>
                </Select>
              </div>
              <div className="flex-1 min-w-[150px]">
                <label className="text-sm font-medium mb-2 block">{t('page.exchange_rates.filter.source')}</label>
                <Select value={filterSource} onValueChange={setFilterSource}>
                  <SelectTrigger>
                    <SelectValue placeholder={t('common.all')} />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem value="__all__">{t('common.all')}</SelectItem>
                    {providers.map((p) => (
                      <SelectItem key={p} value={p}>
                        {p}
                      </SelectItem>
                    ))}
                  </SelectContent>
                </Select>
              </div>
              <Button onClick={handleFilter}>{t('action.apply_filters')}</Button>
            </div>
          </CardContent>
        </Card>

        {/* Rates Table */}
        <Card>
          <CardHeader>
            <CardTitle>{t('page.exchange_rates.history_title')}</CardTitle>
            <CardDescription>
              {t('page.exchange_rates.showing_records', { from: rates.data.length, total: rates.total })}
            </CardDescription>
          </CardHeader>
          <CardContent>
            <Table>
              <TableHeader>
                <TableRow>
                  <TableHead>{t('table.base')}</TableHead>
                  <TableHead>{t('table.target')}</TableHead>
                  <TableHead className="text-right">{t('table.rate')}</TableHead>
                  <TableHead className="text-right">{t('table.bid')}</TableHead>
                  <TableHead className="text-right">{t('table.ask')}</TableHead>
                  <TableHead>{t('table.source')}</TableHead>
                  <TableHead>{t('table.date')}</TableHead>
                  <TableHead className="w-[50px]"></TableHead>
                </TableRow>
              </TableHeader>
              <TableBody>
                {rates.data.length === 0 ? (
                  <TableRow>
                    <TableCell colSpan={8} className="text-center py-8">
                      <ArrowRightLeft className="h-12 w-12 text-muted-foreground mx-auto mb-4" />
                      <p className="text-muted-foreground">
                        {t('page.exchange_rates.no_rates_found')}
                      </p>
                    </TableCell>
                  </TableRow>
                ) : (
                  rates.data.map((rate) => (
                    <TableRow key={rate.id}>
                      <TableCell className="font-medium">
                        {rate.base_currency}
                      </TableCell>
                      <TableCell>{rate.target_currency}</TableCell>
                      <TableCell className="text-right font-mono">
                        {formatRate(rate.rate)}
                      </TableCell>
                      <TableCell className="text-right font-mono text-muted-foreground">
                        {rate.bid_rate ? formatRate(rate.bid_rate) : '-'}
                      </TableCell>
                      <TableCell className="text-right font-mono text-muted-foreground">
                        {rate.ask_rate ? formatRate(rate.ask_rate) : '-'}
                      </TableCell>
                      <TableCell>
                        <Badge variant="outline">{rate.source}</Badge>
                      </TableCell>
                      <TableCell className="text-muted-foreground text-sm">
                        {formatDateTime(rate.rate_date)}
                      </TableCell>
                      <TableCell>
                        <DropdownMenu>
                          <DropdownMenuTrigger asChild>
                            <Button variant="ghost" size="icon" className="h-8 w-8">
                              <MoreHorizontal className="h-4 w-4" />
                            </Button>
                          </DropdownMenuTrigger>
                          <DropdownMenuContent align="end">
                            <DropdownMenuItem asChild>
                              <Link
                                href={route(
                                  'dashboard.finance.exchange-rates.edit',
                                  rate.id
                                )}
                              >
                                <Pencil className="mr-2 h-4 w-4" />
                                {t('action.edit')}
                              </Link>
                            </DropdownMenuItem>
                            <DropdownMenuItem
                              onClick={() => handleDelete(rate)}
                              className="text-red-600"
                            >
                              <Trash2 className="mr-2 h-4 w-4" />
                              {t('action.delete')}
                            </DropdownMenuItem>
                          </DropdownMenuContent>
                        </DropdownMenu>
                      </TableCell>
                    </TableRow>
                  ))
                )}
              </TableBody>
            </Table>

            {/* Pagination */}
            {rates.last_page > 1 && (
              <div className="flex items-center justify-between mt-4 pt-4 border-t">
                <p className="text-sm text-muted-foreground">
                  {t('pagination.showing', { from: rates.from, to: rates.to, total: rates.total })}
                </p>
                <div className="flex items-center gap-1">
                  {/* First & Previous */}
                  <Button
                    variant="outline"
                    size="icon"
                    className="h-8 w-8"
                    disabled={rates.current_page === 1}
                    onClick={() => router.get(route('dashboard.finance.exchange-rates.index'), { ...filters, page: 1 }, { preserveState: true })}
                  >
                    <ChevronsLeft className="h-4 w-4" />
                  </Button>
                  <Button
                    variant="outline"
                    size="icon"
                    className="h-8 w-8"
                    disabled={rates.current_page === 1}
                    onClick={() => router.get(route('dashboard.finance.exchange-rates.index'), { ...filters, page: rates.current_page - 1 }, { preserveState: true })}
                  >
                    <ChevronLeft className="h-4 w-4" />
                  </Button>

                  {/* Page numbers */}
                  {(() => {
                    const pages: (number | string)[] = []
                    const current = rates.current_page
                    const last = rates.last_page
                    const delta = 2

                    pages.push(1)

                    if (current - delta > 2) {
                      pages.push('...')
                    }

                    for (let i = Math.max(2, current - delta); i <= Math.min(last - 1, current + delta); i++) {
                      if (!pages.includes(i)) pages.push(i)
                    }

                    if (current + delta < last - 1) {
                      pages.push('...')
                    }

                    if (last > 1 && !pages.includes(last)) {
                      pages.push(last)
                    }

                    return pages.map((page, idx) =>
                      page === '...' ? (
                        <span key={`ellipsis-${idx}`} className="px-2 text-muted-foreground">...</span>
                      ) : (
                        <Button
                          key={page}
                          variant={page === current ? 'default' : 'outline'}
                          size="sm"
                          className="h-8 w-8"
                          onClick={() => router.get(route('dashboard.finance.exchange-rates.index'), { ...filters, page }, { preserveState: true })}
                        >
                          {page}
                        </Button>
                      )
                    )
                  })()}

                  {/* Next & Last */}
                  <Button
                    variant="outline"
                    size="icon"
                    className="h-8 w-8"
                    disabled={rates.current_page === rates.last_page}
                    onClick={() => router.get(route('dashboard.finance.exchange-rates.index'), { ...filters, page: rates.current_page + 1 }, { preserveState: true })}
                  >
                    <ChevronRight className="h-4 w-4" />
                  </Button>
                  <Button
                    variant="outline"
                    size="icon"
                    className="h-8 w-8"
                    disabled={rates.current_page === rates.last_page}
                    onClick={() => router.get(route('dashboard.finance.exchange-rates.index'), { ...filters, page: rates.last_page }, { preserveState: true })}
                  >
                    <ChevronsRight className="h-4 w-4" />
                  </Button>
                </div>
              </div>
            )}
          </CardContent>
        </Card>

        {/* Delete Confirmation */}
        <AlertDialog open={showDeleteDialog} onOpenChange={setShowDeleteDialog}>
          <AlertDialogContent>
            <AlertDialogHeader>
              <AlertDialogTitle>{t('page.exchange_rates.delete_title')}</AlertDialogTitle>
              <AlertDialogDescription>
                {t('page.exchange_rates.delete_description')}
              </AlertDialogDescription>
            </AlertDialogHeader>
            <AlertDialogFooter>
              <AlertDialogCancel>{t('action.cancel')}</AlertDialogCancel>
              <AlertDialogAction
                onClick={confirmDelete}
                className="bg-red-600 hover:bg-red-700"
              >
                {t('action.delete')}
              </AlertDialogAction>
            </AlertDialogFooter>
          </AlertDialogContent>
        </AlertDialog>
      </Main>
    </AuthenticatedLayout>
  )
}
