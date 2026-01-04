import { useState } from 'react'
import { router, Link } from '@inertiajs/react'
import { AuthenticatedLayout } from '@/layouts'
import { Main } from '@/components/layout/main'
import { Button } from '@/components/ui/button'
import { Badge } from '@/components/ui/badge'
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
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from '@/components/ui/card'
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu'
import {
  Plus,
  Calendar,
  MoreVertical,
  Eye,
  Trash2,
  BarChart3,
  TrendingUp,
  TrendingDown,
} from 'lucide-react'
import type { FinancialPlan } from '@modules/Finance/types/finance'

interface Props {
  plans: FinancialPlan[]
}

function formatMoney(amount: number, currencyCode = 'VND'): string {
  return new Intl.NumberFormat('vi-VN', {
    style: 'currency',
    currency: currencyCode,
  }).format(amount)
}

function getStatusBadge(status: string) {
  const variants: Record<string, { variant: 'default' | 'secondary' | 'outline'; label: string }> = {
    draft: { variant: 'secondary', label: 'Draft' },
    active: { variant: 'default', label: 'Active' },
    archived: { variant: 'outline', label: 'Archived' },
  }
  const config = variants[status] || variants.draft
  return <Badge variant={config.variant}>{config.label}</Badge>
}

export default function PlansIndex({ plans }: Props) {
  const [showDeleteDialog, setShowDeleteDialog] = useState(false)
  const [selectedPlan, setSelectedPlan] = useState<FinancialPlan | null>(null)

  const handleDelete = (plan: FinancialPlan) => {
    setSelectedPlan(plan)
    setShowDeleteDialog(true)
  }

  const confirmDelete = () => {
    if (selectedPlan) {
      router.delete(route('dashboard.finance.plans.destroy', selectedPlan.id), {
        onSuccess: () => {
          setShowDeleteDialog(false)
          setSelectedPlan(null)
        },
      })
    }
  }

  const activePlans = plans.filter((p) => p.status === 'active')
  const draftPlans = plans.filter((p) => p.status === 'draft')
  const archivedPlans = plans.filter((p) => p.status === 'archived')

  return (
    <AuthenticatedLayout title="Financial Plans">
      <Main>
        <div className="mb-4 flex items-center justify-between">
          <div>
            <h1 className="text-2xl font-bold tracking-tight">Financial Plans</h1>
            <p className="text-muted-foreground">
              Plan your income and expenses for 1-5 years ahead
            </p>
          </div>
          <Button asChild>
            <Link href={route('dashboard.finance.plans.create')}>
              <Plus className="mr-2 h-4 w-4" />
              New Plan
            </Link>
          </Button>
        </div>

        {plans.length === 0 ? (
          <div className="flex flex-col items-center justify-center py-16 text-center">
            <Calendar className="h-16 w-16 text-muted-foreground mb-4" />
            <h3 className="text-xl font-semibold mb-2">No financial plans yet</h3>
            <p className="text-muted-foreground mb-4">
              Create a plan to forecast your income and expenses
            </p>
            <Button asChild>
              <Link href={route('dashboard.finance.plans.create')}>
                <Plus className="mr-2 h-4 w-4" />
                Create Plan
              </Link>
            </Button>
          </div>
        ) : (
          <div className="space-y-6">
            {/* Active Plans */}
            {activePlans.length > 0 && (
              <div>
                <h3 className="text-lg font-medium mb-4">Active Plans</h3>
                <div className="grid grid-cols-1 gap-4 lg:grid-cols-2">
                  {activePlans.map((plan) => (
                    <PlanCard key={plan.id} plan={plan} onDelete={handleDelete} />
                  ))}
                </div>
              </div>
            )}

            {/* Draft Plans */}
            {draftPlans.length > 0 && (
              <div>
                <h3 className="text-lg font-medium text-muted-foreground mb-4">
                  Drafts
                </h3>
                <div className="grid grid-cols-1 gap-4 lg:grid-cols-2">
                  {draftPlans.map((plan) => (
                    <PlanCard key={plan.id} plan={plan} onDelete={handleDelete} />
                  ))}
                </div>
              </div>
            )}

            {/* Archived Plans */}
            {archivedPlans.length > 0 && (
              <div>
                <h3 className="text-lg font-medium text-muted-foreground mb-4">
                  Archived
                </h3>
                <div className="grid grid-cols-1 gap-4 lg:grid-cols-2 opacity-60">
                  {archivedPlans.map((plan) => (
                    <PlanCard key={plan.id} plan={plan} onDelete={handleDelete} />
                  ))}
                </div>
              </div>
            )}
          </div>
        )}

        {/* Delete Confirmation */}
        <AlertDialog open={showDeleteDialog} onOpenChange={setShowDeleteDialog}>
          <AlertDialogContent>
            <AlertDialogHeader>
              <AlertDialogTitle>Delete Financial Plan</AlertDialogTitle>
              <AlertDialogDescription>
                Are you sure you want to delete "{selectedPlan?.name}"? This
                action cannot be undone.
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

function PlanCard({
  plan,
  onDelete,
}: {
  plan: FinancialPlan
  onDelete: (plan: FinancialPlan) => void
}) {
  return (
    <Card>
      <CardHeader className="pb-2">
        <div className="flex items-start justify-between">
          <div className="space-y-1">
            <CardTitle className="text-lg">{plan.name}</CardTitle>
            <CardDescription>
              {plan.start_year} - {plan.end_year} ({plan.year_span} year
              {plan.year_span > 1 ? 's' : ''})
            </CardDescription>
          </div>
          <div className="flex items-center gap-2">
            {getStatusBadge(plan.status)}
            <DropdownMenu>
              <DropdownMenuTrigger asChild>
                <Button variant="ghost" size="icon" className="h-8 w-8">
                  <MoreVertical className="h-4 w-4" />
                </Button>
              </DropdownMenuTrigger>
              <DropdownMenuContent align="end">
                <DropdownMenuItem asChild>
                  <Link href={route('dashboard.finance.plans.show', plan.id)}>
                    <Eye className="mr-2 h-4 w-4" />
                    View & Edit
                  </Link>
                </DropdownMenuItem>
                <DropdownMenuItem asChild>
                  <Link href={route('dashboard.finance.plans.compare', plan.id)}>
                    <BarChart3 className="mr-2 h-4 w-4" />
                    Compare
                  </Link>
                </DropdownMenuItem>
                <DropdownMenuItem
                  onClick={() => onDelete(plan)}
                  className="text-red-600"
                >
                  <Trash2 className="mr-2 h-4 w-4" />
                  Delete
                </DropdownMenuItem>
              </DropdownMenuContent>
            </DropdownMenu>
          </div>
        </div>
      </CardHeader>
      <CardContent>
        {plan.description && (
          <p className="text-sm text-muted-foreground mb-4 line-clamp-2">
            {plan.description}
          </p>
        )}
        <div className="grid grid-cols-3 gap-4 text-sm">
          <div>
            <p className="text-muted-foreground">Income</p>
            <p className="font-medium text-green-600 flex items-center gap-1">
              <TrendingUp className="h-3 w-3" />
              {formatMoney(plan.total_planned_income, plan.currency_code)}
            </p>
          </div>
          <div>
            <p className="text-muted-foreground">Expense</p>
            <p className="font-medium text-red-600 flex items-center gap-1">
              <TrendingDown className="h-3 w-3" />
              {formatMoney(plan.total_planned_expense, plan.currency_code)}
            </p>
          </div>
          <div>
            <p className="text-muted-foreground">Net</p>
            <p
              className={`font-medium ${
                plan.net_planned >= 0 ? 'text-green-600' : 'text-red-600'
              }`}
            >
              {formatMoney(plan.net_planned, plan.currency_code)}
            </p>
          </div>
        </div>
      </CardContent>
    </Card>
  )
}
