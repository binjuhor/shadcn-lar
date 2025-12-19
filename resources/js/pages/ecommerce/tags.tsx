import {AuthenticatedLayout} from "@/layouts"
import {
  File,
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
import { ProductTag } from "@/types/ecommerce"
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

interface TagFilters {
  search?: string
}

interface TagsPageProps extends PageProps {
  tags?: {
    data: ProductTag[]
    current_page: number
    last_page: number
    per_page: number
    total: number
  }
  filters?: TagFilters
}

export default function Tags({
  tags = { data: [], current_page: 1, last_page: 1, per_page: 15, total: 0 },
  filters: initialFilters = {},
}: TagsPageProps) {
  const [filters, setFilters] = useState<TagFilters>(initialFilters)
  const [searchTerm, setSearchTerm] = useState(initialFilters?.search || "")
  const { toast } = useToast()

  const handleSearch = (value: string) => {
    setSearchTerm(value)
    router.get(route('dashboard.ecommerce.product-tags.index'), { ...filters, search: value }, {
      preserveState: true,
      replace: true,
    })
  }

  const handleDelete = (tag: ProductTag) => {
    if (confirm('Are you sure you want to delete this tag?')) {
      router.delete(route('dashboard.ecommerce.product-tags.destroy', tag.slug), {
        onSuccess: () => {
          toast({
            title: "Tag deleted!",
            description: `"${tag.name}" has been deleted successfully.`,
          })
        },
        onError: () => {
          toast({
            variant: "destructive",
            title: "Error deleting tag",
            description: "Something went wrong. Please try again.",
          })
        }
      })
    }
  }

  const handlePageChange = (page: number) => {
    router.get(route('dashboard.ecommerce.product-tags.index'), { ...filters, page }, {
      preserveState: true,
      replace: true,
    })
  }

  const generatePageNumbers = () => {
    const pages = []
    const delta = 2
    const rangeStart = Math.max(2, tags.current_page - delta)
    const rangeEnd = Math.min(tags.last_page - 1, tags.current_page + delta)

    if (tags.last_page > 1) {
      pages.push(1)
    }

    if (rangeStart > 2) {
      pages.push('...')
    }

    for (let i = rangeStart; i <= rangeEnd; i++) {
      if (i !== 1 && i !== tags.last_page) {
        pages.push(i)
      }
    }

    if (rangeEnd < tags.last_page - 1) {
      pages.push('...')
    }

    if (tags.last_page > 1 && tags.last_page !== 1) {
      pages.push(tags.last_page)
    }

    return pages
  }

  const formatDate = (dateString?: string) => {
    if (!dateString) return '-'
    return new Date(dateString).toLocaleDateString("en-US", {
      year: "numeric",
      month: "short",
      day: "numeric",
    })
  }

  const renderTagTable = () => (
    <Table>
      <TableHeader>
        <TableRow>
          <TableHead>Name</TableHead>
          <TableHead>Slug</TableHead>
          <TableHead>Description</TableHead>
          <TableHead>Products</TableHead>
          <TableHead className="hidden md:table-cell">
            Created
          </TableHead>
          <TableHead>
            <span className="sr-only">Actions</span>
          </TableHead>
        </TableRow>
      </TableHeader>
      <TableBody>
        {!tags.data || tags.data.length === 0 ? (
          <TableRow>
            <TableCell colSpan={6} className="h-24 text-center">
              No tags found.
            </TableCell>
          </TableRow>
        ) : (
          tags.data.map((tag) => (
          <TableRow key={tag.id}>
            <TableCell className="font-medium">
              <div className="flex items-center gap-2">
                {tag.color && (
                  <div
                    className="w-4 h-4 rounded-full border"
                    style={{ backgroundColor: tag.color }}
                  />
                )}
                <span>{tag.name}</span>
              </div>
            </TableCell>
            <TableCell>
              <span className="font-mono text-sm">{tag.slug}</span>
            </TableCell>
            <TableCell>
              <span className="text-sm text-muted-foreground line-clamp-2">
                {tag.description || 'N/A'}
              </span>
            </TableCell>
            <TableCell>
              <span className="text-sm">{tag.products_count || 0}</span>
            </TableCell>
            <TableCell className="hidden md:table-cell">
              {formatDate(tag.created_at)}
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
                    onClick={() => router.get(route('dashboard.ecommerce.product-tags.edit', tag.slug))}
                  >
                    <Edit className="mr-2 h-4 w-4" />
                    Edit
                  </DropdownMenuItem>
                  <DropdownMenuSeparator />
                  <DropdownMenuItem
                    className="text-red-600"
                    onClick={() => handleDelete(tag)}
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
      <AuthenticatedLayout title="Product Tags">
        <Main>
          <div className="grid flex-1 items-start gap-4 md:gap-8">
            <Tabs defaultValue="all">
              <div className="flex items-center">
                <TabsList>
                  <TabsTrigger value="all">All</TabsTrigger>
                </TabsList>
                <div className="ml-auto flex items-center gap-2">
                  <div className="relative">
                    <Input
                      placeholder="Search tags..."
                      value={searchTerm}
                      onChange={(e) => handleSearch(e.target.value)}
                      className="w-64"
                    />
                  </div>
                  <Button size="sm" variant="outline" className="h-7 gap-1">
                    <File className="h-3.5 w-3.5" />
                    <span className="sr-only sm:not-sr-only sm:whitespace-nowrap">
                      Export
                    </span>
                  </Button>
                  <Button
                    size="sm"
                    className="h-7 gap-1"
                    onClick={() => router.get(route('dashboard.ecommerce.product-tags.create'))}
                  >
                    <PlusCircle className="h-3.5 w-3.5" />
                    <span className="sr-only sm:not-sr-only sm:whitespace-nowrap">
                      Add Tag
                    </span>
                  </Button>
                </div>
              </div>

              <TabsContent value="all">
                <Card>
                  <CardHeader>
                    <CardTitle>Product Tags</CardTitle>
                    <CardDescription>
                      Manage your product tags and organize your catalog.
                    </CardDescription>
                  </CardHeader>
                  <CardContent>
                    {renderTagTable()}
                  </CardContent>
                  <CardFooter className="flex flex-col sm:flex-row items-center justify-between gap-4">
                    <div className="text-xs text-muted-foreground">
                      {tags?.current_page && tags?.per_page && tags?.total ? (
                        <>
                          Showing <strong>{((tags.current_page - 1) * tags.per_page) + 1}-{Math.min(tags.current_page * tags.per_page, tags.total)}</strong> of <strong>{tags.total}</strong>{" "}
                          tags
                        </>
                      ) : (
                        <>Showing <strong>0</strong> tags</>
                      )}
                    </div>

                    {tags.last_page > 1 && (
                      <Pagination>
                        <PaginationContent>
                          <PaginationItem>
                            <PaginationPrevious
                              href="#"
                              onClick={(e) => {
                                e.preventDefault()
                                if (tags.current_page > 1) {
                                  handlePageChange(tags.current_page - 1)
                                }
                              }}
                              className={tags.current_page === 1 ? "pointer-events-none opacity-50" : ""}
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
                                  isActive={page === tags.current_page}
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
                                if (tags.current_page < tags.last_page) {
                                  handlePageChange(tags.current_page + 1)
                                }
                              }}
                              className={tags.current_page === tags.last_page ? "pointer-events-none opacity-50" : ""}
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
