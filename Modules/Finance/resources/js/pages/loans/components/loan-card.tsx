import { useTranslation } from 'react-i18next'
import { formatDateDisplay } from '@/lib/date-utils'
import { Card, CardContent, CardFooter, CardHeader } from '@/components/ui/card'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Progress } from '@/components/ui/progress'
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu'
import {
  MoreHorizontal,
  Pencil,
  Trash2,
  Plus,
  Calendar,
  AlertCircle,
  CheckCircle2,
} from 'lucide-react'
import type { Loan } from '@modules/Finance/types/finance'

interface LoanCardProps {
  loan: Loan
  onEdit: (loan: Loan) => void
  onDelete: (loan: Loan) => void
  onRepay: (loan: Loan) => void
}

function formatMoney(amount: number, currencyCode = 'VND'): string {
  try {
    return new Intl.NumberFormat('vi-VN', { style: 'currency', currency: currencyCode }).format(amount)
  } catch {
    return `${currencyCode} ${amount.toLocaleString()}`
  }
}

function getDaysOverdue(dueDate: string): number {
  const due = new Date(dueDate)
  const today = new Date()
  return Math.ceil((today.getTime() - due.getTime()) / (1000 * 60 * 60 * 24))
}

export function LoanCard({ loan, onEdit, onDelete, onRepay }: LoanCardProps) {
  const { t } = useTranslation()
  const progress = Math.min(loan.progress_percent, 100)
  const isRepaid = loan.status === 'repaid'
  const isWrittenOff = loan.status === 'written_off'
  const isSettled = isRepaid || isWrittenOff
  const daysOverdue = loan.due_date && !isSettled ? getDaysOverdue(loan.due_date) : 0
  const isOverdue = daysOverdue > 0

  const statusLabel = t(`page.loans.status.${loan.status}`, { defaultValue: loan.status })

  const initials = loan.borrower_name
    .split(' ')
    .map((n) => n[0])
    .slice(0, 2)
    .join('')
    .toUpperCase()

  return (
    <Card className={isSettled ? 'opacity-70' : ''}>
      <CardHeader className="flex flex-row items-start justify-between pb-2">
        <div className="flex items-center gap-3">
          <div className="flex h-10 w-10 items-center justify-center rounded-full bg-primary/10 text-primary font-semibold text-sm">
            {initials}
          </div>
          <div>
            <h3 className="font-semibold leading-none">{loan.borrower_name}</h3>
            {loan.message && (
              <p className="text-xs text-muted-foreground mt-1 line-clamp-1">{loan.message}</p>
            )}
          </div>
        </div>

        <DropdownMenu>
          <DropdownMenuTrigger asChild>
            <Button variant="ghost" size="icon" className="h-8 w-8">
              <MoreHorizontal className="h-4 w-4" />
            </Button>
          </DropdownMenuTrigger>
          <DropdownMenuContent align="end">
            {!isSettled && (
              <>
                <DropdownMenuItem onClick={() => onRepay(loan)}>
                  <Plus className="mr-2 h-4 w-4" />
                  {t('page.loans.action.add_repayment')}
                </DropdownMenuItem>
                <DropdownMenuSeparator />
              </>
            )}
            <DropdownMenuItem onClick={() => onEdit(loan)}>
              <Pencil className="mr-2 h-4 w-4" />
              {t('action.edit')}
            </DropdownMenuItem>
            <DropdownMenuSeparator />
            <DropdownMenuItem onClick={() => onDelete(loan)} className="text-red-600">
              <Trash2 className="mr-2 h-4 w-4" />
              {t('action.delete')}
            </DropdownMenuItem>
          </DropdownMenuContent>
        </DropdownMenu>
      </CardHeader>

      <CardContent className="pb-2">
        <div className="space-y-3">
          <div className="flex justify-between items-baseline">
            <span className="text-xl font-bold">{formatMoney(loan.amount, loan.currency_code)}</span>
            {!isSettled && (
              <span className="text-sm text-muted-foreground">
                {formatMoney(loan.remaining_amount, loan.currency_code)} {t('page.loans.card.left')}
              </span>
            )}
          </div>

          {!isSettled && (
            <div>
              <div className="flex justify-between text-xs mb-1">
                <span className="text-muted-foreground">{t('page.loans.status.partially_repaid')}</span>
                <span className="font-medium">{progress.toFixed(0)}%</span>
              </div>
              <Progress value={progress} className="h-2" />
            </div>
          )}

          {loan.repayments && loan.repayments.length > 0 && (
            <p className="text-xs text-muted-foreground">
              {t('page.loans.card.repayments', { count: loan.repayments.length })} · {formatMoney(loan.amount_repaid, loan.currency_code)} {t('page.loans.card.paid')}
            </p>
          )}
        </div>
      </CardContent>

      <CardFooter className="flex items-center justify-between pt-2">
        <div className="flex items-center gap-2">
          <Badge
            variant={isRepaid ? 'default' : isWrittenOff ? 'secondary' : 'outline'}
            className={isRepaid ? 'bg-green-600' : undefined}
          >
            {isRepaid && <CheckCircle2 className="mr-1 h-3 w-3" />}
            {statusLabel}
          </Badge>
          {isOverdue && !isSettled && (
            <Badge variant="destructive" className="text-xs">
              <AlertCircle className="mr-1 h-3 w-3" />
              {t('page.loans.card.overdue', { days: daysOverdue })}
            </Badge>
          )}
        </div>

        <div className="flex items-center gap-1 text-xs text-muted-foreground">
          <Calendar className="h-3 w-3" />
          {loan.due_date && !isSettled ? (
            <span className={isOverdue ? 'text-red-500' : ''}>
              {t('page.loans.card.due', { date: formatDateDisplay(loan.due_date) })}
            </span>
          ) : (
            <span>{formatDateDisplay(loan.loan_date)}</span>
          )}
        </div>
      </CardFooter>
    </Card>
  )
}
