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
    if (confirm('Are you sure you want to delete this product?')) {
      router.delete(route('dashboard.ecommerce.products.destroy', product.slug), {
        onSuccess: () => {
          toast({
            title: "Product deleted!",
            description: `"${product.name}" has been deleted successfully.`,
          })
        },
        onError: () => {
          toast({
            variant: "destructive",
            title: "Error deleting product",
            description: "Something went wrong. Please try again.",
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
        return <Badge variant="outline" className="text-green-600 border-green-600">Active</Badge>
      case "draft":
        return <Badge variant="secondary">Draft</Badge>
      case "archived":
        return <Badge variant="outline">Archived</Badge>
      default:
        return <Badge>{status}</Badge>
    }
  }

  const getStockBadge = (product: Product) => {
    if (product.is_out_of_stock) {
      return <Badge variant="destructive">Out of Stock</Badge>
    }
    if (product.is_low_stock) {
      return <Badge variant="outline" className="text-orange-600 border-orange-600">Low Stock</Badge>
    }
    return <Badge variant="outline" className="text-green-600 border-green-600">In Stock</Badge>
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
            <span className="sr-only">Image</span>
          </TableHead>
          <TableHead>Name</TableHead>
          <TableHead>SKU</TableHead>
          <TableHead>Price</TableHead>
          <TableHead>Stock</TableHead>
          <TableHead>Category</TableHead>
          <TableHead>Status</TableHead>
          <TableHead className="hidden md:table-cell">
            Created
          </TableHead>
          <TableHead>
            <span className="sr-only">Actions</span>
          </TableHead>
        </TableRow>
      </TableHeader>
      <TableBody>
        {!products.data || products.data.length === 0 ? (
          <TableRow>
            <TableCell colSpan={9} className="h-24 text-center">
              No products found.
            </TableCell>
          </TableRow>
        ) : (
          products.data.map((product) => (
          <TableRow key={product.id}>
            <TableCell className="hidden sm:table-cell">
              <img
                alt="Product image"
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
                    Featured
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
                <span className="text-sm">{product.stock_quantity} units</span>
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
                    <span className="sr-only">Toggle menu</span>
                  </Button>
                </DropdownMenuTrigger>
                <DropdownMenuContent align="end">
                  <DropdownMenuLabel>Actions</DropdownMenuLabel>
                  <DropdownMenuSeparator />
                  <DropdownMenuItem
                    onClick={() => router.get(route('dashboard.ecommerce.products.show', product.slug))}
                  >
                    <Eye className="mr-2 h-4 w-4" />
                    View
                  </DropdownMenuItem>
                  <DropdownMenuItem
                    onClick={() => router.get(route('dashboard.ecommerce.products.edit', product.slug))}
                  >
                    <Edit className="mr-2 h-4 w-4" />
                    Edit
                  </DropdownMenuItem>
                  <DropdownMenuSeparator />
                  <DropdownMenuItem
                    className="text-red-600"
                    onClick={() => handleDelete(product)}
                  >
                    <Trash2 className="mr-2 h-4 w-4" />
                    Delete
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
      <AuthenticatedLayout title="Products">
        <Main>
          <div className="grid flex-1 items-start gap-4 md:gap-8">
            <Tabs defaultValue={filters.status || "all"} onValueChange={handleTabChange}>
              <div className="flex items-center">
                <TabsList>
                  <TabsTrigger value="all">All</TabsTrigger>
                  <TabsTrigger value="active">Active</TabsTrigger>
                  <TabsTrigger value="draft">Draft</TabsTrigger>
                  <TabsTrigger value="archived" className="hidden sm:flex">
                    Archived
                  </TabsTrigger>
                </TabsList>
                <div className="ml-auto flex items-center gap-2">
                  <div className="relative">
                    <Input
                      placeholder="Search products..."
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
                          Filter
                        </span>
                      </Button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent align="end" className="w-56">
                      <DropdownMenuLabel>Filter by</DropdownMenuLabel>
                      <DropdownMenuSeparator />

                      <DropdownMenuCheckboxItem
                        checked={filters.featured === true}
                        onCheckedChange={(checked) =>
                          handleFilterChange({ featured: checked ? true : undefined })
                        }
                      >
                        Featured Only
                      </DropdownMenuCheckboxItem>

                      <DropdownMenuSeparator />
                      <DropdownMenuLabel className="text-xs">Category</DropdownMenuLabel>

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
                            All Categories
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
                          No categories
                        </div>
                      )}
                    </DropdownMenuContent>
                  </DropdownMenu>
                  <Button size="sm" variant="outline" className="h-7 gap-1">
                    <File className="h-3.5 w-3.5" />
                    <span className="sr-only sm:not-sr-only sm:whitespace-nowrap">
                      Export
                    </span>
                  </Button>
                  <Button
                    size="sm"
                    className="h-7 gap-1"
                    onClick={() => router.get(route('dashboard.ecommerce.products.create'))}
                  >
                    <PlusCircle className="h-3.5 w-3.5" />
                    <span className="sr-only sm:not-sr-only sm:whitespace-nowrap">
                      Add Product
                    </span>
                  </Button>
                </div>
              </div>

              {hasActiveFilters() && (
                <div className="flex items-center gap-2 pt-2">
                  <span className="text-sm text-muted-foreground">Active filters:</span>
                  {filters.featured && (
                    <Badge variant="secondary" className="gap-1">
                      Featured
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
                    Clear all
                  </Button>
                </div>
              )}

              <TabsContent value="all">
                <Card>
                  <CardHeader>
                    <CardTitle>Products</CardTitle>
                    <CardDescription>
                      Manage your products and view their inventory status.
                    </CardDescription>
                  </CardHeader>
                  <CardContent>
                    {renderProductTable()}
                  </CardContent>
                  <CardFooter className="flex flex-col sm:flex-row items-center justify-between gap-4">
                    <div className="text-xs text-muted-foreground">
                      {products?.current_page && products?.per_page && products?.total ? (
                        <>
                          Showing <strong>{((products.current_page - 1) * products.per_page) + 1}-{Math.min(products.current_page * products.per_page, products.total)}</strong> of <strong>{products.total}</strong>{" "}
                          products
                        </>
                      ) : (
                        <>Showing <strong>0</strong> products</>
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
                    <CardTitle>Products</CardTitle>
                    <CardDescription>
                      Manage your products and view their inventory status.
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
                    <CardTitle>Products</CardTitle>
                    <CardDescription>
                      Manage your products and view their inventory status.
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
                    <CardTitle>Products</CardTitle>
                    <CardDescription>
                      Manage your products and view their inventory status.
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
