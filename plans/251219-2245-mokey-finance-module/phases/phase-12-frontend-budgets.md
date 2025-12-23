# Phase 12: Frontend - Budgets Module

## Context
- Parent plan: [plan.md](../plan.md)
- Dependencies: Phase 11 (transactions for spent calculation)

## Overview
- Priority: medium
- Status: pending
- Description: Build budget list with progress indicators, create/edit forms with period selection.

## Requirements
### Functional
- Budget list with progress bars
- Period type selection (monthly/yearly/custom)
- Category assignment
- Over-budget alerts
- Variance display

### Non-functional
- Progress bars animate
- Color coding for budget status

## Related Code Files
### Files to Create
```
resources/js/pages/mokey/
├── budgets.tsx
├── create-budget.tsx
├── edit-budget.tsx
└── components/
    └── budget-form.tsx
```

## Implementation Steps

### 1. Create Budget Form
```tsx
// resources/js/pages/mokey/components/budget-form.tsx
import { useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { z } from 'zod'
import { router } from '@inertiajs/react'
import { useEffect } from 'react'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { Calendar } from '@/components/ui/calendar'
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover'
import { Switch } from '@/components/ui/switch'
import { format, startOfMonth, endOfMonth, startOfYear, endOfYear } from 'date-fns'
import { IconCalendar } from '@tabler/icons-react'
import { Budget, Category, Currency } from '../types/mokey'

const budgetSchema = z.object({
  category_id: z.number().nullable(),
  period_type: z.enum(['monthly', 'yearly', 'custom']),
  allocated_amount: z.number().positive('Budget must be positive'),
  currency_code: z.string().length(3),
  start_date: z.date(),
  end_date: z.date(),
  is_active: z.boolean(),
}).refine((data) => data.end_date > data.start_date, {
  message: 'End date must be after start date',
  path: ['end_date'],
})

type BudgetFormData = z.infer<typeof budgetSchema>

interface BudgetFormProps {
  budget?: Budget
  categories: Category[]
  currencies: Currency[]
}

export function BudgetForm({ budget, categories, currencies }: BudgetFormProps) {
  const isEditing = !!budget

  const { register, handleSubmit, setValue, watch, formState: { errors, isSubmitting } } = useForm<BudgetFormData>({
    resolver: zodResolver(budgetSchema),
    defaultValues: {
      category_id: budget?.category_id ?? null,
      period_type: budget?.period_type ?? 'monthly',
      allocated_amount: budget ? budget.allocated_amount / 100 : 0,
      currency_code: budget?.currency_code ?? 'USD',
      start_date: budget ? new Date(budget.start_date) : startOfMonth(new Date()),
      end_date: budget ? new Date(budget.end_date) : endOfMonth(new Date()),
      is_active: budget?.is_active ?? true,
    },
  })

  const periodType = watch('period_type')

  // Auto-calculate dates when period type changes
  useEffect(() => {
    if (periodType === 'monthly') {
      setValue('start_date', startOfMonth(new Date()))
      setValue('end_date', endOfMonth(new Date()))
    } else if (periodType === 'yearly') {
      setValue('start_date', startOfYear(new Date()))
      setValue('end_date', endOfYear(new Date()))
    }
  }, [periodType, setValue])

  const expenseCategories = categories.filter((cat) => cat.type === 'expense')

  const onSubmit = (data: BudgetFormData) => {
    const payload = {
      ...data,
      allocated_amount: Math.round(data.allocated_amount * 100),
      start_date: format(data.start_date, 'yyyy-MM-dd'),
      end_date: format(data.end_date, 'yyyy-MM-dd'),
    }

    if (isEditing) {
      router.put(route('dashboard.mokey.budgets.update', budget.id), payload)
    } else {
      router.post(route('dashboard.mokey.budgets.store'), payload)
    }
  }

  return (
    <form onSubmit={handleSubmit(onSubmit)} className="space-y-6">
      <div className="grid gap-4 md:grid-cols-2">
        <div className="space-y-2">
          <Label>Category</Label>
          <Select
            value={watch('category_id')?.toString() ?? ''}
            onValueChange={(val) => setValue('category_id', val ? parseInt(val) : null)}
          >
            <SelectTrigger>
              <SelectValue placeholder="All expenses (optional)" />
            </SelectTrigger>
            <SelectContent>
              <SelectItem value="">All expenses</SelectItem>
              {expenseCategories.map((cat) => (
                <SelectItem key={cat.id} value={cat.id.toString()}>
                  {cat.name}
                </SelectItem>
              ))}
            </SelectContent>
          </Select>
        </div>

        <div className="space-y-2">
          <Label>Period</Label>
          <Select value={periodType} onValueChange={(val) => setValue('period_type', val as any)}>
            <SelectTrigger>
              <SelectValue />
            </SelectTrigger>
            <SelectContent>
              <SelectItem value="monthly">Monthly</SelectItem>
              <SelectItem value="yearly">Yearly</SelectItem>
              <SelectItem value="custom">Custom</SelectItem>
            </SelectContent>
          </Select>
        </div>

        <div className="space-y-2">
          <Label>Budget Amount</Label>
          <Input
            type="number"
            step="0.01"
            min="0.01"
            {...register('allocated_amount', { valueAsNumber: true })}
          />
          {errors.allocated_amount && <p className="text-sm text-red-500">{errors.allocated_amount.message}</p>}
        </div>

        <div className="space-y-2">
          <Label>Currency</Label>
          <Select value={watch('currency_code')} onValueChange={(val) => setValue('currency_code', val)}>
            <SelectTrigger>
              <SelectValue />
            </SelectTrigger>
            <SelectContent>
              {currencies.map((cur) => (
                <SelectItem key={cur.code} value={cur.code}>
                  {cur.symbol} {cur.code}
                </SelectItem>
              ))}
            </SelectContent>
          </Select>
        </div>

        {periodType === 'custom' && (
          <>
            <div className="space-y-2">
              <Label>Start Date</Label>
              <Popover>
                <PopoverTrigger asChild>
                  <Button variant="outline" className="w-full justify-start">
                    <IconCalendar className="mr-2 h-4 w-4" />
                    {format(watch('start_date'), 'PPP')}
                  </Button>
                </PopoverTrigger>
                <PopoverContent className="w-auto p-0">
                  <Calendar
                    mode="single"
                    selected={watch('start_date')}
                    onSelect={(date) => date && setValue('start_date', date)}
                  />
                </PopoverContent>
              </Popover>
            </div>

            <div className="space-y-2">
              <Label>End Date</Label>
              <Popover>
                <PopoverTrigger asChild>
                  <Button variant="outline" className="w-full justify-start">
                    <IconCalendar className="mr-2 h-4 w-4" />
                    {format(watch('end_date'), 'PPP')}
                  </Button>
                </PopoverTrigger>
                <PopoverContent className="w-auto p-0">
                  <Calendar
                    mode="single"
                    selected={watch('end_date')}
                    onSelect={(date) => date && setValue('end_date', date)}
                  />
                </PopoverContent>
              </Popover>
              {errors.end_date && <p className="text-sm text-red-500">{errors.end_date.message}</p>}
            </div>
          </>
        )}
      </div>

      <div className="flex items-center space-x-2">
        <Switch
          id="is_active"
          checked={watch('is_active')}
          onCheckedChange={(val) => setValue('is_active', val)}
        />
        <Label htmlFor="is_active">Active</Label>
      </div>

      <div className="flex gap-4">
        <Button type="submit" disabled={isSubmitting}>
          {isEditing ? 'Update Budget' : 'Create Budget'}
        </Button>
        <Button type="button" variant="outline" onClick={() => router.get(route('dashboard.mokey.budgets.index'))}>
          Cancel
        </Button>
      </div>
    </form>
  )
}
```

### 2. Create Budgets List page
```tsx
// resources/js/pages/mokey/budgets.tsx
import { AuthenticatedLayout } from '@/layouts'
import { Main } from '@/components/layout'
import { usePage } from '@inertiajs/react'
import { router } from '@inertiajs/react'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card'
import { Progress } from '@/components/ui/progress'
import { Badge } from '@/components/ui/badge'
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from '@/components/ui/dropdown-menu'
import { IconPlus, IconDotsVertical, IconEdit, IconTrash, IconAlertTriangle } from '@tabler/icons-react'
import { Budget, BudgetVariance, Category } from './types/mokey'
import { PageProps } from '@/types'
import { useToast } from '@/hooks/use-toast'

interface BudgetWithVariance {
  budget: Budget
  variance: BudgetVariance
}

interface BudgetsPageProps extends PageProps {
  budgets: { data: BudgetWithVariance[]; total: number }
  categories: Category[]
}

export default function BudgetsPage() {
  const { budgets, categories } = usePage<BudgetsPageProps>().props
  const { toast } = useToast()

  const handleDelete = (budget: Budget) => {
    if (confirm('Delete this budget?')) {
      router.delete(route('dashboard.mokey.budgets.destroy', budget.id), {
        onSuccess: () => toast({ title: 'Budget deleted' }),
      })
    }
  }

  const formatMoney = (cents: number) => `$${(cents / 100).toFixed(2)}`

  return (
    <AuthenticatedLayout title="Budgets">
      <Main>
        <div className="flex items-center justify-between mb-6">
          <div>
            <h2 className="text-3xl font-bold tracking-tight">Budgets</h2>
            <p className="text-muted-foreground">Track your spending against planned budgets</p>
          </div>
          <Button onClick={() => router.get(route('dashboard.mokey.budgets.create'))}>
            <IconPlus className="mr-2 h-4 w-4" /> Add Budget
          </Button>
        </div>

        <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
          {budgets.data.map(({ budget, variance }) => (
            <Card key={budget.id} className={variance.is_over_budget ? 'border-red-200' : ''}>
              <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                <div>
                  <CardTitle className="text-lg">
                    {budget.category?.name || 'All Expenses'}
                  </CardTitle>
                  <CardDescription>
                    {budget.period_type.charAt(0).toUpperCase() + budget.period_type.slice(1)}
                  </CardDescription>
                </div>
                <DropdownMenu>
                  <DropdownMenuTrigger asChild>
                    <Button variant="ghost" size="icon">
                      <IconDotsVertical className="h-4 w-4" />
                    </Button>
                  </DropdownMenuTrigger>
                  <DropdownMenuContent align="end">
                    <DropdownMenuItem onClick={() => router.get(route('dashboard.mokey.budgets.edit', budget.id))}>
                      <IconEdit className="mr-2 h-4 w-4" /> Edit
                    </DropdownMenuItem>
                    <DropdownMenuItem className="text-red-600" onClick={() => handleDelete(budget)}>
                      <IconTrash className="mr-2 h-4 w-4" /> Delete
                    </DropdownMenuItem>
                  </DropdownMenuContent>
                </DropdownMenu>
              </CardHeader>
              <CardContent>
                <div className="space-y-3">
                  <div className="flex justify-between text-sm">
                    <span className="text-muted-foreground">Spent</span>
                    <span className={variance.is_over_budget ? 'text-red-600 font-medium' : ''}>
                      {formatMoney(variance.spent)} / {formatMoney(variance.allocated)}
                    </span>
                  </div>

                  <Progress
                    value={Math.min(variance.percent_used, 100)}
                    className={variance.is_over_budget ? '[&>div]:bg-red-500' : variance.percent_used > 80 ? '[&>div]:bg-yellow-500' : ''}
                  />

                  <div className="flex justify-between items-center">
                    <span className="text-sm text-muted-foreground">
                      {variance.is_over_budget ? (
                        <span className="flex items-center text-red-600">
                          <IconAlertTriangle className="h-4 w-4 mr-1" />
                          Over by {formatMoney(Math.abs(variance.variance))}
                        </span>
                      ) : (
                        `${formatMoney(variance.remaining)} remaining`
                      )}
                    </span>
                    <Badge variant={budget.is_active ? 'default' : 'secondary'}>
                      {budget.is_active ? 'Active' : 'Inactive'}
                    </Badge>
                  </div>
                </div>
              </CardContent>
            </Card>
          ))}

          {budgets.data.length === 0 && (
            <Card className="col-span-full">
              <CardContent className="flex flex-col items-center justify-center py-12">
                <p className="text-muted-foreground mb-4">No budgets yet</p>
                <Button onClick={() => router.get(route('dashboard.mokey.budgets.create'))}>
                  Create your first budget
                </Button>
              </CardContent>
            </Card>
          )}
        </div>
      </Main>
    </AuthenticatedLayout>
  )
}
```

## Todo List
- [ ] Add Budget types to mokey.ts
- [ ] Create BudgetForm component
- [ ] Create budgets.tsx list page with cards
- [ ] Create create-budget.tsx page
- [ ] Create edit-budget.tsx page
- [ ] Add period auto-calculation
- [ ] Add over-budget styling
- [ ] Test variance calculation display

## Success Criteria
- [ ] Budget cards show progress correctly
- [ ] Over-budget highlighted in red
- [ ] Period type auto-sets dates
- [ ] Create/edit forms work
- [ ] Variance data displays correctly

## Risk Assessment
- **Risk:** Spent amount not updating. **Mitigation:** Backend recalculates on transaction changes.
- **Risk:** Date calculation timezone issues. **Mitigation:** Use date-fns for consistent handling.

## Security Considerations
- Only show user's own budgets
- Spent amount calculated server-side, not editable

## Next Steps
Proceed to Phase 13: Frontend - Goals Module
