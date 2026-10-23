import { useEffect, useMemo, useState } from 'react'
import { useForm } from '@inertiajs/react'
import { useTranslation } from 'react-i18next'
import { format } from 'date-fns'
import { Button } from '@/components/ui/button'
import { DatePicker } from '@/components/ui/date-picker'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Textarea } from '@/components/ui/textarea'
import { Combobox } from '@/components/ui/combobox'
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
import { formatDateDisplay } from '@/lib/date-utils'
import type { Borrowing, Currency, Transaction } from '@modules/Finance/types/finance'

type IncomeTransaction = Pick<Transaction, 'id' | 'description' | 'notes' | 'amount' | 'currency_code' | 'transaction_date' | 'account'>

interface BorrowingFormProps {
  open: boolean
  onOpenChange: (open: boolean) => void
  borrowing?: Borrowing | null
  currencies: Currency[]
  incomeTransactions: IncomeTransaction[]
  onSuccess?: () => void
}

function formatMoney(amount: number, currencyCode = 'VND'): string {
  try {
    return new Intl.NumberFormat('vi-VN', { style: 'currency', currency: currencyCode }).format(amount)
  } catch {
    return `${currencyCode} ${amount.toLocaleString()}`
  }
}

export function BorrowingForm({
  open,
  onOpenChange,
  borrowing,
  currencies,
  incomeTransactions,
  onSuccess,
}: BorrowingFormProps) {
  const { t } = useTranslation()
  const isEditing = !!borrowing
  const defaultCurrency = currencies.find(c => c.is_default)?.code || 'VND'
  const [transactionSearch, setTransactionSearch] = useState('')
  const [transactionSearchResults, setTransactionSearchResults] = useState<IncomeTransaction[]>([])

  const { data, setData, post, put, processing, errors, reset } = useForm({
    transaction_id: '' as string,
    lender_name: '',
    amount: '',
    currency_code: defaultCurrency,
    borrowed_date: format(new Date(), 'yyyy-MM-dd'),
    due_date: '',
    message: '',
  })

  useEffect(() => {
    if (borrowing) {
      setData({
        transaction_id: borrowing.transaction_id ? String(borrowing.transaction_id) : '',
        lender_name: borrowing.lender_name || '',
        amount: String(borrowing.amount),
        currency_code: borrowing.currency_code || defaultCurrency,
        borrowed_date: borrowing.borrowed_date ? borrowing.borrowed_date.split('T')[0] : '',
        due_date: borrowing.due_date ? borrowing.due_date.split('T')[0] : '',
        message: borrowing.message || '',
      })
    } else if (open) {
      reset()
      setData('borrowed_date', format(new Date(), 'yyyy-MM-dd'))
    }
  }, [borrowing, open])

  useEffect(() => {
    if (!open) {
      setTransactionSearch('')
      setTransactionSearchResults([])
      return
    }

    const search = transactionSearch.trim()
    if (!search) {
      setTransactionSearchResults([])
      return
    }

    const controller = new AbortController()
    const timeout = window.setTimeout(async () => {
      try {
        const response = await fetch(
          route('dashboard.finance.borrowings.search-income-transactions', { search }),
          {
            headers: { Accept: 'application/json' },
            signal: controller.signal,
          }
        )

        if (!response.ok) {
          return
        }

        const payload = await response.json()
        setTransactionSearchResults(payload.data ?? [])
      } catch (error) {
        if (!(error instanceof DOMException && error.name === 'AbortError')) {
          setTransactionSearchResults([])
        }
      }
    }, 250)

    return () => {
      controller.abort()
      window.clearTimeout(timeout)
    }
  }, [open, transactionSearch])

  const availableIncomeTransactions = useMemo(() => {
    const transactions = new Map<string, IncomeTransaction>()

    incomeTransactions.forEach(tx => transactions.set(String(tx.id), tx))
    transactionSearchResults.forEach(tx => transactions.set(String(tx.id), tx))

    if (borrowing?.transaction) {
      transactions.set(String(borrowing.transaction.id), borrowing.transaction)
    }

    return Array.from(transactions.values())
  }, [incomeTransactions, transactionSearchResults, borrowing])

  // When a transaction is selected, auto-fill amount, currency, and date
  const handleTransactionSelect = (txId: string) => {
    setData('transaction_id', txId)
    if (!txId) {
      return
    }
    const tx = availableIncomeTransactions.find(t => String(t.id) === txId)
    if (!tx) {
      return
    }
    setData(prev => ({
      ...prev,
      transaction_id: txId,
      amount: String(tx.amount),
      currency_code: tx.currency_code,
      borrowed_date: tx.transaction_date.split('T')[0],
    }))
  }

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault()

    const payload = {
      ...data,
      transaction_id: data.transaction_id ? parseInt(data.transaction_id) : null,
      amount: parseFloat(data.amount || '0'),
      due_date: data.due_date || null,
      message: data.message || null,
    }

    if (isEditing && borrowing) {
      put(route('dashboard.finance.borrowings.update', borrowing.id), {
        ...payload,
        onSuccess: () => {
          reset()
          onOpenChange(false)
          onSuccess?.()
        },
      })
    } else {
      post(route('dashboard.finance.borrowings.store'), {
        ...payload,
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

  const txOptions = [
    { value: '', label: t('page.borrowings.form.tx_none') },
    ...availableIncomeTransactions.map(tx => ({
      value: String(tx.id),
      label: `${formatDateDisplay(tx.transaction_date)} · ${tx.description || t('common.no_description')} · ${formatMoney(tx.amount, tx.currency_code)}${tx.account ? ` (${tx.account.name})` : ''}`,
      keywords: [tx.description, tx.notes, tx.account?.name].filter(Boolean).join(' '),
    })),
  ]

  return (
    <Sheet open={open} onOpenChange={onOpenChange}>
      <SheetContent className="overflow-y-auto">
        <SheetHeader>
          <SheetTitle>
            {isEditing ? t('page.borrowings.form.edit_title') : t('page.borrowings.form.record_title')}
          </SheetTitle>
          <SheetDescription>
            {isEditing ? t('page.borrowings.form.edit_desc') : t('page.borrowings.form.record_desc')}
          </SheetDescription>
        </SheetHeader>

        <form onSubmit={handleSubmit} className="space-y-4 mt-4">
          {/* Link to income transaction */}
          <div className="space-y-2">
            <Label>{t('page.borrowings.form.link_transaction')}</Label>
            <Combobox
              options={txOptions}
              value={data.transaction_id}
              onChange={handleTransactionSelect}
              placeholder={t('page.borrowings.form.tx_placeholder')}
              searchPlaceholder={t('page.borrowings.form.tx_search')}
              emptyText={t('page.borrowings.form.tx_empty')}
              onSearchChange={setTransactionSearch}
            />
            {data.transaction_id && (
              <p className="text-xs text-muted-foreground">{t('page.borrowings.form.tx_autofill_hint')}</p>
            )}
            {errors.transaction_id && <p className="text-sm text-red-600">{errors.transaction_id}</p>}
          </div>

          <div className="space-y-2">
            <Label htmlFor="lender_name">{t('page.borrowings.form.lender_name')}</Label>
            <Input
              id="lender_name"
              value={data.lender_name}
              onChange={(e) => setData('lender_name', e.target.value)}
              placeholder={t('page.borrowings.form.lender_placeholder')}
            />
            {errors.lender_name && <p className="text-sm text-red-600">{errors.lender_name}</p>}
          </div>

          <div className="grid grid-cols-2 gap-3">
            <div className="space-y-2">
              <Label htmlFor="amount">{t('page.borrowings.form.amount')}</Label>
              <Input
                id="amount"
                type="number"
                step="any"
                min="0.01"
                value={data.amount}
                onChange={(e) => setData('amount', e.target.value)}
                placeholder="0"
              />
              {errors.amount && <p className="text-sm text-red-600">{errors.amount}</p>}
            </div>

            <div className="space-y-2">
              <Label htmlFor="currency_code">{t('page.borrowings.form.currency')}</Label>
              <Select value={data.currency_code} onValueChange={(v) => setData('currency_code', v)}>
                <SelectTrigger>
                  <SelectValue />
                </SelectTrigger>
                <SelectContent>
                  {currencies.map((c) => (
                    <SelectItem key={c.code} value={c.code}>
                      {c.code}
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
              {errors.currency_code && <p className="text-sm text-red-600">{errors.currency_code}</p>}
            </div>
          </div>

          <div className="space-y-2">
            <Label>{t('page.borrowings.form.borrowed_date')}</Label>
            <DatePicker
              value={data.borrowed_date}
              onChange={(date) => setData('borrowed_date', date ? format(date, 'yyyy-MM-dd') : '')}
              placeholder={t('page.borrowings.form.borrowed_date_placeholder')}
            />
            {errors.borrowed_date && <p className="text-sm text-red-600">{errors.borrowed_date}</p>}
          </div>

          <div className="space-y-2">
            <Label>{t('page.borrowings.form.due_date')}</Label>
            <DatePicker
              value={data.due_date}
              onChange={(date) => setData('due_date', date ? format(date, 'yyyy-MM-dd') : '')}
              placeholder={t('page.borrowings.form.due_date_placeholder')}
            />
            {errors.due_date && <p className="text-sm text-red-600">{errors.due_date}</p>}
          </div>

          <div className="space-y-2">
            <Label htmlFor="message">{t('page.borrowings.form.notes')}</Label>
            <Textarea
              id="message"
              value={data.message}
              onChange={(e) => setData('message', e.target.value)}
              placeholder={t('page.borrowings.form.notes_placeholder')}
              rows={3}
            />
            {errors.message && <p className="text-sm text-red-600">{errors.message}</p>}
          </div>

          <SheetFooter className="gap-2 pt-4">
            <Button type="button" variant="outline" onClick={handleClose} disabled={processing}>
              {t('action.cancel')}
            </Button>
            <Button type="submit" disabled={processing}>
              {processing
                ? t('action.save')
                : isEditing
                  ? t('page.borrowings.form.submit_update')
                  : t('page.borrowings.form.submit_record')}
            </Button>
          </SheetFooter>
        </form>
      </SheetContent>
    </Sheet>
  )
}
