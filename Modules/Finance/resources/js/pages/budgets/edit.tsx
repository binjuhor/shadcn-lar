import { useEffect, useRef } from 'react'
import { Link, useForm, router } from '@inertiajs/react'
import { format } from 'date-fns'
import { AuthenticatedLayout } from '@/layouts'
import { Main } from '@/components/layout/main'
import { Button } from '@/components/ui/button'
import { Combobox } from '@/components/ui/combobox'
import { DatePicker } from '@/components/ui/date-picker'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Switch } from '@/components/ui/switch'
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
import { ArrowLeft } from 'lucide-react'
import type { Budget, Category, Currency, BudgetPeriod } from '@modules/Finance/types/finance'

interface Props {
  budget: Budget
  categories: Category[]
  currencies: Currency[]
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

export default function EditBudget({ budget, categories, currencies }: Props) {
  const { data, setData, put, processing, errors, transform } = useForm({
    name: budget.name || '',
    category_id: budget.category_id ? String(budget.category_id) : '',
    amount: budget.amount ? String(budget.amount) : '',
    currency_code: budget.currency_code || 'VND',
    period_type: budget.period_type || 'monthly',
    start_date: budget.start_date?.split('T')[0] || '',
    end_date: budget.end_date?.split('T')[0] || '',
    is_active: budget.is_active ?? true,
    rollover: budget.rollover ?? false,
  })

  const expenseCategories = categories.filter((c) => c.type === 'expense' || c.type === 'both')
  const isFirstRender = useRef(true)

  transform((formData) => ({
    ...formData,
    amount: Math.round(parseFloat(formData.amount || '0')),
    category_id: formData.category_id ? parseInt(formData.category_id) : null,
  }))

  // Only update dates when user changes period type, not on initial load
  useEffect(() => {
    if (isFirstRender.current) {
      isFirstRender.current = false
      return
    }
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
    put(route('dashboard.finance.budgets.update', budget.id))
  }

  return (
    <AuthenticatedLayout title="Edit Budget">
      <Main>
        <div className="mb-4">
          <Link
            href={route('dashboard.finance.budgets.index')}
            className="inline-flex items-center text-sm text-muted-foreground hover:text-foreground"
          >
            <ArrowLeft className="mr-2 h-4 w-4" />
            Back to Budgets
          </Link>
        </div>

        <Card className="max-w-2xl">
          <CardHeader>
            <CardTitle>Edit Budget</CardTitle>
            <CardDescription>
              Update budget settings for "{budget.name}"
            </CardDescription>
          </CardHeader>
          <CardContent>
            <form onSubmit={handleSubmit} className="space-y-4">
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
                <Label>Currency</Label>
                <Combobox
                  options={currencies.map((c) => ({
                    value: c.code,
                    label: `${c.code} - ${c.name}`,
                  }))}
                  value={data.currency_code}
                  onChange={(value) => setData('currency_code', value)}
                  placeholder="Select currency"
                  searchPlaceholder="Search currencies..."
                />
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

              <div className="flex gap-3 pt-4">
                <Button
                  type="button"
                  variant="outline"
                  onClick={() => router.visit(route('dashboard.finance.budgets.index'))}
                  disabled={processing}
                >
                  Cancel
                </Button>
                <Button type="submit" disabled={processing}>
                  {processing ? 'Saving...' : 'Update Budget'}
                </Button>
              </div>
            </form>
          </CardContent>
        </Card>
      </Main>
    </AuthenticatedLayout>
  )
}
