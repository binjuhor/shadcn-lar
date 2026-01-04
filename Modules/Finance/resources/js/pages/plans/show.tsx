import { Link } from '@inertiajs/react'
import { AuthenticatedLayout } from '@/layouts'
import { Main } from '@/components/layout/main'
import { Button } from '@/components/ui/button'
import { Badge } from '@/components/ui/badge'
import { BarChart3 } from 'lucide-react'
import { PlanForm } from './components/plan-form'
import type { FinancialPlan, Currency, Category } from '@modules/Finance/types/finance'

interface Props {
  plan: FinancialPlan
  currencies: Currency[]
  categories: Category[]
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

export default function PlansShow({ plan, currencies, categories }: Props) {
  return (
    <AuthenticatedLayout title={`Plan: ${plan.name}`}>
      <Main>
        <div className="mb-6 flex items-center justify-between">
          <div className="flex items-center gap-3">
            <div>
              <h1 className="text-2xl font-bold tracking-tight">{plan.name}</h1>
              <p className="text-muted-foreground">
                {plan.start_year} - {plan.end_year}
              </p>
            </div>
            {getStatusBadge(plan.status)}
          </div>
          <Button asChild variant="outline">
            <Link href={route('dashboard.finance.plans.compare', plan.id)}>
              <BarChart3 className="mr-2 h-4 w-4" />
              Compare with Actual
            </Link>
          </Button>
        </div>

        <PlanForm
          plan={plan}
          currencies={currencies}
          categories={categories}
          currentYear={plan.start_year}
        />
      </Main>
    </AuthenticatedLayout>
  )
}
