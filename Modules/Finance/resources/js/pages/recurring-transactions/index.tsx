import { Link, router } from '@inertiajs/react'
import { useState } from 'react'
import { AuthenticatedLayout } from '@/layouts'
import { Main } from '@/components/layout/main'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
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
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs'
import {
  ArrowDownLeft,
  ArrowLeft,
  ArrowUpRight,
  Calendar,
  Plus,
  RefreshCw,
  Target,
  TrendingDown,
  TrendingUp,
  Wallet,
} from 'lucide-react'
import { RecurringCard } from './components/recurring-card'
import { RecurringForm } from './components/recurring-form'
import type { Account, Category, RecurringTransaction, MonthlyProjection } from '@modules/Finance/types/finance'

interface Props {
  recurrings: RecurringTransaction[]
  upcoming: RecurringTransaction[]
  projection: MonthlyProjection
  accounts: Account[]
  categories: Category[]
}

function formatMoney(amount: number, currencyCode = 'VND'): string {
  return new Intl.NumberFormat('vi-VN', {
    style: 'currency',
    currency: currencyCode,
    maximumFractionDigits: 0,
  }).format(amount)
}

export default function RecurringTransactionsIndex({
  recurrings,
  upcoming,
  projection,
  accounts,
  categories,
}: Props) {
  const [formOpen, setFormOpen] = useState(false)
  const [editingRecurring, setEditingRecurring] = useState<RecurringTransaction | null>(null)
  const [deletingRecurring, setDeletingRecurring] = useState<RecurringTransaction | null>(null)

  const activeRecurrings = recurrings.filter((r) => r.is_active)
  const pausedRecurrings = recurrings.filter((r) => !r.is_active)

  const incomeRecurrings = activeRecurrings.filter((r) => r.transaction_type === 'income')
  const expenseRecurrings = activeRecurrings.filter((r) => r.transaction_type === 'expense')

  const handleEdit = (recurring: RecurringTransaction) => {
    setEditingRecurring(recurring)
    setFormOpen(true)
  }

  const handleDelete = (recurring: RecurringTransaction) => {
    setDeletingRecurring(recurring)
  }

  const confirmDelete = () => {
    if (deletingRecurring) {
      router.delete(route('dashboard.finance.recurring-transactions.destroy', deletingRecurring.id), {
        preserveScroll: true,
      })
    }
    setDeletingRecurring(null)
  }

  const handleProcess = () => {
    router.post(route('dashboard.finance.recurring-transactions.process'), {}, {
      preserveScroll: true,
    })
  }

  const handleFormClose = () => {
    setFormOpen(false)
    setEditingRecurring(null)
  }

  return (
    <AuthenticatedLayout title="Recurring Transactions">
      <Main>
        <div className="mb-4">
          <Link
            href={route('dashboard.finance.index')}
            className="inline-flex items-center text-sm text-muted-foreground hover:text-foreground"
          >
            <ArrowLeft className="mr-2 h-4 w-4" />
            Back to Dashboard
          </Link>
        </div>

        <div className="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
          <div>
            <h1 className="text-2xl font-bold tracking-tight">Recurring Transactions</h1>
            <p className="text-muted-foreground">
              Manage your scheduled income and expenses
            </p>
          </div>
          <div className="flex gap-2">
            <Button variant="outline" onClick={handleProcess}>
              <RefreshCw className="mr-2 h-4 w-4" />
              Process Due
            </Button>
            <Button onClick={() => setFormOpen(true)}>
              <Plus className="mr-2 h-4 w-4" />
              New Recurring
            </Button>
          </div>
        </div>

        {/* Monthly Projection Summary */}
        <div className="grid gap-3 grid-cols-2 lg:grid-cols-5 mb-6">
          <Card className="p-3">
            <div className="flex items-center justify-between">
              <span className="text-xs text-muted-foreground">Monthly Income</span>
              <ArrowDownLeft className="h-3 w-3 text-green-600" />
            </div>
            <div className="text-lg font-bold text-green-600 mt-1">
              {formatMoney(projection.monthly_income, projection.currency_code)}
            </div>
          </Card>

          <Card className="p-3">
            <div className="flex items-center justify-between">
              <span className="text-xs text-muted-foreground">Monthly Expense</span>
              <ArrowUpRight className="h-3 w-3 text-red-600" />
            </div>
            <div className="text-lg font-bold text-red-600 mt-1">
              {formatMoney(projection.monthly_expense, projection.currency_code)}
            </div>
          </Card>

          <Card className="p-3">
            <div className="flex items-center justify-between">
              <span className="text-xs text-muted-foreground">Net Monthly</span>
              {projection.monthly_net >= 0 ? (
                <TrendingUp className="h-3 w-3 text-green-600" />
              ) : (
                <TrendingDown className="h-3 w-3 text-red-600" />
              )}
            </div>
            <div className={`text-lg font-bold mt-1 ${projection.monthly_net >= 0 ? 'text-green-600' : 'text-red-600'}`}>
              {projection.monthly_net >= 0 ? '+' : ''}{formatMoney(projection.monthly_net, projection.currency_code)}
            </div>
          </Card>

          <Card className="p-3">
            <div className="flex items-center justify-between">
              <span className="text-xs text-muted-foreground">Passive Income</span>
              <Wallet className="h-3 w-3 text-blue-600" />
            </div>
            <div className="text-lg font-bold text-blue-600 mt-1">
              {formatMoney(projection.monthly_passive_income, projection.currency_code)}
            </div>
          </Card>

          <Card className="p-3">
            <div className="flex items-center justify-between">
              <span className="text-xs text-muted-foreground">Passive Coverage</span>
              <Target className="h-3 w-3 text-purple-600" />
            </div>
            <div className="text-lg font-bold text-purple-600 mt-1">
              {projection.passive_coverage}%
            </div>
          </Card>
        </div>

        {/* Recurring Transactions List */}
        <Tabs defaultValue="all" className="space-y-4">
          <TabsList>
            <TabsTrigger value="all">All ({recurrings.length})</TabsTrigger>
            <TabsTrigger value="income">Income ({incomeRecurrings.length})</TabsTrigger>
            <TabsTrigger value="expense">Expense ({expenseRecurrings.length})</TabsTrigger>
            {pausedRecurrings.length > 0 && (
              <TabsTrigger value="paused">Paused ({pausedRecurrings.length})</TabsTrigger>
            )}
          </TabsList>

          <TabsContent value="all" className="space-y-3">
            {recurrings.length === 0 ? (
              <Card className="p-8 text-center">
                <Calendar className="h-12 w-12 mx-auto text-muted-foreground mb-4" />
                <h3 className="font-semibold mb-2">No recurring transactions</h3>
                <p className="text-sm text-muted-foreground mb-4">
                  Set up recurring transactions to automate your income and expense tracking
                </p>
                <Button onClick={() => setFormOpen(true)}>
                  <Plus className="mr-2 h-4 w-4" />
                  Create First Recurring
                </Button>
              </Card>
            ) : (
              recurrings.map((recurring) => (
                <RecurringCard
                  key={recurring.id}
                  recurring={recurring}
                  onEdit={handleEdit}
                  onDelete={handleDelete}
                />
              ))
            )}
          </TabsContent>

          <TabsContent value="income" className="space-y-3">
            {incomeRecurrings.length === 0 ? (
              <Card className="p-8 text-center">
                <p className="text-muted-foreground">No recurring income set up</p>
              </Card>
            ) : (
              incomeRecurrings.map((recurring) => (
                <RecurringCard
                  key={recurring.id}
                  recurring={recurring}
                  onEdit={handleEdit}
                  onDelete={handleDelete}
                />
              ))
            )}
          </TabsContent>

          <TabsContent value="expense" className="space-y-3">
            {expenseRecurrings.length === 0 ? (
              <Card className="p-8 text-center">
                <p className="text-muted-foreground">No recurring expenses set up</p>
              </Card>
            ) : (
              expenseRecurrings.map((recurring) => (
                <RecurringCard
                  key={recurring.id}
                  recurring={recurring}
                  onEdit={handleEdit}
                  onDelete={handleDelete}
                />
              ))
            )}
          </TabsContent>

          <TabsContent value="paused" className="space-y-3">
            {pausedRecurrings.map((recurring) => (
              <RecurringCard
                key={recurring.id}
                recurring={recurring}
                onEdit={handleEdit}
                onDelete={handleDelete}
              />
            ))}
          </TabsContent>
        </Tabs>

        {/* Form Modal */}
        <RecurringForm
          open={formOpen}
          onOpenChange={handleFormClose}
          recurring={editingRecurring}
          accounts={accounts}
          categories={categories}
        />

        {/* Delete Confirmation */}
        <AlertDialog open={!!deletingRecurring} onOpenChange={() => setDeletingRecurring(null)}>
          <AlertDialogContent>
            <AlertDialogHeader>
              <AlertDialogTitle>Delete Recurring Transaction</AlertDialogTitle>
              <AlertDialogDescription>
                Are you sure you want to delete "{deletingRecurring?.name}"?
                This will not affect any transactions already created.
              </AlertDialogDescription>
            </AlertDialogHeader>
            <AlertDialogFooter>
              <AlertDialogCancel>Cancel</AlertDialogCancel>
              <AlertDialogAction onClick={confirmDelete} className="bg-red-600 hover:bg-red-700">
                Delete
              </AlertDialogAction>
            </AlertDialogFooter>
          </AlertDialogContent>
        </AlertDialog>
      </Main>
    </AuthenticatedLayout>
  )
}
