import { AuthenticatedLayout } from '@/layouts'
import { Main } from '@/components/layout/main'
import { PlanForm } from './components/plan-form'
import type { Currency, Category } from '@modules/Finance/types/finance'

interface Props {
  currencies: Currency[]
  categories: Category[]
  currentYear: number
}

export default function PlansCreate({ currencies, categories, currentYear }: Props) {
  return (
    <AuthenticatedLayout title="Create Financial Plan">
      <Main>
        <div className="mb-6">
          <h1 className="text-2xl font-bold tracking-tight">Create Financial Plan</h1>
          <p className="text-muted-foreground">
            Plan your income and expenses for the upcoming years
          </p>
        </div>

        <PlanForm
          currencies={currencies}
          categories={categories}
          currentYear={currentYear}
        />
      </Main>
    </AuthenticatedLayout>
  )
}
