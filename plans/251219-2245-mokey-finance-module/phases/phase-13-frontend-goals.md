# Phase 13: Frontend - Goals Module

## Context
- Parent plan: [plan.md](../plan.md)
- Dependencies: Phase 12 (similar patterns)

## Overview
- Priority: medium
- Status: pending
- Description: Build goals list with progress tracking, create/edit forms, progress update functionality.

## Requirements
### Functional
- Goals list with progress visualization
- Goal type and timeframe selection
- Manual progress updates
- Completion marking
- Projected completion date display

### Non-functional
- Progress circles or bars
- On-track vs behind status indicators

## Related Code Files
### Files to Create
```
resources/js/pages/mokey/
├── goals.tsx
├── goal.tsx
├── create-goal.tsx
├── edit-goal.tsx
└── components/
    ├── goal-form.tsx
    └── goal-progress-card.tsx
```

## Implementation Steps

### 1. Create Goal Form component
```tsx
// resources/js/pages/mokey/components/goal-form.tsx
import { useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { z } from 'zod'
import { router } from '@inertiajs/react'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Textarea } from '@/components/ui/textarea'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { Calendar } from '@/components/ui/calendar'
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover'
import { Switch } from '@/components/ui/switch'
import { format } from 'date-fns'
import { IconCalendar } from '@tabler/icons-react'
import { Goal, Currency } from '../types/mokey'

const goalSchema = z.object({
  goal_type: z.enum(['savings', 'debt_payoff', 'purchase']),
  timeframe: z.enum(['short_term', 'medium_term', 'long_term']),
  name: z.string().min(1, 'Name is required').max(255),
  description: z.string().max(2000).optional(),
  target_amount: z.number().positive('Target must be positive'),
  current_amount: z.number().min(0).optional(),
  currency_code: z.string().length(3),
  start_date: z.date(),
  target_date: z.date().nullable(),
  is_active: z.boolean(),
}).refine((data) => !data.target_date || data.target_date > data.start_date, {
  message: 'Target date must be after start date',
  path: ['target_date'],
})

type GoalFormData = z.infer<typeof goalSchema>

interface GoalFormProps {
  goal?: Goal
  currencies: Currency[]
}

export function GoalForm({ goal, currencies }: GoalFormProps) {
  const isEditing = !!goal

  const { register, handleSubmit, setValue, watch, formState: { errors, isSubmitting } } = useForm<GoalFormData>({
    resolver: zodResolver(goalSchema),
    defaultValues: {
      goal_type: goal?.goal_type ?? 'savings',
      timeframe: goal?.timeframe ?? 'short_term',
      name: goal?.name ?? '',
      description: goal?.description ?? '',
      target_amount: goal ? goal.target_amount / 100 : 0,
      current_amount: goal ? goal.current_amount / 100 : 0,
      currency_code: goal?.currency_code ?? 'USD',
      start_date: goal ? new Date(goal.start_date) : new Date(),
      target_date: goal?.target_date ? new Date(goal.target_date) : null,
      is_active: goal?.is_active ?? true,
    },
  })

  const goalTypes = [
    { value: 'savings', label: 'Savings', description: 'Build an emergency fund or save for something' },
    { value: 'debt_payoff', label: 'Debt Payoff', description: 'Pay off a loan or credit card' },
    { value: 'purchase', label: 'Major Purchase', description: 'Save for a big purchase' },
  ]

  const timeframes = [
    { value: 'short_term', label: 'Short Term', description: '< 1 year' },
    { value: 'medium_term', label: 'Medium Term', description: '1-5 years' },
    { value: 'long_term', label: 'Long Term', description: '> 5 years' },
  ]

  const onSubmit = (data: GoalFormData) => {
    const payload = {
      ...data,
      target_amount: Math.round(data.target_amount * 100),
      current_amount: Math.round((data.current_amount ?? 0) * 100),
      start_date: format(data.start_date, 'yyyy-MM-dd'),
      target_date: data.target_date ? format(data.target_date, 'yyyy-MM-dd') : null,
    }

    if (isEditing) {
      router.put(route('dashboard.mokey.goals.update', goal.id), payload)
    } else {
      router.post(route('dashboard.mokey.goals.store'), payload)
    }
  }

  return (
    <form onSubmit={handleSubmit(onSubmit)} className="space-y-6">
      <div className="grid gap-4 md:grid-cols-2">
        <div className="space-y-2">
          <Label>Goal Type</Label>
          <Select value={watch('goal_type')} onValueChange={(val) => setValue('goal_type', val as any)}>
            <SelectTrigger>
              <SelectValue />
            </SelectTrigger>
            <SelectContent>
              {goalTypes.map((type) => (
                <SelectItem key={type.value} value={type.value}>
                  <div>
                    <div className="font-medium">{type.label}</div>
                    <div className="text-xs text-muted-foreground">{type.description}</div>
                  </div>
                </SelectItem>
              ))}
            </SelectContent>
          </Select>
        </div>

        <div className="space-y-2">
          <Label>Timeframe</Label>
          <Select value={watch('timeframe')} onValueChange={(val) => setValue('timeframe', val as any)}>
            <SelectTrigger>
              <SelectValue />
            </SelectTrigger>
            <SelectContent>
              {timeframes.map((tf) => (
                <SelectItem key={tf.value} value={tf.value}>
                  {tf.label} ({tf.description})
                </SelectItem>
              ))}
            </SelectContent>
          </Select>
        </div>

        <div className="space-y-2 md:col-span-2">
          <Label>Goal Name</Label>
          <Input {...register('name')} placeholder="e.g., Emergency Fund" />
          {errors.name && <p className="text-sm text-red-500">{errors.name.message}</p>}
        </div>

        <div className="space-y-2">
          <Label>Target Amount</Label>
          <Input type="number" step="0.01" min="0.01" {...register('target_amount', { valueAsNumber: true })} />
          {errors.target_amount && <p className="text-sm text-red-500">{errors.target_amount.message}</p>}
        </div>

        <div className="space-y-2">
          <Label>Current Amount</Label>
          <Input type="number" step="0.01" min="0" {...register('current_amount', { valueAsNumber: true })} />
        </div>

        <div className="space-y-2">
          <Label>Currency</Label>
          <Select value={watch('currency_code')} onValueChange={(val) => setValue('currency_code', val)}>
            <SelectTrigger>
              <SelectValue />
            </SelectTrigger>
            <SelectContent>
              {currencies.map((cur) => (
                <SelectItem key={cur.code} value={cur.code}>{cur.symbol} {cur.code}</SelectItem>
              ))}
            </SelectContent>
          </Select>
        </div>

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
              <Calendar mode="single" selected={watch('start_date')} onSelect={(d) => d && setValue('start_date', d)} />
            </PopoverContent>
          </Popover>
        </div>

        <div className="space-y-2">
          <Label>Target Date (optional)</Label>
          <Popover>
            <PopoverTrigger asChild>
              <Button variant="outline" className="w-full justify-start">
                <IconCalendar className="mr-2 h-4 w-4" />
                {watch('target_date') ? format(watch('target_date')!, 'PPP') : 'No target date'}
              </Button>
            </PopoverTrigger>
            <PopoverContent className="w-auto p-0">
              <Calendar mode="single" selected={watch('target_date') ?? undefined} onSelect={(d) => setValue('target_date', d ?? null)} />
            </PopoverContent>
          </Popover>
          {errors.target_date && <p className="text-sm text-red-500">{errors.target_date.message}</p>}
        </div>

        <div className="space-y-2 md:col-span-2">
          <Label>Description (optional)</Label>
          <Textarea {...register('description')} rows={3} />
        </div>
      </div>

      <div className="flex items-center space-x-2">
        <Switch id="is_active" checked={watch('is_active')} onCheckedChange={(val) => setValue('is_active', val)} />
        <Label htmlFor="is_active">Active</Label>
      </div>

      <div className="flex gap-4">
        <Button type="submit" disabled={isSubmitting}>
          {isEditing ? 'Update Goal' : 'Create Goal'}
        </Button>
        <Button type="button" variant="outline" onClick={() => router.get(route('dashboard.mokey.goals.index'))}>
          Cancel
        </Button>
      </div>
    </form>
  )
}
```

### 2. Create Goals List page
```tsx
// resources/js/pages/mokey/goals.tsx
import { AuthenticatedLayout } from '@/layouts'
import { Main } from '@/components/layout'
import { usePage } from '@inertiajs/react'
import { router } from '@inertiajs/react'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card'
import { Progress } from '@/components/ui/progress'
import { Badge } from '@/components/ui/badge'
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from '@/components/ui/dropdown-menu'
import { IconPlus, IconDotsVertical, IconEdit, IconTrash, IconCheck, IconTarget, IconTrendingUp } from '@tabler/icons-react'
import { Goal } from './types/mokey'
import { PageProps } from '@/types'
import { useToast } from '@/hooks/use-toast'

interface GoalsPageProps extends PageProps {
  goals: { data: Goal[]; total: number }
}

export default function GoalsPage() {
  const { goals } = usePage<GoalsPageProps>().props
  const { toast } = useToast()

  const handleComplete = (goal: Goal) => {
    router.post(route('dashboard.mokey.goals.complete', goal.id), {}, {
      onSuccess: () => toast({ title: 'Goal completed!' }),
    })
  }

  const handleDelete = (goal: Goal) => {
    if (confirm('Delete this goal?')) {
      router.delete(route('dashboard.mokey.goals.destroy', goal.id), {
        onSuccess: () => toast({ title: 'Goal deleted' }),
      })
    }
  }

  const formatMoney = (cents: number) => `$${(cents / 100).toFixed(2)}`

  const getProgressColor = (percent: number, completed: boolean) => {
    if (completed) return '[&>div]:bg-green-500'
    if (percent >= 75) return '[&>div]:bg-green-500'
    if (percent >= 50) return '[&>div]:bg-yellow-500'
    return ''
  }

  const getGoalTypeBadge = (type: string) => {
    const styles: Record<string, string> = {
      savings: 'bg-blue-100 text-blue-800',
      debt_payoff: 'bg-red-100 text-red-800',
      purchase: 'bg-purple-100 text-purple-800',
    }
    return <Badge className={styles[type]}>{type.replace('_', ' ')}</Badge>
  }

  return (
    <AuthenticatedLayout title="Goals">
      <Main>
        <div className="flex items-center justify-between mb-6">
          <div>
            <h2 className="text-3xl font-bold tracking-tight">Financial Goals</h2>
            <p className="text-muted-foreground">Track your progress towards financial milestones</p>
          </div>
          <Button onClick={() => router.get(route('dashboard.mokey.goals.create'))}>
            <IconPlus className="mr-2 h-4 w-4" /> Add Goal
          </Button>
        </div>

        <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
          {goals.data.map((goal) => {
            const percent = goal.target_amount > 0 ? (goal.current_amount / goal.target_amount) * 100 : 0

            return (
              <Card key={goal.id} className={goal.is_completed ? 'border-green-200 bg-green-50/50' : ''}>
                <CardHeader className="flex flex-row items-start justify-between space-y-0 pb-2">
                  <div className="space-y-1">
                    <div className="flex items-center gap-2">
                      <IconTarget className="h-5 w-5 text-muted-foreground" />
                      <CardTitle className="text-lg">{goal.name}</CardTitle>
                    </div>
                    <div className="flex gap-2">
                      {getGoalTypeBadge(goal.goal_type)}
                      <Badge variant="outline">{goal.timeframe.replace('_', ' ')}</Badge>
                    </div>
                  </div>
                  <DropdownMenu>
                    <DropdownMenuTrigger asChild>
                      <Button variant="ghost" size="icon">
                        <IconDotsVertical className="h-4 w-4" />
                      </Button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent align="end">
                      {!goal.is_completed && (
                        <DropdownMenuItem onClick={() => handleComplete(goal)}>
                          <IconCheck className="mr-2 h-4 w-4" /> Mark Complete
                        </DropdownMenuItem>
                      )}
                      <DropdownMenuItem onClick={() => router.get(route('dashboard.mokey.goals.edit', goal.id))}>
                        <IconEdit className="mr-2 h-4 w-4" /> Edit
                      </DropdownMenuItem>
                      <DropdownMenuItem className="text-red-600" onClick={() => handleDelete(goal)}>
                        <IconTrash className="mr-2 h-4 w-4" /> Delete
                      </DropdownMenuItem>
                    </DropdownMenuContent>
                  </DropdownMenu>
                </CardHeader>
                <CardContent>
                  <div className="space-y-3">
                    <div className="flex justify-between text-sm">
                      <span className="text-muted-foreground">Progress</span>
                      <span className="font-medium">
                        {formatMoney(goal.current_amount)} / {formatMoney(goal.target_amount)}
                      </span>
                    </div>

                    <Progress
                      value={Math.min(percent, 100)}
                      className={getProgressColor(percent, goal.is_completed)}
                    />

                    <div className="flex justify-between items-center text-sm">
                      <span className={goal.is_completed ? 'text-green-600 font-medium' : 'text-muted-foreground'}>
                        {goal.is_completed ? (
                          <span className="flex items-center">
                            <IconCheck className="h-4 w-4 mr-1" /> Completed!
                          </span>
                        ) : (
                          `${percent.toFixed(1)}% complete`
                        )}
                      </span>
                      {goal.target_date && !goal.is_completed && (
                        <span className="text-muted-foreground">
                          Target: {new Date(goal.target_date).toLocaleDateString()}
                        </span>
                      )}
                    </div>

                    {goal.description && (
                      <p className="text-sm text-muted-foreground line-clamp-2">{goal.description}</p>
                    )}
                  </div>
                </CardContent>
              </Card>
            )
          })}

          {goals.data.length === 0 && (
            <Card className="col-span-full">
              <CardContent className="flex flex-col items-center justify-center py-12">
                <IconTarget className="h-12 w-12 text-muted-foreground mb-4" />
                <p className="text-muted-foreground mb-4">No goals yet</p>
                <Button onClick={() => router.get(route('dashboard.mokey.goals.create'))}>
                  Create your first goal
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
- [ ] Add Goal types to mokey.ts
- [ ] Create GoalForm component
- [ ] Create goals.tsx list page with cards
- [ ] Create goal.tsx detail page with history
- [ ] Create create-goal.tsx page
- [ ] Create edit-goal.tsx page
- [ ] Add progress update action
- [ ] Add complete action
- [ ] Add progress history chart
- [ ] Test goal completion flow

## Success Criteria
- [ ] Goal cards show progress correctly
- [ ] Completed goals highlighted
- [ ] Goal types displayed with badges
- [ ] Create/edit forms work
- [ ] Complete action marks goal done

## Risk Assessment
- **Risk:** Progress not syncing with accounts. **Mitigation:** Manual progress update vs linked accounts (future feature).
- **Risk:** Target date in past. **Mitigation:** Allow historical dates for completed goals.

## Security Considerations
- Only show user's own goals
- Current amount editable by user

## Next Steps
Proceed to Phase 14: Frontend - Categories Management
