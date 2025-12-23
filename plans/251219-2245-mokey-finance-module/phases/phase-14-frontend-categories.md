# Phase 14: Frontend - Categories Management

## Context
- Parent plan: [plan.md](../plan.md)
- Dependencies: Phase 06 (admin permissions)

## Overview
- Priority: low
- Status: pending
- Description: Build category management with nested tree view, admin-only create/edit/delete.

## Requirements
### Functional
- Category list with tree structure (parent/child)
- Create/edit forms with parent selection
- Income/expense type distinction
- Icon and color selection
- Delete with children handling

### Non-functional
- Collapsible tree view
- Admin-only create/edit/delete
- All users can view

## Related Code Files
### Files to Create
```
resources/js/pages/mokey/
├── categories.tsx
├── create-category.tsx
├── edit-category.tsx
└── components/
    ├── category-form.tsx
    └── category-tree.tsx
```

## Implementation Steps

### 1. Create Category Tree component
```tsx
// resources/js/pages/mokey/components/category-tree.tsx
import { useState } from 'react'
import { router } from '@inertiajs/react'
import { Button } from '@/components/ui/button'
import { Badge } from '@/components/ui/badge'
import { Collapsible, CollapsibleContent, CollapsibleTrigger } from '@/components/ui/collapsible'
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from '@/components/ui/dropdown-menu'
import { IconChevronRight, IconChevronDown, IconDotsVertical, IconEdit, IconTrash, IconPlus } from '@tabler/icons-react'
import { Category } from '../types/mokey'
import { cn } from '@/lib/utils'

interface CategoryTreeProps {
  categories: Category[]
  canManage: boolean
  onDelete: (category: Category) => void
}

interface CategoryNodeProps {
  category: Category
  level: number
  canManage: boolean
  onDelete: (category: Category) => void
}

function CategoryNode({ category, level, canManage, onDelete }: CategoryNodeProps) {
  const [isOpen, setIsOpen] = useState(false)
  const hasChildren = category.children && category.children.length > 0

  return (
    <div>
      <Collapsible open={isOpen} onOpenChange={setIsOpen}>
        <div
          className={cn(
            'flex items-center justify-between py-2 px-3 rounded-md hover:bg-muted/50',
            level > 0 && 'ml-6'
          )}
        >
          <div className="flex items-center gap-2">
            {hasChildren ? (
              <CollapsibleTrigger asChild>
                <Button variant="ghost" size="icon" className="h-6 w-6">
                  {isOpen ? <IconChevronDown className="h-4 w-4" /> : <IconChevronRight className="h-4 w-4" />}
                </Button>
              </CollapsibleTrigger>
            ) : (
              <div className="w-6" />
            )}

            {category.icon && (
              <span
                className="w-6 h-6 rounded-full flex items-center justify-center text-sm"
                style={{ backgroundColor: category.color || '#e5e7eb' }}
              >
                {category.icon}
              </span>
            )}

            <span className="font-medium">{category.name}</span>

            <Badge variant={category.type === 'income' ? 'default' : 'secondary'} className="text-xs">
              {category.type}
            </Badge>

            {!category.is_active && (
              <Badge variant="outline" className="text-xs">Inactive</Badge>
            )}
          </div>

          {canManage && (
            <DropdownMenu>
              <DropdownMenuTrigger asChild>
                <Button variant="ghost" size="icon" className="h-8 w-8">
                  <IconDotsVertical className="h-4 w-4" />
                </Button>
              </DropdownMenuTrigger>
              <DropdownMenuContent align="end">
                <DropdownMenuItem onClick={() => router.get(route('dashboard.mokey.categories.create', { parent_id: category.id }))}>
                  <IconPlus className="mr-2 h-4 w-4" /> Add Subcategory
                </DropdownMenuItem>
                <DropdownMenuItem onClick={() => router.get(route('dashboard.mokey.categories.edit', category.id))}>
                  <IconEdit className="mr-2 h-4 w-4" /> Edit
                </DropdownMenuItem>
                <DropdownMenuItem className="text-red-600" onClick={() => onDelete(category)}>
                  <IconTrash className="mr-2 h-4 w-4" /> Delete
                </DropdownMenuItem>
              </DropdownMenuContent>
            </DropdownMenu>
          )}
        </div>

        {hasChildren && (
          <CollapsibleContent>
            {category.children!.map((child) => (
              <CategoryNode
                key={child.id}
                category={child}
                level={level + 1}
                canManage={canManage}
                onDelete={onDelete}
              />
            ))}
          </CollapsibleContent>
        )}
      </Collapsible>
    </div>
  )
}

export function CategoryTree({ categories, canManage, onDelete }: CategoryTreeProps) {
  // Build tree from flat list
  const buildTree = (items: Category[], parentId: number | null = null): Category[] => {
    return items
      .filter((item) => item.parent_id === parentId)
      .map((item) => ({
        ...item,
        children: buildTree(items, item.id),
      }))
  }

  const tree = buildTree(categories)

  return (
    <div className="space-y-1">
      {tree.map((category) => (
        <CategoryNode
          key={category.id}
          category={category}
          level={0}
          canManage={canManage}
          onDelete={onDelete}
        />
      ))}
    </div>
  )
}
```

### 2. Create Category Form component
```tsx
// resources/js/pages/mokey/components/category-form.tsx
import { useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { z } from 'zod'
import { router } from '@inertiajs/react'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { Switch } from '@/components/ui/switch'
import { Category } from '../types/mokey'

const categorySchema = z.object({
  name: z.string().min(1, 'Name is required').max(255),
  type: z.enum(['income', 'expense']),
  parent_id: z.number().nullable(),
  icon: z.string().max(50).optional().nullable(),
  color: z.string().regex(/^#[a-fA-F0-9]{6}$/, 'Invalid hex color').optional().nullable(),
  is_active: z.boolean(),
})

type CategoryFormData = z.infer<typeof categorySchema>

interface CategoryFormProps {
  category?: Category
  categories: Category[]
  defaultParentId?: number
}

export function CategoryForm({ category, categories, defaultParentId }: CategoryFormProps) {
  const isEditing = !!category

  const { register, handleSubmit, setValue, watch, formState: { errors, isSubmitting } } = useForm<CategoryFormData>({
    resolver: zodResolver(categorySchema),
    defaultValues: {
      name: category?.name ?? '',
      type: category?.type ?? 'expense',
      parent_id: category?.parent_id ?? defaultParentId ?? null,
      icon: category?.icon ?? '',
      color: category?.color ?? '#6366f1',
      is_active: category?.is_active ?? true,
    },
  })

  const selectedType = watch('type')

  // Filter parent categories to same type, exclude self and descendants
  const availableParents = categories.filter((cat) =>
    cat.type === selectedType &&
    cat.id !== category?.id &&
    cat.parent_id !== category?.id
  )

  const onSubmit = (data: CategoryFormData) => {
    if (isEditing) {
      router.put(route('dashboard.mokey.categories.update', category.id), data)
    } else {
      router.post(route('dashboard.mokey.categories.store'), data)
    }
  }

  const commonIcons = ['🏠', '🍔', '🚗', '💊', '🎬', '✈️', '📱', '💼', '🎓', '🏋️', '💰', '🛒']
  const commonColors = ['#ef4444', '#f97316', '#eab308', '#22c55e', '#06b6d4', '#3b82f6', '#8b5cf6', '#ec4899']

  return (
    <form onSubmit={handleSubmit(onSubmit)} className="space-y-6">
      <div className="grid gap-4 md:grid-cols-2">
        <div className="space-y-2">
          <Label>Category Name</Label>
          <Input {...register('name')} placeholder="e.g., Groceries" />
          {errors.name && <p className="text-sm text-red-500">{errors.name.message}</p>}
        </div>

        <div className="space-y-2">
          <Label>Type</Label>
          <Select value={watch('type')} onValueChange={(val) => setValue('type', val as any)}>
            <SelectTrigger>
              <SelectValue />
            </SelectTrigger>
            <SelectContent>
              <SelectItem value="income">Income</SelectItem>
              <SelectItem value="expense">Expense</SelectItem>
            </SelectContent>
          </Select>
        </div>

        <div className="space-y-2">
          <Label>Parent Category (optional)</Label>
          <Select
            value={watch('parent_id')?.toString() ?? ''}
            onValueChange={(val) => setValue('parent_id', val ? parseInt(val) : null)}
          >
            <SelectTrigger>
              <SelectValue placeholder="None (top level)" />
            </SelectTrigger>
            <SelectContent>
              <SelectItem value="">None (top level)</SelectItem>
              {availableParents.map((cat) => (
                <SelectItem key={cat.id} value={cat.id.toString()}>
                  {cat.name}
                </SelectItem>
              ))}
            </SelectContent>
          </Select>
        </div>

        <div className="space-y-2">
          <Label>Icon (optional)</Label>
          <div className="flex gap-2">
            <Input {...register('icon')} placeholder="Emoji or text" className="w-24" />
            <div className="flex gap-1 flex-wrap">
              {commonIcons.map((icon) => (
                <Button
                  key={icon}
                  type="button"
                  variant="outline"
                  size="icon"
                  className="h-8 w-8"
                  onClick={() => setValue('icon', icon)}
                >
                  {icon}
                </Button>
              ))}
            </div>
          </div>
        </div>

        <div className="space-y-2">
          <Label>Color</Label>
          <div className="flex gap-2 items-center">
            <Input
              type="color"
              {...register('color')}
              className="w-12 h-10 p-1 cursor-pointer"
            />
            <div className="flex gap-1">
              {commonColors.map((color) => (
                <button
                  key={color}
                  type="button"
                  className="w-6 h-6 rounded-full border-2 border-white shadow-sm"
                  style={{ backgroundColor: color }}
                  onClick={() => setValue('color', color)}
                />
              ))}
            </div>
          </div>
          {errors.color && <p className="text-sm text-red-500">{errors.color.message}</p>}
        </div>
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
          {isEditing ? 'Update Category' : 'Create Category'}
        </Button>
        <Button type="button" variant="outline" onClick={() => router.get(route('dashboard.mokey.categories.index'))}>
          Cancel
        </Button>
      </div>
    </form>
  )
}
```

### 3. Create Categories page
```tsx
// resources/js/pages/mokey/categories.tsx
import { AuthenticatedLayout } from '@/layouts'
import { Main } from '@/components/layout'
import { usePage } from '@inertiajs/react'
import { router } from '@inertiajs/react'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs'
import { IconPlus } from '@tabler/icons-react'
import { CategoryTree } from './components/category-tree'
import { Category } from './types/mokey'
import { PageProps } from '@/types'
import { useToast } from '@/hooks/use-toast'

interface CategoriesPageProps extends PageProps {
  categories: Category[]
  can_manage: boolean
}

export default function CategoriesPage() {
  const { categories, can_manage } = usePage<CategoriesPageProps>().props
  const { toast } = useToast()

  const handleDelete = (category: Category) => {
    const hasChildren = categories.some((c) => c.parent_id === category.id)
    const message = hasChildren
      ? `Delete "${category.name}" and all its subcategories?`
      : `Delete "${category.name}"?`

    if (confirm(message)) {
      router.delete(route('dashboard.mokey.categories.destroy', category.id), {
        onSuccess: () => toast({ title: 'Category deleted' }),
      })
    }
  }

  const incomeCategories = categories.filter((c) => c.type === 'income')
  const expenseCategories = categories.filter((c) => c.type === 'expense')

  return (
    <AuthenticatedLayout title="Categories">
      <Main>
        <div className="flex items-center justify-between mb-6">
          <div>
            <h2 className="text-3xl font-bold tracking-tight">Categories</h2>
            <p className="text-muted-foreground">Organize your transactions by category</p>
          </div>
          {can_manage && (
            <Button onClick={() => router.get(route('dashboard.mokey.categories.create'))}>
              <IconPlus className="mr-2 h-4 w-4" /> Add Category
            </Button>
          )}
        </div>

        <Tabs defaultValue="expense">
          <TabsList>
            <TabsTrigger value="expense">Expense ({expenseCategories.length})</TabsTrigger>
            <TabsTrigger value="income">Income ({incomeCategories.length})</TabsTrigger>
          </TabsList>

          <TabsContent value="expense">
            <Card>
              <CardHeader>
                <CardTitle>Expense Categories</CardTitle>
              </CardHeader>
              <CardContent>
                {expenseCategories.length > 0 ? (
                  <CategoryTree
                    categories={expenseCategories}
                    canManage={can_manage}
                    onDelete={handleDelete}
                  />
                ) : (
                  <p className="text-muted-foreground">No expense categories yet</p>
                )}
              </CardContent>
            </Card>
          </TabsContent>

          <TabsContent value="income">
            <Card>
              <CardHeader>
                <CardTitle>Income Categories</CardTitle>
              </CardHeader>
              <CardContent>
                {incomeCategories.length > 0 ? (
                  <CategoryTree
                    categories={incomeCategories}
                    canManage={can_manage}
                    onDelete={handleDelete}
                  />
                ) : (
                  <p className="text-muted-foreground">No income categories yet</p>
                )}
              </CardContent>
            </Card>
          </TabsContent>
        </Tabs>
      </Main>
    </AuthenticatedLayout>
  )
}
```

## Todo List
- [ ] Add Category types to mokey.ts (with children)
- [ ] Create CategoryTree component with collapsible nodes
- [ ] Create CategoryForm with icon/color pickers
- [ ] Create categories.tsx page with tabs
- [ ] Create create-category.tsx page
- [ ] Create edit-category.tsx page
- [ ] Handle subcategory creation
- [ ] Handle cascade delete warning
- [ ] Test permission-based UI

## Success Criteria
- [ ] Tree displays nested categories
- [ ] Collapsible parent nodes work
- [ ] Admin can create/edit/delete
- [ ] Regular users see read-only view
- [ ] Icons and colors display correctly

## Risk Assessment
- **Risk:** Deep nesting causes UI issues. **Mitigation:** Limit to 2 levels in UI.
- **Risk:** Delete orphans children. **Mitigation:** Show warning, cascade delete.

## Security Considerations
- Create/edit/delete require admin permission
- Permission check on backend and frontend

## Next Steps
Proceed to Phase 15: Testing & Documentation
