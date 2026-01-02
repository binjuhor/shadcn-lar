import { useState, useMemo } from 'react'
import { router } from '@inertiajs/react'
import { AuthenticatedLayout } from '@/layouts'
import { Main } from '@/components/layout/main'
import { Button } from '@/components/ui/button'
import {
  AlertDialog,
  AlertDialogAction,
  AlertDialogCancel,
  AlertDialogContent,
  AlertDialogDescription,
  AlertDialogFooter,
  AlertDialogHeader,
  AlertDialogTitle,
} from '@/components/ui/alert-dialog'
import {
  Collapsible,
  CollapsibleContent,
  CollapsibleTrigger,
} from '@/components/ui/collapsible'
import { Plus, Target, ChevronDown } from 'lucide-react'
import { SavingsGoalCard } from './components/savings-goal-card'
import { SavingsGoalForm } from './components/savings-goal-form'
import { ContributionForm } from './components/contribution-form'
import type { SavingsGoal, Currency, Account } from '@modules/Finance/types/finance'

interface Props {
  goals: SavingsGoal[]
  currencies: Currency[]
  accounts: Account[]
}

const filterStatuses: { value: string; label: string }[] = [
  { value: 'all', label: 'All' },
  { value: 'active', label: 'Active' },
  { value: 'completed', label: 'Completed' },
  { value: 'paused', label: 'Paused' },
]

function formatMoney(amount: number, currencyCode = 'VND'): string {
  return new Intl.NumberFormat('vi-VN', {
    style: 'currency',
    currency: currencyCode,
  }).format(amount)
}

export default function SavingsGoalsIndex({ goals, currencies, accounts }: Props) {
  const [showForm, setShowForm] = useState(false)
  const [showDeleteDialog, setShowDeleteDialog] = useState(false)
  const [showContributionForm, setShowContributionForm] = useState(false)
  const [contributionMode, setContributionMode] = useState<'deposit' | 'withdraw'>('deposit')
  const [selectedGoal, setSelectedGoal] = useState<SavingsGoal | null>(null)
  const [filterStatus, setFilterStatus] = useState('all')
  const [showCompleted, setShowCompleted] = useState(false)

  const filteredGoals = useMemo(() => {
    if (filterStatus === 'all') {
      return goals
    }
    return goals.filter((goal) => goal.status === filterStatus)
  }, [goals, filterStatus])

  const activeGoals = filteredGoals.filter((g) => g.status === 'active')
  const pausedGoals = filteredGoals.filter((g) => g.status === 'paused')
  const completedGoals = filteredGoals.filter((g) => g.status === 'completed')

  // Summary calculations
  const summary = useMemo(() => {
    const activeOnlyGoals = goals.filter((g) => g.status === 'active')
    const totalTarget = activeOnlyGoals.reduce((sum, g) => sum + g.target_amount, 0)
    const totalSaved = activeOnlyGoals.reduce((sum, g) => sum + g.current_amount, 0)
    const defaultCurrency = currencies.find((c) => c.is_default)?.code || 'VND'

    return {
      totalTarget,
      totalSaved,
      currencyCode: defaultCurrency,
      goalsCount: activeOnlyGoals.length,
    }
  }, [goals, currencies])

  const handleEdit = (goal: SavingsGoal) => {
    setSelectedGoal(goal)
    setShowForm(true)
  }

  const handleDelete = (goal: SavingsGoal) => {
    setSelectedGoal(goal)
    setShowDeleteDialog(true)
  }

  const handleContribute = (goal: SavingsGoal) => {
    setSelectedGoal(goal)
    setContributionMode('deposit')
    setShowContributionForm(true)
  }

  const handleWithdraw = (goal: SavingsGoal) => {
    setSelectedGoal(goal)
    setContributionMode('withdraw')
    setShowContributionForm(true)
  }

  const confirmDelete = () => {
    if (selectedGoal) {
      router.delete(route('dashboard.finance.savings-goals.destroy', selectedGoal.id), {
        onSuccess: () => {
          setShowDeleteDialog(false)
          setSelectedGoal(null)
        },
      })
    }
  }

  const handleFormClose = () => {
    setShowForm(false)
    setSelectedGoal(null)
  }

  const handleContributionFormClose = () => {
    setShowContributionForm(false)
    setSelectedGoal(null)
  }

  const handleSuccess = () => {
    router.reload({ only: ['goals'] })
  }

  return (
    <AuthenticatedLayout title="Savings Goals">
      <Main>
        <div className="mb-4 flex items-center justify-between">
          <div>
            <h1 className="text-2xl font-bold tracking-tight">Savings Goals</h1>
            <p className="text-muted-foreground">
              Track your financial targets and savings progress
            </p>
          </div>
          <Button onClick={() => setShowForm(true)}>
            <Plus className="mr-2 h-4 w-4" />
            New Goal
          </Button>
        </div>

        {/* Summary */}
        {summary.goalsCount > 0 && (
          <div className="grid gap-4 grid-cols-1 sm:grid-cols-3 mb-6">
            <div className="rounded-lg border bg-card p-4">
              <p className="text-sm text-muted-foreground">Total Saved</p>
              <p className="text-2xl font-bold text-green-600">
                {formatMoney(summary.totalSaved, summary.currencyCode)}
              </p>
            </div>
            <div className="rounded-lg border bg-card p-4">
              <p className="text-sm text-muted-foreground">Total Target</p>
              <p className="text-2xl font-bold">
                {formatMoney(summary.totalTarget, summary.currencyCode)}
              </p>
            </div>
            <div className="rounded-lg border bg-card p-4">
              <p className="text-sm text-muted-foreground">Active Goals</p>
              <p className="text-2xl font-bold">{summary.goalsCount}</p>
            </div>
          </div>
        )}

        {/* Filters */}
        <div className="flex gap-2 flex-wrap mb-6">
          {filterStatuses.map((status) => (
            <Button
              key={status.value}
              variant={filterStatus === status.value ? 'default' : 'outline'}
              size="sm"
              onClick={() => setFilterStatus(status.value)}
            >
              {status.label}
            </Button>
          ))}
        </div>

        {/* Active Goals */}
        {activeGoals.length > 0 && (
          <div className="mb-6">
            <h3 className="text-lg font-medium mb-4">Active Goals</h3>
            <div className="grid grid-cols-1 gap-4 lg:grid-cols-2">
              {activeGoals.map((goal) => (
                <SavingsGoalCard
                  key={goal.id}
                  goal={goal}
                  onEdit={handleEdit}
                  onDelete={handleDelete}
                  onContribute={handleContribute}
                  onWithdraw={handleWithdraw}
                />
              ))}
            </div>
          </div>
        )}

        {/* Paused Goals */}
        {pausedGoals.length > 0 && (
          <div className="mb-6">
            <h3 className="text-lg font-medium text-muted-foreground mb-4">
              Paused Goals
            </h3>
            <div className="grid grid-cols-1 gap-4 lg:grid-cols-2">
              {pausedGoals.map((goal) => (
                <SavingsGoalCard
                  key={goal.id}
                  goal={goal}
                  onEdit={handleEdit}
                  onDelete={handleDelete}
                  onContribute={handleContribute}
                  onWithdraw={handleWithdraw}
                />
              ))}
            </div>
          </div>
        )}

        {/* Completed Goals */}
        {completedGoals.length > 0 && (
          <Collapsible open={showCompleted} onOpenChange={setShowCompleted}>
            <CollapsibleTrigger asChild>
              <Button
                variant="ghost"
                className="w-full justify-between mb-4"
              >
                <span className="text-lg font-medium text-muted-foreground">
                  Completed Goals ({completedGoals.length})
                </span>
                <ChevronDown
                  className={`h-4 w-4 transition-transform ${
                    showCompleted ? 'rotate-180' : ''
                  }`}
                />
              </Button>
            </CollapsibleTrigger>
            <CollapsibleContent>
              <div className="grid grid-cols-1 gap-4 lg:grid-cols-2 opacity-60">
                {completedGoals.map((goal) => (
                  <SavingsGoalCard
                    key={goal.id}
                    goal={goal}
                    onEdit={handleEdit}
                    onDelete={handleDelete}
                    onContribute={handleContribute}
                    onWithdraw={handleWithdraw}
                  />
                ))}
              </div>
            </CollapsibleContent>
          </Collapsible>
        )}

        {/* Empty State */}
        {activeGoals.length === 0 &&
          pausedGoals.length === 0 &&
          completedGoals.length === 0 && (
            <div className="flex flex-col items-center justify-center py-16 text-center">
              <Target className="h-16 w-16 text-muted-foreground mb-4" />
              <h3 className="text-xl font-semibold mb-2">No savings goals yet</h3>
              <p className="text-muted-foreground mb-4">
                Start saving towards your financial targets
              </p>
              <Button onClick={() => setShowForm(true)}>
                <Plus className="mr-2 h-4 w-4" />
                Create Goal
              </Button>
            </div>
          )}

        {/* Savings Goal Form */}
        <SavingsGoalForm
          open={showForm}
          onOpenChange={handleFormClose}
          goal={selectedGoal}
          currencies={currencies}
          accounts={accounts}
          onSuccess={handleSuccess}
        />

        {/* Contribution Form */}
        <ContributionForm
          open={showContributionForm}
          onOpenChange={handleContributionFormClose}
          goal={selectedGoal}
          mode={contributionMode}
          onSuccess={handleSuccess}
        />

        {/* Delete Confirmation */}
        <AlertDialog open={showDeleteDialog} onOpenChange={setShowDeleteDialog}>
          <AlertDialogContent>
            <AlertDialogHeader>
              <AlertDialogTitle>Delete Savings Goal</AlertDialogTitle>
              <AlertDialogDescription>
                Are you sure you want to delete "{selectedGoal?.name}"? This
                action cannot be undone. All contribution history will also be
                deleted.
              </AlertDialogDescription>
            </AlertDialogHeader>
            <AlertDialogFooter>
              <AlertDialogCancel>Cancel</AlertDialogCancel>
              <AlertDialogAction
                onClick={confirmDelete}
                className="bg-red-600 hover:bg-red-700"
              >
                Delete
              </AlertDialogAction>
            </AlertDialogFooter>
          </AlertDialogContent>
        </AlertDialog>
      </Main>
    </AuthenticatedLayout>
  )
}
