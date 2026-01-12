import { Link, useForm, router } from '@inertiajs/react'
import { format } from 'date-fns'
import { AuthenticatedLayout } from '@/layouts'
import { Main } from '@/components/layout/main'
import { Button } from '@/components/ui/button'
import { DatePicker } from '@/components/ui/date-picker'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Textarea } from '@/components/ui/textarea'
import {
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from '@/components/ui/card'
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select'
import { Tabs, TabsList, TabsTrigger } from '@/components/ui/tabs'
import { ArrowLeft } from 'lucide-react'
import type { Account, Category, Transaction, TransactionType } from '@modules/Finance/types/finance'

interface Props {
  transaction: Transaction
  accounts: Account[]
  categories: Category[]
}

export default function EditTransaction({ transaction, accounts, categories }: Props) {
  const { data, setData, put, processing, errors, transform } = useForm({
    type: (transaction.type || 'expense') as TransactionType,
    account_id: transaction.account_id ? String(transaction.account_id) : '',
    category_id: transaction.category_id ? String(transaction.category_id) : '',
    amount: transaction.amount ? String(transaction.amount) : '',
    description: transaction.description || '',
    notes: transaction.notes || '',
    transaction_date: transaction.transaction_date?.split('T')[0] || new Date().toISOString().split('T')[0],
    transfer_account_id: transaction.transfer_account_id ? String(transaction.transfer_account_id) : '',
  })

  const incomeCategories = categories.filter((c) => c.type === 'income' || c.type === 'both')
  const expenseCategories = categories.filter((c) => c.type === 'expense' || c.type === 'both')
  const currentCategories = data.type === 'income' ? incomeCategories : expenseCategories

  transform((formData) => ({
    ...formData,
    amount: parseFloat(formData.amount || '0'),
    account_id: formData.account_id ? parseInt(formData.account_id) : null,
    category_id: formData.category_id ? parseInt(formData.category_id) : null,
    transfer_account_id: formData.transfer_account_id ? parseInt(formData.transfer_account_id) : null,
  }))

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault()
    put(route('dashboard.finance.transactions.update', transaction.id))
  }

  const handleTypeChange = (type: TransactionType) => {
    setData('type', type)
    setData('category_id', '')
    if (type !== 'transfer') {
      setData('transfer_account_id', '')
    }
  }

  return (
    <AuthenticatedLayout title="Edit Transaction">
      <Main>
        <div className="mb-4">
          <Link
            href={route('dashboard.finance.transactions.index')}
            className="inline-flex items-center text-sm text-muted-foreground hover:text-foreground"
          >
            <ArrowLeft className="mr-2 h-4 w-4" />
            Back to Transactions
          </Link>
        </div>

        <Card className="max-w-2xl">
          <CardHeader>
            <CardTitle>Edit Transaction</CardTitle>
            <CardDescription>
              Update transaction details
            </CardDescription>
          </CardHeader>
          <CardContent>
            <form onSubmit={handleSubmit} className="space-y-4">
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

              <div className="flex gap-3 pt-4">
                <Button
                  type="button"
                  variant="outline"
                  onClick={() => router.visit(route('dashboard.finance.transactions.index'))}
                  disabled={processing}
                >
                  Cancel
                </Button>
                <Button type="submit" disabled={processing}>
                  {processing ? 'Saving...' : 'Update Transaction'}
                </Button>
              </div>
            </form>
          </CardContent>
        </Card>
      </Main>
    </AuthenticatedLayout>
  )
}
