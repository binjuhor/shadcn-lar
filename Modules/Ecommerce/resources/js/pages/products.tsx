import {AuthenticatedLayout} from "@/layouts"
import {
  File,
  ListFilter,
  MoreHorizontal,
  PlusCircle,
  Eye,
  Edit,
  Trash2,
} from "lucide-react"
import { useTranslation } from 'react-i18next'

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
import { Product, ProductCategory, ProductTag, ProductFilters } from "@/types/ecommerce"
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

interface ProductsPageProps extends PageProps {
  products?: {
    data: Product[]
    current_page: number
    last_page: number
    per_page: number
    total: number
  }
  filters?: ProductFilters
  categories?: ProductCategory[]
  tags?: ProductTag[]
}

export default function Products({
  products = { data: [], current_page: 1, last_page: 1, per_page: 15, total: 0 },
  filters: initialFilters = {},
  categories = [],
  tags = []
}: ProductsPageProps) {
  const { t } = useTranslation()
  const [filters, setFilters] = useState<ProductFilters>(initialFilters)
  const [searchTerm, setSearchTerm] = useState(initialFilters?.search || "")
  const { toast } = useToast()

  const handleSearch = (value: string) => {
    setSearchTerm(value)
    router.get(route('dashboard.ecommerce.products.index'), { ...filters, search: value }, {
      preserveState: true,
      replace: true,
    })
  }

  const handleFilterChange = (newFilters: Partial<ProductFilters>) => {
    const updatedFilters = { ...filters, ...newFilters }
    setFilters(updatedFilters)
    router.get(route('dashboard.ecommerce.products.index'), updatedFilters, {
      preserveState: true,
      replace: true,
    })
  }

  const handleTabChange = (status: string) => {
    const newStatus = status === 'all' ? undefined : status as 'draft' | 'active' | 'archived'
    handleFilterChange({ status: newStatus })
  }

  const handleDelete = (product: Product) => {
    if (confirm(t('page.ecommerce.products.toast.delete_confirm'))) {
      router.delete(route('dashboard.ecommerce.products.destroy', product.slug), {
        onSuccess: () => {
          toast({
            title: t('page.ecommerce.products.toast.delete_success'),
            description: t('page.ecommerce.products.toast.delete_success_description', { name: product.name }),
          })
        },
        onError: () => {
          toast({
            variant: "destructive",
            title: t('page.ecommerce.products.toast.delete_error'),
            description: t('common.messages.error_try_again'),
          })
        }
      })
    }
  }

  const handlePageChange = (page: number) => {
    router.get(route('dashboard.ecommerce.products.index'), { ...filters, page }, {
      preserveState: true,
      replace: true,
    })
  }

  const generatePageNumbers = () => {
    const pages = []
    const delta = 2
    const rangeStart = Math.max(2, products.current_page - delta)
    const rangeEnd = Math.min(products.last_page - 1, products.current_page + delta)

    if (products.last_page > 1) {
      pages.push(1)
    }

    if (rangeStart > 2) {
      pages.push('...')
    }

    for (let i = rangeStart; i <= rangeEnd; i++) {
      if (i !== 1 && i !== products.last_page) {
        pages.push(i)
      }
    }

    if (rangeEnd < products.last_page - 1) {
      pages.push('...')
    }

    if (products.last_page > 1 && products.last_page !== 1) {
      pages.push(products.last_page)
    }

    return pages
  }

  const getStatusBadge = (status: string) => {
    switch (status) {
      case "active":
        return <Badge variant="outline" className="text-green-600 border-green-600">{t('common.statuses.active')}</Badge>
      case "draft":
        return <Badge variant="secondary">{t('common.statuses.draft')}</Badge>
      case "archived":
        return <Badge variant="outline">{t('common.statuses.archived')}</Badge>
      default:
        return <Badge>{status}</Badge>
    }
  }

  const getStockBadge = (product: Product) => {
    if (product.is_out_of_stock) {
      return <Badge variant="destructive">{t('page.ecommerce.products.status.out_of_stock')}</Badge>
    }
    if (product.is_low_stock) {
      return <Badge variant="outline" className="text-orange-600 border-orange-600">{t('page.ecommerce.products.status.low_stock')}</Badge>
    }
    return <Badge variant="outline" className="text-green-600 border-green-600">{t('page.ecommerce.products.status.in_stock')}</Badge>
  }

  const formatPrice = (price: number) => {
    return new Intl.NumberFormat('en-US', {
      style: 'currency',
      currency: 'USD',
    }).format(price)
  }

  const formatDate = (dateString: string) => {
    return new Date(dateString).toLocaleDateString("en-US", {
      year: "numeric",
      month: "short",
      day: "numeric",
    })
  }

  const hasActiveFilters = () => {
    return filters.featured || filters.category_id
  }

  const clearAllFilters = () => {
    handleFilterChange({
      featured: undefined,
      category_id: undefined,
    })
  }

  const getActiveFilterLabel = (categoryId: number) => {
    return categories.find(c => c.id === categoryId)?.name
  }

  const renderProductTable = () => (
    <Table>
      <TableHeader>
        <TableRow>
          <TableHead className="hidden w-[100px] sm:table-cell">
            <span className="sr-only">{t('page.ecommerce.products.table.image')}</span>
          </TableHead>
          <TableHead>{t('page.ecommerce.products.table.name')}</TableHead>
          <TableHead>{t('page.ecommerce.products.table.sku')}</TableHead>
          <TableHead>{t('page.ecommerce.products.table.price')}</TableHead>
          <TableHead>{t('page.ecommerce.products.table.stock')}</TableHead>
          <TableHead>{t('page.ecommerce.products.table.category')}</TableHead>
          <TableHead>{t('page.ecommerce.products.table.status')}</TableHead>
          <TableHead className="hidden md:table-cell">
            {t('page.ecommerce.products.table.created')}
          </TableHead>
          <TableHead>
            <span className="sr-only">{t('common.actions.actions')}</span>
          </TableHead>
        </TableRow>
      </TableHeader>
      <TableBody>
        {!products.data || products.data.length === 0 ? (
          <TableRow>
            <TableCell colSpan={9} className="h-24 text-center">
              {t('page.ecommerce.products.table.no_products')}
            </TableCell>
          </TableRow>
        ) : (
          products.data.map((product) => (
          <TableRow key={product.id}>
            <TableCell className="hidden sm:table-cell">
              <img
                alt={t('page.ecommerce.products.table.product_image')}
                className="aspect-square rounded-md object-cover"
                height="64"
                src={product.featured_image_url || "/placeholder.svg"}
                width="64"
              />
            </TableCell>
            <TableCell className="font-medium">
              <div className="flex flex-col gap-1">
                <span>{product.name}</span>
                {product.is_featured && (
                  <Badge variant="secondary" className="w-fit text-xs">
                    {t('page.ecommerce.products.status.featured')}
                  </Badge>
                )}
              </div>
            </TableCell>
            <TableCell>
              <span className="font-mono text-sm">{product.sku || 'N/A'}</span>
            </TableCell>
            <TableCell>
              <div className="flex flex-col gap-1">
                {product.is_on_sale ? (
                  <>
                    <span className="font-medium">{formatPrice(product.sale_price!)}</span>
                    <span className="text-xs line-through text-muted-foreground">
                      {formatPrice(product.price)}
                    </span>
                    <Badge variant="destructive" className="w-fit text-xs">
                      -{product.discount_percentage}%
                    </Badge>
                  </>
                ) : (
                  <span className="font-medium">{formatPrice(product.price)}</span>
                )}
              </div>
            </TableCell>
            <TableCell>
              <div className="flex flex-col gap-1">
                <span className="text-sm">{t('page.ecommerce.products.table.units', { count: product.stock_quantity })}</span>
                {getStockBadge(product)}
              </div>
            </TableCell>
            <TableCell>
              {product.category?.name && (
                <Badge variant="outline">
                  {product.category?.name}
                </Badge>
              )}
            </TableCell>
            <TableCell>
              {getStatusBadge(product.status)}
            </TableCell>
            <TableCell className="hidden md:table-cell">
              {formatDate(product.created_at)}
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
                    onClick={() => router.get(route('dashboard.ecommerce.products.show', product.slug))}
                  >
                    <Eye className="mr-2 h-4 w-4" />
                    {t('common.actions.view')}
                  </DropdownMenuItem>
                  <DropdownMenuItem
                    onClick={() => router.get(route('dashboard.ecommerce.products.edit', product.slug))}
                  >
                    <Edit className="mr-2 h-4 w-4" />
                    {t('common.actions.edit')}
                  </DropdownMenuItem>
                  <DropdownMenuSeparator />
                  <DropdownMenuItem
                    className="text-red-600"
                    onClick={() => handleDelete(product)}
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
      <AuthenticatedLayout title={t('page.ecommerce.products.title')}>
        <Main>
          <div className="grid flex-1 items-start gap-4 md:gap-8">
            <Tabs defaultValue={filters.status || "all"} onValueChange={handleTabChange}>
              <div className="flex items-center">
                <TabsList>
                  <TabsTrigger value="all">{t('common.filters.all')}</TabsTrigger>
                  <TabsTrigger value="active">{t('common.statuses.active')}</TabsTrigger>
                  <TabsTrigger value="draft">{t('common.statuses.draft')}</TabsTrigger>
                  <TabsTrigger value="archived" className="hidden sm:flex">
                    {t('common.statuses.archived')}
                  </TabsTrigger>
                </TabsList>
                <div className="ml-auto flex items-center gap-2">
                  <div className="relative">
                    <Input
                      placeholder={t('page.ecommerce.products.search_placeholder')}
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
                        checked={filters.featured === true}
                        onCheckedChange={(checked) =>
                          handleFilterChange({ featured: checked ? true : undefined })
                        }
                      >
                        {t('page.ecommerce.products.filter.featured_only')}
                      </DropdownMenuCheckboxItem>

                      <DropdownMenuSeparator />
                      <DropdownMenuLabel className="text-xs">{t('page.ecommerce.products.filter.category')}</DropdownMenuLabel>

                      {categories.length > 0 ? (
                        <>
                          <DropdownMenuCheckboxItem
                            checked={!filters.category_id}
                            onCheckedChange={(checked) => {
                              if (checked) {
                                handleFilterChange({ category_id: undefined })
                              }
                            }}
                          >
                            {t('page.ecommerce.products.filter.all_categories')}
                          </DropdownMenuCheckboxItem>
                          {categories.map((category) => (
                            <DropdownMenuCheckboxItem
                              key={category.id}
                              checked={filters.category_id === category.id}
                              onCheckedChange={(checked) =>
                                handleFilterChange({ category_id: checked ? category.id : undefined })
                              }
                            >
                              {category.name}
                            </DropdownMenuCheckboxItem>
                          ))}
                        </>
                      ) : (
                        <div className="px-2 py-1.5 text-sm text-muted-foreground">
                          {t('page.ecommerce.products.filter.no_categories')}
                        </div>
                      )}
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
                    onClick={() => router.get(route('dashboard.ecommerce.products.create'))}
                  >
                    <PlusCircle className="h-3.5 w-3.5" />
                    <span className="sr-only sm:not-sr-only sm:whitespace-nowrap">
                      {t('page.ecommerce.products.actions.add_product')}
                    </span>
                  </Button>
                </div>
              </div>

              {hasActiveFilters() && (
                <div className="flex items-center gap-2 pt-2">
                  <span className="text-sm text-muted-foreground">{t('common.filters.active_filters')}:</span>
                  {filters.featured && (
                    <Badge variant="secondary" className="gap-1">
                      {t('page.ecommerce.products.status.featured')}
                      <button
                        onClick={() => handleFilterChange({ featured: undefined })}
                        className="ml-1 hover:bg-secondary-foreground/20 rounded-full"
                      >
                        ×
                      </button>
                    </Badge>
                  )}
                  {filters.category_id && (
                    <Badge variant="secondary" className="gap-1">
                      {getActiveFilterLabel(filters.category_id)}
                      <button
                        onClick={() => handleFilterChange({ category_id: undefined })}
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
                    <CardTitle>{t('page.ecommerce.products.title')}</CardTitle>
                    <CardDescription>
                      {t('page.ecommerce.products.description')}
                    </CardDescription>
                  </CardHeader>
                  <CardContent>
                    {renderProductTable()}
                  </CardContent>
                  <CardFooter className="flex flex-col sm:flex-row items-center justify-between gap-4">
                    <div className="text-xs text-muted-foreground">
                      {products?.current_page && products?.per_page && products?.total ? (
                        t('page.ecommerce.products.pagination.showing', {
                          from: ((products.current_page - 1) * products.per_page) + 1,
                          to: Math.min(products.current_page * products.per_page, products.total),
                          total: products.total
                        })
                      ) : (
                        t('page.ecommerce.products.pagination.showing_zero')
                      )}
                    </div>

                    {products.last_page > 1 && (
                      <Pagination>
                        <PaginationContent>
                          <PaginationItem>
                            <PaginationPrevious
                              href="#"
                              onClick={(e) => {
                                e.preventDefault()
                                if (products.current_page > 1) {
                                  handlePageChange(products.current_page - 1)
                                }
                              }}
                              className={products.current_page === 1 ? "pointer-events-none opacity-50" : ""}
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
                                  isActive={page === products.current_page}
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
                                if (products.current_page < products.last_page) {
                                  handlePageChange(products.current_page + 1)
                                }
                              }}
                              className={products.current_page === products.last_page ? "pointer-events-none opacity-50" : ""}
                            />
                          </PaginationItem>
                        </PaginationContent>
                      </Pagination>
                    )}
                  </CardFooter>
                </Card>
              </TabsContent>

              <TabsContent value="active">
                <Card>
                  <CardHeader>
                    <CardTitle>{t('page.ecommerce.products.title')}</CardTitle>
                    <CardDescription>
                      {t('page.ecommerce.products.description')}
                    </CardDescription>
                  </CardHeader>
                  <CardContent>
                    {renderProductTable()}
                  </CardContent>
                </Card>
              </TabsContent>

              <TabsContent value="draft">
                <Card>
                  <CardHeader>
                    <CardTitle>{t('page.ecommerce.products.title')}</CardTitle>
                    <CardDescription>
                      {t('page.ecommerce.products.description')}
                    </CardDescription>
                  </CardHeader>
                  <CardContent>
                    {renderProductTable()}
                  </CardContent>
                </Card>
              </TabsContent>

              <TabsContent value="archived">
                <Card>
                  <CardHeader>
                    <CardTitle>{t('page.ecommerce.products.title')}</CardTitle>
                    <CardDescription>
                      {t('page.ecommerce.products.description')}
                    </CardDescription>
                  </CardHeader>
                  <CardContent>
                    {renderProductTable()}
                  </CardContent>
                </Card>
              </TabsContent>
            </Tabs>
          </div>
        </Main>
      </AuthenticatedLayout>
    </>
  )
}
