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
import type { Borrowing } from '@modules/Finance/types/finance'

interface RepaymentFormProps {
  open: boolean
  onOpenChange: (open: boolean) => void
  borrowing: Borrowing | null
  onSuccess?: () => void
}

export function RepaymentForm({ open, onOpenChange, borrowing, onSuccess }: RepaymentFormProps) {
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
    if (!borrowing) {
      return
    }

    post(route('dashboard.finance.borrowings.repayments.store', borrowing.id), {
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

  if (!borrowing) {
    return null
  }

  return (
    <Sheet open={open} onOpenChange={onOpenChange}>
      <SheetContent className="overflow-y-auto">
        <SheetHeader>
          <SheetTitle>{t('page.borrowings.repayment.title')}</SheetTitle>
          <SheetDescription>
            {t('page.borrowings.repayment.desc', {
              name: borrowing.lender_name,
              currency: borrowing.currency_code,
              remaining: borrowing.remaining_amount.toLocaleString(),
            })}
          </SheetDescription>
        </SheetHeader>

        <form onSubmit={handleSubmit} className="space-y-4 mt-4">
          <div className="space-y-2">
            <Label htmlFor="amount">
              {t('page.borrowings.repayment.amount', { currency: borrowing.currency_code })}
            </Label>
            <Input
              id="amount"
              type="number"
              step="any"
              min="0.01"
              value={data.amount}
              onChange={(e) => setData('amount', e.target.value)}
              placeholder={t('page.borrowings.repayment.amount_placeholder', {
                max: borrowing.remaining_amount.toLocaleString(),
              })}
              autoFocus
            />
            {errors.amount && <p className="text-sm text-red-600">{errors.amount}</p>}
          </div>

          <div className="space-y-2">
            <Label>{t('page.borrowings.repayment.date')}</Label>
            <DatePicker
              value={data.repaid_at}
              onChange={(date) => setData('repaid_at', date ? format(date, 'yyyy-MM-dd') : '')}
              placeholder={t('page.borrowings.repayment.date')}
            />
            {errors.repaid_at && <p className="text-sm text-red-600">{errors.repaid_at}</p>}
          </div>

          <div className="space-y-2">
            <Label htmlFor="note">{t('page.borrowings.repayment.note')}</Label>
            <Textarea
              id="note"
              value={data.note}
              onChange={(e) => setData('note', e.target.value)}
              placeholder={t('page.borrowings.repayment.note_placeholder')}
              rows={2}
            />
            {errors.note && <p className="text-sm text-red-600">{errors.note}</p>}
          </div>

          <SheetFooter className="gap-2 pt-4">
            <Button type="button" variant="outline" onClick={handleClose} disabled={processing}>
              {t('action.cancel')}
            </Button>
            <Button type="submit" disabled={processing}>
              {processing ? t('action.save') : t('page.borrowings.repayment.submit')}
            </Button>
          </SheetFooter>
        </form>
      </SheetContent>
    </Sheet>
  )
}
