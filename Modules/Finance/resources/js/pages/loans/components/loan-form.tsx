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
import type { Loan, Currency, Transaction } from '@modules/Finance/types/finance'

type ExpenseTransaction = Pick<Transaction, 'id' | 'description' | 'notes' | 'amount' | 'currency_code' | 'transaction_date' | 'account'>

interface LoanFormProps {
  open: boolean
  onOpenChange: (open: boolean) => void
  loan?: Loan | null
  currencies: Currency[]
  expenseTransactions: ExpenseTransaction[]
  onSuccess?: () => void
}

function formatMoney(amount: number, currencyCode = 'VND'): string {
  try {
    return new Intl.NumberFormat('vi-VN', { style: 'currency', currency: currencyCode }).format(amount)
  } catch {
    return `${currencyCode} ${amount.toLocaleString()}`
  }
}

export function LoanForm({
  open,
  onOpenChange,
  loan,
  currencies,
  expenseTransactions,
  onSuccess,
}: LoanFormProps) {
  const { t } = useTranslation()
  const isEditing = !!loan
  const defaultCurrency = currencies.find(c => c.is_default)?.code || 'VND'
  const [transactionSearch, setTransactionSearch] = useState('')
  const [transactionSearchResults, setTransactionSearchResults] = useState<ExpenseTransaction[]>([])

  const { data, setData, post, put, processing, errors, reset } = useForm({
    transaction_id: '' as string,
    borrower_name: '',
    amount: '',
    currency_code: defaultCurrency,
    loan_date: format(new Date(), 'yyyy-MM-dd'),
    due_date: '',
    message: '',
  })

  useEffect(() => {
    if (loan) {
      setData({
        transaction_id: loan.transaction_id ? String(loan.transaction_id) : '',
        borrower_name: loan.borrower_name || '',
        amount: String(loan.amount),
        currency_code: loan.currency_code || defaultCurrency,
        loan_date: loan.loan_date ? loan.loan_date.split('T')[0] : '',
        due_date: loan.due_date ? loan.due_date.split('T')[0] : '',
        message: loan.message || '',
      })
    } else if (open) {
      reset()
      setData('loan_date', format(new Date(), 'yyyy-MM-dd'))
    }
  }, [loan, open])

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
          route('dashboard.finance.loans.search-expense-transactions', { search }),
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

  const availableExpenseTransactions = useMemo(() => {
    const transactions = new Map<string, ExpenseTransaction>()

    expenseTransactions.forEach(tx => transactions.set(String(tx.id), tx))
    transactionSearchResults.forEach(tx => transactions.set(String(tx.id), tx))

    if (loan?.transaction) {
      transactions.set(String(loan.transaction.id), loan.transaction)
    }

    return Array.from(transactions.values())
  }, [expenseTransactions, transactionSearchResults, loan])

  // When a transaction is selected, auto-fill amount, currency, and date
  const handleTransactionSelect = (txId: string) => {
    setData('transaction_id', txId)
    if (!txId) {
      return
    }
    const tx = availableExpenseTransactions.find(t => String(t.id) === txId)
    if (!tx) {
      return
    }
    setData(prev => ({
      ...prev,
      transaction_id: txId,
      amount: String(tx.amount),
      currency_code: tx.currency_code,
      loan_date: tx.transaction_date.split('T')[0],
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

    if (isEditing && loan) {
      put(route('dashboard.finance.loans.update', loan.id), {
        ...payload,
        onSuccess: () => {
          reset()
          onOpenChange(false)
          onSuccess?.()
        },
      })
    } else {
      post(route('dashboard.finance.loans.store'), {
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
    { value: '', label: t('page.loans.form.tx_none') },
    ...availableExpenseTransactions.map(tx => ({
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
            {isEditing ? t('page.loans.form.edit_title') : t('page.loans.form.record_title')}
          </SheetTitle>
          <SheetDescription>
            {isEditing ? t('page.loans.form.edit_desc') : t('page.loans.form.record_desc')}
          </SheetDescription>
        </SheetHeader>

        <form onSubmit={handleSubmit} className="space-y-4 mt-4">
          {/* Link to expense transaction */}
          <div className="space-y-2">
            <Label>{t('page.loans.form.link_transaction')}</Label>
            <Combobox
              options={txOptions}
              value={data.transaction_id}
              onChange={handleTransactionSelect}
              placeholder={t('page.loans.form.tx_placeholder')}
              searchPlaceholder={t('page.loans.form.tx_search')}
              emptyText={t('page.loans.form.tx_empty')}
              onSearchChange={setTransactionSearch}
            />
            {data.transaction_id && (
              <p className="text-xs text-muted-foreground">{t('page.loans.form.tx_autofill_hint')}</p>
            )}
            {errors.transaction_id && <p className="text-sm text-red-600">{errors.transaction_id}</p>}
          </div>

          <div className="space-y-2">
            <Label htmlFor="borrower_name">{t('page.loans.form.borrower_name')}</Label>
            <Input
              id="borrower_name"
              value={data.borrower_name}
              onChange={(e) => setData('borrower_name', e.target.value)}
              placeholder={t('page.loans.form.borrower_placeholder')}
            />
            {errors.borrower_name && <p className="text-sm text-red-600">{errors.borrower_name}</p>}
          </div>

          <div className="grid grid-cols-2 gap-3">
            <div className="space-y-2">
              <Label htmlFor="amount">{t('page.loans.form.amount')}</Label>
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
              <Label htmlFor="currency_code">{t('page.loans.form.currency')}</Label>
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
            <Label>{t('page.loans.form.loan_date')}</Label>
            <DatePicker
              value={data.loan_date}
              onChange={(date) => setData('loan_date', date ? format(date, 'yyyy-MM-dd') : '')}
              placeholder={t('page.loans.form.loan_date_placeholder')}
            />
            {errors.loan_date && <p className="text-sm text-red-600">{errors.loan_date}</p>}
          </div>

          <div className="space-y-2">
            <Label>{t('page.loans.form.due_date')}</Label>
            <DatePicker
              value={data.due_date}
              onChange={(date) => setData('due_date', date ? format(date, 'yyyy-MM-dd') : '')}
              placeholder={t('page.loans.form.due_date_placeholder')}
            />
            {errors.due_date && <p className="text-sm text-red-600">{errors.due_date}</p>}
          </div>

          <div className="space-y-2">
            <Label htmlFor="message">{t('page.loans.form.notes')}</Label>
            <Textarea
              id="message"
              value={data.message}
              onChange={(e) => setData('message', e.target.value)}
              placeholder={t('page.loans.form.notes_placeholder')}
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
                  ? t('page.loans.form.submit_update')
                  : t('page.loans.form.submit_record')}
            </Button>
          </SheetFooter>
        </form>
      </SheetContent>
    </Sheet>
  )
}
