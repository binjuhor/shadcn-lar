import { useEffect } from 'react'
import { useForm } from '@inertiajs/react'
import { useTranslation } from 'react-i18next'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Switch } from '@/components/ui/switch'
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select'
import {
  Sheet,
  SheetContent,
  SheetDescription,
  SheetHeader,
  SheetTitle,
  SheetFooter,
} from '@/components/ui/sheet'
import type { Category, ExpenseType } from '@modules/Finance/types/finance'

interface CategoryFormProps {
  open: boolean
  onOpenChange: (open: boolean) => void
  category?: Category | null
  parentCategories: Category[]
  onSuccess?: () => void
}

const icons = [
  'wallet', 'briefcase', 'trending-up', 'plus-circle',
  'utensils', 'car', 'home', 'zap', 'heart', 'film',
  'shopping-bag', 'book', 'shield', 'more-horizontal',
  'gift', 'plane', 'coffee', 'music', 'gamepad-2', 'dog',
]

const colors = [
  '#10b981', '#3b82f6', '#8b5cf6', '#ef4444', '#f59e0b',
  '#ec4899', '#14b8a6', '#fbbf24', '#0ea5e9', '#f43f5e',
  '#06b6d4', '#84cc16', '#a855f7', '#22c55e', '#6366f1',
]

export function CategoryForm({
  open,
  onOpenChange,
  category,
  parentCategories,
  onSuccess,
}: CategoryFormProps) {
  const { t } = useTranslation()
  const isEditing = !!category

  const getFormDefaults = () => ({
    name: category?.name || '',
    type: (category?.type || 'expense') as 'income' | 'expense',
    parent_id: category?.parent_id ? String(category.parent_id) : '',
    icon: category?.icon || 'more-horizontal',
    color: category?.color || '#6b7280',
    is_active: category?.is_active ?? true,
    is_passive: category?.is_passive ?? false,
    expense_type: category?.expense_type || '',
  })

  const { data, setData, post, put, processing, errors, reset } = useForm(getFormDefaults())

  // Update form data when category changes (for edit mode)
  useEffect(() => {
    if (open) {
      reset()
      Object.entries(getFormDefaults()).forEach(([key, value]) => {
        setData(key as keyof typeof data, value)
      })
    }
  }, [open, category?.id])

  const filteredParents = parentCategories.filter(
    (p) => p.type === data.type && p.id !== category?.id
  )

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault()

    const formData = {
      ...data,
      parent_id: data.parent_id ? parseInt(data.parent_id) : null,
      expense_type: data.type === 'expense' && data.expense_type ? data.expense_type : null,
      is_passive: data.type === 'income' ? data.is_passive : false,
    }

    if (isEditing && category) {
      put(route('dashboard.finance.categories.update', category.id), {
        ...formData,
        onSuccess: () => {
          reset()
          onOpenChange(false)
          onSuccess?.()
        },
      })
    } else {
      post(route('dashboard.finance.categories.store'), {
        ...formData,
        onSuccess: () => {
          reset()
          onOpenChange(false)
          onSuccess?.()
        },
      })
    }
  }

  const handleClose = () => {
    reset()
    onOpenChange(false)
  }

  return (
    <Sheet open={open} onOpenChange={onOpenChange}>
      <SheetContent className="overflow-y-auto">
        <SheetHeader>
          <SheetTitle>
            {isEditing ? t('form.category.edit') : t('form.category.create')}
          </SheetTitle>
          <SheetDescription>
            {isEditing
              ? t('form.category.edit_description')
              : t('form.category.create_description')}
          </SheetDescription>
        </SheetHeader>

        <form onSubmit={handleSubmit} className="space-y-4 mt-4">
          <div className="space-y-2">
            <Label htmlFor="name">{t('form.category_name')}</Label>
            <Input
              id="name"
              value={data.name}
              onChange={(e) => setData('name', e.target.value)}
              placeholder={t('form.category_name_placeholder')}
            />
            {errors.name && (
              <p className="text-sm text-red-600">{errors.name}</p>
            )}
          </div>

          <div className="space-y-2">
            <Label htmlFor="type">{t('form.type')}</Label>
            <Select
              value={data.type}
              onValueChange={(value: 'income' | 'expense') => {
                setData('type', value)
                setData('parent_id', '')
              }}
            >
              <SelectTrigger>
                <SelectValue placeholder={t('form.select_type')} />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="income">{t('transaction.income')}</SelectItem>
                <SelectItem value="expense">{t('transaction.expense')}</SelectItem>
              </SelectContent>
            </Select>
          </div>

          {filteredParents.length > 0 && (
            <div className="space-y-2">
              <Label htmlFor="parent_id">{t('form.parent_category_optional')}</Label>
              <Select
                value={data.parent_id || '__none__'}
                onValueChange={(value) => setData('parent_id', value === '__none__' ? '' : value)}
              >
                <SelectTrigger>
                  <SelectValue placeholder={t('form.no_parent')} />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="__none__">{t('form.no_parent')}</SelectItem>
                  {filteredParents.map((parent) => (
                    <SelectItem key={parent.id} value={String(parent.id)}>
                      {parent.name}
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
            </div>
          )}

          <div className="space-y-2">
            <Label>{t('form.icon')}</Label>
            <div className="flex gap-2 flex-wrap">
              {icons.map((icon) => (
                <button
                  key={icon}
                  type="button"
                  className={`w-10 h-10 rounded-lg border-2 flex items-center justify-center text-sm transition-all ${
                    data.icon === icon
                      ? 'border-primary bg-primary/10'
                      : 'border-transparent bg-muted hover:border-muted-foreground/30'
                  }`}
                  onClick={() => setData('icon', icon)}
                >
                  {icon.slice(0, 2)}
                </button>
              ))}
            </div>
          </div>

          <div className="space-y-2">
            <Label>{t('form.color')}</Label>
            <div className="flex gap-2 flex-wrap">
              {colors.map((color) => (
                <button
                  key={color}
                  type="button"
                  className={`w-8 h-8 rounded-full border-2 transition-all ${
                    data.color === color
                      ? 'border-foreground scale-110'
                      : 'border-transparent'
                  }`}
                  style={{ backgroundColor: color }}
                  onClick={() => setData('color', color)}
                />
              ))}
            </div>
          </div>

          <div className="flex items-center justify-between">
            <div className="space-y-0.5">
              <Label htmlFor="is_active">{t('form.is_active')}</Label>
              <p className="text-xs text-muted-foreground">
                {t('form.category_is_active_description')}
              </p>
            </div>
            <Switch
              id="is_active"
              checked={data.is_active}
              onCheckedChange={(checked) => setData('is_active', checked)}
            />
          </div>

          {data.type === 'income' && (
            <div className="flex items-center justify-between">
              <div className="space-y-0.5">
                <Label htmlFor="is_passive">{t('form.passive_income')}</Label>
                <p className="text-xs text-muted-foreground">
                  {t('form.passive_income_description')}
                </p>
              </div>
              <Switch
                id="is_passive"
                checked={data.is_passive}
                onCheckedChange={(checked) => setData('is_passive', checked)}
              />
            </div>
          )}

          {data.type === 'expense' && (
            <div className="space-y-2">
              <Label htmlFor="expense_type">{t('form.expense_type')}</Label>
              <Select
                value={data.expense_type || '__none__'}
                onValueChange={(value) => setData('expense_type', value === '__none__' ? '' : value as ExpenseType)}
              >
                <SelectTrigger>
                  <SelectValue placeholder={t('form.select_expense_type')} />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="__none__">{t('form.not_specified')}</SelectItem>
                  <SelectItem value="essential">{t('form.expense_type_essential')}</SelectItem>
                  <SelectItem value="discretionary">{t('form.expense_type_discretionary')}</SelectItem>
                  <SelectItem value="savings">{t('form.expense_type_savings')}</SelectItem>
                </SelectContent>
              </Select>
              <p className="text-xs text-muted-foreground">
                {t('form.expense_type_description')}
              </p>
            </div>
          )}

          <SheetFooter className="gap-2 pt-4">
            <Button
              type="button"
              variant="outline"
              onClick={handleClose}
              disabled={processing}
            >
              {t('action.cancel')}
            </Button>
            <Button type="submit" disabled={processing}>
              {processing
                ? t('common.saving')
                : isEditing
                  ? t('form.update_category_button')
                  : t('form.create_category_button')}
            </Button>
          </SheetFooter>
        </form>
      </SheetContent>
    </Sheet>
  )
}
