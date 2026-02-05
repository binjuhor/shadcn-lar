import { useEffect } from 'react'
import { useForm } from '@inertiajs/react'
import { useTranslation } from 'react-i18next'
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

const colors = [
  '#3b82f6', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6',
  '#ec4899', '#06b6d4', '#84cc16', '#f97316', '#6366f1',
]

export function AccountForm({
  open,
  onOpenChange,
  account,
  currencies,
  onSuccess,
}: AccountFormProps) {
  const { t } = useTranslation()
  const isEditing = !!account

  const accountTypes: { value: AccountType; label: string }[] = [
    { value: 'bank', label: t('account_type.bank') },
    { value: 'credit_card', label: t('account_type.credit_card') },
    { value: 'investment', label: t('account_type.investment') },
    { value: 'e_wallet', label: t('account_type.e_wallet') },
    { value: 'cash', label: t('account_type.cash') },
    { value: 'loan', label: t('account_type.loan') },
    { value: 'other', label: t('account_type.other') },
  ]

  const rateSources: { value: string; label: string }[] = [
    { value: '__default__', label: t('form.rate_source.default') },
    { value: 'payoneer', label: t('form.rate_source.payoneer') },
    { value: 'vietcombank', label: t('form.rate_source.vietcombank') },
    { value: 'exchangerate_api', label: t('form.rate_source.exchangerate_api') },
    { value: 'open_exchange_rates', label: t('form.rate_source.open_exchange_rates') },
  ]

  const defaultCurrency = currencies.find(c => c.is_default)?.code || 'VND'

  const MAX_BALANCE = 999999999999999999

  const { data, setData, post, put, processing, errors, reset, setError, clearErrors } = useForm({
    name: '',
    account_type: 'bank' as AccountType,
    has_credit_limit: false as boolean,
    currency_code: defaultCurrency,
    rate_source: '__default__',
    initial_balance: '0',
    current_balance: '0',
    description: '',
    color: '#3b82f6',
    is_active: true as boolean,
    exclude_from_total: false as boolean,
  })

  // Sync form data when account prop changes (for editing)
  useEffect(() => {
    if (account) {
      setData({
        name: account.name || '',
        account_type: account.account_type || 'bank',
        has_credit_limit: account.has_credit_limit ?? false,
        currency_code: account.currency_code || defaultCurrency,
        rate_source: account.rate_source || '__default__',
        initial_balance: String(account.initial_balance ?? 0),
        current_balance: String(account.current_balance ?? 0),
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

  // Credit limit applies to credit_card/loan by default, or any account with has_credit_limit enabled
  const isDefaultCreditType = ['credit_card', 'loan'].includes(data.account_type)
  const hasCreditLimit = data.has_credit_limit || isDefaultCreditType

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault()
    clearErrors()

    const initialBalanceValue = parseFloat(data.initial_balance || '0')
    const currentBalanceValue = parseFloat(data.current_balance || '0')

    // Validate based on account type and mode
    if (hasCreditLimit) {
      // Credit accounts: validate both fields
      if (isNaN(initialBalanceValue) || initialBalanceValue < 0) {
        setError('initial_balance', 'Credit limit must be 0 or greater')
        return
      }
      if (isNaN(currentBalanceValue) || currentBalanceValue < 0) {
        setError('current_balance', 'Available credit must be 0 or greater')
        return
      }
      if (currentBalanceValue > initialBalanceValue) {
        setError('current_balance', 'Available credit cannot exceed credit limit')
        return
      }
    } else {
      // Regular accounts: validate single balance field
      const balanceToValidate = isEditing ? currentBalanceValue : initialBalanceValue
      if (isNaN(balanceToValidate) || Math.abs(balanceToValidate) > MAX_BALANCE) {
        const errorField = isEditing ? 'current_balance' : 'initial_balance'
        setError(errorField, `Balance must be between -${MAX_BALANCE.toLocaleString()} and ${MAX_BALANCE.toLocaleString()}`)
        return
      }
    }

    // Build form data
    let formInitialBalance = initialBalanceValue
    let formCurrentBalance = currentBalanceValue

    // For regular accounts: initial_balance = current_balance
    if (!hasCreditLimit) {
      if (isEditing) {
        // Keep original initial_balance, only update current_balance
        formInitialBalance = parseFloat(String(account?.initial_balance ?? '0'))
      } else {
        // New account: set both to the same value
        formCurrentBalance = initialBalanceValue
      }
    }

    const formData: Record<string, any> = {
      ...data,
      initial_balance: Math.round(formInitialBalance),
      current_balance: Math.round(formCurrentBalance),
      rate_source: data.rate_source === '__default__' ? null : data.rate_source,
      // For credit_card/loan, always set has_credit_limit to true
      has_credit_limit: isDefaultCreditType || data.has_credit_limit,
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
            {isEditing ? t('form.account.edit') : t('form.account.create')}
          </SheetTitle>
          <SheetDescription>
            {isEditing
              ? t('form.account.edit_description')
              : t('form.account.create_description')}
          </SheetDescription>
        </SheetHeader>

        <form onSubmit={handleSubmit} className="space-y-4 mt-4">
          <div className="space-y-2">
            <Label htmlFor="name">{t('form.account_name')}</Label>
            <Input
              id="name"
              value={data.name}
              onChange={(e) => setData('name', e.target.value)}
              placeholder={t('form.account_name_placeholder')}
            />
            {errors.name && (
              <p className="text-sm text-red-600">{errors.name}</p>
            )}
          </div>

          <div className="space-y-2">
            <Label htmlFor="account_type">{t('form.account_type')}</Label>
            <Select
              value={data.account_type}
              onValueChange={(value) => setData('account_type', value as AccountType)}
            >
              <SelectTrigger>
                <SelectValue placeholder={t('form.select_type_placeholder')} />
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

          {/* Credit Limit toggle - only for non-credit_card/loan types */}
          {!isDefaultCreditType && (
            <div className="flex items-center justify-between">
              <div className="space-y-0.5">
                <Label htmlFor="has_credit_limit">{t('form.has_credit_limit')}</Label>
                <p className="text-xs text-muted-foreground">
                  {t('form.has_credit_limit_description')}
                </p>
              </div>
              <Switch
                id="has_credit_limit"
                checked={data.has_credit_limit}
                onCheckedChange={(checked) => setData('has_credit_limit', checked)}
              />
            </div>
          )}

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
            {errors.currency_code && (
              <p className="text-sm text-red-600">{errors.currency_code}</p>
            )}
          </div>

          <div className="space-y-2">
            <Label htmlFor="rate_source">{t('form.exchange_rate_source')}</Label>
            <Select
              value={data.rate_source}
              onValueChange={(value) => setData('rate_source', value)}
            >
              <SelectTrigger>
                <SelectValue placeholder={t('form.select_rate_source')} />
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
              {t('form.rate_source_description')}
            </p>
            {errors.rate_source && (
              <p className="text-sm text-red-600">{errors.rate_source}</p>
            )}
          </div>

          {/* For regular accounts: single balance field */}
          {!hasCreditLimit && (
            <div className="space-y-2">
              <Label htmlFor="balance">
                {isEditing ? t('form.current_balance') : t('form.initial_balance')}
              </Label>
              <Input
                id="balance"
                type="number"
                step="0.01"
                min="-999999999999999999"
                max="999999999999999999"
                value={isEditing ? data.current_balance : data.initial_balance}
                onChange={(e) => {
                  if (isEditing) {
                    setData('current_balance', e.target.value)
                  } else {
                    setData('initial_balance', e.target.value)
                  }
                }}
                placeholder={t('form.balance_placeholder')}
              />
              <p className="text-xs text-muted-foreground">
                {isEditing
                  ? t('form.balance_description_edit')
                  : t('form.balance_description_create')}
              </p>
              {(errors.initial_balance || errors.current_balance) && (
                <p className="text-sm text-red-600">{errors.initial_balance || errors.current_balance}</p>
              )}
            </div>
          )}

          {/* For credit accounts: Credit Limit field */}
          {hasCreditLimit && (
            <div className="space-y-2">
              <Label htmlFor="initial_balance">{t('form.credit_limit')}</Label>
              <Input
                id="initial_balance"
                type="number"
                step="0.01"
                min="0"
                max="999999999999999999"
                value={data.initial_balance}
                onChange={(e) => setData('initial_balance', e.target.value)}
                placeholder={t('form.balance_placeholder')}
              />
              <p className="text-xs text-muted-foreground">
                {data.account_type === 'credit_card'
                  ? t('form.credit_limit_description_card')
                  : t('form.credit_limit_description_loan')}
              </p>
              {errors.initial_balance && (
                <p className="text-sm text-red-600">{errors.initial_balance}</p>
              )}
            </div>
          )}

          {/* For credit accounts: Available Credit field */}
          {hasCreditLimit && (
            <div className="space-y-2">
              <Label htmlFor="current_balance">{t('form.available_credit')}</Label>
              <Input
                id="current_balance"
                type="number"
                step="0.01"
                min="0"
                max={data.initial_balance}
                value={data.current_balance}
                onChange={(e) => setData('current_balance', e.target.value)}
                placeholder={t('form.balance_placeholder')}
              />
              <p className="text-xs text-muted-foreground">
                {t('form.available_credit_description')}
              </p>
              {errors.current_balance && (
                <p className="text-sm text-red-600">{errors.current_balance}</p>
              )}
            </div>
          )}

          <div className="space-y-2">
            <Label htmlFor="description">{t('form.description_optional')}</Label>
            <Textarea
              id="description"
              value={data.description}
              onChange={(e) => setData('description', e.target.value)}
              placeholder={t('form.description_placeholder')}
              rows={3}
            />
            {errors.description && (
              <p className="text-sm text-red-600">{errors.description}</p>
            )}
          </div>

          <div className="space-y-2">
            <Label>{t('form.color')}</Label>
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
              <Label htmlFor="is_active">{t('form.is_active')}</Label>
              <p className="text-xs text-muted-foreground">
                {t('form.is_active_description')}
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
              <Label htmlFor="exclude_from_total">{t('form.exclude_from_total')}</Label>
              <p className="text-xs text-muted-foreground">
                {t('form.exclude_from_total_description')}
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
              {t('action.cancel')}
            </Button>
            <Button type="submit" disabled={processing}>
              {processing
                ? t('common.saving')
                : isEditing
                  ? t('form.update_account_button')
                  : t('form.create_account_button')}
            </Button>
          </SheetFooter>
        </form>
      </SheetContent>
    </Sheet>
  )
}
