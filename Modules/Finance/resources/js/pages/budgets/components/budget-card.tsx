import { formatDateDisplay } from '@/lib/date-utils'
import { Button } from '@/components/ui/button'
import {
  Card,
  CardContent,
  CardFooter,
  CardHeader,
  CardTitle,
} from '@/components/ui/card'
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu'
import { Progress } from '@/components/ui/progress'
import { Badge } from '@/components/ui/badge'
import { MoreHorizontal, Pencil, Trash2, RefreshCw, AlertCircle } from 'lucide-react'
import type { Budget } from '@modules/Finance/types/finance'

interface BudgetCardProps {
  budget: Budget
  onEdit: (budget: Budget) => void
  onDelete: (budget: Budget) => void
  onRefresh: (budget: Budget) => void
  compact?: boolean
}

function formatMoney(amount: number, currencyCode = 'VND'): string {
  return new Intl.NumberFormat('vi-VN', {
    style: 'currency',
    currency: currencyCode,
  }).format(amount)
}

const periodLabels: Record<string, string> = {
  weekly: 'Weekly',
  monthly: 'Monthly',
  quarterly: 'Quarterly',
  yearly: 'Yearly',
  custom: 'Custom',
}

export function BudgetCard({
  budget,
  onEdit,
  onDelete,
  onRefresh,
  compact = false,
}: BudgetCardProps) {
  const spentPercent = budget.amount > 0
    ? Math.min((budget.spent / budget.amount) * 100, 100)
    : 0
  const isOverBudget = budget.spent > budget.amount
  const remaining = budget.amount - budget.spent

  if (compact) {
    return (
      <div className="flex items-center justify-between p-3 border rounded-lg">
        <div className="flex-1 min-w-0">
          <div className="flex items-center gap-2 mb-1">
            <span className="font-medium truncate">{budget.name}</span>
            {isOverBudget && (
              <AlertCircle className="h-4 w-4 text-red-600 flex-shrink-0" />
            )}
          </div>
          <Progress
            value={spentPercent}
            className={`h-2 ${isOverBudget ? '[&>div]:bg-red-600' : ''}`}
          />
          <div className="flex justify-between text-xs text-muted-foreground mt-1">
            <span>{formatMoney(budget.spent, budget.currency_code)}</span>
            <span>{formatMoney(budget.amount, budget.currency_code)}</span>
          </div>
        </div>
      </div>
    )
  }

  return (
    <Card>
      <CardHeader className="flex flex-row items-center justify-between pb-2">
        <div className="flex items-center gap-2">
          <CardTitle className="text-base">{budget.name}</CardTitle>
          {isOverBudget && (
            <AlertCircle className="h-4 w-4 text-red-600" />
          )}
        </div>
        <DropdownMenu>
          <DropdownMenuTrigger asChild>
            <Button variant="ghost" size="icon" className="h-8 w-8">
              <MoreHorizontal className="h-4 w-4" />
            </Button>
          </DropdownMenuTrigger>
          <DropdownMenuContent align="end">
            <DropdownMenuItem onClick={() => onRefresh(budget)}>
              <RefreshCw className="mr-2 h-4 w-4" />
              Refresh Spent
            </DropdownMenuItem>
            <DropdownMenuItem onClick={() => onEdit(budget)}>
              <Pencil className="mr-2 h-4 w-4" />
              Edit
            </DropdownMenuItem>
            <DropdownMenuSeparator />
            <DropdownMenuItem
              onClick={() => onDelete(budget)}
              className="text-red-600"
            >
              <Trash2 className="mr-2 h-4 w-4" />
              Delete
            </DropdownMenuItem>
          </DropdownMenuContent>
        </DropdownMenu>
      </CardHeader>
      <CardContent className="space-y-3">
        <div>
          <div className="flex justify-between text-sm mb-2">
            <span className={isOverBudget ? 'text-red-600 font-medium' : ''}>
              {formatMoney(budget.spent, budget.currency_code)} spent
            </span>
            <span className="text-muted-foreground">
              of {formatMoney(budget.amount, budget.currency_code)}
            </span>
          </div>
          <Progress
            value={spentPercent}
            className={isOverBudget ? '[&>div]:bg-red-600' : ''}
          />
        </div>

        <div className="flex justify-between text-sm">
          <span className="text-muted-foreground">Remaining</span>
          <span className={remaining < 0 ? 'text-red-600 font-medium' : 'text-green-600'}>
            {formatMoney(remaining, budget.currency_code)}
          </span>
        </div>

        {budget.category && (
          <div className="flex justify-between text-sm">
            <span className="text-muted-foreground">Category</span>
            <Badge variant="secondary">{budget.category.name}</Badge>
          </div>
        )}
      </CardContent>
      <CardFooter className="flex justify-between text-xs text-muted-foreground pt-0">
        <span>{periodLabels[budget.period_type]}</span>
        <span>
          {formatDateDisplay(budget.start_date)} - {formatDateDisplay(budget.end_date)}
        </span>
      </CardFooter>
    </Card>
  )
}
