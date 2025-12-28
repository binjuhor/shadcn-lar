import { useState, useMemo } from 'react'
import { router } from '@inertiajs/react'
import { AuthenticatedLayout } from '@/layouts'
import { Main } from '@/components/layout/main'
import { Button } from '@/components/ui/button'
import { Badge } from '@/components/ui/badge'
import {
  Card,
  CardContent,
  CardHeader,
  CardTitle,
} from '@/components/ui/card'
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
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu'
import { Plus, MoreHorizontal, Pencil, Trash2, Tags } from 'lucide-react'
import { CategoryForm } from './components/category-form'
import type { Category } from '@modules/Finance/resources/js/types/finance'

interface Props {
  categories: Category[]
}

export default function CategoriesIndex({ categories }: Props) {
  const [showForm, setShowForm] = useState(false)
  const [showDeleteDialog, setShowDeleteDialog] = useState(false)
  const [selectedCategory, setSelectedCategory] = useState<Category | null>(null)
  const [filterType, setFilterType] = useState<'all' | 'income' | 'expense'>('all')

  const filteredCategories = useMemo(() => {
    if (filterType === 'all') {
      return categories
    }
    return categories.filter((c) => c.type === filterType)
  }, [categories, filterType])

  const incomeCategories = filteredCategories.filter((c) => c.type === 'income')
  const expenseCategories = filteredCategories.filter((c) => c.type === 'expense')

  const parentCategories = categories.filter((c) => !c.parent_id)

  const handleEdit = (category: Category) => {
    setSelectedCategory(category)
    setShowForm(true)
  }

  const handleDelete = (category: Category) => {
    setSelectedCategory(category)
    setShowDeleteDialog(true)
  }

  const confirmDelete = () => {
    if (selectedCategory) {
      router.delete(
        route('dashboard.finance.categories.destroy', selectedCategory.id),
        {
          onSuccess: () => {
            setShowDeleteDialog(false)
            setSelectedCategory(null)
          },
        }
      )
    }
  }

  const handleFormClose = () => {
    setShowForm(false)
    setSelectedCategory(null)
  }

  const handleSuccess = () => {
    router.reload({ only: ['categories'] })
  }

  const renderCategory = (category: Category) => {
    const isSystem = !category.user_id
    const children = categories.filter((c) => c.parent_id === category.id)

    return (
      <div key={category.id} className="space-y-2">
        <div
          className={`flex items-center justify-between p-3 rounded-lg border ${
            !category.is_active ? 'opacity-60' : ''
          }`}
        >
          <div className="flex items-center gap-3">
            <div
              className="w-8 h-8 rounded-lg flex items-center justify-center text-white text-sm font-medium"
              style={{ backgroundColor: category.color || '#6b7280' }}
            >
              {category.name.slice(0, 2).toUpperCase()}
            </div>
            <div>
              <p className="font-medium">{category.name}</p>
              <div className="flex gap-1">
                {isSystem && (
                  <Badge variant="outline" className="text-xs">
                    System
                  </Badge>
                )}
                {!category.is_active && (
                  <Badge variant="secondary" className="text-xs">
                    Inactive
                  </Badge>
                )}
              </div>
            </div>
          </div>
          {!isSystem && (
            <DropdownMenu>
              <DropdownMenuTrigger asChild>
                <Button variant="ghost" size="icon" className="h-8 w-8">
                  <MoreHorizontal className="h-4 w-4" />
                </Button>
              </DropdownMenuTrigger>
              <DropdownMenuContent align="end">
                <DropdownMenuItem onClick={() => handleEdit(category)}>
                  <Pencil className="mr-2 h-4 w-4" />
                  Edit
                </DropdownMenuItem>
                <DropdownMenuSeparator />
                <DropdownMenuItem
                  onClick={() => handleDelete(category)}
                  className="text-red-600"
                >
                  <Trash2 className="mr-2 h-4 w-4" />
                  Delete
                </DropdownMenuItem>
              </DropdownMenuContent>
            </DropdownMenu>
          )}
        </div>
        {children.length > 0 && (
          <div className="ml-8 space-y-2">
            {children.map(renderCategory)}
          </div>
        )}
      </div>
    )
  }

  const renderCategoryList = (categoryList: Category[], type: 'income' | 'expense') => {
    const rootCategories = categoryList.filter((c) => !c.parent_id)

    if (rootCategories.length === 0) {
      return (
        <div className="text-center py-8 text-muted-foreground">
          No {type} categories yet
        </div>
      )
    }

    return (
      <div className="space-y-2">
        {rootCategories.map(renderCategory)}
      </div>
    )
  }

  return (
    <AuthenticatedLayout title="Categories">
      <Main>
        <div className="mb-4 flex items-center justify-between">
          <div>
            <h1 className="text-2xl font-bold tracking-tight">Categories</h1>
            <p className="text-muted-foreground">
              Organize your transactions with categories
            </p>
          </div>
          <Button onClick={() => setShowForm(true)}>
            <Plus className="mr-2 h-4 w-4" />
            New Category
          </Button>
        </div>

        {/* Filters */}
        <div className="flex gap-2 flex-wrap mb-6">
          <Button
            variant={filterType === 'all' ? 'default' : 'outline'}
            size="sm"
            onClick={() => setFilterType('all')}
          >
            All
          </Button>
          <Button
            variant={filterType === 'income' ? 'default' : 'outline'}
            size="sm"
            onClick={() => setFilterType('income')}
            className={filterType === 'income' ? '' : 'text-green-600'}
          >
            Income
          </Button>
          <Button
            variant={filterType === 'expense' ? 'default' : 'outline'}
            size="sm"
            onClick={() => setFilterType('expense')}
            className={filterType === 'expense' ? '' : 'text-red-600'}
          >
            Expense
          </Button>
        </div>

        {categories.length === 0 ? (
          <div className="flex flex-col items-center justify-center py-16 text-center">
            <Tags className="h-16 w-16 text-muted-foreground mb-4" />
            <h3 className="text-xl font-semibold mb-2">No categories yet</h3>
            <p className="text-muted-foreground mb-4">
              Create categories to organize your transactions
            </p>
            <Button onClick={() => setShowForm(true)}>
              <Plus className="mr-2 h-4 w-4" />
              Create Category
            </Button>
          </div>
        ) : (
          <div className="grid grid-cols-1 gap-6 lg:grid-cols-2">
            {/* Income Categories */}
            {(filterType === 'all' || filterType === 'income') && (
              <Card>
                <CardHeader>
                  <CardTitle className="flex items-center gap-2 text-green-600">
                    <div className="w-3 h-3 rounded-full bg-green-600" />
                    Income Categories
                  </CardTitle>
                </CardHeader>
                <CardContent>
                  {renderCategoryList(incomeCategories, 'income')}
                </CardContent>
              </Card>
            )}

            {/* Expense Categories */}
            {(filterType === 'all' || filterType === 'expense') && (
              <Card>
                <CardHeader>
                  <CardTitle className="flex items-center gap-2 text-red-600">
                    <div className="w-3 h-3 rounded-full bg-red-600" />
                    Expense Categories
                  </CardTitle>
                </CardHeader>
                <CardContent>
                  {renderCategoryList(expenseCategories, 'expense')}
                </CardContent>
              </Card>
            )}
          </div>
        )}

        {/* Category Form */}
        <CategoryForm
          open={showForm}
          onOpenChange={handleFormClose}
          category={selectedCategory}
          parentCategories={parentCategories}
          onSuccess={handleSuccess}
        />

        {/* Delete Confirmation */}
        <AlertDialog open={showDeleteDialog} onOpenChange={setShowDeleteDialog}>
          <AlertDialogContent>
            <AlertDialogHeader>
              <AlertDialogTitle>Delete Category</AlertDialogTitle>
              <AlertDialogDescription>
                Are you sure you want to delete "{selectedCategory?.name}"?
                Transactions using this category will become uncategorized.
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
