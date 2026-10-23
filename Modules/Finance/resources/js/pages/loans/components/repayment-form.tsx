import { useEffect } from 'react'
import { useForm } from '@inertiajs/react'
import { useTranslation } from 'react-i18next'
import { format } from 'date-fns'
import { Button } from '@/components/ui/button'
import { DatePicker } from '@/components/ui/date-picker'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Textarea } from '@/components/ui/textarea'
import {
  Sheet,
  SheetContent,
  SheetDescription,
  SheetHeader,
  SheetTitle,
  SheetFooter,
} from '@/components/ui/sheet'
import type { Loan } from '@modules/Finance/types/finance'

interface RepaymentFormProps {
  open: boolean
  onOpenChange: (open: boolean) => void
  loan: Loan | null
  onSuccess?: () => void
}

export function RepaymentForm({ open, onOpenChange, loan, onSuccess }: RepaymentFormProps) {
  const { t } = useTranslation()
  const { data, setData, post, processing, errors, reset } = useForm({
    amount: '',
    repaid_at: format(new Date(), 'yyyy-MM-dd'),
    note: '',
  })

  useEffect(() => {
    if (open) {
      reset()
      setData('repaid_at', format(new Date(), 'yyyy-MM-dd'))
    }
  }, [open])

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault()
    if (!loan) {
      return
    }

    post(route('dashboard.finance.loans.repayments.store', loan.id), {
      onSuccess: () => {
        reset()
        onOpenChange(false)
        onSuccess?.()
      },
    })
  }

  const handleClose = () => {
    reset()
    onOpenChange(false)
  }

  if (!loan) {
    return null
  }

  return (
    <Sheet open={open} onOpenChange={onOpenChange}>
      <SheetContent className="overflow-y-auto">
        <SheetHeader>
          <SheetTitle>{t('page.loans.repayment.title')}</SheetTitle>
          <SheetDescription>
            {t('page.loans.repayment.desc', {
              name: loan.borrower_name,
              currency: loan.currency_code,
              remaining: loan.remaining_amount.toLocaleString(),
            })}
          </SheetDescription>
        </SheetHeader>

        <form onSubmit={handleSubmit} className="space-y-4 mt-4">
          <div className="space-y-2">
            <Label htmlFor="amount">
              {t('page.loans.repayment.amount', { currency: loan.currency_code })}
            </Label>
            <Input
              id="amount"
              type="number"
              step="any"
              min="0.01"
              value={data.amount}
              onChange={(e) => setData('amount', e.target.value)}
              placeholder={t('page.loans.repayment.amount_placeholder', {
                max: loan.remaining_amount.toLocaleString(),
              })}
              autoFocus
            />
            {errors.amount && <p className="text-sm text-red-600">{errors.amount}</p>}
          </div>

          <div className="space-y-2">
            <Label>{t('page.loans.repayment.date')}</Label>
            <DatePicker
              value={data.repaid_at}
              onChange={(date) => setData('repaid_at', date ? format(date, 'yyyy-MM-dd') : '')}
              placeholder={t('page.loans.repayment.date')}
            />
            {errors.repaid_at && <p className="text-sm text-red-600">{errors.repaid_at}</p>}
          </div>

          <div className="space-y-2">
            <Label htmlFor="note">{t('page.loans.repayment.note')}</Label>
            <Textarea
              id="note"
              value={data.note}
              onChange={(e) => setData('note', e.target.value)}
              placeholder={t('page.loans.repayment.note_placeholder')}
              rows={2}
            />
            {errors.note && <p className="text-sm text-red-600">{errors.note}</p>}
          </div>

          <SheetFooter className="gap-2 pt-4">
            <Button type="button" variant="outline" onClick={handleClose} disabled={processing}>
              {t('action.cancel')}
            </Button>
            <Button type="submit" disabled={processing}>
              {processing ? t('action.save') : t('page.loans.repayment.submit')}
            </Button>
          </SheetFooter>
        </form>
      </SheetContent>
    </Sheet>
  )
}
