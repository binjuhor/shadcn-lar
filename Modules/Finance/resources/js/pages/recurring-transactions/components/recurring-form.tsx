import { useForm } from '@inertiajs/react'
import { format } from 'date-fns'
import { useEffect } from 'react'
import { Button } from '@/components/ui/button'
import { DatePicker } from '@/components/ui/date-picker'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Switch } from '@/components/ui/switch'
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
import { Tabs, TabsList, TabsTrigger } from '@/components/ui/tabs'
import type { Account, Category, RecurringTransaction, RecurringFrequency } from '@modules/Finance/types/finance'

interface RecurringFormProps {
  open: boolean
  onOpenChange: (open: boolean) => void
  recurring?: RecurringTransaction | null
  accounts: Account[]
  categories: Category[]
  onSuccess?: () => void
}

const frequencies: { value: RecurringFrequency; label: string }[] = [
  { value: 'daily', label: 'Daily' },
  { value: 'weekly', label: 'Weekly' },
  { value: 'monthly', label: 'Monthly' },
  { value: 'yearly', label: 'Yearly' },
]

const daysOfWeek = [
  { value: 0, label: 'Sunday' },
  { value: 1, label: 'Monday' },
  { value: 2, label: 'Tuesday' },
  { value: 3, label: 'Wednesday' },
  { value: 4, label: 'Thursday' },
  { value: 5, label: 'Friday' },
  { value: 6, label: 'Saturday' },
]

const months = [
  { value: 1, label: 'January' },
  { value: 2, label: 'February' },
  { value: 3, label: 'March' },
  { value: 4, label: 'April' },
  { value: 5, label: 'May' },
  { value: 6, label: 'June' },
  { value: 7, label: 'July' },
  { value: 8, label: 'August' },
  { value: 9, label: 'September' },
  { value: 10, label: 'October' },
  { value: 11, label: 'November' },
  { value: 12, label: 'December' },
]

export function RecurringForm({
  open,
  onOpenChange,
  recurring,
  accounts,
  categories,
  onSuccess,
}: RecurringFormProps) {
  const isEditing = !!recurring

  const { data, setData, post, put, processing, errors, reset } = useForm({
    name: '',
    description: '',
    account_id: '',
    category_id: '',
    transaction_type: 'expense' as 'income' | 'expense',
    amount: '',
    frequency: 'monthly' as RecurringFrequency,
    day_of_week: '',
    day_of_month: '',
    month_of_year: '',
    start_date: new Date().toISOString().split('T')[0],
    end_date: '',
    is_active: true as boolean,
    auto_create: true as boolean,
  })

  useEffect(() => {
    if (recurring) {
      setData((prev) => ({
        ...prev,
        name: recurring.name || '',
        description: recurring.description || '',
        account_id: String(recurring.account_id),
        category_id: recurring.category_id ? String(recurring.category_id) : '',
        transaction_type: recurring.transaction_type,
        amount: String(recurring.amount),
        frequency: recurring.frequency,
        day_of_week: recurring.day_of_week !== null ? String(recurring.day_of_week) : '',
        day_of_month: recurring.day_of_month !== null ? String(recurring.day_of_month) : '',
        month_of_year: recurring.month_of_year !== null ? String(recurring.month_of_year) : '',
        start_date: recurring.start_date,
        end_date: recurring.end_date || '',
        is_active: recurring.is_active,
        auto_create: recurring.auto_create,
      }))
    } else {
      reset()
    }
  }, [recurring, open])

  const incomeCategories = categories.filter((c) => c.type === 'income' || c.type === 'both')
  const expenseCategories = categories.filter((c) => c.type === 'expense' || c.type === 'both')
  const currentCategories = data.transaction_type === 'income' ? incomeCategories : expenseCategories

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault()

    const formData = {
      ...data,
      amount: Math.round(parseFloat(data.amount || '0')),
      account_id: data.account_id ? parseInt(data.account_id) : null,
      category_id: data.category_id ? parseInt(data.category_id) : null,
      day_of_week: data.day_of_week ? parseInt(data.day_of_week) : null,
      day_of_month: data.day_of_month ? parseInt(data.day_of_month) : null,
      month_of_year: data.month_of_year ? parseInt(data.month_of_year) : null,
      end_date: data.end_date || null,
    }

    if (isEditing && recurring) {
      put(route('dashboard.finance.recurring-transactions.update', recurring.id), {
        ...formData,
        onSuccess: () => {
          reset()
          onOpenChange(false)
          onSuccess?.()
        },
      })
    } else {
      post(route('dashboard.finance.recurring-transactions.store'), {
        ...formData,
        onSuccess: () => {
          reset()
          onOpenChange(false)
          onSuccess?.()
        },
      })
    }
  }

  const handleClose = () => {
    reset()
    onOpenChange(false)
  }

  return (
    <Sheet open={open} onOpenChange={onOpenChange}>
      <SheetContent className="overflow-y-auto sm:max-w-lg">
        <SheetHeader>
          <SheetTitle>
            {isEditing ? 'Edit Recurring Transaction' : 'New Recurring Transaction'}
          </SheetTitle>
          <SheetDescription>
            {isEditing
              ? 'Update your recurring transaction settings'
              : 'Set up a recurring transaction that automatically creates transactions on schedule'}
          </SheetDescription>
        </SheetHeader>

        <form onSubmit={handleSubmit} className="space-y-4 mt-4">
          <Tabs
            value={data.transaction_type}
            onValueChange={(v) => {
              setData('transaction_type', v as 'income' | 'expense')
              setData('category_id', '')
            }}
          >
            <TabsList className="grid w-full grid-cols-2">
              <TabsTrigger
                value="expense"
                className="data-[state=active]:bg-red-500 data-[state=active]:text-white"
              >
                Expense
              </TabsTrigger>
              <TabsTrigger
                value="income"
                className="data-[state=active]:bg-green-500 data-[state=active]:text-white"
              >
                Income
              </TabsTrigger>
            </TabsList>
          </Tabs>

          <div className="space-y-2">
            <Label htmlFor="name">Name</Label>
            <Input
              id="name"
              value={data.name}
              onChange={(e) => setData('name', e.target.value)}
              placeholder="e.g., Monthly Rent, Salary"
            />
            {errors.name && <p className="text-sm text-red-600">{errors.name}</p>}
          </div>

          <div className="space-y-2">
            <Label htmlFor="amount">Amount</Label>
            <Input
              id="amount"
              type="number"
              min="0"
              value={data.amount}
              onChange={(e) => setData('amount', e.target.value)}
              placeholder="0"
              className="text-xl font-bold"
            />
            {errors.amount && <p className="text-sm text-red-600">{errors.amount}</p>}
          </div>

          <div className="space-y-2">
            <Label htmlFor="account_id">Account</Label>
            <Select
              value={data.account_id}
              onValueChange={(value) => setData('account_id', value)}
            >
              <SelectTrigger>
                <SelectValue placeholder="Select account" />
              </SelectTrigger>
              <SelectContent>
                {accounts.map((account) => (
                  <SelectItem key={account.id} value={String(account.id)}>
                    {account.name} ({account.currency_code})
                  </SelectItem>
                ))}
              </SelectContent>
            </Select>
            {errors.account_id && <p className="text-sm text-red-600">{errors.account_id}</p>}
          </div>

          <div className="space-y-2">
            <Label htmlFor="category_id">Category</Label>
            <Select
              value={data.category_id || '__none__'}
              onValueChange={(value) => setData('category_id', value === '__none__' ? '' : value)}
            >
              <SelectTrigger>
                <SelectValue placeholder="Select category" />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="__none__">No category</SelectItem>
                {currentCategories.map((category) => (
                  <SelectItem key={category.id} value={String(category.id)}>
                    {category.name}
                  </SelectItem>
                ))}
              </SelectContent>
            </Select>
          </div>

          <div className="space-y-2">
            <Label htmlFor="frequency">Frequency</Label>
            <Select
              value={data.frequency}
              onValueChange={(value) => setData('frequency', value as RecurringFrequency)}
            >
              <SelectTrigger>
                <SelectValue placeholder="Select frequency" />
              </SelectTrigger>
              <SelectContent>
                {frequencies.map((f) => (
                  <SelectItem key={f.value} value={f.value}>
                    {f.label}
                  </SelectItem>
                ))}
              </SelectContent>
            </Select>
          </div>

          {data.frequency === 'weekly' && (
            <div className="space-y-2">
              <Label>Day of Week</Label>
              <Select
                value={data.day_of_week}
                onValueChange={(value) => setData('day_of_week', value)}
              >
                <SelectTrigger>
                  <SelectValue placeholder="Select day" />
                </SelectTrigger>
                <SelectContent>
                  {daysOfWeek.map((d) => (
                    <SelectItem key={d.value} value={String(d.value)}>
                      {d.label}
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
            </div>
          )}

          {(data.frequency === 'monthly' || data.frequency === 'yearly') && (
            <div className="space-y-2">
              <Label>Day of Month</Label>
              <Select
                value={data.day_of_month}
                onValueChange={(value) => setData('day_of_month', value)}
              >
                <SelectTrigger>
                  <SelectValue placeholder="Select day" />
                </SelectTrigger>
                <SelectContent>
                  {Array.from({ length: 31 }, (_, i) => i + 1).map((d) => (
                    <SelectItem key={d} value={String(d)}>
                      {d}
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
            </div>
          )}

          {data.frequency === 'yearly' && (
            <div className="space-y-2">
              <Label>Month</Label>
              <Select
                value={data.month_of_year}
                onValueChange={(value) => setData('month_of_year', value)}
              >
                <SelectTrigger>
                  <SelectValue placeholder="Select month" />
                </SelectTrigger>
                <SelectContent>
                  {months.map((m) => (
                    <SelectItem key={m.value} value={String(m.value)}>
                      {m.label}
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
            </div>
          )}

          <div className="grid grid-cols-2 gap-4">
            <div className="space-y-2">
              <Label>Start Date</Label>
              <DatePicker
                value={data.start_date}
                onChange={(date) => setData('start_date', date ? format(date, 'yyyy-MM-dd') : '')}
                placeholder="Select date"
              />
            </div>
            <div className="space-y-2">
              <Label>End Date (Optional)</Label>
              <DatePicker
                value={data.end_date}
                onChange={(date) => setData('end_date', date ? format(date, 'yyyy-MM-dd') : '')}
                placeholder="No end date"
              />
            </div>
          </div>

          <div className="space-y-2">
            <Label htmlFor="description">Description (Optional)</Label>
            <Textarea
              id="description"
              value={data.description}
              onChange={(e) => setData('description', e.target.value)}
              placeholder="Additional notes..."
              rows={2}
            />
          </div>

          <div className="space-y-4">
            <div className="flex items-center justify-between">
              <div className="space-y-0.5">
                <Label htmlFor="is_active">Active</Label>
                <p className="text-xs text-muted-foreground">
                  Paused transactions won't be created
                </p>
              </div>
              <Switch
                id="is_active"
                checked={data.is_active}
                onCheckedChange={(checked) => setData('is_active', checked)}
              />
            </div>

            <div className="flex items-center justify-between">
              <div className="space-y-0.5">
                <Label htmlFor="auto_create">Auto Create</Label>
                <p className="text-xs text-muted-foreground">
                  Automatically create transactions on schedule
                </p>
              </div>
              <Switch
                id="auto_create"
                checked={data.auto_create}
                onCheckedChange={(checked) => setData('auto_create', checked)}
              />
            </div>
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
              {processing ? 'Saving...' : isEditing ? 'Update' : 'Create'}
            </Button>
          </SheetFooter>
        </form>
      </SheetContent>
    </Sheet>
  )
}
