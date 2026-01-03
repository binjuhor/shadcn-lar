import { useForm } from '@inertiajs/react'
import { format } from 'date-fns'
import { Button } from '@/components/ui/button'
import { DatePicker } from '@/components/ui/date-picker'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Textarea } from '@/components/ui/textarea'
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select'
import {
  Sheet,
  SheetContent,
  SheetDescription,
  SheetHeader,
  SheetTitle,
  SheetFooter,
} from '@/components/ui/sheet'
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs'
import type { Account, Category, TransactionType } from '@modules/Finance/types/finance'

interface TransactionFormProps {
  open: boolean
  onOpenChange: (open: boolean) => void
  accounts: Account[]
  categories: Category[]
  onSuccess?: () => void
}

export function TransactionForm({
  open,
  onOpenChange,
  accounts,
  categories,
  onSuccess,
}: TransactionFormProps) {
  const { data, setData, post, processing, errors, reset } = useForm({
    type: 'expense' as TransactionType,
    account_id: '',
    category_id: '',
    amount: '',
    description: '',
    notes: '',
    transaction_date: new Date().toISOString().split('T')[0],
    transfer_account_id: '',
  })

  const incomeCategories = categories.filter((c) => c.type === 'income')
  const expenseCategories = categories.filter((c) => c.type === 'expense')
  const currentCategories = data.type === 'income' ? incomeCategories : expenseCategories

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault()

    const formData = {
      ...data,
      amount: Math.round(parseFloat(data.amount || '0')),
      account_id: parseInt(data.account_id),
      category_id: data.category_id ? parseInt(data.category_id) : null,
      transfer_account_id: data.transfer_account_id ? parseInt(data.transfer_account_id) : null,
    }

    post(route('dashboard.finance.transactions.store'), {
      ...formData,
      onSuccess: () => {
        reset()
        onOpenChange(false)
        onSuccess?.()
      },
    })
  }

  const handleClose = () => {
    reset()
    onOpenChange(false)
  }

  const handleTypeChange = (type: TransactionType) => {
    setData('type', type)
    setData('category_id', '')
    if (type !== 'transfer') {
      setData('transfer_account_id', '')
    }
  }

  return (
    <Sheet open={open} onOpenChange={onOpenChange}>
      <SheetContent className="overflow-y-auto">
        <SheetHeader>
          <SheetTitle>New Transaction</SheetTitle>
          <SheetDescription>
            Record a new financial transaction
          </SheetDescription>
        </SheetHeader>

        <form onSubmit={handleSubmit} className="space-y-4 mt-4">
          {/* Transaction Type Tabs */}
          <Tabs value={data.type} onValueChange={(v) => handleTypeChange(v as TransactionType)}>
            <TabsList className="grid w-full grid-cols-3">
              <TabsTrigger value="expense" className="text-red-600 data-[state=active]:bg-red-100">
                Expense
              </TabsTrigger>
              <TabsTrigger value="income" className="text-green-600 data-[state=active]:bg-green-100">
                Income
              </TabsTrigger>
              <TabsTrigger value="transfer" className="text-blue-600 data-[state=active]:bg-blue-100">
                Transfer
              </TabsTrigger>
            </TabsList>
          </Tabs>

          <div className="space-y-2">
            <Label htmlFor="amount">Amount</Label>
            <Input
              id="amount"
              type="number"
              step="0.01"
              min="0"
              value={data.amount}
              onChange={(e) => setData('amount', e.target.value)}
              placeholder="0.00"
              className="text-2xl font-bold h-14"
            />
            {errors.amount && (
              <p className="text-sm text-red-600">{errors.amount}</p>
            )}
          </div>

          <div className="space-y-2">
            <Label htmlFor="account_id">
              {data.type === 'transfer' ? 'From Account' : 'Account'}
            </Label>
            <Select
              value={data.account_id}
              onValueChange={(value) => setData('account_id', value)}
            >
              <SelectTrigger>
                <SelectValue placeholder="Select account" />
              </SelectTrigger>
              <SelectContent>
                {accounts.filter(a => a.is_active).map((account) => (
                  <SelectItem key={account.id} value={String(account.id)}>
                    {account.name}
                  </SelectItem>
                ))}
              </SelectContent>
            </Select>
            {errors.account_id && (
              <p className="text-sm text-red-600">{errors.account_id}</p>
            )}
          </div>

          {data.type === 'transfer' && (
            <div className="space-y-2">
              <Label htmlFor="transfer_account_id">To Account</Label>
              <Select
                value={data.transfer_account_id}
                onValueChange={(value) => setData('transfer_account_id', value)}
              >
                <SelectTrigger>
                  <SelectValue placeholder="Select destination account" />
                </SelectTrigger>
                <SelectContent>
                  {accounts
                    .filter(a => a.is_active && String(a.id) !== data.account_id)
                    .map((account) => (
                      <SelectItem key={account.id} value={String(account.id)}>
                        {account.name}
                      </SelectItem>
                    ))}
                </SelectContent>
              </Select>
              {errors.transfer_account_id && (
                <p className="text-sm text-red-600">{errors.transfer_account_id}</p>
              )}
            </div>
          )}

          {data.type !== 'transfer' && (
            <div className="space-y-2">
              <Label htmlFor="category_id">Category</Label>
              <Select
                value={data.category_id}
                onValueChange={(value) => setData('category_id', value)}
              >
                <SelectTrigger>
                  <SelectValue placeholder="Select category" />
                </SelectTrigger>
                <SelectContent>
                  {currentCategories.map((category) => (
                    <SelectItem key={category.id} value={String(category.id)}>
                      {category.name}
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
              {errors.category_id && (
                <p className="text-sm text-red-600">{errors.category_id}</p>
              )}
            </div>
          )}

          <div className="space-y-2">
            <Label>Date</Label>
            <DatePicker
              value={data.transaction_date}
              onChange={(date) => setData('transaction_date', date ? format(date, 'yyyy-MM-dd') : '')}
              placeholder="Select date"
            />
            {errors.transaction_date && (
              <p className="text-sm text-red-600">{errors.transaction_date}</p>
            )}
          </div>

          <div className="space-y-2">
            <Label htmlFor="description">Description</Label>
            <Input
              id="description"
              value={data.description}
              onChange={(e) => setData('description', e.target.value)}
              placeholder="e.g., Grocery shopping"
            />
            {errors.description && (
              <p className="text-sm text-red-600">{errors.description}</p>
            )}
          </div>

          <div className="space-y-2">
            <Label htmlFor="notes">Notes (Optional)</Label>
            <Textarea
              id="notes"
              value={data.notes}
              onChange={(e) => setData('notes', e.target.value)}
              placeholder="Additional notes..."
              rows={2}
            />
          </div>

          <SheetFooter className="gap-2 pt-4">
            <Button
              type="button"
              variant="outline"
              onClick={handleClose}
              disabled={processing}
            >
              Cancel
            </Button>
            <Button type="submit" disabled={processing}>
              {processing ? 'Saving...' : 'Save Transaction'}
            </Button>
          </SheetFooter>
        </form>
      </SheetContent>
    </Sheet>
  )
}
