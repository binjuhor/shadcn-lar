import { router } from '@inertiajs/react'
import {
  Card,
  CardContent,
  CardFooter,
  CardHeader,
} from '@/components/ui/card'
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
  Target,
  MoreHorizontal,
  Pencil,
  Trash2,
  Plus,
  Minus,
  Pause,
  Play,
  CheckCircle,
  Calendar,
} from 'lucide-react'
import type { SavingsGoal } from '@modules/Finance/types/finance'

interface SavingsGoalCardProps {
  goal: SavingsGoal
  onEdit: (goal: SavingsGoal) => void
  onDelete: (goal: SavingsGoal) => void
  onContribute: (goal: SavingsGoal) => void
  onWithdraw: (goal: SavingsGoal) => void
}

function formatMoney(amount: number, currencyCode = 'VND'): string {
  return new Intl.NumberFormat('vi-VN', {
    style: 'currency',
    currency: currencyCode,
  }).format(amount)
}

function formatDate(dateStr: string): string {
  return new Date(dateStr).toLocaleDateString('vi-VN', {
    year: 'numeric',
    month: 'short',
    day: 'numeric',
  })
}

function getDaysRemaining(targetDate: string): number {
  const target = new Date(targetDate)
  const today = new Date()
  const diffTime = target.getTime() - today.getTime()
  return Math.ceil(diffTime / (1000 * 60 * 60 * 24))
}

export function SavingsGoalCard({
  goal,
  onEdit,
  onDelete,
  onContribute,
  onWithdraw,
}: SavingsGoalCardProps) {
  const progress = Math.min(goal.progress_percent, 100)
  const isCompleted = goal.status === 'completed'
  const isPaused = goal.status === 'paused'
  const daysRemaining = goal.target_date ? getDaysRemaining(goal.target_date) : null

  const handlePauseResume = () => {
    if (isPaused) {
      router.post(route('dashboard.finance.savings-goals.resume', goal.id))
    } else {
      router.post(route('dashboard.finance.savings-goals.pause', goal.id))
    }
  }

  const getStatusBadge = () => {
    if (isCompleted) {
      return (
        <Badge variant="default" className="bg-green-600">
          <CheckCircle className="mr-1 h-3 w-3" />
          Completed
        </Badge>
      )
    }

    if (isPaused) {
      return (
        <Badge variant="secondary">
          <Pause className="mr-1 h-3 w-3" />
          Paused
        </Badge>
      )
    }

    if (daysRemaining !== null && daysRemaining < 0) {
      return <Badge variant="destructive">Overdue</Badge>
    }

    if (daysRemaining !== null && daysRemaining <= 7) {
      return <Badge variant="outline" className="border-orange-500 text-orange-500">Due Soon</Badge>
    }

    return null
  }

  return (
    <Card className={isPaused ? 'opacity-60' : ''}>
      <CardHeader className="flex flex-row items-start justify-between pb-2">
        <div className="flex items-center gap-3">
          <div
            className="flex h-10 w-10 items-center justify-center rounded-full"
            style={{ backgroundColor: goal.color || '#3b82f6' }}
          >
            <Target className="h-5 w-5 text-white" />
          </div>
          <div>
            <h3 className="font-semibold leading-none">{goal.name}</h3>
            {goal.description && (
              <p className="text-xs text-muted-foreground mt-1 line-clamp-1">
                {goal.description}
              </p>
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
            {!isCompleted && (
              <>
                <DropdownMenuItem onClick={() => onContribute(goal)}>
                  <Plus className="mr-2 h-4 w-4" />
                  Add Money
                </DropdownMenuItem>
                {goal.current_amount > 0 && (
                  <DropdownMenuItem onClick={() => onWithdraw(goal)}>
                    <Minus className="mr-2 h-4 w-4" />
                    Withdraw
                  </DropdownMenuItem>
                )}
                <DropdownMenuSeparator />
              </>
            )}
            <DropdownMenuItem onClick={() => onEdit(goal)}>
              <Pencil className="mr-2 h-4 w-4" />
              Edit
            </DropdownMenuItem>
            {!isCompleted && (
              <DropdownMenuItem onClick={handlePauseResume}>
                {isPaused ? (
                  <>
                    <Play className="mr-2 h-4 w-4" />
                    Resume
                  </>
                ) : (
                  <>
                    <Pause className="mr-2 h-4 w-4" />
                    Pause
                  </>
                )}
              </DropdownMenuItem>
            )}
            <DropdownMenuSeparator />
            <DropdownMenuItem
              onClick={() => onDelete(goal)}
              className="text-red-600"
            >
              <Trash2 className="mr-2 h-4 w-4" />
              Delete
            </DropdownMenuItem>
          </DropdownMenuContent>
        </DropdownMenu>
      </CardHeader>

      <CardContent className="pb-2">
        <div className="space-y-3">
          <div>
            <div className="flex justify-between text-sm mb-1">
              <span className="text-muted-foreground">Progress</span>
              <span className="font-medium">{progress.toFixed(0)}%</span>
            </div>
            <Progress value={progress} className="h-2" />
          </div>

          <div className="flex justify-between items-baseline">
            <div>
              <span className="text-lg font-bold">
                {formatMoney(goal.current_amount, goal.currency_code)}
              </span>
              <span className="text-muted-foreground text-sm">
                {' '}/ {formatMoney(goal.target_amount, goal.currency_code)}
              </span>
            </div>
          </div>

          {goal.remaining_amount > 0 && !isCompleted && (
            <p className="text-xs text-muted-foreground">
              {formatMoney(goal.remaining_amount, goal.currency_code)} remaining
            </p>
          )}
        </div>
      </CardContent>

      <CardFooter className="flex items-center justify-between pt-2">
        <div className="flex items-center gap-2">
          {getStatusBadge()}
        </div>
        {goal.target_date && !isCompleted && (
          <div className="flex items-center gap-1 text-xs text-muted-foreground">
            <Calendar className="h-3 w-3" />
            {daysRemaining !== null && daysRemaining >= 0 ? (
              <span>{daysRemaining} days left</span>
            ) : (
              <span>{formatDate(goal.target_date)}</span>
            )}
          </div>
        )}
        {isCompleted && goal.completed_at && (
          <span className="text-xs text-muted-foreground">
            Completed {formatDate(goal.completed_at)}
          </span>
        )}
      </CardFooter>
    </Card>
  )
}
