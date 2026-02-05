import { useState, useEffect, useRef } from 'react'
import { router } from '@inertiajs/react'
import { format } from 'date-fns'
import { useTranslation } from 'react-i18next'
import { useCategoryName } from '@/hooks/use-category-name'
import { AuthenticatedLayout } from '@/layouts'
import { Main } from '@/components/layout/main'
import { Button } from '@/components/ui/button'
import { Badge } from '@/components/ui/badge'
import { Input } from '@/components/ui/input'
import { DatePicker } from '@/components/ui/date-picker'
import { Checkbox } from '@/components/ui/checkbox'
import { formatDateDisplay } from '@/lib/date-utils'
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table'
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
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select'
import {
  Plus,
  MoreHorizontal,
  Trash2,
  CheckCircle,
  ArrowDownLeft,
  ArrowUpRight,
  ArrowRight,
  Filter,
  Inbox,
  Sparkles,
  Search,
  Download,
  Upload,
  Pencil,
  XCircle,
  X,
  ChevronLeft,
  ChevronRight,
  ChevronsLeft,
  ChevronsRight,
  Link2,
} from 'lucide-react'
import { TransactionForm } from './components/transaction-form'
import { ExportDialog } from './components/export-dialog'
import { BulkEditDialog } from './components/bulk-edit-dialog'
import { MultiSelect } from '@/components/ui/multi-select'
import type { Transaction, Account, Category, PaginatedData } from '@modules/Finance/types/finance'

interface Props {
  transactions: PaginatedData<Transaction>
  accounts: Account[]
  categories: Category[]
  filters: {
    account_ids?: string[]
    category_ids?: string[]
    type?: string
    search?: string
    date_from?: string
    date_to?: string
    amount_from?: string
    amount_to?: string
  }
  totals: {
    income: number
    expense: number
    net: number
    count: number
  }
}

function formatMoney(amount: number, currencyCode = 'VND'): string {
  return new Intl.NumberFormat('vi-VN', {
    style: 'currency',
    currency: currencyCode,
  }).format(amount)
}

export default function TransactionsIndex({
  transactions,
  accounts,
  categories,
  filters,
  totals,
}: Props) {
  const { t } = useTranslation()
  const getCategoryName = useCategoryName()
  const [showForm, setShowForm] = useState(false)
  const [showDeleteDialog, setShowDeleteDialog] = useState(false)
  const [selectedTransaction, setSelectedTransaction] = useState<Transaction | null>(null)
  const [editingTransaction, setEditingTransaction] = useState<Transaction | null>(null)
  const [showFilters, setShowFilters] = useState(false)
  const [showExportDialog, setShowExportDialog] = useState(false)
  const [showBulkEditDialog, setShowBulkEditDialog] = useState(false)
  const [showBulkDeleteDialog, setShowBulkDeleteDialog] = useState(false)
  const [showLinkDialog, setShowLinkDialog] = useState(false)
  const [selectedIds, setSelectedIds] = useState<number[]>([])
  const [searchQuery, setSearchQuery] = useState(filters.search || '')
  const searchTimeoutRef = useRef<NodeJS.Timeout | null>(null)

  // Debounced search effect
  useEffect(() => {
    if (searchTimeoutRef.current) {
      clearTimeout(searchTimeoutRef.current)
    }

    // Skip if search matches current filter (initial load)
    if (searchQuery === (filters.search || '')) return

    searchTimeoutRef.current = setTimeout(() => {
      router.get(
        route('dashboard.finance.transactions.index'),
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

  const handleDelete = (transaction: Transaction) => {
    setSelectedTransaction(transaction)
    setShowDeleteDialog(true)
  }

  const confirmDelete = () => {
    if (selectedTransaction) {
      router.delete(
        route('dashboard.finance.transactions.destroy', selectedTransaction.id),
        {
          onSuccess: () => {
            setShowDeleteDialog(false)
            setSelectedTransaction(null)
          },
        }
      )
    }
  }

  const handleEdit = (transaction: Transaction) => {
    setEditingTransaction(transaction)
    setShowForm(true)
  }

  const handleReconcile = (transaction: Transaction) => {
    router.post(
      route('dashboard.finance.transactions.reconcile', transaction.id),
      {},
      {
        preserveState: true,
        preserveScroll: true,
      }
    )
  }

  const handleUnreconcile = (transaction: Transaction) => {
    router.post(
      route('dashboard.finance.transactions.unreconcile', transaction.id),
      {},
      {
        preserveState: true,
        preserveScroll: true,
      }
    )
  }

  const handleFilterChange = (key: string, value: string) => {
    router.get(
      route('dashboard.finance.transactions.index'),
      {
        ...filters,
        [key]: value === 'all' ? undefined : value,
      },
      {
        preserveState: true,
        preserveScroll: true,
      }
    )
  }

  const handleMultiFilterChange = (key: string, values: string[]) => {
    router.get(
      route('dashboard.finance.transactions.index'),
      {
        ...filters,
        [key]: values.length > 0 ? values : undefined,
      },
      {
        preserveState: true,
        preserveScroll: true,
      }
    )
  }

  const handleSuccess = () => {
    setEditingTransaction(null)
    router.reload({ only: ['transactions'] })
  }

  const handleFormClose = (open: boolean) => {
    setShowForm(open)
    if (!open) {
      setEditingTransaction(null)
    }
  }

  const getTypeIcon = (type: string) => {
    switch (type) {
      case 'income':
        return <ArrowDownLeft className="h-4 w-4 text-green-600" />
      case 'expense':
        return <ArrowUpRight className="h-4 w-4 text-red-600" />
      case 'transfer':
        return <ArrowRight className="h-4 w-4 text-blue-600" />
      default:
        return null
    }
  }

  // Selection helpers - allow selecting all except linked transfers
  const selectableTransactions = transactions.data.filter(
    (t) => !t.transfer_transaction_id
  )
  const isAllSelected = selectableTransactions.length > 0 &&
    selectableTransactions.every((t) => selectedIds.includes(t.id))
  const isSomeSelected = selectedIds.length > 0 && !isAllSelected

  const toggleSelect = (id: number) => {
    setSelectedIds((prev) =>
      prev.includes(id) ? prev.filter((i) => i !== id) : [...prev, id]
    )
  }

  const toggleSelectAll = () => {
    if (isAllSelected) {
      setSelectedIds([])
    } else {
      setSelectedIds(selectableTransactions.map((t) => t.id))
    }
  }

  const clearSelection = () => {
    setSelectedIds([])
  }

  const handleBulkEditSuccess = () => {
    clearSelection()
    router.reload({ only: ['transactions'] })
  }

  const confirmBulkDelete = () => {
    router.post(
      route('dashboard.finance.transactions.bulk-destroy'),
      { transaction_ids: selectedIds },
      {
        preserveScroll: true,
        onSuccess: () => {
          setShowBulkDeleteDialog(false)
          clearSelection()
        },
      }
    )
  }

  const confirmLinkAsTransfer = () => {
    router.post(
      route('dashboard.finance.transactions.link-as-transfer'),
      { transaction_ids: selectedIds },
      {
        preserveScroll: true,
        onSuccess: () => {
          setShowLinkDialog(false)
          clearSelection()
        },
      }
    )
  }

  return (
    <AuthenticatedLayout title={t('page.transactions.title')}>
      <Main>
        <div className="mb-4 md:flex items-center justify-between">
          <div className="mb-4">
            <h1 className="text-2xl font-bold tracking-tight">{t('page.transactions.title')}</h1>
            <p className="text-muted-foreground">
              {t('page.transactions.description')}
            </p>
          </div>
          <div className="flex gap-2 flex-wrap">
            <Button
              variant="outline"
              onClick={() => setShowFilters(!showFilters)}
            >
              <Filter className="mr-2 h-4 w-4" />
              {showFilters ? t('filter.hide') : t('filter.show')} {t('filter.filters')}
            </Button>
            <Button
              variant="outline"
              onClick={() => setShowExportDialog(true)}
            >
              <Download className="mr-2 h-4 w-4" /> {t('action.export')}
            </Button>
            <a href={route('dashboard.finance.transactions.import')}>
              <Button variant="outline">
                <Upload className="mr-2 h-4 w-4" /> {t('action.import')}
              </Button>
            </a>
            <a href={route('dashboard.finance.smart-input')}>
              <Button variant="outline">
                <Sparkles className="mr-2 h-4 w-4" /> {t('sidebar.smart_input')}
              </Button>
            </a>
            <Button onClick={() => setShowForm(true)}>
              <Plus className="mr-2 h-4 w-4" /> {t('page.transactions.new')}
            </Button>
          </div>
        </div>

        {/* Search */}
        <div className="relative mb-4">
          <Search className="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-muted-foreground" />
          <Input
            placeholder={t('page.transactions.search_placeholder')}
            value={searchQuery}
            onChange={(e) => setSearchQuery(e.target.value)}
            className="pl-10"
          />
        </div>

        {/* Filters */}
        {showFilters && (
          <div className="flex gap-4 flex-wrap mb-6 p-4 border rounded-lg bg-muted/50">
            <div className="flex flex-col gap-1.5">
              <label className="text-sm font-medium">{t('filter.account')}</label>
              <MultiSelect
                options={accounts.map((account) => ({
                  value: String(account.id),
                  label: account.name,
                }))}
                value={filters.account_ids || []}
                onChange={(values) => handleMultiFilterChange('account_ids', values)}
                placeholder={t('filter.all_accounts')}
                searchPlaceholder={t('filter.search_accounts')}
                className="w-56"
              />
            </div>

            <div className="flex flex-col gap-1.5">
              <label className="text-sm font-medium">{t('filter.category')}</label>
              <MultiSelect
                options={categories.map((category) => ({
                  value: String(category.id),
                  label: getCategoryName(category),
                }))}
                value={filters.category_ids || []}
                onChange={(values) => handleMultiFilterChange('category_ids', values)}
                placeholder={t('filter.all_categories')}
                searchPlaceholder={t('filter.search_categories')}
                className="w-56"
              />
            </div>

            <div className="flex flex-col gap-1.5">
              <label className="text-sm font-medium">{t('filter.type')}</label>
              <Select
                value={filters.type || 'all'}
                onValueChange={(value) => handleFilterChange('type', value)}
              >
                <SelectTrigger className="w-36">
                  <SelectValue placeholder={t('filter.all_types')} />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="all">{t('filter.all_types')}</SelectItem>
                  <SelectItem value="income">{t('filter.income')}</SelectItem>
                  <SelectItem value="expense">{t('filter.expense')}</SelectItem>
                  <SelectItem value="transfer">{t('filter.transfer')}</SelectItem>
                </SelectContent>
              </Select>
            </div>

            <div className="flex flex-col gap-1.5">
              <label className="text-sm font-medium">{t('filter.from_date')}</label>
              <DatePicker
                value={filters.date_from || undefined}
                onChange={(date) => handleFilterChange('date_from', date ? format(date, 'yyyy-MM-dd') : 'all')}
                placeholder={t('filter.select_date')}
                dateFormat="yyyy-MM-dd"
                className="w-40"
              />
            </div>

            <div className="flex flex-col gap-1.5">
              <label className="text-sm font-medium">{t('filter.to_date')}</label>
              <DatePicker
                value={filters.date_to || undefined}
                onChange={(date) => handleFilterChange('date_to', date ? format(date, 'yyyy-MM-dd') : 'all')}
                placeholder={t('filter.select_date')}
                dateFormat="yyyy-MM-dd"
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

            {((filters.account_ids && filters.account_ids.length > 0) || (filters.category_ids && filters.category_ids.length > 0) || filters.type || filters.date_from || filters.date_to || filters.amount_from || filters.amount_to) && (
              <div className="flex flex-col gap-1.5 justify-end">
                <Button
                  variant="ghost"
                  size="sm"
                  onClick={() => {
                    router.get(
                      route('dashboard.finance.transactions.index'),
                      { search: filters.search },
                      { preserveState: true, preserveScroll: true }
                    )
                  }}
                >
                  {t('filter.clear')}
                </Button>
              </div>
            )}
          </div>
        )}

        {/* Totals Summary - show when filters are active */}
        {((filters.account_ids && filters.account_ids.length > 0) || (filters.category_ids && filters.category_ids.length > 0) || filters.type || filters.date_from || filters.date_to || filters.amount_from || filters.amount_to || filters.search) && (
          <div className="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
            <div className="p-4 border rounded-lg bg-background">
              <p className="text-sm text-muted-foreground">{t('page.transactions.total_transactions')}</p>
              <p className="text-2xl font-bold">{totals.count.toLocaleString()}</p>
            </div>
            <div className="p-4 border rounded-lg bg-background">
              <p className="text-sm text-muted-foreground">{t('page.transactions.total_income')}</p>
              <p className="text-2xl font-bold text-green-600">{formatMoney(totals.income)}</p>
            </div>
            <div className="p-4 border rounded-lg bg-background">
              <p className="text-sm text-muted-foreground">{t('page.transactions.total_expense')}</p>
              <p className="text-2xl font-bold text-red-600">{formatMoney(totals.expense)}</p>
            </div>
            <div className="p-4 border rounded-lg bg-background">
              <p className="text-sm text-muted-foreground">{t('page.transactions.net')}</p>
              <p className={`text-2xl font-bold ${totals.net >= 0 ? 'text-green-600' : 'text-red-600'}`}>
                {formatMoney(totals.net)}
              </p>
            </div>
          </div>
        )}

        {/* Bulk Actions Toolbar */}
        {selectedIds.length > 0 && (
          <div className="flex items-center gap-4 p-3 bg-muted rounded-lg mb-4">
            <span className="text-sm font-medium">
              {t('bulk.selected_count', { count: selectedIds.length })}
            </span>
            <Button size="sm" onClick={() => setShowBulkEditDialog(true)}>
              <Pencil className="mr-2 h-4 w-4" />
              {t('bulk.edit_selected')}
            </Button>
            {selectedIds.length === 2 && (
              <Button size="sm" variant="outline" onClick={() => setShowLinkDialog(true)}>
                <Link2 className="mr-2 h-4 w-4" />
                {t('bulk.link_as_transfer')}
              </Button>
            )}
            <Button
              size="sm"
              variant="destructive"
              onClick={() => setShowBulkDeleteDialog(true)}
            >
              <Trash2 className="mr-2 h-4 w-4" />
              {t('bulk.delete_selected')}
            </Button>
            <Button variant="ghost" size="sm" onClick={clearSelection}>
              <X className="mr-2 h-4 w-4" />
              {t('action.clear')}
            </Button>
          </div>
        )}

        {/* Transactions Table */}
        {transactions.data.length > 0 ? (
          <>
            <div className="rounded-md border">
              <Table>
                <TableHeader>
                  <TableRow>
                    <TableHead className="w-12">
                      <Checkbox
                        checked={isAllSelected}
                        onCheckedChange={toggleSelectAll}
                        aria-label={t('action.select_all')}
                        data-state={isSomeSelected ? 'indeterminate' : undefined}
                      />
                    </TableHead>
                    <TableHead className="w-12">{t('table.type')}</TableHead>
                    <TableHead>{t('table.description')}</TableHead>
                    <TableHead>{t('table.category')}</TableHead>
                    <TableHead>{t('table.account')}</TableHead>
                    <TableHead>{t('table.date')}</TableHead>
                    <TableHead className="text-right">{t('table.amount')}</TableHead>
                    <TableHead className="w-12"></TableHead>
                  </TableRow>
                </TableHeader>
                <TableBody>
                  {transactions.data.map((transaction) => {
                    const isTransfer = transaction.type === 'transfer' || transaction.transfer_transaction_id
                    const isSelected = selectedIds.includes(transaction.id)
                    return (
                    <TableRow
                      key={transaction.id}
                      className={isSelected ? 'bg-muted/50' : undefined}
                    >
                      <TableCell>
                        {isTransfer ? (
                          <span className="text-muted-foreground text-xs">-</span>
                        ) : (
                          <Checkbox
                            checked={isSelected}
                            onCheckedChange={() => toggleSelect(transaction.id)}
                            aria-label={`Select transaction ${transaction.id}`}
                          />
                        )}
                      </TableCell>
                      <TableCell>
                        <div className="flex items-center justify-center">
                          {getTypeIcon(transaction.type)}
                        </div>
                      </TableCell>
                      <TableCell>
                        <div className="max-w-xl">
                          <p className="font-medium">
                            {transaction.description || t('common.no_description')}
                          </p>
                          {transaction.notes && (
                            <p className="text-xs text-muted-foreground line-clamp-1">
                              {transaction.notes}
                            </p>
                          )}
                        </div>
                      </TableCell>
                      <TableCell>
                        {transaction.category ? (
                          <Badge variant="secondary">
                            {getCategoryName(transaction.category)}
                          </Badge>
                        ) : transaction.type === 'transfer' ? (
                          <span className="text-muted-foreground">{t('filter.transfer')}</span>
                        ) : (
                          <span className="text-muted-foreground">{t('common.uncategorized')}</span>
                        )}
                      </TableCell>
                      <TableCell>
                        <div>
                          <p>{transaction.account?.name}</p>
                          {transaction.type === 'transfer' && transaction.transfer_account && (
                            <p className="text-xs text-muted-foreground">
                              → {transaction.transfer_account.name}
                            </p>
                          )}
                        </div>
                      </TableCell>
                      <TableCell>
                        <div className="flex items-center gap-2">
                          {formatDateDisplay(transaction.transaction_date)}
                          {transaction.is_reconciled && (
                            <CheckCircle className="h-4 w-4 text-green-600" />
                          )}
                        </div>
                      </TableCell>
                      <TableCell className="text-right">
                        <span
                          className={`font-semibold ${
                            transaction.type === 'income'
                              ? 'text-green-600'
                              : transaction.type === 'expense'
                                ? 'text-red-600'
                                : ''
                          }`}
                        >
                          {transaction.type === 'income' ? '+' : transaction.type === 'expense' ? '-' : ''}
                          {formatMoney(transaction.amount, transaction.currency_code)}
                        </span>
                      </TableCell>
                      <TableCell>
                        <DropdownMenu>
                          <DropdownMenuTrigger asChild>
                            <Button variant="ghost" size="icon">
                              <MoreHorizontal className="h-4 w-4" />
                            </Button>
                          </DropdownMenuTrigger>
                          <DropdownMenuContent align="end">
                            {!transaction.transfer_transaction_id && (
                              <DropdownMenuItem
                                onClick={() => handleEdit(transaction)}
                              >
                                <Pencil className="mr-2 h-4 w-4" />
                                {t('action.edit')}
                              </DropdownMenuItem>
                            )}
                            {!transaction.is_reconciled ? (
                              <DropdownMenuItem
                                onClick={() => handleReconcile(transaction)}
                              >
                                <CheckCircle className="mr-2 h-4 w-4" />
                                {t('action.mark_reconciled')}
                              </DropdownMenuItem>
                            ) : (
                              <DropdownMenuItem
                                onClick={() => handleUnreconcile(transaction)}
                              >
                                <XCircle className="mr-2 h-4 w-4" />
                                {t('action.unreconcile')}
                              </DropdownMenuItem>
                            )}
                            <DropdownMenuItem
                              onClick={() => handleDelete(transaction)}
                              className="text-red-600"
                            >
                              <Trash2 className="mr-2 h-4 w-4" />
                              {t('action.delete')}
                            </DropdownMenuItem>
                          </DropdownMenuContent>
                        </DropdownMenu>
                      </TableCell>
                    </TableRow>
                  )})}
                </TableBody>
              </Table>
            </div>

            {/* Pagination */}
            {transactions.last_page > 1 && (
              <div className="flex items-center justify-between mt-4">
                <p className="text-sm text-muted-foreground">
                  {t('pagination.showing', { from: transactions.from, to: transactions.to, total: transactions.total })}
                </p>
                <div className="flex items-center gap-1">
                  {/* First & Previous */}
                  <Button
                    variant="outline"
                    size="icon"
                    className="h-8 w-8"
                    disabled={transactions.current_page === 1}
                    onClick={() => router.get(route('dashboard.finance.transactions.index'), { ...filters, page: 1 })}
                  >
                    <ChevronsLeft className="h-4 w-4" />
                  </Button>
                  <Button
                    variant="outline"
                    size="icon"
                    className="h-8 w-8"
                    disabled={transactions.current_page === 1}
                    onClick={() => router.get(route('dashboard.finance.transactions.index'), { ...filters, page: transactions.current_page - 1 })}
                  >
                    <ChevronLeft className="h-4 w-4" />
                  </Button>

                  {/* Page numbers */}
                  {(() => {
                    const pages: (number | string)[] = []
                    const current = transactions.current_page
                    const last = transactions.last_page
                    const delta = 2

                    // Always show first page
                    pages.push(1)

                    // Left ellipsis
                    if (current - delta > 2) {
                      pages.push('...')
                    }

                    // Pages around current
                    for (let i = Math.max(2, current - delta); i <= Math.min(last - 1, current + delta); i++) {
                      if (!pages.includes(i)) pages.push(i)
                    }

                    // Right ellipsis
                    if (current + delta < last - 1) {
                      pages.push('...')
                    }

                    // Always show last page
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
                          onClick={() => router.get(route('dashboard.finance.transactions.index'), { ...filters, page })}
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
                    disabled={transactions.current_page === transactions.last_page}
                    onClick={() => router.get(route('dashboard.finance.transactions.index'), { ...filters, page: transactions.current_page + 1 })}
                  >
                    <ChevronRight className="h-4 w-4" />
                  </Button>
                  <Button
                    variant="outline"
                    size="icon"
                    className="h-8 w-8"
                    disabled={transactions.current_page === transactions.last_page}
                    onClick={() => router.get(route('dashboard.finance.transactions.index'), { ...filters, page: transactions.last_page })}
                  >
                    <ChevronsRight className="h-4 w-4" />
                  </Button>
                </div>
              </div>
            )}
          </>
        ) : (
          <div className="flex flex-col items-center justify-center py-16 text-center">
            <Inbox className="h-16 w-16 text-muted-foreground mb-4" />
            <h3 className="text-xl font-semibold mb-2">{t('page.transactions.empty_title')}</h3>
            <p className="text-muted-foreground mb-4">
              {t('page.transactions.empty_description')}
            </p>
            <Button onClick={() => setShowForm(true)}>
              <Plus className="mr-2 h-4 w-4" />
              {t('page.transactions.record')}
            </Button>
          </div>
        )}

        {/* Transaction Form */}
        <TransactionForm
          open={showForm}
          onOpenChange={handleFormClose}
          accounts={accounts}
          categories={categories}
          transaction={editingTransaction}
          onSuccess={handleSuccess}
        />

        {/* Delete Confirmation */}
        <AlertDialog open={showDeleteDialog} onOpenChange={setShowDeleteDialog}>
          <AlertDialogContent>
            <AlertDialogHeader>
              <AlertDialogTitle>{t('dialog.delete_transaction.title')}</AlertDialogTitle>
              <AlertDialogDescription>
                {t('dialog.delete_transaction.description')}
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

        {/* Bulk Delete Confirmation */}
        <AlertDialog open={showBulkDeleteDialog} onOpenChange={setShowBulkDeleteDialog}>
          <AlertDialogContent>
            <AlertDialogHeader>
              <AlertDialogTitle>{t('dialog.bulk_delete.title', { count: selectedIds.length })}</AlertDialogTitle>
              <AlertDialogDescription>
                {t('dialog.bulk_delete.description', { count: selectedIds.length })}
              </AlertDialogDescription>
            </AlertDialogHeader>
            <AlertDialogFooter>
              <AlertDialogCancel>{t('action.cancel')}</AlertDialogCancel>
              <AlertDialogAction
                onClick={confirmBulkDelete}
                className="bg-red-600 hover:bg-red-700"
              >
                {t('dialog.bulk_delete.confirm', { count: selectedIds.length })}
              </AlertDialogAction>
            </AlertDialogFooter>
          </AlertDialogContent>
        </AlertDialog>

        {/* Link as Transfer Confirmation */}
        <AlertDialog open={showLinkDialog} onOpenChange={setShowLinkDialog}>
          <AlertDialogContent>
            <AlertDialogHeader>
              <AlertDialogTitle>{t('dialog.link_transfer.title')}</AlertDialogTitle>
              <AlertDialogDescription>
                {t('dialog.link_transfer.description')}
              </AlertDialogDescription>
            </AlertDialogHeader>
            <AlertDialogFooter>
              <AlertDialogCancel>{t('action.cancel')}</AlertDialogCancel>
              <AlertDialogAction onClick={confirmLinkAsTransfer}>
                {t('dialog.link_transfer.confirm')}
              </AlertDialogAction>
            </AlertDialogFooter>
          </AlertDialogContent>
        </AlertDialog>

        {/* Export Dialog */}
        <ExportDialog
          open={showExportDialog}
          onOpenChange={setShowExportDialog}
        />

        {/* Bulk Edit Dialog */}
        <BulkEditDialog
          open={showBulkEditDialog}
          onOpenChange={setShowBulkEditDialog}
          selectedIds={selectedIds}
          accounts={accounts}
          categories={categories}
          onSuccess={handleBulkEditSuccess}
        />
      </Main>
    </AuthenticatedLayout>
  )
}
