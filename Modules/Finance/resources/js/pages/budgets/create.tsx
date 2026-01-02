import { useEffect } from 'react'
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
import type { Category, Currency, BudgetPeriod } from '@modules/Finance/types/finance'

interface Props {
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

export default function CreateBudget({ categories = [], currencies = [] }: Props) {
  const defaultDates = getDefaultDates('monthly')
  const defaultCurrency = currencies.find(c => c.is_default)?.code || 'VND'

  const { data, setData, post, processing, errors, transform, reset } = useForm({
    name: '',
    category_id: '',
    amount: '',
    currency_code: defaultCurrency,
    period_type: 'monthly' as BudgetPeriod,
    start_date: defaultDates.start,
    end_date: defaultDates.end,
    is_active: true as boolean,
    rollover: false as boolean,
  })

  const expenseCategories = categories.filter((c) => c.type === 'expense' || c.type === 'both')

  transform((formData) => ({
    ...formData,
    amount: Math.round(parseFloat(formData.amount || '0')),
    category_id: formData.category_id ? parseInt(formData.category_id) : null,
  }))

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
    post(route('dashboard.finance.budgets.store'), {
      preserveState: false,
      onSuccess: () => reset(),
    })
  }

  return (
    <AuthenticatedLayout title="Create Budget">
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
            <CardTitle>Create Budget</CardTitle>
            <CardDescription>
              Set up a new budget to track your spending
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
                  {processing ? 'Creating...' : 'Create Budget'}
                </Button>
              </div>
            </form>
          </CardContent>
        </Card>
      </Main>
    </AuthenticatedLayout>
  )
}
