import { useState } from 'react'
import { router, Link } from '@inertiajs/react'
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
    <AuthenticatedLayout title="Exchange Rates">
      <Main>
        <div className="mb-4 flex items-center justify-between">
          <div>
            <h1 className="text-2xl font-bold tracking-tight">Exchange Rates</h1>
            <p className="text-muted-foreground">
              Manage currency exchange rates
            </p>
          </div>
          <div className="flex gap-2">
            <DropdownMenu>
              <DropdownMenuTrigger asChild>
                <Button variant="outline" disabled={isFetching}>
                  <RefreshCw
                    className={`mr-2 h-4 w-4 ${isFetching ? 'animate-spin' : ''}`}
                  />
                  Fetch Rates
                </Button>
              </DropdownMenuTrigger>
              <DropdownMenuContent align="end">
                <DropdownMenuItem onClick={() => handleFetchRates('vietcombank')}>
                  Vietcombank
                </DropdownMenuItem>
                <DropdownMenuItem onClick={() => handleFetchRates('payoneer')}>
                  Payoneer (estimated)
                </DropdownMenuItem>
                <DropdownMenuItem onClick={() => handleFetchRates('exchangerate_api')}>
                  ExchangeRate API
                </DropdownMenuItem>
                <DropdownMenuItem onClick={() => handleFetchRates('open_exchange_rates')}>
                  Open Exchange Rates
                </DropdownMenuItem>
                <DropdownMenuItem onClick={() => handleFetchRates('all')}>
                  All Providers
                </DropdownMenuItem>
              </DropdownMenuContent>
            </DropdownMenu>
            <Button asChild>
              <Link href={route('dashboard.finance.exchange-rates.create')}>
                <Plus className="mr-2 h-4 w-4" />
                Add Rate
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
                Latest Rates
              </CardTitle>
              <CardDescription>
                Current exchange rates from all sources
              </CardDescription>
            </CardHeader>
            <CardContent>
              <div className="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4">
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
                  Base Currency
                </label>
                <Select value={filterBase} onValueChange={setFilterBase}>
                  <SelectTrigger>
                    <SelectValue placeholder="All" />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem value="__all__">All</SelectItem>
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
                  Target Currency
                </label>
                <Select value={filterTarget} onValueChange={setFilterTarget}>
                  <SelectTrigger>
                    <SelectValue placeholder="All" />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem value="__all__">All</SelectItem>
                    {currencies.map((c) => (
                      <SelectItem key={c.code} value={c.code}>
                        {c.code} - {c.name}
                      </SelectItem>
                    ))}
                  </SelectContent>
                </Select>
              </div>
              <div className="flex-1 min-w-[150px]">
                <label className="text-sm font-medium mb-2 block">Source</label>
                <Select value={filterSource} onValueChange={setFilterSource}>
                  <SelectTrigger>
                    <SelectValue placeholder="All" />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem value="__all__">All</SelectItem>
                    {providers.map((p) => (
                      <SelectItem key={p} value={p}>
                        {p}
                      </SelectItem>
                    ))}
                  </SelectContent>
                </Select>
              </div>
              <Button onClick={handleFilter}>Apply Filters</Button>
            </div>
          </CardContent>
        </Card>

        {/* Rates Table */}
        <Card>
          <CardHeader>
            <CardTitle>Exchange Rate History</CardTitle>
            <CardDescription>
              Showing {rates.data.length} of {rates.total} records
            </CardDescription>
          </CardHeader>
          <CardContent>
            <Table>
              <TableHeader>
                <TableRow>
                  <TableHead>Base</TableHead>
                  <TableHead>Target</TableHead>
                  <TableHead className="text-right">Rate</TableHead>
                  <TableHead className="text-right">Bid</TableHead>
                  <TableHead className="text-right">Ask</TableHead>
                  <TableHead>Source</TableHead>
                  <TableHead>Date</TableHead>
                  <TableHead className="w-[50px]"></TableHead>
                </TableRow>
              </TableHeader>
              <TableBody>
                {rates.data.length === 0 ? (
                  <TableRow>
                    <TableCell colSpan={8} className="text-center py-8">
                      <ArrowRightLeft className="h-12 w-12 text-muted-foreground mx-auto mb-4" />
                      <p className="text-muted-foreground">
                        No exchange rates found
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
                                Edit
                              </Link>
                            </DropdownMenuItem>
                            <DropdownMenuItem
                              onClick={() => handleDelete(rate)}
                              className="text-red-600"
                            >
                              <Trash2 className="mr-2 h-4 w-4" />
                              Delete
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
                  Page {rates.current_page} of {rates.last_page}
                </p>
                <div className="flex gap-2">
                  {rates.current_page > 1 && (
                    <Button
                      variant="outline"
                      size="sm"
                      onClick={() =>
                        router.get(
                          route('dashboard.finance.exchange-rates.index'),
                          { ...filters, page: rates.current_page - 1 },
                          { preserveState: true }
                        )
                      }
                    >
                      Previous
                    </Button>
                  )}
                  {rates.current_page < rates.last_page && (
                    <Button
                      variant="outline"
                      size="sm"
                      onClick={() =>
                        router.get(
                          route('dashboard.finance.exchange-rates.index'),
                          { ...filters, page: rates.current_page + 1 },
                          { preserveState: true }
                        )
                      }
                    >
                      Next
                    </Button>
                  )}
                </div>
              </div>
            )}
          </CardContent>
        </Card>

        {/* Delete Confirmation */}
        <AlertDialog open={showDeleteDialog} onOpenChange={setShowDeleteDialog}>
          <AlertDialogContent>
            <AlertDialogHeader>
              <AlertDialogTitle>Delete Exchange Rate</AlertDialogTitle>
              <AlertDialogDescription>
                Are you sure you want to delete this exchange rate record? This
                action cannot be undone.
              </AlertDialogDescription>
            </AlertDialogHeader>
            <AlertDialogFooter>
              <AlertDialogCancel>Cancel</AlertDialogCancel>
              <AlertDialogAction
                onClick={confirmDelete}
                className="bg-red-600 hover:bg-red-700"
              >
                Delete
              </AlertDialogAction>
            </AlertDialogFooter>
          </AlertDialogContent>
        </AlertDialog>
      </Main>
    </AuthenticatedLayout>
  )
}
