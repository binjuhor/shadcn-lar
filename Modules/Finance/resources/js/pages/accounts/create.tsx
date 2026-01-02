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
import type { Currency, AccountType } from '@modules/Finance/types/finance'

interface Props {
  currencies: Currency[]
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

export default function CreateAccount({ currencies }: Props) {
  const defaultCurrency = currencies.find(c => c.is_default)?.code || 'VND'

  const { data, setData, post, processing, errors, transform, reset } = useForm({
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

  transform((formData) => ({
    ...formData,
    initial_balance: Math.round(parseFloat(formData.initial_balance || '0')),
    rate_source: formData.rate_source === '__default__' ? null : formData.rate_source,
  }))

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault()
    post(route('dashboard.finance.accounts.store'), {
      preserveState: false,
      onSuccess: () => reset(),
    })
  }

  return (
    <AuthenticatedLayout title="Create Account">
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
            <CardTitle>Create Account</CardTitle>
            <CardDescription>
              Add a new account to track your finances
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
                <Label htmlFor="initial_balance">Initial Balance</Label>
                <Input
                  id="initial_balance"
                  type="number"
                  step="0.01"
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
                  {processing ? 'Creating...' : 'Create Account'}
                </Button>
              </div>
            </form>
          </CardContent>
        </Card>
      </Main>
    </AuthenticatedLayout>
  )
}
