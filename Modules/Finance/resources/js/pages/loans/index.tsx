import { useState, useEffect, useRef } from 'react'
import { router } from '@inertiajs/react'
import { useTranslation } from 'react-i18next'
import { AuthenticatedLayout } from '@/layouts'
import { Main } from '@/components/layout/main'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Badge } from '@/components/ui/badge'
import { Progress } from '@/components/ui/progress'
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
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu'
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
  Plus,
  Coins,
  Search,
  MoreHorizontal,
  Pencil,
  Trash2,
  CheckCircle2,
  AlertCircle,
  ChevronLeft,
  ChevronRight,
  ChevronsLeft,
  ChevronsRight,
  Link2,
} from 'lucide-react'
import { formatDateDisplay } from '@/lib/date-utils'
import { LoanForm } from './components/loan-form'
import { RepaymentForm } from './components/repayment-form'
import type { Loan, Currency, Transaction, PaginatedData } from '@modules/Finance/types/finance'

type ExpenseTransaction = Pick<Transaction, 'id' | 'description' | 'notes' | 'amount' | 'currency_code' | 'transaction_date' | 'account'>

interface Filters {
  search?: string
  status?: string
}

interface LoanSummaryRow {
  currency_code: string
  total_amount: number
  total_repaid: number
  total_outstanding: number
  total_count: number
}

interface Props {
  loans: PaginatedData<Loan>
  currencies: Currency[]
  filters: Filters
  summary: LoanSummaryRow[]
  expenseTransactions: ExpenseTransaction[]
}

function formatMoney(amount: number, currencyCode = 'VND'): string {
  try {
    return new Intl.NumberFormat('vi-VN', { style: 'currency', currency: currencyCode }).format(amount)
  } catch {
    return `${currencyCode} ${amount.toLocaleString()}`
  }
}

function getDaysOverdue(dueDate: string): number {
  const due = new Date(dueDate)
  const today = new Date()
  return Math.ceil((today.getTime() - due.getTime()) / (1000 * 60 * 60 * 24))
}

export default function LoansIndex({ loans, currencies, filters, summary, expenseTransactions }: Props) {
  const { t } = useTranslation()
  const [showForm, setShowForm] = useState(false)
  const [showRepaymentForm, setShowRepaymentForm] = useState(false)
  const [showDeleteDialog, setShowDeleteDialog] = useState(false)
  const [selectedLoan, setSelectedLoan] = useState<Loan | null>(null)
  const [searchQuery, setSearchQuery] = useState(filters.search || '')
  const searchTimeoutRef = useRef<ReturnType<typeof setTimeout> | null>(null)

  // Debounced search
  useEffect(() => {
    if (searchTimeoutRef.current) {
      clearTimeout(searchTimeoutRef.current)
    }
    if (searchQuery === (filters.search || '')) {
      return
    }
    searchTimeoutRef.current = setTimeout(() => {
      router.get(
        route('dashboard.finance.loans.index'),
        { ...filters, search: searchQuery || undefined, page: 1 },
        { preserveState: true, preserveScroll: true }
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
      route('dashboard.finance.loans.index'),
      { ...filters, [key]: value === 'all' ? undefined : value, page: 1 },
      { preserveState: true, preserveScroll: true }
    )
  }

  const goToPage = (page: number) => {
    router.get(
      route('dashboard.finance.loans.index'),
      { ...filters, page },
      { preserveState: true, preserveScroll: true }
    )
  }

  const handleEdit = (loan: Loan) => {
    setSelectedLoan(loan)
    setShowForm(true)
  }

  const handleDelete = (loan: Loan) => {
    setSelectedLoan(loan)
    setShowDeleteDialog(true)
  }

  const handleRepay = (loan: Loan) => {
    setSelectedLoan(loan)
    setShowRepaymentForm(true)
  }

  const confirmDelete = () => {
    if (!selectedLoan) {
      return
    }
    router.delete(route('dashboard.finance.loans.destroy', selectedLoan.id), {
      onSuccess: () => {
        setShowDeleteDialog(false)
        setSelectedLoan(null)
      },
    })
  }

  const handleFormClose = () => {
    setShowForm(false)
    setSelectedLoan(null)
  }

  const handleRepaymentFormClose = () => {
    setShowRepaymentForm(false)
    setSelectedLoan(null)
  }

  const handleSuccess = () => {
    router.reload({ only: ['loans'] })
  }

  const getStatusBadge = (loan: Loan) => {
    const isRepaid = loan.status === 'repaid'
    const isWrittenOff = loan.status === 'written_off'
    const label = t(`page.loans.status.${loan.status}`, { defaultValue: loan.status })

    if (isRepaid) {
      return (
        <Badge variant="default" className="bg-green-600 whitespace-nowrap">
          <CheckCircle2 className="mr-1 h-3 w-3" />
          {label}
        </Badge>
      )
    }
    if (isWrittenOff) {
      return <Badge variant="secondary">{label}</Badge>
    }
    if (loan.status === 'partially_repaid') {
      return <Badge variant="secondary">{label}</Badge>
    }
    return <Badge variant="outline">{label}</Badge>
  }

  // Build pagination page list with ellipsis
  const buildPageList = (current: number, last: number): (number | string)[] => {
    const pages: (number | string)[] = []
    const delta = 2
    pages.push(1)
    if (current - delta > 2) pages.push('...')
    for (let i = Math.max(2, current - delta); i <= Math.min(last - 1, current + delta); i++) {
      if (!pages.includes(i)) pages.push(i)
    }
    if (current + delta < last - 1) pages.push('...')
    if (last > 1 && !pages.includes(last)) pages.push(last)
    return pages
  }

  return (
    <AuthenticatedLayout title={t('page.loans.title')}>
      <Main>
        {/* Header */}
        <div className="mb-4 md:flex items-center justify-between">
          <div className="mb-4">
            <h1 className="text-2xl font-bold tracking-tight">{t('page.loans.title')}</h1>
            <p className="text-muted-foreground">{t('page.loans.description')}</p>
          </div>
          <Button onClick={() => setShowForm(true)}>
            <Plus className="mr-2 h-4 w-4" />
            {t('page.loans.new')}
          </Button>
        </div>

        {/* Summary cards */}
        {summary.length > 0 && (
          <div className="grid grid-cols-1 sm:grid-cols-3 gap-3 mb-6">
            {summary.map((row) => (
              <div key={row.currency_code} className="contents">
                <div className="rounded-lg border bg-card p-4">
                  <p className="text-xs text-muted-foreground mb-1">{t('page.loans.summary.total_loaned')} · {row.currency_code}</p>
                  <p className="text-2xl font-bold">{formatMoney(row.total_amount, row.currency_code)}</p>
                  <p className="text-xs text-muted-foreground mt-1">{row.total_count} {row.total_count === 1 ? t('page.loans.summary.loan') : t('page.loans.summary.loans')}</p>
                </div>
                <div className="rounded-lg border bg-card p-4">
                  <p className="text-xs text-muted-foreground mb-1">{t('page.loans.summary.total_repaid')} · {row.currency_code}</p>
                  <p className="text-2xl font-bold text-green-600">{formatMoney(row.total_repaid, row.currency_code)}</p>
                  <p className="text-xs text-muted-foreground mt-1">
                    {row.total_amount > 0 ? `${((row.total_repaid / row.total_amount) * 100).toFixed(0)}%` : '0%'} {t('page.loans.summary.recovered')}
                  </p>
                </div>
                <div className="rounded-lg border bg-card p-4">
                  <p className="text-xs text-muted-foreground mb-1">{t('page.loans.summary.outstanding')} · {row.currency_code}</p>
                  <p className={`text-2xl font-bold ${row.total_outstanding > 0 ? 'text-orange-500' : 'text-muted-foreground'}`}>
                    {formatMoney(row.total_outstanding, row.currency_code)}
                  </p>
                  <p className="text-xs text-muted-foreground mt-1">
                    {row.total_amount > 0 ? `${((row.total_outstanding / row.total_amount) * 100).toFixed(0)}%` : '0%'} {t('page.loans.summary.pending')}
                  </p>
                </div>
              </div>
            ))}
          </div>
        )}

        {/* Search + Filter bar */}
        <div className="flex gap-3 flex-wrap mb-4">
          <div className="relative flex-1 min-w-48">
            <Search className="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-muted-foreground" />
            <Input
              placeholder={t('page.loans.search_placeholder')}
              value={searchQuery}
              onChange={(e) => setSearchQuery(e.target.value)}
              className="pl-10"
            />
          </div>
          <Select
            value={filters.status || 'all'}
            onValueChange={(v) => handleFilterChange('status', v)}
          >
            <SelectTrigger className="w-40">
              <SelectValue />
            </SelectTrigger>
            <SelectContent>
              <SelectItem value="all">{t('page.loans.filter.all')}</SelectItem>
              <SelectItem value="active">{t('page.loans.status.active')}</SelectItem>
              <SelectItem value="partially_repaid">{t('page.loans.status.partially_repaid')}</SelectItem>
              <SelectItem value="repaid">{t('page.loans.status.repaid')}</SelectItem>
              <SelectItem value="written_off">{t('page.loans.status.written_off')}</SelectItem>
            </SelectContent>
          </Select>
        </div>

        {/* Table */}
        {loans.data.length > 0 ? (
          <>
            <div className="rounded-md border">
              <Table>
                <TableHeader>
                  <TableRow>
                    <TableHead>{t('page.loans.table.borrower')}</TableHead>
                    <TableHead className="text-right">{t('page.loans.table.amount')}</TableHead>
                    <TableHead className="hidden md:table-cell">{t('page.loans.table.progress')}</TableHead>
                    <TableHead className="hidden sm:table-cell">{t('page.loans.table.loan_date')}</TableHead>
                    <TableHead className="hidden sm:table-cell">{t('page.loans.table.due_date')}</TableHead>
                    <TableHead>{t('page.loans.table.status')}</TableHead>
                    <TableHead className="w-12"></TableHead>
                  </TableRow>
                </TableHeader>
                <TableBody>
                  {loans.data.map((loan) => {
                    const isSettled = loan.status === 'repaid' || loan.status === 'written_off'
                    const daysOverdue = loan.due_date && !isSettled ? getDaysOverdue(loan.due_date) : 0
                    const isOverdue = daysOverdue > 0

                    return (
                      <TableRow key={loan.id}>
                        <TableCell>
                          <div>
                            <p className="font-medium">{loan.borrower_name}</p>
                            {loan.message && (
                              <p className="text-xs text-muted-foreground line-clamp-1">{loan.message}</p>
                            )}
                            {loan.transaction && (
                              <p className="text-xs text-blue-500 flex items-center gap-1 mt-0.5">
                                <Link2 className="h-3 w-3 shrink-0" />
                                <span className="line-clamp-1">
                                  {loan.transaction.description || t('common.no_description')} · {loan.transaction.account?.name}
                                </span>
                              </p>
                            )}
                          </div>
                        </TableCell>
                        <TableCell className="text-right">
                          <div>
                            <p className="font-semibold">{formatMoney(loan.amount, loan.currency_code)}</p>
                            {!isSettled && loan.remaining_amount > 0 && (
                              <p className="text-xs text-muted-foreground">
                                {formatMoney(loan.remaining_amount, loan.currency_code)} {t('page.loans.card.left')}
                              </p>
                            )}
                          </div>
                        </TableCell>
                        <TableCell className="hidden md:table-cell w-36">
                          {!isSettled ? (
                            <div>
                              <div className="flex justify-between text-xs mb-1">
                                <span className="text-muted-foreground">{Math.min(loan.progress_percent, 100).toFixed(0)}%</span>
                              </div>
                              <Progress value={Math.min(loan.progress_percent, 100)} className="h-1.5" />
                            </div>
                          ) : (
                            <span className="text-xs text-muted-foreground">—</span>
                          )}
                        </TableCell>
                        <TableCell className="hidden sm:table-cell text-sm">
                          {formatDateDisplay(loan.loan_date)}
                        </TableCell>
                        <TableCell className="hidden sm:table-cell text-sm">
                          {loan.due_date ? (
                            <span className={isOverdue && !isSettled ? 'text-red-500 flex items-center gap-1' : ''}>
                              {isOverdue && !isSettled && <AlertCircle className="h-3 w-3" />}
                              {formatDateDisplay(loan.due_date)}
                            </span>
                          ) : (
                            <span className="text-muted-foreground">—</span>
                          )}
                        </TableCell>
                        <TableCell>
                          {getStatusBadge(loan)}
                        </TableCell>
                        <TableCell>
                          <DropdownMenu>
                            <DropdownMenuTrigger asChild>
                              <Button variant="ghost" size="icon" className="h-8 w-8">
                                <MoreHorizontal className="h-4 w-4" />
                              </Button>
                            </DropdownMenuTrigger>
                            <DropdownMenuContent align="end">
                              {!isSettled && (
                                <>
                                  <DropdownMenuItem onClick={() => handleRepay(loan)}>
                                    <Plus className="mr-2 h-4 w-4" />
                                    {t('page.loans.action.add_repayment')}
                                  </DropdownMenuItem>
                                  <DropdownMenuSeparator />
                                </>
                              )}
                              <DropdownMenuItem onClick={() => handleEdit(loan)}>
                                <Pencil className="mr-2 h-4 w-4" />
                                {t('action.edit')}
                              </DropdownMenuItem>
                              <DropdownMenuSeparator />
                              <DropdownMenuItem onClick={() => handleDelete(loan)} className="text-red-600">
                                <Trash2 className="mr-2 h-4 w-4" />
                                {t('action.delete')}
                              </DropdownMenuItem>
                            </DropdownMenuContent>
                          </DropdownMenu>
                        </TableCell>
                      </TableRow>
                    )
                  })}
                </TableBody>
              </Table>
            </div>

            {/* Pagination */}
            {loans.last_page > 1 && (
              <div className="flex items-center justify-between mt-4">
                <p className="text-sm text-muted-foreground">
                  {t('pagination.showing', { from: loans.from, to: loans.to, total: loans.total })}
                </p>
                <div className="flex items-center gap-1">
                  <Button
                    variant="outline" size="icon" className="h-8 w-8"
                    disabled={loans.current_page === 1}
                    onClick={() => goToPage(1)}
                  >
                    <ChevronsLeft className="h-4 w-4" />
                  </Button>
                  <Button
                    variant="outline" size="icon" className="h-8 w-8"
                    disabled={loans.current_page === 1}
                    onClick={() => goToPage(loans.current_page - 1)}
                  >
                    <ChevronLeft className="h-4 w-4" />
                  </Button>

                  {buildPageList(loans.current_page, loans.last_page).map((page, idx) =>
                    page === '...' ? (
                      <span key={`ellipsis-${idx}`} className="px-2 text-muted-foreground">...</span>
                    ) : (
                      <Button
                        key={page}
                        variant={page === loans.current_page ? 'default' : 'outline'}
                        size="sm" className="h-8 w-8"
                        onClick={() => goToPage(page as number)}
                      >
                        {page}
                      </Button>
                    )
                  )}

                  <Button
                    variant="outline" size="icon" className="h-8 w-8"
                    disabled={loans.current_page === loans.last_page}
                    onClick={() => goToPage(loans.current_page + 1)}
                  >
                    <ChevronRight className="h-4 w-4" />
                  </Button>
                  <Button
                    variant="outline" size="icon" className="h-8 w-8"
                    disabled={loans.current_page === loans.last_page}
                    onClick={() => goToPage(loans.last_page)}
                  >
                    <ChevronsRight className="h-4 w-4" />
                  </Button>
                </div>
              </div>
            )}
          </>
        ) : (
          <div className="flex flex-col items-center justify-center py-16 text-center">
            <Coins className="h-16 w-16 text-muted-foreground mb-4" />
            <h3 className="text-xl font-semibold mb-2">{t('page.loans.no_loans')}</h3>
            <p className="text-muted-foreground mb-4">{t('page.loans.get_started')}</p>
            <Button onClick={() => setShowForm(true)}>
              <Plus className="mr-2 h-4 w-4" />
              {t('page.loans.record_first')}
            </Button>
          </div>
        )}

        <LoanForm
          open={showForm}
          onOpenChange={handleFormClose}
          loan={selectedLoan}
          currencies={currencies}
          expenseTransactions={expenseTransactions}
          onSuccess={handleSuccess}
        />

        <RepaymentForm
          open={showRepaymentForm}
          onOpenChange={handleRepaymentFormClose}
          loan={selectedLoan}
          onSuccess={handleSuccess}
        />

        <AlertDialog open={showDeleteDialog} onOpenChange={setShowDeleteDialog}>
          <AlertDialogContent>
            <AlertDialogHeader>
              <AlertDialogTitle>{t('page.loans.delete_title')}</AlertDialogTitle>
              <AlertDialogDescription>
                {t('page.loans.delete_confirm', { name: selectedLoan?.borrower_name })}
              </AlertDialogDescription>
            </AlertDialogHeader>
            <AlertDialogFooter>
              <AlertDialogCancel>{t('action.cancel')}</AlertDialogCancel>
              <AlertDialogAction onClick={confirmDelete} className="bg-red-600 hover:bg-red-700">
                {t('action.delete')}
              </AlertDialogAction>
            </AlertDialogFooter>
          </AlertDialogContent>
        </AlertDialog>
      </Main>
    </AuthenticatedLayout>
  )
}
