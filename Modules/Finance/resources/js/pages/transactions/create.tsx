import { Link, useForm, router } from '@inertiajs/react'
import { format } from 'date-fns'
import { useEffect, useState, useCallback, useMemo } from 'react'
import { useTranslation } from 'react-i18next'
import { AuthenticatedLayout } from '@/layouts'
import { Main } from '@/components/layout/main'
import { Button } from '@/components/ui/button'
import { DatePicker } from '@/components/ui/date-picker'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Textarea } from '@/components/ui/textarea'
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
import { Tabs, TabsList, TabsTrigger } from '@/components/ui/tabs'
import { ArrowLeft, ArrowRight, RefreshCw } from 'lucide-react'
import type { Account, Category, TransactionType } from '@modules/Finance/types/finance'

interface Props {
  accounts: Account[]
  categories: Category[]
}

interface ConversionPreview {
  same_currency: boolean
  amount: number
  converted_amount: number
  exchange_rate: number
  from_currency: string
  to_currency: string
  rate_source?: string
  error?: string
}

export default function CreateTransaction({ accounts, categories }: Props) {
  const { t } = useTranslation()
  const { data, setData, post, processing, errors, transform, reset } = useForm({
    type: 'expense' as TransactionType,
    account_id: '',
    category_id: '',
    amount: '',
    description: '',
    notes: '',
    transaction_date: new Date().toISOString().split('T')[0],
    transfer_account_id: '',
  })

  const [conversionPreview, setConversionPreview] = useState<ConversionPreview | null>(null)
  const [loadingPreview, setLoadingPreview] = useState(false)

  const incomeCategories = categories.filter((c) => c.type === 'income' || c.type === 'both')
  const expenseCategories = categories.filter((c) => c.type === 'expense' || c.type === 'both')
  const currentCategories = data.type === 'income' ? incomeCategories : expenseCategories

  const fromAccount = useMemo(
    () => accounts.find((a) => String(a.id) === data.account_id),
    [accounts, data.account_id]
  )
  const toAccount = useMemo(
    () => accounts.find((a) => String(a.id) === data.transfer_account_id),
    [accounts, data.transfer_account_id]
  )

  const fetchConversionPreview = useCallback(async () => {
    if (
      data.type !== 'transfer' ||
      !data.account_id ||
      !data.transfer_account_id ||
      !data.amount ||
      parseFloat(data.amount) <= 0
    ) {
      setConversionPreview(null)
      return
    }

    setLoadingPreview(true)
    try {
      const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
      const response = await fetch(route('dashboard.finance.transactions.conversion-preview'), {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          Accept: 'application/json',
          'X-CSRF-TOKEN': csrfToken || '',
        },
        body: JSON.stringify({
          from_account_id: parseInt(data.account_id),
          to_account_id: parseInt(data.transfer_account_id),
          amount: parseFloat(data.amount),
        }),
      })

      if (response.ok) {
        const preview = await response.json()
        setConversionPreview(preview)
      }
    } catch (error) {
      console.error('Failed to fetch conversion preview:', error)
    } finally {
      setLoadingPreview(false)
    }
  }, [data.type, data.account_id, data.transfer_account_id, data.amount])

  // Debounce conversion preview fetch
  useEffect(() => {
    const timeout = setTimeout(fetchConversionPreview, 300)
    return () => clearTimeout(timeout)
  }, [fetchConversionPreview])

  transform((formData) => ({
    ...formData,
    amount: parseFloat(formData.amount || '0'),
    account_id: formData.account_id ? parseInt(formData.account_id) : null,
    category_id: formData.category_id ? parseInt(formData.category_id) : null,
    transfer_account_id: formData.transfer_account_id ? parseInt(formData.transfer_account_id) : null,
  }))

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault()
    post(route('dashboard.finance.transactions.store'), {
      preserveState: false,
      onSuccess: () => reset(),
    })
  }

  const handleTypeChange = (type: TransactionType) => {
    setData('type', type)
    setData('category_id', '')
    if (type !== 'transfer') {
      setData('transfer_account_id', '')
      setConversionPreview(null)
    }
  }

  const formatMoney = (amount: number, currency: string) => {
    return new Intl.NumberFormat('vi-VN', {
      style: 'currency',
      currency,
      maximumFractionDigits: 0,
    }).format(amount)
  }

  return (
    <AuthenticatedLayout title={t('form.transaction.create')}>
      <Main>
        <div className="mb-4">
          <Link
            href={route('dashboard.finance.transactions.index')}
            className="inline-flex items-center text-sm text-muted-foreground hover:text-foreground"
          >
            <ArrowLeft className="mr-2 h-4 w-4" />
            {t('action.back_to_transactions')}
          </Link>
        </div>

        <Card className="max-w-2xl">
          <CardHeader>
            <CardTitle>{t('form.transaction.create')}</CardTitle>
            <CardDescription>
              {t('form.transaction.create_description')}
            </CardDescription>
          </CardHeader>
          <CardContent>
            <form onSubmit={handleSubmit} className="space-y-4">
              {/* Transaction Type Tabs */}
              <Tabs value={data.type} onValueChange={(v) => handleTypeChange(v as TransactionType)}>
                <TabsList className="grid w-full grid-cols-3">
                  <TabsTrigger
                    value="expense"
                    className="data-[state=active]:bg-red-500 data-[state=active]:text-white data-[state=inactive]:text-red-600"
                  >
                    {t('transaction.expense')}
                  </TabsTrigger>
                  <TabsTrigger
                    value="income"
                    className="data-[state=active]:bg-green-500 data-[state=active]:text-white data-[state=inactive]:text-green-600"
                  >
                    {t('transaction.income')}
                  </TabsTrigger>
                  <TabsTrigger
                    value="transfer"
                    className="data-[state=active]:bg-blue-500 data-[state=active]:text-white data-[state=inactive]:text-blue-600"
                  >
                    {t('transaction.transfer')}
                  </TabsTrigger>
                </TabsList>
              </Tabs>

              <div className="space-y-2">
                <Label htmlFor="amount">{t('form.amount')}</Label>
                <Input
                  id="amount"
                  type="number"
                  step="0.01"
                  min="0"
                  value={data.amount}
                  onChange={(e) => setData('amount', e.target.value)}
                  placeholder={t('form.balance_placeholder')}
                  className="text-2xl font-bold h-14"
                />
                {errors.amount && (
                  <p className="text-sm text-red-600">{errors.amount}</p>
                )}
              </div>

              <div className="space-y-2">
                <Label htmlFor="account_id">
                  {data.type === 'transfer' ? t('form.from_account') : t('form.account')}
                </Label>
                <Select
                  value={data.account_id}
                  onValueChange={(value) => setData('account_id', value)}
                >
                  <SelectTrigger>
                    <SelectValue placeholder={t('form.select_account')} />
                  </SelectTrigger>
                  <SelectContent>
                    {accounts.filter(a => a.is_active).map((account) => (
                      <SelectItem key={account.id} value={String(account.id)}>
                        {account.name} ({account.currency_code})
                      </SelectItem>
                    ))}
                  </SelectContent>
                </Select>
                {errors.account_id && (
                  <p className="text-sm text-red-600">{errors.account_id}</p>
                )}
              </div>

              {data.type === 'transfer' && (
                <>
                  <div className="space-y-2">
                    <Label htmlFor="transfer_account_id">{t('form.to_account')}</Label>
                    <Select
                      value={data.transfer_account_id}
                      onValueChange={(value) => setData('transfer_account_id', value)}
                    >
                      <SelectTrigger>
                        <SelectValue placeholder={t('form.select_destination_account')} />
                      </SelectTrigger>
                      <SelectContent>
                        {accounts
                          .filter(a => a.is_active && String(a.id) !== data.account_id)
                          .map((account) => (
                            <SelectItem key={account.id} value={String(account.id)}>
                              {account.name} ({account.currency_code})
                            </SelectItem>
                          ))}
                      </SelectContent>
                    </Select>
                    {errors.transfer_account_id && (
                      <p className="text-sm text-red-600">{errors.transfer_account_id}</p>
                    )}
                  </div>

                  {/* Conversion Preview */}
                  {conversionPreview && !conversionPreview.same_currency && (
                    <div className="rounded-lg border bg-blue-50 dark:bg-blue-950/30 p-4">
                      <div className="flex items-center gap-2 text-sm text-muted-foreground mb-2">
                        <RefreshCw className={`h-4 w-4 ${loadingPreview ? 'animate-spin' : ''}`} />
                        <span>{t('form.currency_conversion')}</span>
                      </div>
                      {conversionPreview.error ? (
                        <p className="text-sm text-red-600">{conversionPreview.error}</p>
                      ) : (
                        <>
                          <div className="flex items-center gap-3 text-lg font-semibold">
                            <span>{formatMoney(conversionPreview.amount, conversionPreview.from_currency)}</span>
                            <ArrowRight className="h-5 w-5 text-muted-foreground" />
                            <span className="text-green-600">
                              {formatMoney(conversionPreview.converted_amount, conversionPreview.to_currency)}
                            </span>
                          </div>
                          <p className="text-xs text-muted-foreground mt-1">
                            Rate: 1 {conversionPreview.from_currency} = {conversionPreview.exchange_rate.toLocaleString('en-US', { maximumFractionDigits: 4 })} {conversionPreview.to_currency}
                            {conversionPreview.rate_source && conversionPreview.rate_source !== 'default' && (
                              <span className="ml-1">({conversionPreview.rate_source})</span>
                            )}
                          </p>
                        </>
                      )}
                    </div>
                  )}
                </>
              )}

              {data.type !== 'transfer' && (
                <div className="space-y-2">
                  <Label htmlFor="category_id">{t('form.category')}</Label>
                  <Select
                    value={data.category_id}
                    onValueChange={(value) => setData('category_id', value)}
                  >
                    <SelectTrigger>
                      <SelectValue placeholder={t('form.select_category')} />
                    </SelectTrigger>
                    <SelectContent>
                      {currentCategories.map((category) => (
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
              )}

              <div className="space-y-2">
                <Label>{t('form.date')}</Label>
                <DatePicker
                  value={data.transaction_date}
                  onChange={(date) => setData('transaction_date', date ? format(date, 'yyyy-MM-dd') : '')}
                  placeholder={t('filter.select_date')}
                />
                {errors.transaction_date && (
                  <p className="text-sm text-red-600">{errors.transaction_date}</p>
                )}
              </div>

              <div className="space-y-2">
                <Label htmlFor="description">{t('form.description')}</Label>
                <Input
                  id="description"
                  value={data.description}
                  onChange={(e) => setData('description', e.target.value)}
                  placeholder={t('form.description_placeholder_transaction')}
                />
                {errors.description && (
                  <p className="text-sm text-red-600">{errors.description}</p>
                )}
              </div>

              <div className="space-y-2">
                <Label htmlFor="notes">{t('form.notes_optional')}</Label>
                <Textarea
                  id="notes"
                  value={data.notes}
                  onChange={(e) => setData('notes', e.target.value)}
                  placeholder={t('form.additional_notes')}
                  rows={2}
                />
              </div>

              <div className="flex gap-3 pt-4">
                <Button
                  type="button"
                  variant="outline"
                  onClick={() => router.visit(route('dashboard.finance.transactions.index'))}
                  disabled={processing}
                >
                  {t('action.cancel')}
                </Button>
                <Button type="submit" disabled={processing}>
                  {processing ? t('common.saving') : t('form.save_transaction')}
                </Button>
              </div>
            </form>
          </CardContent>
        </Card>
      </Main>
    </AuthenticatedLayout>
  )
}
