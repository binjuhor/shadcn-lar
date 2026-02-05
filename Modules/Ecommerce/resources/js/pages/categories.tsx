import {AuthenticatedLayout} from "@/layouts"
import {
  File,
  ListFilter,
  MoreHorizontal,
  PlusCircle,
  Edit,
  Trash2,
} from "lucide-react"

import {Badge} from "@/components/ui/badge"
import {Button} from "@/components/ui/button"
import {
  Card,
  CardContent,
  CardDescription,
  CardFooter,
  CardHeader,
  CardTitle,
} from "@/components/ui/card"
import {
  DropdownMenu,
  DropdownMenuCheckboxItem,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuLabel,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu"
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from "@/components/ui/table"
import {
  Tabs,
  TabsContent,
  TabsList,
  TabsTrigger,
} from "@/components/ui/tabs"
import { Input } from "@/components/ui/input"
import { Main } from "@/components/layout"
import { useState } from "react"
import { ProductCategory } from "@/types/ecommerce"
import { router } from "@inertiajs/react"
import { PageProps } from "@/types"
import { useToast } from "@/hooks/use-toast"
import {
  Pagination,
  PaginationContent,
  PaginationItem,
  PaginationLink,
  PaginationNext,
  PaginationPrevious,
} from "@/components/ui/pagination"
import { useTranslation } from "react-i18next"

interface CategoryFilters {
  search?: string
  is_active?: boolean
}

interface CategoriesPageProps extends PageProps {
  categories?: {
    data: ProductCategory[]
    current_page: number
    last_page: number
    per_page: number
    total: number
  }
  filters?: CategoryFilters
}

export default function Categories({
  categories = { data: [], current_page: 1, last_page: 1, per_page: 15, total: 0 },
  filters: initialFilters = {},
}: CategoriesPageProps) {
  const { t } = useTranslation()
  const [filters, setFilters] = useState<CategoryFilters>(initialFilters)
  const [searchTerm, setSearchTerm] = useState(initialFilters?.search || "")
  const { toast } = useToast()

  const handleSearch = (value: string) => {
    setSearchTerm(value)
    router.get(route('dashboard.ecommerce.product-categories.index'), { ...filters, search: value }, {
      preserveState: true,
      replace: true,
    })
  }

  const handleFilterChange = (newFilters: Partial<CategoryFilters>) => {
    const updatedFilters = { ...filters, ...newFilters }
    setFilters(updatedFilters)
    router.get(route('dashboard.ecommerce.product-categories.index'), updatedFilters, {
      preserveState: true,
      replace: true,
    })
  }

  const handleDelete = (category: ProductCategory) => {
    if (confirm(t('page.ecommerce.categories.delete_confirm'))) {
      router.delete(route('dashboard.ecommerce.product-categories.destroy', category.slug), {
        onSuccess: () => {
          toast({
            title: t('page.ecommerce.categories.toast.delete_success'),
            description: t('page.ecommerce.categories.toast.delete_success_description', { name: category.name }),
          })
        },
        onError: () => {
          toast({
            variant: "destructive",
            title: t('common.messages.error'),
            description: t('common.messages.error_try_again'),
          })
        }
      })
    }
  }

  const handlePageChange = (page: number) => {
    router.get(route('dashboard.ecommerce.product-categories.index'), { ...filters, page }, {
      preserveState: true,
      replace: true,
    })
  }

  const generatePageNumbers = () => {
    const pages = []
    const delta = 2
    const rangeStart = Math.max(2, categories.current_page - delta)
    const rangeEnd = Math.min(categories.last_page - 1, categories.current_page + delta)

    if (categories.last_page > 1) {
      pages.push(1)
    }

    if (rangeStart > 2) {
      pages.push('...')
    }

    for (let i = rangeStart; i <= rangeEnd; i++) {
      if (i !== 1 && i !== categories.last_page) {
        pages.push(i)
      }
    }

    if (rangeEnd < categories.last_page - 1) {
      pages.push('...')
    }

    if (categories.last_page > 1 && categories.last_page !== 1) {
      pages.push(categories.last_page)
    }

    return pages
  }

  const getStatusBadge = (isActive: boolean) => {
    return isActive ? (
      <Badge variant="outline" className="text-green-600 border-green-600">{t('common.statuses.active')}</Badge>
    ) : (
      <Badge variant="secondary">{t('common.statuses.inactive')}</Badge>
    )
  }

  const formatDate = (dateString: string) => {
    return new Date(dateString).toLocaleDateString("en-US", {
      year: "numeric",
      month: "short",
      day: "numeric",
    })
  }

  const hasActiveFilters = () => {
    return filters.is_active !== undefined
  }

  const clearAllFilters = () => {
    handleFilterChange({
      is_active: undefined,
    })
  }

  const renderCategoryTable = () => (
    <Table>
      <TableHeader>
        <TableRow>
          <TableHead>{t('common.fields.name')}</TableHead>
          <TableHead>{t('common.fields.slug')}</TableHead>
          <TableHead>{t('common.fields.description')}</TableHead>
          <TableHead>{t('page.ecommerce.categories.table.parent')}</TableHead>
          <TableHead>{t('page.ecommerce.categories.table.products')}</TableHead>
          <TableHead>{t('common.fields.status')}</TableHead>
          <TableHead className="hidden md:table-cell">
            {t('common.fields.created')}
          </TableHead>
          <TableHead>
            <span className="sr-only">{t('common.actions.actions')}</span>
          </TableHead>
        </TableRow>
      </TableHeader>
      <TableBody>
        {!categories.data || categories.data.length === 0 ? (
          <TableRow>
            <TableCell colSpan={8} className="h-24 text-center">
              {t('page.ecommerce.categories.empty')}
            </TableCell>
          </TableRow>
        ) : (
          categories.data.map((category) => (
          <TableRow key={category.id}>
            <TableCell className="font-medium">
              <div className="flex items-center gap-2">
                {category.color && (
                  <div
                    className="w-4 h-4 rounded-full border"
                    style={{ backgroundColor: category.color }}
                  />
                )}
                <span>{category.name}</span>
              </div>
            </TableCell>
            <TableCell>
              <span className="font-mono text-sm">{category.slug}</span>
            </TableCell>
            <TableCell>
              <span className="text-sm text-muted-foreground line-clamp-2">
                {category.description || t('common.fields.na')}
              </span>
            </TableCell>
            <TableCell>
              {category.parent?.name ? (
                <Badge variant="outline">
                  {category.parent?.name}
                </Badge>
              ) : (
                <span className="text-sm text-muted-foreground">-</span>
              )}
            </TableCell>
            <TableCell>
              <span className="text-sm">{category.products_count || 0}</span>
            </TableCell>
            <TableCell>
              {getStatusBadge(category.is_active)}
            </TableCell>
            <TableCell className="hidden md:table-cell">
              {formatDate(category.created_at)}
            </TableCell>
            <TableCell>
              <DropdownMenu>
                <DropdownMenuTrigger asChild>
                  <Button
                    aria-haspopup="true"
                    size="icon"
                    variant="ghost"
                  >
                    <MoreHorizontal className="h-4 w-4" />
                    <span className="sr-only">{t('common.actions.toggle_menu')}</span>
                  </Button>
                </DropdownMenuTrigger>
                <DropdownMenuContent align="end">
                  <DropdownMenuLabel>{t('common.actions.actions')}</DropdownMenuLabel>
                  <DropdownMenuSeparator />
                  <DropdownMenuItem
                    onClick={() => router.get(route('dashboard.ecommerce.product-categories.edit', category.slug))}
                  >
                    <Edit className="mr-2 h-4 w-4" />
                    {t('common.actions.edit')}
                  </DropdownMenuItem>
                  <DropdownMenuSeparator />
                  <DropdownMenuItem
                    className="text-red-600"
                    onClick={() => handleDelete(category)}
                  >
                    <Trash2 className="mr-2 h-4 w-4" />
                    {t('common.actions.delete')}
                  </DropdownMenuItem>
                </DropdownMenuContent>
              </DropdownMenu>
            </TableCell>
          </TableRow>
        ))
        )}
      </TableBody>
    </Table>
  )

  return (
    <>
      <AuthenticatedLayout title={t('page.ecommerce.categories.title')}>
        <Main>
          <div className="grid flex-1 items-start gap-4 md:gap-8">
            <Tabs defaultValue="all">
              <div className="flex items-center">
                <TabsList>
                  <TabsTrigger value="all">{t('common.filters.all')}</TabsTrigger>
                </TabsList>
                <div className="ml-auto flex items-center gap-2">
                  <div className="relative">
                    <Input
                      placeholder={t('page.ecommerce.categories.search_placeholder')}
                      value={searchTerm}
                      onChange={(e) => handleSearch(e.target.value)}
                      className="w-64"
                    />
                  </div>
                  <DropdownMenu>
                    <DropdownMenuTrigger asChild>
                      <Button variant="outline" size="sm" className="h-7 gap-1">
                        <ListFilter className="h-3.5 w-3.5" />
                        <span className="sr-only sm:not-sr-only sm:whitespace-nowrap">
                          {t('common.actions.filter')}
                        </span>
                      </Button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent align="end" className="w-56">
                      <DropdownMenuLabel>{t('common.filters.filter_by')}</DropdownMenuLabel>
                      <DropdownMenuSeparator />

                      <DropdownMenuCheckboxItem
                        checked={filters.is_active === true}
                        onCheckedChange={(checked) =>
                          handleFilterChange({ is_active: checked ? true : undefined })
                        }
                      >
                        {t('page.ecommerce.categories.filters.active_only')}
                      </DropdownMenuCheckboxItem>

                      <DropdownMenuCheckboxItem
                        checked={filters.is_active === false}
                        onCheckedChange={(checked) =>
                          handleFilterChange({ is_active: checked ? false : undefined })
                        }
                      >
                        {t('page.ecommerce.categories.filters.inactive_only')}
                      </DropdownMenuCheckboxItem>
                    </DropdownMenuContent>
                  </DropdownMenu>
                  <Button size="sm" variant="outline" className="h-7 gap-1">
                    <File className="h-3.5 w-3.5" />
                    <span className="sr-only sm:not-sr-only sm:whitespace-nowrap">
                      {t('common.actions.export')}
                    </span>
                  </Button>
                  <Button
                    size="sm"
                    className="h-7 gap-1"
                    onClick={() => router.get(route('dashboard.ecommerce.product-categories.create'))}
                  >
                    <PlusCircle className="h-3.5 w-3.5" />
                    <span className="sr-only sm:not-sr-only sm:whitespace-nowrap">
                      {t('page.ecommerce.categories.add_category')}
                    </span>
                  </Button>
                </div>
              </div>

              {hasActiveFilters() && (
                <div className="flex items-center gap-2 pt-2">
                  <span className="text-sm text-muted-foreground">{t('common.filters.active_filters')}:</span>
                  {filters.is_active === true && (
                    <Badge variant="secondary" className="gap-1">
                      {t('common.statuses.active')}
                      <button
                        onClick={() => handleFilterChange({ is_active: undefined })}
                        className="ml-1 hover:bg-secondary-foreground/20 rounded-full"
                      >
                        ×
                      </button>
                    </Badge>
                  )}
                  {filters.is_active === false && (
                    <Badge variant="secondary" className="gap-1">
                      {t('common.statuses.inactive')}
                      <button
                        onClick={() => handleFilterChange({ is_active: undefined })}
                        className="ml-1 hover:bg-secondary-foreground/20 rounded-full"
                      >
                        ×
                      </button>
                    </Badge>
                  )}
                  <Button
                    variant="ghost"
                    size="sm"
                    onClick={clearAllFilters}
                    className="h-6 text-xs"
                  >
                    {t('common.filters.clear_all')}
                  </Button>
                </div>
              )}

              <TabsContent value="all">
                <Card>
                  <CardHeader>
                    <CardTitle>{t('page.ecommerce.categories.title')}</CardTitle>
                    <CardDescription>
                      {t('page.ecommerce.categories.description')}
                    </CardDescription>
                  </CardHeader>
                  <CardContent>
                    {renderCategoryTable()}
                  </CardContent>
                  <CardFooter className="flex flex-col sm:flex-row items-center justify-between gap-4">
                    <div className="text-xs text-muted-foreground">
                      {categories?.current_page && categories?.per_page && categories?.total ? (
                        <>
                          {t('common.pagination.showing')} <strong>{((categories.current_page - 1) * categories.per_page) + 1}-{Math.min(categories.current_page * categories.per_page, categories.total)}</strong> {t('common.pagination.of')} <strong>{categories.total}</strong>{" "}
                          {t('page.ecommerce.categories.categories')}
                        </>
                      ) : (
                        <>{t('common.pagination.showing')} <strong>0</strong> {t('page.ecommerce.categories.categories')}</>
                      )}
                    </div>

                    {categories.last_page > 1 && (
                      <Pagination>
                        <PaginationContent>
                          <PaginationItem>
                            <PaginationPrevious
                              href="#"
                              onClick={(e) => {
                                e.preventDefault()
                                if (categories.current_page > 1) {
                                  handlePageChange(categories.current_page - 1)
                                }
                              }}
                              className={categories.current_page === 1 ? "pointer-events-none opacity-50" : ""}
                            />
                          </PaginationItem>

                          {generatePageNumbers().map((page, index) => (
                            <PaginationItem key={index}>
                              {page === '...' ? (
                                <span className="flex h-9 w-9 items-center justify-center text-sm">...</span>
                              ) : (
                                <PaginationLink
                                  href="#"
                                  onClick={(e) => {
                                    e.preventDefault()
                                    handlePageChange(page as number)
                                  }}
                                  isActive={page === categories.current_page}
                                >
                                  {page}
                                </PaginationLink>
                              )}
                            </PaginationItem>
                          ))}

                          <PaginationItem>
                            <PaginationNext
                              href="#"
                              onClick={(e) => {
                                e.preventDefault()
                                if (categories.current_page < categories.last_page) {
                                  handlePageChange(categories.current_page + 1)
                                }
                              }}
                              className={categories.current_page === categories.last_page ? "pointer-events-none opacity-50" : ""}
                            />
                          </PaginationItem>
                        </PaginationContent>
                      </Pagination>
                    )}
                  </CardFooter>
                </Card>
              </TabsContent>
            </Tabs>
          </div>
        </Main>
      </AuthenticatedLayout>
    </>
  )
}
