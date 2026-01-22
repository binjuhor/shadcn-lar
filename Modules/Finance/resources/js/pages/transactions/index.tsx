import { useState, useEffect, useRef } from 'react'
import { router } from '@inertiajs/react'
import { format } from 'date-fns'
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
  Pencil,
  XCircle,
  X,
  ChevronLeft,
  ChevronRight,
  ChevronsLeft,
  ChevronsRight,
} from 'lucide-react'
import { TransactionForm } from './components/transaction-form'
import { ExportDialog } from './components/export-dialog'
import { BulkEditDialog } from './components/bulk-edit-dialog'
import type { Transaction, Account, Category, PaginatedData } from '@modules/Finance/types/finance'

interface Props {
  transactions: PaginatedData<Transaction>
  accounts: Account[]
  categories: Category[]
  filters: {
    account_id?: string
    category_id?: string
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
  const [showForm, setShowForm] = useState(false)
  const [showDeleteDialog, setShowDeleteDialog] = useState(false)
  const [selectedTransaction, setSelectedTransaction] = useState<Transaction | null>(null)
  const [editingTransaction, setEditingTransaction] = useState<Transaction | null>(null)
  const [showFilters, setShowFilters] = useState(false)
  const [showExportDialog, setShowExportDialog] = useState(false)
  const [showBulkEditDialog, setShowBulkEditDialog] = useState(false)
  const [showBulkDeleteDialog, setShowBulkDeleteDialog] = useState(false)
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

  // Selection helpers
  const selectableTransactions = transactions.data.filter(
    (t) => t.type !== 'transfer' && !t.transfer_transaction_id
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

  return (
    <AuthenticatedLayout title="Transactions">
      <Main>
        <div className="mb-4 flex items-center justify-between">
          <div>
            <h1 className="text-2xl font-bold tracking-tight">Transactions</h1>
            <p className="text-muted-foreground">
              View and manage your transactions
            </p>
          </div>
          <div className="flex gap-2">
            <Button
              variant="outline"
              onClick={() => setShowFilters(!showFilters)}
            >
              <Filter className="mr-2 h-4 w-4" />
              {showFilters ? 'Hide' : 'Show'} Filters
            </Button>
            <Button
              variant="outline"
              onClick={() => setShowExportDialog(true)}
            >
              <Download className="mr-2 h-4 w-4" />
              Export
            </Button>
            <a href={route('dashboard.finance.smart-input')}>
              <Button variant="outline">
                <Sparkles className="mr-2 h-4 w-4" />
                Smart Input
              </Button>
            </a>
            <Button onClick={() => setShowForm(true)}>
              <Plus className="mr-2 h-4 w-4" />
              New Transaction
            </Button>
          </div>
        </div>

        {/* Search */}
        <div className="relative mb-4">
          <Search className="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-muted-foreground" />
          <Input
            placeholder="Search transactions by description..."
            value={searchQuery}
            onChange={(e) => setSearchQuery(e.target.value)}
            className="pl-10"
          />
        </div>

        {/* Filters */}
        {showFilters && (
          <div className="flex gap-4 flex-wrap mb-6 p-4 border rounded-lg bg-muted/50">
            <div className="flex flex-col gap-1.5">
              <label className="text-sm font-medium">Account</label>
              <Select
                value={filters.account_id || 'all'}
                onValueChange={(value) => handleFilterChange('account_id', value)}
              >
                <SelectTrigger className="w-48">
                  <SelectValue placeholder="All accounts" />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="all">All accounts</SelectItem>
                  {accounts.map((account) => (
                    <SelectItem key={account.id} value={String(account.id)}>
                      {account.name}
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
            </div>

            <div className="flex flex-col gap-1.5">
              <label className="text-sm font-medium">Category</label>
              <Select
                value={filters.category_id || 'all'}
                onValueChange={(value) => handleFilterChange('category_id', value)}
              >
                <SelectTrigger className="w-48">
                  <SelectValue placeholder="All categories" />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="all">All categories</SelectItem>
                  {categories.map((category) => (
                    <SelectItem key={category.id} value={String(category.id)}>
                      {category.name}
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
            </div>

            <div className="flex flex-col gap-1.5">
              <label className="text-sm font-medium">Type</label>
              <Select
                value={filters.type || 'all'}
                onValueChange={(value) => handleFilterChange('type', value)}
              >
                <SelectTrigger className="w-36">
                  <SelectValue placeholder="All types" />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="all">All types</SelectItem>
                  <SelectItem value="income">Income</SelectItem>
                  <SelectItem value="expense">Expense</SelectItem>
                  <SelectItem value="transfer">Transfer</SelectItem>
                </SelectContent>
              </Select>
            </div>

            <div className="flex flex-col gap-1.5">
              <label className="text-sm font-medium">From Date</label>
              <DatePicker
                value={filters.date_from || undefined}
                onChange={(date) => handleFilterChange('date_from', date ? format(date, 'yyyy-MM-dd') : 'all')}
                placeholder="Select date"
                dateFormat="yyyy-MM-dd"
                className="w-40"
              />
            </div>

            <div className="flex flex-col gap-1.5">
              <label className="text-sm font-medium">To Date</label>
              <DatePicker
                value={filters.date_to || undefined}
                onChange={(date) => handleFilterChange('date_to', date ? format(date, 'yyyy-MM-dd') : 'all')}
                placeholder="Select date"
                dateFormat="yyyy-MM-dd"
                className="w-40"
              />
            </div>

            <div className="flex flex-col gap-1.5">
              <label className="text-sm font-medium">Amount From</label>
              <Input
                type="number"
                min="0"
                placeholder="Min amount"
                value={filters.amount_from || ''}
                onChange={(e) => handleFilterChange('amount_from', e.target.value || 'all')}
                className="w-36"
              />
            </div>

            <div className="flex flex-col gap-1.5">
              <label className="text-sm font-medium">Amount To</label>
              <Input
                type="number"
                min="0"
                placeholder="Max amount"
                value={filters.amount_to || ''}
                onChange={(e) => handleFilterChange('amount_to', e.target.value || 'all')}
                className="w-36"
              />
            </div>

            {(filters.account_id || filters.category_id || filters.type || filters.date_from || filters.date_to || filters.amount_from || filters.amount_to) && (
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
                  Clear Filters
                </Button>
              </div>
            )}
          </div>
        )}

        {/* Totals Summary - show when filters are active */}
        {(filters.account_id || filters.category_id || filters.type || filters.date_from || filters.date_to || filters.amount_from || filters.amount_to || filters.search) && (
          <div className="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
            <div className="p-4 border rounded-lg bg-background">
              <p className="text-sm text-muted-foreground">Total Transactions</p>
              <p className="text-2xl font-bold">{totals.count.toLocaleString()}</p>
            </div>
            <div className="p-4 border rounded-lg bg-background">
              <p className="text-sm text-muted-foreground">Total Income</p>
              <p className="text-2xl font-bold text-green-600">{formatMoney(totals.income)}</p>
            </div>
            <div className="p-4 border rounded-lg bg-background">
              <p className="text-sm text-muted-foreground">Total Expense</p>
              <p className="text-2xl font-bold text-red-600">{formatMoney(totals.expense)}</p>
            </div>
            <div className="p-4 border rounded-lg bg-background">
              <p className="text-sm text-muted-foreground">Net</p>
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
              {selectedIds.length} transaction{selectedIds.length !== 1 ? 's' : ''} selected
            </span>
            <Button size="sm" onClick={() => setShowBulkEditDialog(true)}>
              <Pencil className="mr-2 h-4 w-4" />
              Edit Selected
            </Button>
            <Button
              size="sm"
              variant="destructive"
              onClick={() => setShowBulkDeleteDialog(true)}
            >
              <Trash2 className="mr-2 h-4 w-4" />
              Delete Selected
            </Button>
            <Button variant="ghost" size="sm" onClick={clearSelection}>
              <X className="mr-2 h-4 w-4" />
              Clear
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
                        aria-label="Select all"
                        data-state={isSomeSelected ? 'indeterminate' : undefined}
                      />
                    </TableHead>
                    <TableHead className="w-12">Type</TableHead>
                    <TableHead>Description</TableHead>
                    <TableHead>Category</TableHead>
                    <TableHead>Account</TableHead>
                    <TableHead>Date</TableHead>
                    <TableHead className="text-right">Amount</TableHead>
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
                            {transaction.description || 'No description'}
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
                            {transaction.category.name}
                          </Badge>
                        ) : transaction.type === 'transfer' ? (
                          <span className="text-muted-foreground">Transfer</span>
                        ) : (
                          <span className="text-muted-foreground">Uncategorized</span>
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
                            {transaction.type !== 'transfer' && !transaction.transfer_transaction_id && (
                              <DropdownMenuItem
                                onClick={() => handleEdit(transaction)}
                              >
                                <Pencil className="mr-2 h-4 w-4" />
                                Edit
                              </DropdownMenuItem>
                            )}
                            {!transaction.is_reconciled ? (
                              <DropdownMenuItem
                                onClick={() => handleReconcile(transaction)}
                              >
                                <CheckCircle className="mr-2 h-4 w-4" />
                                Mark Reconciled
                              </DropdownMenuItem>
                            ) : (
                              <DropdownMenuItem
                                onClick={() => handleUnreconcile(transaction)}
                              >
                                <XCircle className="mr-2 h-4 w-4" />
                                Unreconcile
                              </DropdownMenuItem>
                            )}
                            <DropdownMenuItem
                              onClick={() => handleDelete(transaction)}
                              className="text-red-600"
                            >
                              <Trash2 className="mr-2 h-4 w-4" />
                              Delete
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
                  Showing {transactions.from} to {transactions.to} of {transactions.total} transactions
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
            <h3 className="text-xl font-semibold mb-2">No transactions yet</h3>
            <p className="text-muted-foreground mb-4">
              Start tracking your finances by recording your first transaction
            </p>
            <Button onClick={() => setShowForm(true)}>
              <Plus className="mr-2 h-4 w-4" />
              Record Transaction
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
              <AlertDialogTitle>Delete Transaction</AlertDialogTitle>
              <AlertDialogDescription>
                Are you sure you want to delete this transaction? This will also
                reverse the balance update.
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

        {/* Bulk Delete Confirmation */}
        <AlertDialog open={showBulkDeleteDialog} onOpenChange={setShowBulkDeleteDialog}>
          <AlertDialogContent>
            <AlertDialogHeader>
              <AlertDialogTitle>Delete {selectedIds.length} Transaction{selectedIds.length !== 1 ? 's' : ''}</AlertDialogTitle>
              <AlertDialogDescription>
                Are you sure you want to delete {selectedIds.length} selected transaction{selectedIds.length !== 1 ? 's' : ''}?
                This will also reverse all balance updates. This action cannot be undone.
              </AlertDialogDescription>
            </AlertDialogHeader>
            <AlertDialogFooter>
              <AlertDialogCancel>Cancel</AlertDialogCancel>
              <AlertDialogAction
                onClick={confirmBulkDelete}
                className="bg-red-600 hover:bg-red-700"
              >
                Delete {selectedIds.length} Transaction{selectedIds.length !== 1 ? 's' : ''}
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
