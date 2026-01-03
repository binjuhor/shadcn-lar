import { useEffect } from 'react'
import { useForm } from '@inertiajs/react'
import { format } from 'date-fns'
import { Button } from '@/components/ui/button'
import { DatePicker } from '@/components/ui/date-picker'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Textarea } from '@/components/ui/textarea'
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
import type { SavingsGoal, Currency, Account } from '@modules/Finance/types/finance'

interface SavingsGoalFormProps {
  open: boolean
  onOpenChange: (open: boolean) => void
  goal?: SavingsGoal | null
  currencies: Currency[]
  accounts: Account[]
  onSuccess?: () => void
}

const colors = [
  '#3b82f6', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6',
  '#ec4899', '#06b6d4', '#84cc16', '#f97316', '#6366f1',
]

export function SavingsGoalForm({
  open,
  onOpenChange,
  goal,
  currencies,
  accounts,
  onSuccess,
}: SavingsGoalFormProps) {
  const isEditing = !!goal

  const defaultCurrency = currencies.find(c => c.is_default)?.code || 'VND'

  const { data, setData, post, put, processing, errors, reset } = useForm({
    name: '',
    description: '',
    target_amount: '',
    currency_code: defaultCurrency,
    target_date: '',
    target_account_id: '',
    color: '#3b82f6',
    is_active: true as boolean,
  })

  useEffect(() => {
    if (goal) {
      setData({
        name: goal.name || '',
        description: goal.description || '',
        target_amount: String(goal.target_amount),
        currency_code: goal.currency_code || defaultCurrency,
        target_date: goal.target_date ? goal.target_date.split('T')[0] : '',
        target_account_id: goal.target_account_id ? String(goal.target_account_id) : '',
        color: goal.color || '#3b82f6',
        is_active: goal.is_active ?? true,
      })
    } else if (open) {
      reset()
    }
  }, [goal, open])

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault()

    const formData = {
      ...data,
      target_amount: Math.round(parseFloat(data.target_amount || '0')),
      target_account_id: data.target_account_id ? parseInt(data.target_account_id) : null,
      target_date: data.target_date || null,
    }

    if (isEditing && goal) {
      put(route('dashboard.finance.savings-goals.update', goal.id), {
        ...formData,
        onSuccess: () => {
          reset()
          onOpenChange(false)
          onSuccess?.()
        },
      })
    } else {
      post(route('dashboard.finance.savings-goals.store'), {
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
            {isEditing ? 'Edit Savings Goal' : 'Create Savings Goal'}
          </SheetTitle>
          <SheetDescription>
            {isEditing
              ? 'Update your savings goal details'
              : 'Set a new financial target to work towards'}
          </SheetDescription>
        </SheetHeader>

        <form onSubmit={handleSubmit} className="space-y-4 mt-4">
          <div className="space-y-2">
            <Label htmlFor="name">Goal Name</Label>
            <Input
              id="name"
              value={data.name}
              onChange={(e) => setData('name', e.target.value)}
              placeholder="e.g., Emergency Fund, New Car"
            />
            {errors.name && (
              <p className="text-sm text-red-600">{errors.name}</p>
            )}
          </div>

          <div className="space-y-2">
            <Label htmlFor="description">Description (Optional)</Label>
            <Textarea
              id="description"
              value={data.description}
              onChange={(e) => setData('description', e.target.value)}
              placeholder="Describe your savings goal..."
              rows={2}
            />
            {errors.description && (
              <p className="text-sm text-red-600">{errors.description}</p>
            )}
          </div>

          <div className="space-y-2">
            <Label htmlFor="target_amount">Target Amount</Label>
            <Input
              id="target_amount"
              type="number"
              step="1"
              min="1"
              value={data.target_amount}
              onChange={(e) => setData('target_amount', e.target.value)}
              placeholder="0"
            />
            {errors.target_amount && (
              <p className="text-sm text-red-600">{errors.target_amount}</p>
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
            {errors.currency_code && (
              <p className="text-sm text-red-600">{errors.currency_code}</p>
            )}
          </div>

          <div className="space-y-2">
            <Label>Target Date (Optional)</Label>
            <DatePicker
              value={data.target_date}
              onChange={(date) => setData('target_date', date ? format(date, 'yyyy-MM-dd') : '')}
              placeholder="Select target date"
            />
            {errors.target_date && (
              <p className="text-sm text-red-600">{errors.target_date}</p>
            )}
          </div>

          <div className="space-y-2">
            <Label htmlFor="target_account_id">Target Account (Optional)</Label>
            <Select
              value={data.target_account_id || 'none'}
              onValueChange={(value) => setData('target_account_id', value === 'none' ? '' : value)}
            >
              <SelectTrigger>
                <SelectValue placeholder="Select account" />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="none">None</SelectItem>
                {accounts.map((account) => (
                  <SelectItem key={account.id} value={String(account.id)}>
                    {account.name}
                  </SelectItem>
                ))}
              </SelectContent>
            </Select>
            {errors.target_account_id && (
              <p className="text-sm text-red-600">{errors.target_account_id}</p>
            )}
          </div>

          <div className="space-y-2">
            <Label>Color</Label>
            <div className="flex gap-2 flex-wrap">
              {colors.map((color) => (
                <button
                  key={color}
                  type="button"
                  className={`w-8 h-8 rounded-full border-2 transition-all ${
                    data.color === color
                      ? 'border-foreground scale-110'
                      : 'border-transparent'
                  }`}
                  style={{ backgroundColor: color }}
                  onClick={() => setData('color', color)}
                />
              ))}
            </div>
          </div>

          <div className="flex items-center justify-between">
            <div className="space-y-0.5">
              <Label htmlFor="is_active">Active</Label>
              <p className="text-xs text-muted-foreground">
                Inactive goals won't appear in the main list
              </p>
            </div>
            <Switch
              id="is_active"
              checked={data.is_active}
              onCheckedChange={(checked) => setData('is_active', checked)}
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
                  ? 'Update Goal'
                  : 'Create Goal'}
            </Button>
          </SheetFooter>
        </form>
      </SheetContent>
    </Sheet>
  )
}
