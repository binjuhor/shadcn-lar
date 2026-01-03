import { useForm } from '@inertiajs/react'
import { format } from 'date-fns'
import { Button } from '@/components/ui/button'
import { DatePicker } from '@/components/ui/date-picker'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Textarea } from '@/components/ui/textarea'
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogHeader,
  DialogTitle,
  DialogFooter,
} from '@/components/ui/dialog'
import type { SavingsGoal } from '@modules/Finance/types/finance'

interface ContributionFormProps {
  open: boolean
  onOpenChange: (open: boolean) => void
  goal: SavingsGoal | null
  mode: 'deposit' | 'withdraw'
  onSuccess?: () => void
}

function formatMoney(amount: number, currencyCode = 'VND'): string {
  return new Intl.NumberFormat('vi-VN', {
    style: 'currency',
    currency: currencyCode,
  }).format(amount)
}

export function ContributionForm({
  open,
  onOpenChange,
  goal,
  mode,
  onSuccess,
}: ContributionFormProps) {
  const isDeposit = mode === 'deposit'

  const { data, setData, post, processing, errors, reset } = useForm({
    amount: '',
    contribution_date: new Date().toISOString().split('T')[0],
    notes: '',
  })

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault()

    if (!goal) return

    const formData = {
      ...data,
      amount: Math.round(parseFloat(data.amount || '0')),
    }

    const routeName = isDeposit
      ? 'dashboard.finance.savings-goals.contribute'
      : 'dashboard.finance.savings-goals.withdraw'

    post(route(routeName, goal.id), {
      ...formData,
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

  if (!goal) return null

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent>
        <DialogHeader>
          <DialogTitle>
            {isDeposit ? 'Add Money' : 'Withdraw Money'}
          </DialogTitle>
          <DialogDescription>
            {isDeposit
              ? `Add funds to "${goal.name}"`
              : `Withdraw funds from "${goal.name}"`}
          </DialogDescription>
        </DialogHeader>

        <form onSubmit={handleSubmit} className="space-y-4">
          {!isDeposit && (
            <div className="p-3 rounded-lg bg-muted">
              <p className="text-sm text-muted-foreground">
                Available balance:{' '}
                <span className="font-medium text-foreground">
                  {formatMoney(goal.current_amount, goal.currency_code)}
                </span>
              </p>
            </div>
          )}

          <div className="space-y-2">
            <Label htmlFor="amount">Amount ({goal.currency_code})</Label>
            <Input
              id="amount"
              type="number"
              step="1"
              min="1"
              max={!isDeposit ? goal.current_amount : undefined}
              value={data.amount}
              onChange={(e) => setData('amount', e.target.value)}
              placeholder="0"
              autoFocus
            />
            {errors.amount && (
              <p className="text-sm text-red-600">{errors.amount}</p>
            )}
          </div>

          <div className="space-y-2">
            <Label>Date</Label>
            <DatePicker
              value={data.contribution_date}
              onChange={(date) => setData('contribution_date', date ? format(date, 'yyyy-MM-dd') : '')}
              placeholder="Select date"
            />
            {errors.contribution_date && (
              <p className="text-sm text-red-600">{errors.contribution_date}</p>
            )}
          </div>

          <div className="space-y-2">
            <Label htmlFor="notes">Notes (Optional)</Label>
            <Textarea
              id="notes"
              value={data.notes}
              onChange={(e) => setData('notes', e.target.value)}
              placeholder="Add a note..."
              rows={2}
            />
            {errors.notes && (
              <p className="text-sm text-red-600">{errors.notes}</p>
            )}
          </div>

          <DialogFooter className="gap-2">
            <Button
              type="button"
              variant="outline"
              onClick={handleClose}
              disabled={processing}
            >
              Cancel
            </Button>
            <Button
              type="submit"
              disabled={processing}
              variant={isDeposit ? 'default' : 'destructive'}
            >
              {processing
                ? 'Processing...'
                : isDeposit
                  ? 'Add Money'
                  : 'Withdraw'}
            </Button>
          </DialogFooter>
        </form>
      </DialogContent>
    </Dialog>
  )
}
