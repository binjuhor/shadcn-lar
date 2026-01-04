import { useState, useEffect } from 'react'
import { router } from '@inertiajs/react'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Textarea } from '@/components/ui/textarea'
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select'
import {
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from '@/components/ui/card'
import {
  Accordion,
  AccordionContent,
  AccordionItem,
  AccordionTrigger,
} from '@/components/ui/accordion'
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs'
import { Plus, Trash2, TrendingUp, TrendingDown, Save } from 'lucide-react'
import type {
  FinancialPlan,
  Currency,
  Category,
  PlanFormPeriod,
  PlanFormItem,
  PlanItemType,
  PlanItemRecurrence,
  PlanStatus,
} from '@modules/Finance/types/finance'

interface Props {
  plan?: FinancialPlan
  currencies: Currency[]
  categories: Category[]
  currentYear: number
}

const recurrenceOptions: { value: PlanItemRecurrence; label: string }[] = [
  { value: 'one_time', label: 'One Time' },
  { value: 'monthly', label: 'Monthly' },
  { value: 'quarterly', label: 'Quarterly' },
  { value: 'yearly', label: 'Yearly' },
]

const statusOptions: { value: PlanStatus; label: string }[] = [
  { value: 'draft', label: 'Draft' },
  { value: 'active', label: 'Active' },
  { value: 'archived', label: 'Archived' },
]

function formatMoney(amount: number, currencyCode = 'VND'): string {
  return new Intl.NumberFormat('vi-VN', {
    style: 'currency',
    currency: currencyCode,
  }).format(amount)
}

function generatePeriods(startYear: number, endYear: number): PlanFormPeriod[] {
  const periods: PlanFormPeriod[] = []
  for (let year = startYear; year <= endYear; year++) {
    periods.push({ year, items: [] })
  }
  return periods
}

export function PlanForm({ plan, currencies, categories, currentYear }: Props) {
  const isEditing = !!plan
  const defaultCurrency = currencies.find((c) => c.is_default)?.code || 'VND'

  const [processing, setProcessing] = useState(false)
  const [name, setName] = useState(plan?.name || '')
  const [description, setDescription] = useState(plan?.description || '')
  const [startYear, setStartYear] = useState(plan?.start_year || currentYear)
  const [endYear, setEndYear] = useState(plan?.end_year || currentYear)
  const [currencyCode, setCurrencyCode] = useState(plan?.currency_code || defaultCurrency)
  const [status, setStatus] = useState<PlanStatus>(plan?.status || 'draft')
  const [periods, setPeriods] = useState<PlanFormPeriod[]>(
    plan?.periods?.map((p) => ({
      id: p.id,
      year: p.year,
      items: p.items.map((i) => ({
        id: i.id,
        name: i.name,
        type: i.type,
        planned_amount: i.planned_amount,
        recurrence: i.recurrence,
        category_id: i.category_id,
        notes: i.notes,
      })),
    })) || generatePeriods(currentYear, currentYear)
  )

  const [openYears, setOpenYears] = useState<string[]>(
    periods.map((p) => String(p.year))
  )

  useEffect(() => {
    if (!isEditing) {
      const newPeriods = generatePeriods(startYear, endYear)
      const existingByYear = new Map(periods.map((p) => [p.year, p]))

      const mergedPeriods = newPeriods.map((newP) => {
        const existing = existingByYear.get(newP.year)
        return existing || newP
      })

      setPeriods(mergedPeriods)
      setOpenYears(mergedPeriods.map((p) => String(p.year)))
    }
  }, [startYear, endYear, isEditing])

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault()
    setProcessing(true)

    const formData = {
      name,
      description,
      start_year: startYear,
      end_year: endYear,
      currency_code: currencyCode,
      status,
      periods,
    }

    if (isEditing && plan) {
      router.put(route('dashboard.finance.plans.update', plan.id), formData, {
        onFinish: () => setProcessing(false),
      })
    } else {
      router.post(route('dashboard.finance.plans.store'), formData, {
        onFinish: () => setProcessing(false),
      })
    }
  }

  const addItem = (periodIndex: number, type: PlanItemType) => {
    const newPeriods = [...periods]
    newPeriods[periodIndex].items.push({
      name: '',
      type,
      planned_amount: 0,
      recurrence: 'monthly',
    })
    setPeriods(newPeriods)
  }

  const updateItem = (
    periodIndex: number,
    itemIndex: number,
    field: keyof PlanFormItem,
    value: string | number | undefined
  ) => {
    const newPeriods = [...periods]
    const item = newPeriods[periodIndex].items[itemIndex]
    if (field === 'planned_amount') {
      item.planned_amount = Number(value) || 0
    } else if (field === 'category_id') {
      item.category_id = value ? Number(value) : undefined
    } else if (field === 'name') {
      item.name = String(value || '')
    } else if (field === 'type') {
      item.type = value as PlanItemType
    } else if (field === 'recurrence') {
      item.recurrence = value as PlanItemRecurrence
    } else if (field === 'notes') {
      item.notes = value ? String(value) : undefined
    }
    setPeriods(newPeriods)
  }

  const removeItem = (periodIndex: number, itemIndex: number) => {
    const newPeriods = [...periods]
    newPeriods[periodIndex].items.splice(itemIndex, 1)
    setPeriods(newPeriods)
  }

  const calculatePeriodTotals = (period: PlanFormPeriod) => {
    const income = period.items
      .filter((i) => i.type === 'income')
      .reduce((sum, i) => sum + (i.planned_amount || 0), 0)
    const expense = period.items
      .filter((i) => i.type === 'expense')
      .reduce((sum, i) => sum + (i.planned_amount || 0), 0)
    return { income, expense, net: income - expense }
  }

  const calculateGrandTotals = () => {
    return periods.reduce(
      (acc, period) => {
        const totals = calculatePeriodTotals(period)
        return {
          income: acc.income + totals.income,
          expense: acc.expense + totals.expense,
          net: acc.net + totals.net,
        }
      },
      { income: 0, expense: 0, net: 0 }
    )
  }

  const grandTotals = calculateGrandTotals()

  return (
    <form onSubmit={handleSubmit} className="space-y-6">
      {/* Plan Details */}
      <Card>
        <CardHeader>
          <CardTitle>Plan Details</CardTitle>
          <CardDescription>Basic information about your financial plan</CardDescription>
        </CardHeader>
        <CardContent className="space-y-4">
          <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
            <div className="space-y-2">
              <Label htmlFor="name">Plan Name</Label>
              <Input
                id="name"
                value={name}
                onChange={(e) => setName(e.target.value)}
                placeholder="e.g., 2025 Budget Plan"
              />
            </div>

            <div className="space-y-2">
              <Label htmlFor="status">Status</Label>
              <Select value={status} onValueChange={(v) => setStatus(v as PlanStatus)}>
                <SelectTrigger>
                  <SelectValue />
                </SelectTrigger>
                <SelectContent>
                  {statusOptions.map((option) => (
                    <SelectItem key={option.value} value={option.value}>
                      {option.label}
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
            </div>
          </div>

          <div className="space-y-2">
            <Label htmlFor="description">Description</Label>
            <Textarea
              id="description"
              value={description}
              onChange={(e) => setDescription(e.target.value)}
              placeholder="Optional description..."
              rows={2}
            />
          </div>

          <div className="grid grid-cols-1 gap-4 md:grid-cols-3">
            <div className="space-y-2">
              <Label htmlFor="start_year">Start Year</Label>
              <Input
                id="start_year"
                type="number"
                min={2000}
                max={2100}
                value={startYear}
                onChange={(e) => setStartYear(Number(e.target.value))}
                disabled={isEditing}
              />
            </div>

            <div className="space-y-2">
              <Label htmlFor="end_year">End Year</Label>
              <Input
                id="end_year"
                type="number"
                min={startYear}
                max={2100}
                value={endYear}
                onChange={(e) => setEndYear(Number(e.target.value))}
                disabled={isEditing}
              />
            </div>

            <div className="space-y-2">
              <Label htmlFor="currency">Currency</Label>
              <Select
                value={currencyCode}
                onValueChange={setCurrencyCode}
                disabled={isEditing}
              >
                <SelectTrigger>
                  <SelectValue />
                </SelectTrigger>
                <SelectContent>
                  {currencies.map((currency) => (
                    <SelectItem key={currency.code} value={currency.code}>
                      {currency.code} - {currency.name}
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
            </div>
          </div>
        </CardContent>
      </Card>

      {/* Summary */}
      <Card>
        <CardHeader>
          <CardTitle>Plan Summary</CardTitle>
        </CardHeader>
        <CardContent>
          <div className="grid grid-cols-3 gap-4">
            <div className="rounded-lg border bg-green-50 dark:bg-green-950/20 p-4">
              <p className="text-sm text-muted-foreground">Total Planned Income</p>
              <p className="text-2xl font-bold text-green-600">
                {formatMoney(grandTotals.income, currencyCode)}
              </p>
            </div>
            <div className="rounded-lg border bg-red-50 dark:bg-red-950/20 p-4">
              <p className="text-sm text-muted-foreground">Total Planned Expense</p>
              <p className="text-2xl font-bold text-red-600">
                {formatMoney(grandTotals.expense, currencyCode)}
              </p>
            </div>
            <div className="rounded-lg border p-4">
              <p className="text-sm text-muted-foreground">Net Savings</p>
              <p
                className={`text-2xl font-bold ${
                  grandTotals.net >= 0 ? 'text-green-600' : 'text-red-600'
                }`}
              >
                {formatMoney(grandTotals.net, currencyCode)}
              </p>
            </div>
          </div>
        </CardContent>
      </Card>

      {/* Yearly Periods */}
      <Card>
        <CardHeader>
          <CardTitle>Yearly Breakdown</CardTitle>
          <CardDescription>Add income and expense items for each year</CardDescription>
        </CardHeader>
        <CardContent>
          <Accordion
            type="multiple"
            value={openYears}
            onValueChange={setOpenYears}
            className="w-full"
          >
            {periods.map((period, periodIndex) => {
              const totals = calculatePeriodTotals(period)
              const incomeItems = period.items.filter((i) => i.type === 'income')
              const expenseItems = period.items.filter((i) => i.type === 'expense')

              return (
                <AccordionItem key={period.year} value={String(period.year)}>
                  <AccordionTrigger className="hover:no-underline">
                    <div className="flex w-full items-center justify-between pr-4">
                      <span className="text-lg font-semibold">{period.year}</span>
                      <div className="flex items-center gap-4 text-sm">
                        <span className="text-green-600">
                          +{formatMoney(totals.income, currencyCode)}
                        </span>
                        <span className="text-red-600">
                          -{formatMoney(totals.expense, currencyCode)}
                        </span>
                        <span
                          className={
                            totals.net >= 0 ? 'text-green-600' : 'text-red-600'
                          }
                        >
                          Net: {formatMoney(totals.net, currencyCode)}
                        </span>
                      </div>
                    </div>
                  </AccordionTrigger>
                  <AccordionContent>
                    <Tabs defaultValue="income" className="w-full">
                      <TabsList className="w-full grid grid-cols-2 mb-4">
                        <TabsTrigger value="income" className="flex items-center gap-2">
                          <TrendingUp className="h-4 w-4 text-green-600" />
                          Income ({incomeItems.length})
                        </TabsTrigger>
                        <TabsTrigger value="expense" className="flex items-center gap-2">
                          <TrendingDown className="h-4 w-4 text-red-600" />
                          Expense ({expenseItems.length})
                        </TabsTrigger>
                      </TabsList>

                      <TabsContent value="income" className="space-y-3">
                        {incomeItems.map((item, idx) => {
                          const itemIndex = period.items.findIndex((i) => i === item)
                          return (
                            <ItemRow
                              key={idx}
                              item={item}
                              categories={categories.filter(
                                (c) => c.type === 'income' || c.type === 'both'
                              )}
                              currencyCode={currencyCode}
                              onUpdate={(field, value) =>
                                updateItem(periodIndex, itemIndex, field, value)
                              }
                              onRemove={() => removeItem(periodIndex, itemIndex)}
                            />
                          )
                        })}
                        <Button
                          type="button"
                          variant="outline"
                          size="sm"
                          onClick={() => addItem(periodIndex, 'income')}
                          className="w-full"
                        >
                          <Plus className="mr-2 h-4 w-4" />
                          Add Income
                        </Button>
                      </TabsContent>

                      <TabsContent value="expense" className="space-y-3">
                        {expenseItems.map((item, idx) => {
                          const itemIndex = period.items.findIndex((i) => i === item)
                          return (
                            <ItemRow
                              key={idx}
                              item={item}
                              categories={categories.filter(
                                (c) => c.type === 'expense' || c.type === 'both'
                              )}
                              currencyCode={currencyCode}
                              onUpdate={(field, value) =>
                                updateItem(periodIndex, itemIndex, field, value)
                              }
                              onRemove={() => removeItem(periodIndex, itemIndex)}
                            />
                          )
                        })}
                        <Button
                          type="button"
                          variant="outline"
                          size="sm"
                          onClick={() => addItem(periodIndex, 'expense')}
                          className="w-full"
                        >
                          <Plus className="mr-2 h-4 w-4" />
                          Add Expense
                        </Button>
                      </TabsContent>
                    </Tabs>
                  </AccordionContent>
                </AccordionItem>
              )
            })}
          </Accordion>
        </CardContent>
      </Card>

      {/* Actions */}
      <div className="flex items-center justify-end gap-3">
        <Button
          type="button"
          variant="outline"
          onClick={() => router.visit(route('dashboard.finance.plans.index'))}
        >
          Cancel
        </Button>
        <Button type="submit" disabled={processing}>
          <Save className="mr-2 h-4 w-4" />
          {isEditing ? 'Update Plan' : 'Create Plan'}
        </Button>
      </div>
    </form>
  )
}

function ItemRow({
  item,
  categories,
  currencyCode,
  onUpdate,
  onRemove,
}: {
  item: PlanFormItem
  categories: Category[]
  currencyCode: string
  onUpdate: (field: keyof PlanFormItem, value: string | number | undefined) => void
  onRemove: () => void
}) {
  return (
    <div className="flex items-start gap-3 rounded-lg border p-3">
      <div className="flex-1 grid grid-cols-1 gap-3 md:grid-cols-4">
        <div>
          <Label className="text-xs">Name</Label>
          <Input
            value={item.name}
            onChange={(e) => onUpdate('name', e.target.value)}
            placeholder="Item name"
          />
        </div>

        <div>
          <Label className="text-xs">Amount ({currencyCode})</Label>
          <Input
            type="number"
            min={0}
            value={item.planned_amount || ''}
            onChange={(e) => onUpdate('planned_amount', e.target.value)}
            placeholder="0"
          />
        </div>

        <div>
          <Label className="text-xs">Recurrence</Label>
          <Select
            value={item.recurrence}
            onValueChange={(value) => onUpdate('recurrence', value)}
          >
            <SelectTrigger>
              <SelectValue />
            </SelectTrigger>
            <SelectContent>
              {recurrenceOptions.map((opt) => (
                <SelectItem key={opt.value} value={opt.value}>
                  {opt.label}
                </SelectItem>
              ))}
            </SelectContent>
          </Select>
        </div>

        <div>
          <Label className="text-xs">Category</Label>
          <Select
            value={item.category_id ? String(item.category_id) : ''}
            onValueChange={(value) => onUpdate('category_id', value || undefined)}
          >
            <SelectTrigger>
              <SelectValue placeholder="Optional" />
            </SelectTrigger>
            <SelectContent>
              {categories.map((cat) => (
                <SelectItem key={cat.id} value={String(cat.id)}>
                  {cat.name}
                </SelectItem>
              ))}
            </SelectContent>
          </Select>
        </div>
      </div>

      <Button
        type="button"
        variant="ghost"
        size="icon"
        onClick={onRemove}
        className="text-red-600 hover:text-red-700 hover:bg-red-50"
      >
        <Trash2 className="h-4 w-4" />
      </Button>
    </div>
  )
}
