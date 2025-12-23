# Phase 11: Frontend - Transactions Module

## Context
- Parent plan: [plan.md](../plan.md)
- Dependencies: Phase 10 (accounts needed for dropdowns)

## Overview
- Priority: high
- Status: pending
- Description: Build transactions list with advanced filtering, create form with transaction type variants (income/expense/transfer).

## Key Insights
From research: Use @tanstack/react-table for filtering/sorting. Form shows/hides fields based on transaction_type.

## Requirements
### Functional
- Transactions list with date range, account, category, type filters
- Create transaction form adapts to type (transfer shows target account)
- Reconcile action
- Edit and delete with confirmation

### Non-functional
- Debounced search
- Date picker for transaction date
- Server-side pagination

## Related Code Files
### Files to Create
```
resources/js/pages/mokey/
├── transactions.tsx
├── create-transaction.tsx
├── edit-transaction.tsx
└── components/
    ├── transaction-form.tsx
    ├── transaction-filters.tsx
    └── transaction-type-select.tsx
```

## Implementation Steps

### 1. Create Transaction Form component
```tsx
// resources/js/pages/mokey/components/transaction-form.tsx
import { useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { z } from 'zod'
import { router } from '@inertiajs/react'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Textarea } from '@/components/ui/textarea'
import {
  Select, SelectContent, SelectItem, SelectTrigger, SelectValue,
} from '@/components/ui/select'
import { Calendar } from '@/components/ui/calendar'
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover'
import { format } from 'date-fns'
import { IconCalendar } from '@tabler/icons-react'
import { cn } from '@/lib/utils'
import { Account, Category, Transaction } from '../types/mokey'

const transactionSchema = z.object({
  account_id: z.number().min(1, 'Select an account'),
  category_id: z.number().nullable(),
  transfer_account_id: z.number().nullable(),
  transaction_type: z.enum(['income', 'expense', 'transfer']),
  amount: z.number().positive('Amount must be positive'),
  currency_code: z.string().length(3),
  description: z.string().max(255).optional(),
  notes: z.string().max(2000).optional(),
  transaction_date: z.date(),
}).refine((data) => {
  if (data.transaction_type === 'transfer' && !data.transfer_account_id) {
    return false
  }
  return true
}, { message: 'Transfer account required', path: ['transfer_account_id'] })
.refine((data) => {
  if (data.transaction_type === 'transfer' && data.transfer_account_id === data.account_id) {
    return false
  }
  return true
}, { message: 'Cannot transfer to same account', path: ['transfer_account_id'] })

type TransactionFormData = z.infer<typeof transactionSchema>

interface TransactionFormProps {
  transaction?: Transaction
  accounts: Account[]
  categories: Category[]
}

export function TransactionForm({ transaction, accounts, categories }: TransactionFormProps) {
  const isEditing = !!transaction

  const { register, handleSubmit, setValue, watch, formState: { errors, isSubmitting } } = useForm<TransactionFormData>({
    resolver: zodResolver(transactionSchema),
    defaultValues: {
      account_id: transaction?.account_id ?? 0,
      category_id: transaction?.category_id ?? null,
      transfer_account_id: transaction?.transfer_account_id ?? null,
      transaction_type: transaction?.transaction_type ?? 'expense',
      amount: transaction ? transaction.amount / 100 : 0,
      currency_code: transaction?.currency_code ?? accounts[0]?.currency_code ?? 'USD',
      description: transaction?.description ?? '',
      notes: transaction?.notes ?? '',
      transaction_date: transaction ? new Date(transaction.transaction_date) : new Date(),
    },
  })

  const transactionType = watch('transaction_type')
  const selectedAccountId = watch('account_id')

  // Filter categories by type
  const filteredCategories = categories.filter((cat) =>
    transactionType === 'transfer' ? false : cat.type === transactionType
  )

  // Filter transfer accounts (exclude selected account)
  const transferAccounts = accounts.filter((acc) => acc.id !== selectedAccountId)

  const onSubmit = (data: TransactionFormData) => {
    const payload = {
      ...data,
      amount: Math.round(data.amount * 100), // Convert to cents
      transaction_date: format(data.transaction_date, 'yyyy-MM-dd'),
      category_id: data.transaction_type === 'transfer' ? null : data.category_id,
      transfer_account_id: data.transaction_type === 'transfer' ? data.transfer_account_id : null,
    }

    if (isEditing) {
      router.put(route('dashboard.mokey.transactions.update', transaction.id), payload)
    } else {
      router.post(route('dashboard.mokey.transactions.store'), payload)
    }
  }

  return (
    <form onSubmit={handleSubmit(onSubmit)} className="space-y-6">
      {/* Transaction Type Selection */}
      <div className="flex gap-2">
        {['income', 'expense', 'transfer'].map((type) => (
          <Button
            key={type}
            type="button"
            variant={transactionType === type ? 'default' : 'outline'}
            onClick={() => setValue('transaction_type', type as any)}
            className={cn(
              type === 'income' && transactionType === type && 'bg-green-600',
              type === 'expense' && transactionType === type && 'bg-red-600',
              type === 'transfer' && transactionType === type && 'bg-blue-600'
            )}
          >
            {type.charAt(0).toUpperCase() + type.slice(1)}
          </Button>
        ))}
      </div>

      <div className="grid gap-4 md:grid-cols-2">
        {/* From Account */}
        <div className="space-y-2">
          <Label>{transactionType === 'transfer' ? 'From Account' : 'Account'}</Label>
          <Select
            value={watch('account_id')?.toString()}
            onValueChange={(val) => setValue('account_id', parseInt(val))}
          >
            <SelectTrigger>
              <SelectValue placeholder="Select account" />
            </SelectTrigger>
            <SelectContent>
              {accounts.map((account) => (
                <SelectItem key={account.id} value={account.id.toString()}>
                  {account.name} ({account.currency_code})
                </SelectItem>
              ))}
            </SelectContent>
          </Select>
          {errors.account_id && <p className="text-sm text-red-500">{errors.account_id.message}</p>}
        </div>

        {/* To Account (Transfer only) */}
        {transactionType === 'transfer' && (
          <div className="space-y-2">
            <Label>To Account</Label>
            <Select
              value={watch('transfer_account_id')?.toString() ?? ''}
              onValueChange={(val) => setValue('transfer_account_id', parseInt(val))}
            >
              <SelectTrigger>
                <SelectValue placeholder="Select destination" />
              </SelectTrigger>
              <SelectContent>
                {transferAccounts.map((account) => (
                  <SelectItem key={account.id} value={account.id.toString()}>
                    {account.name} ({account.currency_code})
                  </SelectItem>
                ))}
              </SelectContent>
            </Select>
            {errors.transfer_account_id && <p className="text-sm text-red-500">{errors.transfer_account_id.message}</p>}
          </div>
        )}

        {/* Category (Income/Expense only) */}
        {transactionType !== 'transfer' && (
          <div className="space-y-2">
            <Label>Category</Label>
            <Select
              value={watch('category_id')?.toString() ?? ''}
              onValueChange={(val) => setValue('category_id', val ? parseInt(val) : null)}
            >
              <SelectTrigger>
                <SelectValue placeholder="Select category (optional)" />
              </SelectTrigger>
              <SelectContent>
                {filteredCategories.map((category) => (
                  <SelectItem key={category.id} value={category.id.toString()}>
                    {category.name}
                  </SelectItem>
                ))}
              </SelectContent>
            </Select>
          </div>
        )}

        {/* Amount */}
        <div className="space-y-2">
          <Label>Amount</Label>
          <Input
            type="number"
            step="0.01"
            min="0.01"
            {...register('amount', { valueAsNumber: true })}
          />
          {errors.amount && <p className="text-sm text-red-500">{errors.amount.message}</p>}
        </div>

        {/* Transaction Date */}
        <div className="space-y-2">
          <Label>Date</Label>
          <Popover>
            <PopoverTrigger asChild>
              <Button variant="outline" className={cn('w-full justify-start text-left font-normal')}>
                <IconCalendar className="mr-2 h-4 w-4" />
                {watch('transaction_date') ? format(watch('transaction_date'), 'PPP') : 'Pick a date'}
              </Button>
            </PopoverTrigger>
            <PopoverContent className="w-auto p-0">
              <Calendar
                mode="single"
                selected={watch('transaction_date')}
                onSelect={(date) => date && setValue('transaction_date', date)}
                initialFocus
              />
            </PopoverContent>
          </Popover>
        </div>

        {/* Description */}
        <div className="space-y-2 md:col-span-2">
          <Label>Description</Label>
          <Input {...register('description')} placeholder="e.g., Grocery shopping" />
        </div>

        {/* Notes */}
        <div className="space-y-2 md:col-span-2">
          <Label>Notes (optional)</Label>
          <Textarea {...register('notes')} rows={3} />
        </div>
      </div>

      <div className="flex gap-4">
        <Button type="submit" disabled={isSubmitting}>
          {isEditing ? 'Update Transaction' : 'Create Transaction'}
        </Button>
        <Button type="button" variant="outline" onClick={() => router.get(route('dashboard.mokey.transactions.index'))}>
          Cancel
        </Button>
      </div>
    </form>
  )
}
```

### 2. Create Transaction Filters component
```tsx
// resources/js/pages/mokey/components/transaction-filters.tsx
import { useState } from 'react'
import { router } from '@inertiajs/react'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { Calendar } from '@/components/ui/calendar'
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover'
import { format } from 'date-fns'
import { IconCalendar, IconX } from '@tabler/icons-react'
import { Account, Category, TransactionFilters } from '../types/mokey'

interface TransactionFiltersProps {
  filters: TransactionFilters
  accounts: Account[]
  categories: Category[]
}

export function TransactionFiltersComponent({ filters, accounts, categories }: TransactionFiltersProps) {
  const [localFilters, setLocalFilters] = useState(filters)

  const applyFilters = (newFilters: Partial<TransactionFilters>) => {
    const updated = { ...localFilters, ...newFilters }
    setLocalFilters(updated)
    router.get(route('dashboard.mokey.transactions.index'), updated, { preserveState: true, replace: true })
  }

  const clearFilters = () => {
    setLocalFilters({})
    router.get(route('dashboard.mokey.transactions.index'), {}, { preserveState: true, replace: true })
  }

  return (
    <div className="flex flex-wrap gap-4 mb-6">
      <Input
        placeholder="Search description..."
        value={localFilters.search || ''}
        onChange={(e) => applyFilters({ search: e.target.value })}
        className="w-64"
      />

      <Select value={localFilters.type || ''} onValueChange={(val) => applyFilters({ type: val || undefined })}>
        <SelectTrigger className="w-40">
          <SelectValue placeholder="All Types" />
        </SelectTrigger>
        <SelectContent>
          <SelectItem value="">All Types</SelectItem>
          <SelectItem value="income">Income</SelectItem>
          <SelectItem value="expense">Expense</SelectItem>
          <SelectItem value="transfer">Transfer</SelectItem>
        </SelectContent>
      </Select>

      <Select value={localFilters.account_id?.toString() || ''} onValueChange={(val) => applyFilters({ account_id: val ? parseInt(val) : undefined })}>
        <SelectTrigger className="w-48">
          <SelectValue placeholder="All Accounts" />
        </SelectTrigger>
        <SelectContent>
          <SelectItem value="">All Accounts</SelectItem>
          {accounts.map((acc) => (
            <SelectItem key={acc.id} value={acc.id.toString()}>{acc.name}</SelectItem>
          ))}
        </SelectContent>
      </Select>

      <Select value={localFilters.category_id?.toString() || ''} onValueChange={(val) => applyFilters({ category_id: val ? parseInt(val) : undefined })}>
        <SelectTrigger className="w-48">
          <SelectValue placeholder="All Categories" />
        </SelectTrigger>
        <SelectContent>
          <SelectItem value="">All Categories</SelectItem>
          {categories.map((cat) => (
            <SelectItem key={cat.id} value={cat.id.toString()}>{cat.name}</SelectItem>
          ))}
        </SelectContent>
      </Select>

      {Object.keys(localFilters).length > 0 && (
        <Button variant="ghost" onClick={clearFilters}>
          <IconX className="mr-2 h-4 w-4" /> Clear
        </Button>
      )}
    </div>
  )
}
```

### 3. Create Transactions List page
```tsx
// resources/js/pages/mokey/transactions.tsx
import { AuthenticatedLayout } from '@/layouts'
import { Main } from '@/components/layout'
import { usePage } from '@inertiajs/react'
import { router } from '@inertiajs/react'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table'
import { Badge } from '@/components/ui/badge'
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from '@/components/ui/dropdown-menu'
import { IconPlus, IconDotsVertical, IconCheck, IconEdit, IconTrash } from '@tabler/icons-react'
import { TransactionFiltersComponent } from './components/transaction-filters'
import { Transaction, Account, Category, TransactionFilters } from './types/mokey'
import { PageProps } from '@/types'
import { useToast } from '@/hooks/use-toast'

interface TransactionsPageProps extends PageProps {
  transactions: { data: Transaction[]; current_page: number; last_page: number; total: number }
  filters: TransactionFilters
  accounts: Account[]
  categories: Category[]
}

export default function TransactionsPage() {
  const { transactions, filters, accounts, categories } = usePage<TransactionsPageProps>().props
  const { toast } = useToast()

  const handleReconcile = (transaction: Transaction) => {
    router.post(route('dashboard.mokey.transactions.reconcile', transaction.id), {}, {
      onSuccess: () => toast({ title: 'Transaction reconciled' }),
    })
  }

  const handleDelete = (transaction: Transaction) => {
    if (confirm('Delete this transaction?')) {
      router.delete(route('dashboard.mokey.transactions.destroy', transaction.id), {
        onSuccess: () => toast({ title: 'Transaction deleted' }),
      })
    }
  }

  const getTypeBadge = (type: string) => {
    const styles: Record<string, string> = {
      income: 'bg-green-100 text-green-800',
      expense: 'bg-red-100 text-red-800',
      transfer: 'bg-blue-100 text-blue-800',
    }
    return <Badge className={styles[type]}>{type}</Badge>
  }

  return (
    <AuthenticatedLayout title="Transactions">
      <Main>
        <div className="flex items-center justify-between mb-6">
          <div>
            <h2 className="text-3xl font-bold tracking-tight">Transactions</h2>
            <p className="text-muted-foreground">Track your income, expenses, and transfers</p>
          </div>
          <Button onClick={() => router.get(route('dashboard.mokey.transactions.create'))}>
            <IconPlus className="mr-2 h-4 w-4" /> Add Transaction
          </Button>
        </div>

        <TransactionFiltersComponent filters={filters} accounts={accounts} categories={categories} />

        <Card>
          <CardHeader>
            <CardTitle>All Transactions ({transactions.total})</CardTitle>
          </CardHeader>
          <CardContent>
            <Table>
              <TableHeader>
                <TableRow>
                  <TableHead>Date</TableHead>
                  <TableHead>Description</TableHead>
                  <TableHead>Type</TableHead>
                  <TableHead>Category</TableHead>
                  <TableHead>Account</TableHead>
                  <TableHead className="text-right">Amount</TableHead>
                  <TableHead>Status</TableHead>
                  <TableHead><span className="sr-only">Actions</span></TableHead>
                </TableRow>
              </TableHeader>
              <TableBody>
                {transactions.data.map((txn) => (
                  <TableRow key={txn.id}>
                    <TableCell>{txn.transaction_date}</TableCell>
                    <TableCell>{txn.description || '-'}</TableCell>
                    <TableCell>{getTypeBadge(txn.transaction_type)}</TableCell>
                    <TableCell>{txn.category?.name || '-'}</TableCell>
                    <TableCell>{txn.account?.name}</TableCell>
                    <TableCell className={`text-right font-mono ${txn.transaction_type === 'income' ? 'text-green-600' : txn.transaction_type === 'expense' ? 'text-red-600' : ''}`}>
                      {txn.transaction_type === 'income' ? '+' : '-'}{txn.amount_formatted}
                    </TableCell>
                    <TableCell>
                      {txn.reconciled ? (
                        <Badge variant="outline" className="text-green-600"><IconCheck className="h-3 w-3 mr-1" /> Reconciled</Badge>
                      ) : (
                        <Badge variant="secondary">Pending</Badge>
                      )}
                    </TableCell>
                    <TableCell>
                      <DropdownMenu>
                        <DropdownMenuTrigger asChild>
                          <Button variant="ghost" size="icon"><IconDotsVertical className="h-4 w-4" /></Button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="end">
                          {!txn.reconciled && (
                            <DropdownMenuItem onClick={() => handleReconcile(txn)}>
                              <IconCheck className="mr-2 h-4 w-4" /> Reconcile
                            </DropdownMenuItem>
                          )}
                          <DropdownMenuItem onClick={() => router.get(route('dashboard.mokey.transactions.edit', txn.id))}>
                            <IconEdit className="mr-2 h-4 w-4" /> Edit
                          </DropdownMenuItem>
                          <DropdownMenuItem className="text-red-600" onClick={() => handleDelete(txn)}>
                            <IconTrash className="mr-2 h-4 w-4" /> Delete
                          </DropdownMenuItem>
                        </DropdownMenuContent>
                      </DropdownMenu>
                    </TableCell>
                  </TableRow>
                ))}
              </TableBody>
            </Table>
          </CardContent>
        </Card>
      </Main>
    </AuthenticatedLayout>
  )
}
```

## Todo List
- [ ] Add Transaction types to mokey.ts
- [ ] Create TransactionForm with type switching
- [ ] Create TransactionFilters component
- [ ] Create transactions.tsx list page
- [ ] Create create-transaction.tsx page
- [ ] Create edit-transaction.tsx page
- [ ] Add date picker integration
- [ ] Add reconcile functionality
- [ ] Test transfer validation
- [ ] Add pagination

## Success Criteria
- [ ] Form adapts to transaction type
- [ ] Filters work correctly
- [ ] Reconcile marks transaction
- [ ] Amount shows correct sign and color
- [ ] Transfer validates different accounts

## Risk Assessment
- **Risk:** Date picker timezone issues. **Mitigation:** Format as YYYY-MM-DD on submit.
- **Risk:** Transfer to same account. **Mitigation:** Zod validation and UI filtering.

## Security Considerations
- Only show user's own accounts in dropdowns
- Prevent editing reconciled transactions unless permitted

## Next Steps
Proceed to Phase 12: Frontend - Budgets Module
