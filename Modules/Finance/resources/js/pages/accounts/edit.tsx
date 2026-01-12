import { useEffect } from 'react'
import { Link, useForm, router } from '@inertiajs/react'
import { AuthenticatedLayout } from '@/layouts'
import { Main } from '@/components/layout/main'
import { Button } from '@/components/ui/button'
import { Combobox } from '@/components/ui/combobox'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Textarea } from '@/components/ui/textarea'
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
import type { Account, Currency, AccountType } from '@modules/Finance/types/finance'

interface Props {
  account: Account
  currencies: Currency[]
}

const accountTypes: { value: AccountType; label: string }[] = [
  { value: 'bank', label: 'Bank Account' },
  { value: 'credit_card', label: 'Credit Card' },
  { value: 'investment', label: 'Investment' },
  { value: 'cash', label: 'Cash' },
  { value: 'e_wallet', label: 'E-Wallet (Payoneer, PayPal, etc.)' },
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

export default function EditAccount({ account, currencies }: Props) {
  const isDefaultCreditType = ['credit_card', 'loan'].includes(account.account_type)

  const { data, setData, put, processing, errors, transform } = useForm({
    name: account.name || '',
    account_type: account.account_type || 'bank',
    has_credit_limit: account.has_credit_limit ?? false,
    currency_code: account.currency_code || 'VND',
    rate_source: account.rate_source || '__default__',
    initial_balance: String(account.initial_balance ?? 0),
    current_balance: String(account.current_balance ?? 0),
    description: account.description || '',
    color: account.color || '#3b82f6',
    is_active: account.is_active ?? true,
    is_default_payment: account.is_default_payment ?? false,
    exclude_from_total: account.exclude_from_total ?? false,
  })

  // Credit limit applies to credit_card/loan by default, or any account with has_credit_limit enabled
  const hasCreditLimit = data.has_credit_limit || isDefaultCreditType

  // Reset form when account changes (e.g., navigating between edit pages)
  useEffect(() => {
    setData({
      name: account.name || '',
      account_type: account.account_type || 'bank',
      has_credit_limit: account.has_credit_limit ?? false,
      currency_code: account.currency_code || 'VND',
      rate_source: account.rate_source || '__default__',
      initial_balance: String(account.initial_balance ?? 0),
      current_balance: String(account.current_balance ?? 0),
      description: account.description || '',
      color: account.color || '#3b82f6',
      is_active: account.is_active ?? true,
      is_default_payment: account.is_default_payment ?? false,
      exclude_from_total: account.exclude_from_total ?? false,
    })
  }, [account.id])

  transform((formData) => {
    const initialVal = parseFloat(formData.initial_balance || '0')
    const currentVal = parseFloat(formData.current_balance || '0')
    const isCreditType = ['credit_card', 'loan'].includes(formData.account_type)
    const accountHasCreditLimit = formData.has_credit_limit || isCreditType

    return {
      ...formData,
      initial_balance: initialVal,
      // For credit accounts, include current_balance; for others, current = initial
      current_balance: accountHasCreditLimit ? currentVal : initialVal,
      rate_source: formData.rate_source === '__default__' ? null : formData.rate_source,
      // For credit_card/loan, always set has_credit_limit to true
      has_credit_limit: accountHasCreditLimit,
    }
  })

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault()
    put(route('dashboard.finance.accounts.update', account.id))
  }

  return (
    <AuthenticatedLayout title="Edit Account">
      <Main>
        <div className="mb-4">
          <Link
            href={route('dashboard.finance.accounts.index')}
            className="inline-flex items-center text-sm text-muted-foreground hover:text-foreground"
          >
            <ArrowLeft className="mr-2 h-4 w-4" />
            Back to Accounts
          </Link>
        </div>

        <Card className="max-w-2xl">
          <CardHeader>
            <CardTitle>Edit Account</CardTitle>
            <CardDescription>
              Update account details for "{account.name}"
            </CardDescription>
          </CardHeader>
          <CardContent>
            <form onSubmit={handleSubmit} className="space-y-4">
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

              {/* Credit Limit toggle - only for non-credit_card/loan types */}
              {!isDefaultCreditType && (
                <div className="flex items-center justify-between">
                  <div className="space-y-0.5">
                    <Label htmlFor="has_credit_limit">Has Credit Limit</Label>
                    <p className="text-xs text-muted-foreground">
                      Enable for accounts with overdraft or credit line (e.g., MyCash)
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
                  {hasCreditLimit ? 'Credit Limit' : 'Current Balance'}
                </Label>
                <Input
                  id="initial_balance"
                  type="number"
                  step="0.01"
                  min={hasCreditLimit ? '0' : undefined}
                  value={data.initial_balance}
                  onChange={(e) => setData('initial_balance', e.target.value)}
                  placeholder="0.00"
                />
                {hasCreditLimit && (
                  <p className="text-xs text-muted-foreground">
                    Your total credit limit
                  </p>
                )}
                {errors.initial_balance && (
                  <p className="text-sm text-red-600">{errors.initial_balance}</p>
                )}
              </div>

              {/* Available Credit field - only for credit accounts */}
              {hasCreditLimit && (
                <div className="space-y-2">
                  <Label htmlFor="current_balance">Available Credit</Label>
                  <Input
                    id="current_balance"
                    type="number"
                    step="0.01"
                    min="0"
                    value={data.current_balance}
                    onChange={(e) => setData('current_balance', e.target.value)}
                    placeholder="0.00"
                  />
                  <p className="text-xs text-muted-foreground">
                    Amount of credit still available. Amount Owed = Credit Limit - Available Credit
                  </p>
                  {errors.current_balance && (
                    <p className="text-sm text-red-600">{errors.current_balance}</p>
                  )}
                </div>
              )}

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
                  <Label htmlFor="is_default_payment">Default Payment Account</Label>
                  <p className="text-xs text-muted-foreground">
                    Use this account by default for smart input transactions
                  </p>
                </div>
                <Switch
                  id="is_default_payment"
                  checked={data.is_default_payment}
                  onCheckedChange={(checked) => setData('is_default_payment', checked)}
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

              <div className="flex gap-3 pt-4">
                <Button
                  type="button"
                  variant="outline"
                  onClick={() => router.visit(route('dashboard.finance.accounts.index'))}
                  disabled={processing}
                >
                  Cancel
                </Button>
                <Button type="submit" disabled={processing}>
                  {processing ? 'Saving...' : 'Update Account'}
                </Button>
              </div>
            </form>
          </CardContent>
        </Card>
      </Main>
    </AuthenticatedLayout>
  )
}
