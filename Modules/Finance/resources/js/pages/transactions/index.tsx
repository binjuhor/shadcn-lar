import { useState } from 'react'
import { router } from '@inertiajs/react'
import { AuthenticatedLayout } from '@/layouts'
import { Main } from '@/components/layout/main'
import { Button } from '@/components/ui/button'
import { Badge } from '@/components/ui/badge'
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
} from 'lucide-react'
import { TransactionForm } from './components/transaction-form'
import type { Transaction, Account, Category, PaginatedData } from '@modules/Finance/types/finance'

interface Props {
  transactions: PaginatedData<Transaction>
  accounts: Account[]
  categories: Category[]
  filters: {
    account_id?: string
    category_id?: string
    type?: string
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
}: Props) {
  const [showForm, setShowForm] = useState(false)
  const [showDeleteDialog, setShowDeleteDialog] = useState(false)
  const [selectedTransaction, setSelectedTransaction] = useState<Transaction | null>(null)
  const [showFilters, setShowFilters] = useState(false)

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
    router.reload({ only: ['transactions'] })
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
            <Button onClick={() => setShowForm(true)}>
              <Plus className="mr-2 h-4 w-4" />
              New Transaction
            </Button>
          </div>
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
          </div>
        )}

        {/* Transactions Table */}
        {transactions.data.length > 0 ? (
          <>
            <div className="rounded-md border">
              <Table>
                <TableHeader>
                  <TableRow>
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
                  {transactions.data.map((transaction) => (
                    <TableRow key={transaction.id}>
                      <TableCell>
                        <div className="flex items-center justify-center">
                          {getTypeIcon(transaction.type)}
                        </div>
                      </TableCell>
                      <TableCell>
                        <div>
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
                          {new Date(transaction.transaction_date).toLocaleDateString()}
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
                            {!transaction.is_reconciled && (
                              <DropdownMenuItem
                                onClick={() => handleReconcile(transaction)}
                              >
                                <CheckCircle className="mr-2 h-4 w-4" />
                                Mark Reconciled
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
                  ))}
                </TableBody>
              </Table>
            </div>

            {/* Pagination */}
            {transactions.last_page > 1 && (
              <div className="flex justify-center mt-4 gap-2">
                {Array.from({ length: transactions.last_page }, (_, i) => i + 1).map(
                  (page) => (
                    <Button
                      key={page}
                      variant={page === transactions.current_page ? 'default' : 'outline'}
                      size="sm"
                      onClick={() =>
                        router.get(route('dashboard.finance.transactions.index'), {
                          ...filters,
                          page,
                        })
                      }
                    >
                      {page}
                    </Button>
                  )
                )}
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
          onOpenChange={setShowForm}
          accounts={accounts}
          categories={categories}
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
      </Main>
    </AuthenticatedLayout>
  )
}
