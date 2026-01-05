import { router } from '@inertiajs/react'
import { format, formatDistanceToNow, isPast, isToday } from 'date-fns'
import {
  ArrowDownLeft,
  ArrowUpRight,
  Calendar,
  MoreHorizontal,
  Pause,
  Pencil,
  Play,
  RefreshCw,
  Trash2,
} from 'lucide-react'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Card } from '@/components/ui/card'
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu'
import type { RecurringTransaction } from '@modules/Finance/types/finance'

interface RecurringCardProps {
  recurring: RecurringTransaction
  onEdit: (recurring: RecurringTransaction) => void
  onDelete: (recurring: RecurringTransaction) => void
}

function formatMoney(amount: number, currencyCode = 'VND'): string {
  return new Intl.NumberFormat('vi-VN', {
    style: 'currency',
    currency: currencyCode,
    maximumFractionDigits: 0,
  }).format(amount)
}

const frequencyLabels: Record<string, string> = {
  daily: 'Daily',
  weekly: 'Weekly',
  monthly: 'Monthly',
  yearly: 'Yearly',
}

export function RecurringCard({ recurring, onEdit, onDelete }: RecurringCardProps) {
  const isIncome = recurring.transaction_type === 'income'
  const nextRunDate = new Date(recurring.next_run_date)
  const isDue = isPast(nextRunDate) || isToday(nextRunDate)

  const handleToggle = () => {
    router.post(route('dashboard.finance.recurring-transactions.toggle', recurring.id), {}, {
      preserveState: true,
      preserveScroll: true,
    })
  }

  return (
    <Card className={`p-4 ${!recurring.is_active ? 'opacity-60' : ''}`}>
      <div className="flex items-start justify-between gap-3">
        <div className="flex items-start gap-3 flex-1 min-w-0">
          <div
            className={`p-2 rounded-lg shrink-0 ${
              isIncome ? 'bg-green-100 text-green-600' : 'bg-red-100 text-red-600'
            }`}
          >
            {isIncome ? (
              <ArrowDownLeft className="h-5 w-5" />
            ) : (
              <ArrowUpRight className="h-5 w-5" />
            )}
          </div>

          <div className="min-w-0 flex-1">
            <div className="flex items-center gap-2 flex-wrap">
              <h3 className="font-semibold truncate">{recurring.name}</h3>
              {!recurring.is_active && (
                <Badge variant="secondary" className="text-xs">Paused</Badge>
              )}
              {isDue && recurring.is_active && (
                <Badge variant="destructive" className="text-xs">Due</Badge>
              )}
            </div>

            <div className="flex items-center gap-2 mt-1 text-sm text-muted-foreground">
              <Badge variant="outline" className="text-xs">
                {frequencyLabels[recurring.frequency]}
              </Badge>
              {recurring.account && (
                <span className="truncate">{recurring.account.name}</span>
              )}
            </div>

            {recurring.category && (
              <div className="mt-1 flex items-center gap-1">
                <div
                  className="w-2 h-2 rounded-full"
                  style={{ backgroundColor: recurring.category.color || '#6b7280' }}
                />
                <span className="text-xs text-muted-foreground">
                  {recurring.category.name}
                </span>
              </div>
            )}

            <div className="flex items-center gap-1 mt-2 text-xs text-muted-foreground">
              <Calendar className="h-3 w-3" />
              <span>
                Next: {format(nextRunDate, 'MMM d, yyyy')}
                {' '}
                ({formatDistanceToNow(nextRunDate, { addSuffix: true })})
              </span>
            </div>
          </div>
        </div>

        <div className="flex flex-col items-end gap-2">
          <div className={`text-lg font-bold ${isIncome ? 'text-green-600' : 'text-red-600'}`}>
            {isIncome ? '+' : '-'}{formatMoney(recurring.amount, recurring.currency_code)}
          </div>

          <div className="text-xs text-muted-foreground">
            ~{formatMoney(recurring.monthly_amount, recurring.currency_code)}/mo
          </div>

          <DropdownMenu>
            <DropdownMenuTrigger asChild>
              <Button variant="ghost" size="icon" className="h-8 w-8">
                <MoreHorizontal className="h-4 w-4" />
              </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end">
              <DropdownMenuItem onClick={() => onEdit(recurring)}>
                <Pencil className="mr-2 h-4 w-4" />
                Edit
              </DropdownMenuItem>
              <DropdownMenuItem onClick={handleToggle}>
                {recurring.is_active ? (
                  <>
                    <Pause className="mr-2 h-4 w-4" />
                    Pause
                  </>
                ) : (
                  <>
                    <Play className="mr-2 h-4 w-4" />
                    Resume
                  </>
                )}
              </DropdownMenuItem>
              <DropdownMenuSeparator />
              <DropdownMenuItem
                onClick={() => onDelete(recurring)}
                className="text-red-600"
              >
                <Trash2 className="mr-2 h-4 w-4" />
                Delete
              </DropdownMenuItem>
            </DropdownMenuContent>
          </DropdownMenu>
        </div>
      </div>
    </Card>
  )
}
