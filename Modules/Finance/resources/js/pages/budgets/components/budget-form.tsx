import { useEffect, useRef } from 'react'
import { useForm } from '@inertiajs/react'
import { useTranslation } from 'react-i18next'
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

const periodTypeValues: BudgetPeriod[] = ['weekly', 'monthly', 'quarterly', 'yearly', 'custom']

function getDefaultDates(period: BudgetPeriod) {
  const now = new Date()
  let start: Date
  let end: Date

  switch (period) {
    case 'weekly': {
      const day = now.getDay()
      start = new Date(now.getFullYear(), now.getMonth(), now.getDate() - day + 1)
      end = new Date(start.getFullYear(), start.getMonth(), start.getDate() + 6)
      break
    }
    case 'monthly':
      start = new Date(now.getFullYear(), now.getMonth(), 1)
      end = new Date(now.getFullYear(), now.getMonth() + 1, 0)
      break
    case 'quarterly': {
      const quarterStart = Math.floor(now.getMonth() / 3) * 3
      start = new Date(now.getFullYear(), quarterStart, 1)
      end = new Date(now.getFullYear(), quarterStart + 3, 0)
      break
    }
    case 'yearly':
      start = new Date(now.getFullYear(), 0, 1)
      end = new Date(now.getFullYear(), 11, 31)
      break
    default:
      start = new Date(now.getFullYear(), now.getMonth(), 1)
      end = new Date(now.getFullYear(), now.getMonth() + 1, 0)
  }

  return {
    start: format(start, 'yyyy-MM-dd'),
    end: format(end, 'yyyy-MM-dd'),
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
  const { t } = useTranslation()
  const isEditing = !!budget

  const periodTypes = periodTypeValues.map(value => ({
    value,
    label: t(`period.${value}`)
  }))

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
  const isInitialLoad = useRef(true)

  // Sync form data when budget prop changes (for editing)
  useEffect(() => {
    if (budget) {
      setData({
        name: budget.name || '',
        category_id: budget.category_id ? String(budget.category_id) : '',
        amount: budget.amount ? String(budget.amount) : '',
        currency_code: budget.currency_code || currencies.find(c => c.is_default)?.code || 'VND',
        period_type: budget.period_type || 'monthly',
        start_date: budget.start_date?.split('T')[0] || defaultDates.start,
        end_date: budget.end_date?.split('T')[0] || defaultDates.end,
        is_active: budget.is_active ?? true,
        rollover: budget.rollover ?? false,
      })
      isInitialLoad.current = true // Mark as initial load for editing
    } else if (open) {
      // Reset to defaults when creating new budget
      reset()
      isInitialLoad.current = false // New budget, allow date auto-update
    }
  }, [budget, open])

  // Auto-update dates when period type changes (only for new budgets)
  useEffect(() => {
    if (isInitialLoad.current) {
      isInitialLoad.current = false
      return
    }
    if (!budget && data.period_type !== 'custom') {
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
            {isEditing ? t('form.budget.edit') : t('form.budget.create')}
          </SheetTitle>
          <SheetDescription>
            {isEditing
              ? t('form.budget.edit_description')
              : t('form.budget.create_description')}
          </SheetDescription>
        </SheetHeader>

        <form onSubmit={handleSubmit} className="space-y-4 mt-4">
          <div className="space-y-2">
            <Label htmlFor="name">{t('form.budget_name')}</Label>
            <Input
              id="name"
              value={data.name}
              onChange={(e) => setData('name', e.target.value)}
              placeholder={t('form.budget_name_placeholder')}
            />
            {errors.name && (
              <p className="text-sm text-red-600">{errors.name}</p>
            )}
          </div>

          <div className="space-y-2">
            <Label htmlFor="category_id">{t('form.category_optional')}</Label>
            <Select
              value={data.category_id || '__all__'}
              onValueChange={(value) => setData('category_id', value === '__all__' ? '' : value)}
            >
              <SelectTrigger>
                <SelectValue placeholder={t('form.all_expenses')} />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="__all__">{t('form.all_expenses')}</SelectItem>
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
            <Label htmlFor="amount">{t('form.budget_amount')}</Label>
            <Input
              id="amount"
              type="number"
              step="0.01"
              min="0"
              value={data.amount}
              onChange={(e) => setData('amount', e.target.value)}
              placeholder={t('form.balance_placeholder')}
            />
            {errors.amount && (
              <p className="text-sm text-red-600">{errors.amount}</p>
            )}
          </div>

          <div className="space-y-2">
            <Label htmlFor="currency_code">{t('form.currency')}</Label>
            <Select
              value={data.currency_code}
              onValueChange={(value) => setData('currency_code', value)}
            >
              <SelectTrigger>
                <SelectValue placeholder={t('form.select_currency')} />
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
            <Label htmlFor="period_type">{t('form.period')}</Label>
            <Select
              value={data.period_type}
              onValueChange={(value) => setData('period_type', value as BudgetPeriod)}
            >
              <SelectTrigger>
                <SelectValue placeholder={t('form.select_period')} />
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
              <Label>{t('form.start_date')}</Label>
              <DatePicker
                value={data.start_date}
                onChange={(date) => setData('start_date', date ? format(date, 'yyyy-MM-dd') : '')}
                placeholder={t('filter.select_date')}
                disabled={data.period_type !== 'custom'}
              />
            </div>
            <div className="space-y-2">
              <Label>{t('form.end_date')}</Label>
              <DatePicker
                value={data.end_date}
                onChange={(date) => setData('end_date', date ? format(date, 'yyyy-MM-dd') : '')}
                placeholder={t('filter.select_date')}
                disabled={data.period_type !== 'custom'}
              />
            </div>
          </div>

          <div className="flex items-center justify-between">
            <div className="space-y-0.5">
              <Label htmlFor="is_active">{t('form.is_active')}</Label>
              <p className="text-xs text-muted-foreground">
                {t('form.budget_is_active_description')}
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
              <Label htmlFor="rollover">{t('form.rollover')}</Label>
              <p className="text-xs text-muted-foreground">
                {t('form.rollover_description')}
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
              {t('action.cancel')}
            </Button>
            <Button type="submit" disabled={processing}>
              {processing
                ? t('common.saving')
                : isEditing
                  ? t('form.update_budget_button')
                  : t('form.create_budget_button')}
            </Button>
          </SheetFooter>
        </form>
      </SheetContent>
    </Sheet>
  )
}
