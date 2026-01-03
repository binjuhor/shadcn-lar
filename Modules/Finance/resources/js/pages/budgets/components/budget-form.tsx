import { useEffect } from 'react'
import { useForm } from '@inertiajs/react'
import { format } from 'date-fns'
import { Button } from '@/components/ui/button'
import { DatePicker } from '@/components/ui/date-picker'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Switch } from '@/components/ui/switch'
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
import type { Budget, Category, BudgetPeriod, Currency } from '@modules/Finance/types/finance'

interface BudgetFormProps {
  open: boolean
  onOpenChange: (open: boolean) => void
  budget?: Budget | null
  categories: Category[]
  currencies: Currency[]
  onSuccess?: () => void
}

const periodTypes: { value: BudgetPeriod; label: string }[] = [
  { value: 'weekly', label: 'Weekly' },
  { value: 'monthly', label: 'Monthly' },
  { value: 'quarterly', label: 'Quarterly' },
  { value: 'yearly', label: 'Yearly' },
  { value: 'custom', label: 'Custom' },
]

function getDefaultDates(period: BudgetPeriod) {
  const now = new Date()
  const start = new Date(now.getFullYear(), now.getMonth(), 1)
  let end: Date

  switch (period) {
    case 'weekly':
      end = new Date(start)
      end.setDate(start.getDate() + 6)
      break
    case 'monthly':
      end = new Date(now.getFullYear(), now.getMonth() + 1, 0)
      break
    case 'quarterly':
      end = new Date(now.getFullYear(), now.getMonth() + 3, 0)
      break
    case 'yearly':
      end = new Date(now.getFullYear(), 11, 31)
      break
    default:
      end = new Date(now.getFullYear(), now.getMonth() + 1, 0)
  }

  return {
    start: start.toISOString().split('T')[0],
    end: end.toISOString().split('T')[0],
  }
}

export function BudgetForm({
  open,
  onOpenChange,
  budget,
  categories,
  currencies,
  onSuccess,
}: BudgetFormProps) {
  const isEditing = !!budget

  const defaultDates = getDefaultDates('monthly')

  const { data, setData, post, put, processing, errors, reset } = useForm({
    name: budget?.name || '',
    category_id: budget?.category_id ? String(budget.category_id) : '',
    amount: budget?.amount ? String(budget.amount) : '',
    currency_code: budget?.currency_code || currencies.find(c => c.is_default)?.code || 'VND',
    period_type: budget?.period_type || 'monthly',
    start_date: budget?.start_date?.split('T')[0] || defaultDates.start,
    end_date: budget?.end_date?.split('T')[0] || defaultDates.end,
    is_active: budget?.is_active ?? true,
    rollover: budget?.rollover ?? false,
  })

  const expenseCategories = categories.filter((c) => c.type === 'expense')

  useEffect(() => {
    if (data.period_type !== 'custom') {
      const dates = getDefaultDates(data.period_type as BudgetPeriod)
      setData((prev) => ({
        ...prev,
        start_date: dates.start,
        end_date: dates.end,
      }))
    }
  }, [data.period_type])

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault()

    const formData = {
      ...data,
      amount: Math.round(parseFloat(data.amount || '0')),
      category_id: data.category_id ? parseInt(data.category_id) : null,
    }

    if (isEditing && budget) {
      put(route('dashboard.finance.budgets.update', budget.id), {
        ...formData,
        onSuccess: () => {
          reset()
          onOpenChange(false)
          onSuccess?.()
        },
      })
    } else {
      post(route('dashboard.finance.budgets.store'), {
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
      <SheetContent className="overflow-y-auto">
        <SheetHeader>
          <SheetTitle>
            {isEditing ? 'Edit Budget' : 'Create Budget'}
          </SheetTitle>
          <SheetDescription>
            {isEditing
              ? 'Update your budget settings'
              : 'Set up a new budget to track spending'}
          </SheetDescription>
        </SheetHeader>

        <form onSubmit={handleSubmit} className="space-y-4 mt-4">
          <div className="space-y-2">
            <Label htmlFor="name">Budget Name</Label>
            <Input
              id="name"
              value={data.name}
              onChange={(e) => setData('name', e.target.value)}
              placeholder="e.g., Monthly Groceries"
            />
            {errors.name && (
              <p className="text-sm text-red-600">{errors.name}</p>
            )}
          </div>

          <div className="space-y-2">
            <Label htmlFor="category_id">Category (Optional)</Label>
            <Select
              value={data.category_id || '__all__'}
              onValueChange={(value) => setData('category_id', value === '__all__' ? '' : value)}
            >
              <SelectTrigger>
                <SelectValue placeholder="All expenses" />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="__all__">All expenses</SelectItem>
                {expenseCategories.map((category) => (
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

          <div className="space-y-2">
            <Label htmlFor="amount">Budget Amount</Label>
            <Input
              id="amount"
              type="number"
              step="0.01"
              min="0"
              value={data.amount}
              onChange={(e) => setData('amount', e.target.value)}
              placeholder="0.00"
            />
            {errors.amount && (
              <p className="text-sm text-red-600">{errors.amount}</p>
            )}
          </div>

          <div className="space-y-2">
            <Label htmlFor="currency_code">Currency</Label>
            <Select
              value={data.currency_code}
              onValueChange={(value) => setData('currency_code', value)}
            >
              <SelectTrigger>
                <SelectValue placeholder="Select currency" />
              </SelectTrigger>
              <SelectContent>
                {currencies.map((currency) => (
                  <SelectItem key={currency.code} value={currency.code}>
                    {currency.code} - {currency.name}
                  </SelectItem>
                ))}
              </SelectContent>
            </Select>
          </div>

          <div className="space-y-2">
            <Label htmlFor="period_type">Period</Label>
            <Select
              value={data.period_type}
              onValueChange={(value) => setData('period_type', value as BudgetPeriod)}
            >
              <SelectTrigger>
                <SelectValue placeholder="Select period" />
              </SelectTrigger>
              <SelectContent>
                {periodTypes.map((period) => (
                  <SelectItem key={period.value} value={period.value}>
                    {period.label}
                  </SelectItem>
                ))}
              </SelectContent>
            </Select>
          </div>

          <div className="grid grid-cols-2 gap-4">
            <div className="space-y-2">
              <Label>Start Date</Label>
              <DatePicker
                value={data.start_date}
                onChange={(date) => setData('start_date', date ? format(date, 'yyyy-MM-dd') : '')}
                placeholder="Select start date"
                disabled={data.period_type !== 'custom'}
              />
            </div>
            <div className="space-y-2">
              <Label>End Date</Label>
              <DatePicker
                value={data.end_date}
                onChange={(date) => setData('end_date', date ? format(date, 'yyyy-MM-dd') : '')}
                placeholder="Select end date"
                disabled={data.period_type !== 'custom'}
              />
            </div>
          </div>

          <div className="flex items-center justify-between">
            <div className="space-y-0.5">
              <Label htmlFor="is_active">Active</Label>
              <p className="text-xs text-muted-foreground">
                Track spending against this budget
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
              <Label htmlFor="rollover">Rollover</Label>
              <p className="text-xs text-muted-foreground">
                Carry unused budget to next period
              </p>
            </div>
            <Switch
              id="rollover"
              checked={data.rollover}
              onCheckedChange={(checked) => setData('rollover', checked)}
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
              {processing
                ? 'Saving...'
                : isEditing
                  ? 'Update Budget'
                  : 'Create Budget'}
            </Button>
          </SheetFooter>
        </form>
      </SheetContent>
    </Sheet>
  )
}
