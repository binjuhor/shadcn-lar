import { useState, useMemo } from 'react'
import { router } from '@inertiajs/react'
import { useTranslation } from 'react-i18next'
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
import { Plus, PiggyBank, ChevronDown } from 'lucide-react'
import { BudgetCard } from './components/budget-card'
import { BudgetForm } from './components/budget-form'
import type { Budget, Category, Currency } from '@modules/Finance/types/finance'

interface Props {
  budgets: Budget[]
  categories: Category[]
  currencies: Currency[]
}

export default function BudgetsIndex({ budgets, categories, currencies }: Props) {
  const { t } = useTranslation()
  const [showForm, setShowForm] = useState(false)
  const [showDeleteDialog, setShowDeleteDialog] = useState(false)
  const [selectedBudget, setSelectedBudget] = useState<Budget | null>(null)
  const [filterPeriod, setFilterPeriod] = useState('all')
  const [showPast, setShowPast] = useState(false)

  const filterPeriods: { value: string; label: string }[] = [
    { value: 'all', label: t('filter.all_periods') },
    { value: 'weekly', label: t('filter.weekly') },
    { value: 'monthly', label: t('filter.monthly') },
    { value: 'quarterly', label: t('filter.quarterly') },
    { value: 'yearly', label: t('filter.yearly') },
  ]

  const filteredBudgets = useMemo(() => {
    if (filterPeriod === 'all') {
      return budgets
    }
    return budgets.filter((budget) => budget.period_type === filterPeriod)
  }, [budgets, filterPeriod])

  const now = new Date()

  const currentBudgets = filteredBudgets.filter((budget) => {
    const start = new Date(budget.start_date)
    const end = new Date(budget.end_date)
    return start <= now && now <= end
  })

  const upcomingBudgets = filteredBudgets.filter((budget) => {
    const start = new Date(budget.start_date)
    return start > now
  })

  const pastBudgets = filteredBudgets.filter((budget) => {
    const end = new Date(budget.end_date)
    return end < now
  })

  const handleEdit = (budget: Budget) => {
    setSelectedBudget(budget)
    setShowForm(true)
  }

  const handleDelete = (budget: Budget) => {
    setSelectedBudget(budget)
    setShowDeleteDialog(true)
  }

  const handleRefresh = (budget: Budget) => {
    router.post(
      route('dashboard.finance.budgets.refresh', budget.id),
      {},
      {
        preserveState: true,
        preserveScroll: true,
      }
    )
  }

  const confirmDelete = () => {
    if (selectedBudget) {
      router.delete(route('dashboard.finance.budgets.destroy', selectedBudget.id), {
        onSuccess: () => {
          setShowDeleteDialog(false)
          setSelectedBudget(null)
        },
      })
    }
  }

  const handleFormClose = () => {
    setShowForm(false)
    setSelectedBudget(null)
  }

  const handleSuccess = () => {
    router.reload({ only: ['budgets'] })
  }

  return (
    <AuthenticatedLayout title={t('page.budgets.title')}>
      <Main>
        <div className="mb-4 md:flex items-center justify-between">
          <div className="mb-4">
            <h1 className="text-2xl font-bold tracking-tight">{t('page.budgets.title')}</h1>
            <p className="text-muted-foreground">
              {t('page.budgets.description')}
            </p>
          </div>
          <Button onClick={() => setShowForm(true)}>
            <Plus className="mr-2 h-4 w-4" />
            {t('page.budgets.new')}
          </Button>
        </div>

        {/* Filters */}
        <div className="flex gap-2 flex-wrap mb-6">
          {filterPeriods.map((period) => (
            <Button
              key={period.value}
              variant={filterPeriod === period.value ? 'default' : 'outline'}
              size="sm"
              onClick={() => setFilterPeriod(period.value)}
            >
              {period.label}
            </Button>
          ))}
        </div>

        {/* Current Period Budgets */}
        {currentBudgets.length > 0 && (
          <div className="mb-6">
            <h3 className="text-lg font-medium mb-4">{t('page.budgets.current_period')}</h3>
            <div className="grid grid-cols-1 gap-4 lg:grid-cols-2">
              {currentBudgets.map((budget) => (
                <BudgetCard
                  key={budget.id}
                  budget={budget}
                  onEdit={handleEdit}
                  onDelete={handleDelete}
                  onRefresh={handleRefresh}
                />
              ))}
            </div>
          </div>
        )}

        {/* Upcoming Budgets */}
        {upcomingBudgets.length > 0 && (
          <div className="mb-6">
            <h3 className="text-lg font-medium text-muted-foreground mb-4">
              {t('page.budgets.upcoming')}
            </h3>
            <div className="grid grid-cols-1 gap-4 lg:grid-cols-2 opacity-60">
              {upcomingBudgets.map((budget) => (
                <BudgetCard
                  key={budget.id}
                  budget={budget}
                  onEdit={handleEdit}
                  onDelete={handleDelete}
                  onRefresh={handleRefresh}
                />
              ))}
            </div>
          </div>
        )}

        {/* Past Budgets */}
        {pastBudgets.length > 0 && (
          <Collapsible open={showPast} onOpenChange={setShowPast}>
            <CollapsibleTrigger asChild>
              <Button
                variant="ghost"
                className="w-full justify-between mb-4"
              >
                <span className="text-lg font-medium text-muted-foreground">
                  {t('page.budgets.past_budgets', { count: pastBudgets.length })}
                </span>
                <ChevronDown
                  className={`h-4 w-4 transition-transform ${
                    showPast ? 'rotate-180' : ''
                  }`}
                />
              </Button>
            </CollapsibleTrigger>
            <CollapsibleContent>
              <div className="grid grid-cols-1 gap-4 lg:grid-cols-2 opacity-40">
                {pastBudgets.map((budget) => (
                  <BudgetCard
                    key={budget.id}
                    budget={budget}
                    onEdit={handleEdit}
                    onDelete={handleDelete}
                    onRefresh={handleRefresh}
                  />
                ))}
              </div>
            </CollapsibleContent>
          </Collapsible>
        )}

        {/* Empty State */}
        {currentBudgets.length === 0 &&
          upcomingBudgets.length === 0 &&
          pastBudgets.length === 0 && (
            <div className="flex flex-col items-center justify-center py-16 text-center">
              <PiggyBank className="h-16 w-16 text-muted-foreground mb-4" />
              <h3 className="text-xl font-semibold mb-2">{t('page.budgets.no_budgets')}</h3>
              <p className="text-muted-foreground mb-4">
                {t('page.budgets.get_started')}
              </p>
              <Button onClick={() => setShowForm(true)}>
                <Plus className="mr-2 h-4 w-4" />
                {t('page.budgets.create_budget')}
              </Button>
            </div>
          )}

        {/* Budget Form */}
        <BudgetForm
          open={showForm}
          onOpenChange={handleFormClose}
          budget={selectedBudget}
          categories={categories}
          currencies={currencies}
          onSuccess={handleSuccess}
        />

        {/* Delete Confirmation */}
        <AlertDialog open={showDeleteDialog} onOpenChange={setShowDeleteDialog}>
          <AlertDialogContent>
            <AlertDialogHeader>
              <AlertDialogTitle>{t('page.budgets.delete_budget')}</AlertDialogTitle>
              <AlertDialogDescription>
                {t('page.budgets.delete_confirmation', { name: selectedBudget?.name })}
              </AlertDialogDescription>
            </AlertDialogHeader>
            <AlertDialogFooter>
              <AlertDialogCancel>{t('action.cancel')}</AlertDialogCancel>
              <AlertDialogAction
                onClick={confirmDelete}
                className="bg-red-600 hover:bg-red-700"
              >
                {t('action.delete')}
              </AlertDialogAction>
            </AlertDialogFooter>
          </AlertDialogContent>
        </AlertDialog>
      </Main>
    </AuthenticatedLayout>
  )
}
