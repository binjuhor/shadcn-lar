import { Link, useForm } from '@inertiajs/react'
import { format } from 'date-fns'
import { AuthenticatedLayout } from '@/layouts'
import { Main } from '@/components/layout/main'
import { Button } from '@/components/ui/button'
import { DatePicker } from '@/components/ui/date-picker'
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
import type { SavingsGoal, Currency, Account } from '@modules/Finance/types/finance'

interface Props {
  goal: SavingsGoal
  currencies: Currency[]
  accounts: Account[]
}

const colors = [
  '#3b82f6', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6',
  '#ec4899', '#06b6d4', '#84cc16', '#f97316', '#6366f1',
]

export default function EditSavingsGoal({ goal, currencies, accounts }: Props) {
  const { data, setData, put, processing, errors, transform } = useForm({
    name: goal.name || '',
    description: goal.description || '',
    target_amount: String(goal.target_amount),
    currency_code: goal.currency_code,
    target_date: goal.target_date ? goal.target_date.split('T')[0] : '',
    target_account_id: goal.target_account_id ? String(goal.target_account_id) : '',
    color: goal.color || '#3b82f6',
    is_active: goal.is_active,
  })

  transform((formData) => ({
    ...formData,
    target_amount: Math.round(parseFloat(formData.target_amount || '0')),
    target_account_id: formData.target_account_id ? parseInt(formData.target_account_id) : null,
    target_date: formData.target_date || null,
  }))

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault()
    put(route('dashboard.finance.savings-goals.update', goal.id))
  }

  return (
    <AuthenticatedLayout title="Edit Savings Goal">
      <Main>
        <div className="mb-4">
          <Link
            href={route('dashboard.finance.savings-goals.index')}
            className="inline-flex items-center text-sm text-muted-foreground hover:text-foreground"
          >
            <ArrowLeft className="mr-2 h-4 w-4" />
            Back to Savings Goals
          </Link>
        </div>

        <Card className="max-w-2xl">
          <CardHeader>
            <CardTitle>Edit Savings Goal</CardTitle>
            <CardDescription>
              Update details for "{goal.name}"
            </CardDescription>
          </CardHeader>
          <CardContent>
            <form onSubmit={handleSubmit} className="space-y-4">
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

              <div className="flex gap-3 pt-4">
                <Button
                  type="button"
                  variant="outline"
                  onClick={() => window.history.back()}
                  disabled={processing}
                >
                  Cancel
                </Button>
                <Button type="submit" disabled={processing}>
                  {processing ? 'Saving...' : 'Update Goal'}
                </Button>
              </div>
            </form>
          </CardContent>
        </Card>
      </Main>
    </AuthenticatedLayout>
  )
}
