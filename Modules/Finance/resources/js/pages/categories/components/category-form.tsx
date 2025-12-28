import { useForm } from '@inertiajs/react'
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
import type { Category } from '@modules/Finance/resources/js/types/finance'

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
  const isEditing = !!category

  const { data, setData, post, put, processing, errors, reset } = useForm({
    name: category?.name || '',
    type: category?.type || 'expense',
    parent_id: category?.parent_id ? String(category.parent_id) : '',
    icon: category?.icon || 'more-horizontal',
    color: category?.color || '#6b7280',
    is_active: category?.is_active ?? true,
  })

  const filteredParents = parentCategories.filter(
    (p) => p.type === data.type && p.id !== category?.id
  )

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault()

    const formData = {
      ...data,
      parent_id: data.parent_id ? parseInt(data.parent_id) : null,
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
            {isEditing ? 'Edit Category' : 'Create Category'}
          </SheetTitle>
          <SheetDescription>
            {isEditing
              ? 'Update your category settings'
              : 'Add a new category to organize transactions'}
          </SheetDescription>
        </SheetHeader>

        <form onSubmit={handleSubmit} className="space-y-4 mt-4">
          <div className="space-y-2">
            <Label htmlFor="name">Category Name</Label>
            <Input
              id="name"
              value={data.name}
              onChange={(e) => setData('name', e.target.value)}
              placeholder="e.g., Groceries"
            />
            {errors.name && (
              <p className="text-sm text-red-600">{errors.name}</p>
            )}
          </div>

          <div className="space-y-2">
            <Label htmlFor="type">Type</Label>
            <Select
              value={data.type}
              onValueChange={(value: 'income' | 'expense') => {
                setData('type', value)
                setData('parent_id', '')
              }}
            >
              <SelectTrigger>
                <SelectValue placeholder="Select type" />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="income">Income</SelectItem>
                <SelectItem value="expense">Expense</SelectItem>
              </SelectContent>
            </Select>
          </div>

          {filteredParents.length > 0 && (
            <div className="space-y-2">
              <Label htmlFor="parent_id">Parent Category (Optional)</Label>
              <Select
                value={data.parent_id}
                onValueChange={(value) => setData('parent_id', value)}
              >
                <SelectTrigger>
                  <SelectValue placeholder="No parent (top level)" />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="">No parent (top level)</SelectItem>
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
            <Label>Icon</Label>
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
            <Label>Color</Label>
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
              <Label htmlFor="is_active">Active</Label>
              <p className="text-xs text-muted-foreground">
                Inactive categories won't appear in selection
              </p>
            </div>
            <Switch
              id="is_active"
              checked={data.is_active}
              onCheckedChange={(checked) => setData('is_active', checked)}
            />
          </div>

          <SheetFooter className="gap-2 pt-4">
            <Button
              type="button"
              variant="outline"
              onClick={handleClose}
              disabled={processing}
            >
              Cancel
            </Button>
            <Button type="submit" disabled={processing}>
              {processing
                ? 'Saving...'
                : isEditing
                  ? 'Update Category'
                  : 'Create Category'}
            </Button>
          </SheetFooter>
        </form>
      </SheetContent>
    </Sheet>
  )
}
