import { useEffect } from 'react'
import { useForm } from '@inertiajs/react'
import { Button } from '@/components/ui/button'
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
import type { Account, Currency, AccountType, RateSource } from '@modules/Finance/types/finance'

interface AccountFormProps {
  open: boolean
  onOpenChange: (open: boolean) => void
  account?: Account | null
  currencies: Currency[]
  onSuccess?: () => void
}

const accountTypes: { value: AccountType; label: string }[] = [
  { value: 'bank', label: 'Bank Account' },
  { value: 'credit_card', label: 'Credit Card' },
  { value: 'investment', label: 'Investment' },
  { value: 'cash', label: 'Cash' },
  { value: 'loan', label: 'Loan' },
  { value: 'other', label: 'Other' },
]

const colors = [
  '#3b82f6', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6',
  '#ec4899', '#06b6d4', '#84cc16', '#f97316', '#6366f1',
]

const rateSources: { value: string; label: string }[] = [
  { value: '__default__', label: 'Default (Best available)' },
  { value: 'payoneer', label: 'Payoneer' },
  { value: 'vietcombank', label: 'Vietcombank' },
  { value: 'exchangerate_api', label: 'ExchangeRate API' },
  { value: 'open_exchange_rates', label: 'Open Exchange Rates' },
]

export function AccountForm({
  open,
  onOpenChange,
  account,
  currencies,
  onSuccess,
}: AccountFormProps) {
  const isEditing = !!account

  const defaultCurrency = currencies.find(c => c.is_default)?.code || 'VND'

  const MAX_BALANCE = 999999999999999999

  const { data, setData, post, put, processing, errors, reset, setError, clearErrors } = useForm({
    name: '',
    account_type: 'bank' as AccountType,
    currency_code: defaultCurrency,
    rate_source: '__default__',
    initial_balance: '0',
    description: '',
    color: '#3b82f6',
    is_active: true as boolean,
    exclude_from_total: false as boolean,
  })

  // Sync form data when account prop changes (for editing)
  useEffect(() => {
    if (account) {
      const balanceValue = account.current_balance ?? account.initial_balance ?? 0
      setData({
        name: account.name || '',
        account_type: account.account_type || 'bank',
        currency_code: account.currency_code || defaultCurrency,
        rate_source: account.rate_source || '__default__',
        initial_balance: String(balanceValue),
        description: account.description || '',
        color: account.color || '#3b82f6',
        is_active: account.is_active ?? true,
        exclude_from_total: account.exclude_from_total ?? false,
      })
    } else if (open) {
      // Reset to defaults when creating new account
      reset()
    }
  }, [account, open])

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault()
    clearErrors()

    const balanceValue = parseFloat(data.initial_balance || '0')

    if (isNaN(balanceValue) || Math.abs(balanceValue) > MAX_BALANCE) {
      setError('initial_balance', `Balance must be between -${MAX_BALANCE.toLocaleString()} and ${MAX_BALANCE.toLocaleString()}`)
      return
    }

    const formData = {
      ...data,
      initial_balance: Math.round(balanceValue),
      rate_source: data.rate_source === '__default__' ? null : data.rate_source,
    }

    if (isEditing && account) {
      put(route('dashboard.finance.accounts.update', account.id), {
        ...formData,
        onSuccess: () => {
          reset()
          onOpenChange(false)
          onSuccess?.()
        },
      })
    } else {
      post(route('dashboard.finance.accounts.store'), {
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
            {isEditing ? 'Edit Account' : 'Create Account'}
          </SheetTitle>
          <SheetDescription>
            {isEditing
              ? 'Update your account details'
              : 'Add a new account to track your finances'}
          </SheetDescription>
        </SheetHeader>

        <form onSubmit={handleSubmit} className="space-y-4 mt-4">
          <div className="space-y-2">
            <Label htmlFor="name">Account Name</Label>
            <Input
              id="name"
              value={data.name}
              onChange={(e) => setData('name', e.target.value)}
              placeholder="e.g., Main Checking"
            />
            {errors.name && (
              <p className="text-sm text-red-600">{errors.name}</p>
            )}
          </div>

          <div className="space-y-2">
            <Label htmlFor="account_type">Account Type</Label>
            <Select
              value={data.account_type}
              onValueChange={(value) => setData('account_type', value as AccountType)}
            >
              <SelectTrigger>
                <SelectValue placeholder="Select type" />
              </SelectTrigger>
              <SelectContent>
                {accountTypes.map((type) => (
                  <SelectItem key={type.value} value={type.value}>
                    {type.label}
                  </SelectItem>
                ))}
              </SelectContent>
            </Select>
            {errors.account_type && (
              <p className="text-sm text-red-600">{errors.account_type}</p>
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
            <Label htmlFor="rate_source">Exchange Rate Source</Label>
            <Select
              value={data.rate_source}
              onValueChange={(value) => setData('rate_source', value)}
            >
              <SelectTrigger>
                <SelectValue placeholder="Select rate source" />
              </SelectTrigger>
              <SelectContent>
                {rateSources.map((source) => (
                  <SelectItem key={source.value} value={source.value}>
                    {source.label}
                  </SelectItem>
                ))}
              </SelectContent>
            </Select>
            <p className="text-xs text-muted-foreground">
              Used for currency conversion (e.g., Payoneer account uses Payoneer rates)
            </p>
            {errors.rate_source && (
              <p className="text-sm text-red-600">{errors.rate_source}</p>
            )}
          </div>

          <div className="space-y-2">
            <Label htmlFor="initial_balance">
              {isEditing ? 'Current Balance' : 'Initial Balance'}
            </Label>
            <Input
              id="initial_balance"
              type="number"
              step="0.01"
              min="-999999999999999999"
              max="999999999999999999"
              value={data.initial_balance}
              onChange={(e) => setData('initial_balance', e.target.value)}
              placeholder="0.00"
            />
            {errors.initial_balance && (
              <p className="text-sm text-red-600">{errors.initial_balance}</p>
            )}
          </div>

          <div className="space-y-2">
            <Label htmlFor="description">Description (Optional)</Label>
            <Textarea
              id="description"
              value={data.description}
              onChange={(e) => setData('description', e.target.value)}
              placeholder="Add a description..."
              rows={3}
            />
            {errors.description && (
              <p className="text-sm text-red-600">{errors.description}</p>
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
                Inactive accounts won't show in transactions
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
              <Label htmlFor="exclude_from_total">Exclude from Total</Label>
              <p className="text-xs text-muted-foreground">
                This account won't be included in net worth
              </p>
            </div>
            <Switch
              id="exclude_from_total"
              checked={data.exclude_from_total}
              onCheckedChange={(checked) => setData('exclude_from_total', checked)}
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
                  ? 'Update Account'
                  : 'Create Account'}
            </Button>
          </SheetFooter>
        </form>
      </SheetContent>
    </Sheet>
  )
}
