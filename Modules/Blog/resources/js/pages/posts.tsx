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
import { BlogPost, BlogCategory, BlogTag, BlogFilters } from "@/types/blog"
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

interface BlogPostsPageProps extends PageProps {
  posts: {
    data: BlogPost[]
    current_page: number
    last_page: number
    per_page: number
    total: number
  }
  filters: BlogFilters
  categories: BlogCategory[]
  tags: BlogTag[]
}

export default function BlogPosts({ posts, filters: initialFilters, categories, tags }: BlogPostsPageProps) {
  const [filters, setFilters] = useState<BlogFilters>(initialFilters)
  const [searchTerm, setSearchTerm] = useState(initialFilters.search || "")
  const { toast } = useToast()

  const handleSearch = (value: string) => {
    setSearchTerm(value)
    router.get(route('dashboard.posts.index'), { ...filters, search: value }, {
      preserveState: true,
      replace: true,
    })
  }

  const handleFilterChange = (newFilters: Partial<BlogFilters>) => {
    const updatedFilters = { ...filters, ...newFilters }
    setFilters(updatedFilters)
    router.get(route('dashboard.posts.index'), updatedFilters, {
      preserveState: true,
      replace: true,
    })
  }

  const handleTabChange = (status: string) => {
    const newStatus = status === 'all' ? undefined : status as 'draft' | 'published' | 'archived'
    handleFilterChange({ status: newStatus })
  }

  const handleDelete = (post: BlogPost) => {
    if (confirm('Are you sure you want to delete this post?')) {
      router.delete(route('dashboard.posts.destroy', post.slug), {
        onSuccess: () => {
          toast({
            title: "Post deleted!",
            description: `"${post.title}" has been deleted successfully.`,
          })
        },
        onError: () => {
          toast({
            variant: "destructive",
            title: "Error deleting post",
            description: "Something went wrong. Please try again.",
          })
        }
      })
    }
  }

  const handlePageChange = (page: number) => {
    router.get(route('dashboard.posts.index'), { ...filters, page }, {
      preserveState: true,
      replace: true,
    })
  }

  const generatePageNumbers = () => {
    const pages = []
    const delta = 2 // Number of pages to show on each side of current page
    const rangeStart = Math.max(2, posts.current_page - delta)
    const rangeEnd = Math.min(posts.last_page - 1, posts.current_page + delta)

    // Always show first page
    if (posts.last_page > 1) {
      pages.push(1)
    }

    // Add ellipsis if there's a gap
    if (rangeStart > 2) {
      pages.push('...')
    }

    // Add pages around current page
    for (let i = rangeStart; i <= rangeEnd; i++) {
      if (i !== 1 && i !== posts.last_page) {
        pages.push(i)
      }
    }

    // Add ellipsis if there's a gap
    if (rangeEnd < posts.last_page - 1) {
      pages.push('...')
    }

    // Always show last page
    if (posts.last_page > 1 && posts.last_page !== 1) {
      pages.push(posts.last_page)
    }

    return pages
  }

  const getStatusBadge = (status: string) => {
    switch (status) {
      case "published":
        return <Badge variant="outline" className="text-green-600 border-green-600">Published</Badge>
      case "draft":
        return <Badge variant="secondary">Draft</Badge>
      case "archived":
        return <Badge variant="outline">Archived</Badge>
      default:
        return <Badge>{status}</Badge>
    }
  }

  const formatDate = (dateString: string) => {
    return new Date(dateString).toLocaleDateString("en-US", {
      year: "numeric",
      month: "short",
      day: "numeric",
    })
  }

  const hasActiveFilters = () => {
    return filters.featured || filters.category || filters.tag
  }

  const clearAllFilters = () => {
    handleFilterChange({
      featured: undefined,
      category: undefined,
      tag: undefined
    })
  }

  const getActiveFilterLabel = (type: 'category' | 'tag', slug: string) => {
    if (type === 'category') {
      return categories.find(c => c.slug === slug)?.name
    }
    return tags.find(t => t.slug === slug)?.name
  }

  return (
    <>
      <AuthenticatedLayout title="Blog Posts">
        <Main>
          <div className="grid flex-1 items-start gap-4 md:gap-8">
            <Tabs defaultValue={filters.status || "all"} onValueChange={handleTabChange}>
              <div className="flex items-center">
                <TabsList>
                  <TabsTrigger value="all">All</TabsTrigger>
                  <TabsTrigger value="published">Published</TabsTrigger>
                  <TabsTrigger value="draft">Draft</TabsTrigger>
                  <TabsTrigger value="archived" className="hidden sm:flex">
                    Archived
                  </TabsTrigger>
                </TabsList>
                <div className="ml-auto flex items-center gap-2">
                  <div className="relative">
                    <Input
                      placeholder="Search posts..."
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
                            checked={!filters.category}
                            onCheckedChange={(checked) => {
                              if (checked) {
                                handleFilterChange({ category: undefined })
                              }
                            }}
                          >
                            All Categories
                          </DropdownMenuCheckboxItem>
                          {categories.map((category) => (
                            <DropdownMenuCheckboxItem
                              key={category.id}
                              checked={filters.category === category.slug}
                              onCheckedChange={(checked) =>
                                handleFilterChange({ category: checked ? category.slug : undefined })
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

                      <DropdownMenuSeparator />
                      <DropdownMenuLabel className="text-xs">Tag</DropdownMenuLabel>

                      {tags.length > 0 ? (
                        <>
                          <DropdownMenuCheckboxItem
                            checked={!filters.tag}
                            onCheckedChange={(checked) => {
                              if (checked) {
                                handleFilterChange({ tag: undefined })
                              }
                            }}
                          >
                            All Tags
                          </DropdownMenuCheckboxItem>
                          {tags.slice(0, 10).map((tag) => (
                            <DropdownMenuCheckboxItem
                              key={tag.id}
                              checked={filters.tag === tag.slug}
                              onCheckedChange={(checked) =>
                                handleFilterChange({ tag: checked ? tag.slug : undefined })
                              }
                            >
                              {tag.name}
                            </DropdownMenuCheckboxItem>
                          ))}
                          {tags.length > 10 && (
                            <div className="px-2 py-1.5 text-xs text-muted-foreground">
                              Showing 10 of {tags.length} tags
                            </div>
                          )}
                        </>
                      ) : (
                        <div className="px-2 py-1.5 text-sm text-muted-foreground">
                          No tags
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
                    onClick={() => router.get(route('dashboard.posts.create'))}
                  >
                    <PlusCircle className="h-3.5 w-3.5" />
                    <span className="sr-only sm:not-sr-only sm:whitespace-nowrap">
                      Add Post
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
                  {filters.category && (
                    <Badge variant="secondary" className="gap-1">
                      {getActiveFilterLabel('category', filters.category)}
                      <button
                        onClick={() => handleFilterChange({ category: undefined })}
                        className="ml-1 hover:bg-secondary-foreground/20 rounded-full"
                      >
                        ×
                      </button>
                    </Badge>
                  )}
                  {filters.tag && (
                    <Badge variant="secondary" className="gap-1">
                      {getActiveFilterLabel('tag', filters.tag)}
                      <button
                        onClick={() => handleFilterChange({ tag: undefined })}
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
                    <CardTitle>Blog Posts</CardTitle>
                    <CardDescription>
                      Manage your blog posts and view their performance.
                    </CardDescription>
                  </CardHeader>
                  <CardContent>
                    <Table>
                      <TableHeader>
                        <TableRow>
                          <TableHead className="hidden w-[100px] sm:table-cell">
                            <span className="sr-only">Thumbnail</span>
                          </TableHead>
                          <TableHead>Title</TableHead>
                          <TableHead>Category</TableHead>
                          <TableHead>Status</TableHead>
                          <TableHead className="hidden md:table-cell">
                            Author
                          </TableHead>
                          <TableHead className="hidden md:table-cell">
                            Published
                          </TableHead>
                          <TableHead>
                            <span className="sr-only">Actions</span>
                          </TableHead>
                        </TableRow>
                      </TableHeader>
                      <TableBody>
                        {posts.data.map((post) => (
                          <TableRow key={post.id}>
                            <TableCell className="hidden sm:table-cell">
                              <img
                                alt="Post thumbnail"
                                className="aspect-square rounded-md object-cover"
                                height="64"
                                src={post.featured_image_url || "/placeholder.svg"}
                                width="64"
                              />
                            </TableCell>
                            <TableCell className="font-medium">
                              <div className="flex flex-col gap-1">
                                <span>{post.title}</span>
                                {post.is_featured && (
                                  <Badge variant="secondary" className="w-fit text-xs">
                                    Featured
                                  </Badge>
                                )}
                              </div>
                            </TableCell>
                            <TableCell>
                              {post.category?.name && (
                                <Badge variant="outline">
                                  {post.category?.name }
                                </Badge>
                              )}
                            </TableCell>
                            <TableCell>
                              {getStatusBadge(post.status)}
                            </TableCell>
                            <TableCell className="hidden md:table-cell">
                              <div className="flex items-center gap-2">
                                <img
                                  alt="Author avatar"
                                  className="rounded-full"
                                  height="24"
                                  src="/placeholder.svg"
                                  width="24"
                                />
                                <span className="text-sm">{post.user.name}</span>
                              </div>
                            </TableCell>
                            <TableCell className="hidden md:table-cell">
                              {post.published_at ? formatDate(post.published_at) : "Not published"}
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
                                    onClick={() => router.get(route('dashboard.posts.show', post.slug))}
                                  >
                                    <Eye className="mr-2 h-4 w-4" />
                                    View
                                  </DropdownMenuItem>
                                  <DropdownMenuItem
                                    onClick={() => router.get(route('dashboard.posts.edit', post.slug))}
                                  >
                                    <Edit className="mr-2 h-4 w-4" />
                                    Edit
                                  </DropdownMenuItem>
                                  <DropdownMenuSeparator />
                                  <DropdownMenuItem
                                    className="text-red-600"
                                    onClick={() => handleDelete(post)}
                                  >
                                    <Trash2 className="mr-2 h-4 w-4" />
                                    Delete
                                  </DropdownMenuItem>
                                </DropdownMenuContent>
                              </DropdownMenu>
                            </TableCell>
                          </TableRow>
                        ))}
                      </TableBody>
                    </Table>
                  </CardContent>
                  <CardFooter className="flex flex-col sm:flex-row items-center justify-between gap-4">
                    <div className="text-xs text-muted-foreground">
                      {posts?.current_page && posts?.per_page && posts?.total ? (
                        <>
                          Showing <strong>{((posts.current_page - 1) * posts.per_page) + 1}-{Math.min(posts.current_page * posts.per_page, posts.total)}</strong> of <strong>{posts.total}</strong>{" "}
                          posts
                        </>
                      ) : (
                        <>Showing <strong>0</strong> posts</>
                      )}
                    </div>

                    {posts.last_page > 1 && (
                      <Pagination>
                        <PaginationContent>
                          <PaginationItem>
                            <PaginationPrevious
                              href="#"
                              onClick={(e) => {
                                e.preventDefault()
                                if (posts.current_page > 1) {
                                  handlePageChange(posts.current_page - 1)
                                }
                              }}
                              className={posts.current_page === 1 ? "pointer-events-none opacity-50" : ""}
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
                                  isActive={page === posts.current_page}
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
                                if (posts.current_page < posts.last_page) {
                                  handlePageChange(posts.current_page + 1)
                                }
                              }}
                              className={posts.current_page === posts.last_page ? "pointer-events-none opacity-50" : ""}
                            />
                          </PaginationItem>
                        </PaginationContent>
                      </Pagination>
                    )}
                  </CardFooter>
                </Card>
              </TabsContent>

              <TabsContent value="published">
                <Card>
                  <CardHeader>
                    <CardTitle>Blog Posts</CardTitle>
                    <CardDescription>
                      Manage your blog posts and view their performance.
                    </CardDescription>
                  </CardHeader>
                  <CardContent>
                    <Table>
                      <TableHeader>
                        <TableRow>
                          <TableHead className="hidden w-[100px] sm:table-cell">
                            <span className="sr-only">Thumbnail</span>
                          </TableHead>
                          <TableHead>Title</TableHead>
                          <TableHead>Category</TableHead>
                          <TableHead>Status</TableHead>
                          <TableHead className="hidden md:table-cell">
                            Author
                          </TableHead>
                          <TableHead className="hidden md:table-cell">
                            Published
                          </TableHead>
                          <TableHead>
                            <span className="sr-only">Actions</span>
                          </TableHead>
                        </TableRow>
                      </TableHeader>
                      <TableBody>
                        {posts.data.map((post) => (
                          <TableRow key={post.id}>
                            <TableCell className="hidden sm:table-cell">
                              <img
                                alt="Post thumbnail"
                                className="aspect-square rounded-md object-cover"
                                height="64"
                                src={post.featured_image_url || "/placeholder.svg"}
                                width="64"
                              />
                            </TableCell>
                            <TableCell className="font-medium">
                              <div className="flex flex-col gap-1">
                                <span>{post.title}</span>
                                {post.is_featured && (
                                  <Badge variant="secondary" className="w-fit text-xs">
                                    Featured
                                  </Badge>
                                )}
                              </div>
                            </TableCell>
                            <TableCell>
                              <Badge variant="outline">
                                {post.category?.name}
                              </Badge>
                            </TableCell>
                            <TableCell>
                              {getStatusBadge(post.status)}
                            </TableCell>
                            <TableCell className="hidden md:table-cell">
                              <div className="flex items-center gap-2">
                                <img
                                  alt="Author avatar"
                                  className="rounded-full"
                                  height="24"
                                  src="/placeholder.svg"
                                  width="24"
                                />
                                <span className="text-sm">{post.user.name}</span>
                              </div>
                            </TableCell>
                            <TableCell className="hidden md:table-cell">
                              {post.published_at ? formatDate(post.published_at) : "Not published"}
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
                                    onClick={() => router.get(route('dashboard.posts.show', post.slug))}
                                  >
                                    <Eye className="mr-2 h-4 w-4" />
                                    View
                                  </DropdownMenuItem>
                                  <DropdownMenuItem
                                    onClick={() => router.get(route('dashboard.posts.edit', post.slug))}
                                  >
                                    <Edit className="mr-2 h-4 w-4" />
                                    Edit
                                  </DropdownMenuItem>
                                  <DropdownMenuSeparator />
                                  <DropdownMenuItem
                                    className="text-red-600"
                                    onClick={() => handleDelete(post)}
                                  >
                                    <Trash2 className="mr-2 h-4 w-4" />
                                    Delete
                                  </DropdownMenuItem>
                                </DropdownMenuContent>
                              </DropdownMenu>
                            </TableCell>
                          </TableRow>
                        ))}
                      </TableBody>
                    </Table>
                  </CardContent>
                  <CardFooter className="flex flex-col sm:flex-row items-center justify-between gap-4">
                    <div className="text-xs text-muted-foreground">
                      {posts?.current_page && posts?.per_page && posts?.total ? (
                        <>
                          Showing <strong>{((posts.current_page - 1) * posts.per_page) + 1}-{Math.min(posts.current_page * posts.per_page, posts.total)}</strong> of <strong>{posts.total}</strong>{" "}
                          posts
                        </>
                      ) : (
                        <>Showing <strong>0</strong> posts</>
                      )}
                    </div>

                    {posts.last_page > 1 && (
                      <Pagination>
                        <PaginationContent>
                          <PaginationItem>
                            <PaginationPrevious
                              href="#"
                              onClick={(e) => {
                                e.preventDefault()
                                if (posts.current_page > 1) {
                                  handlePageChange(posts.current_page - 1)
                                }
                              }}
                              className={posts.current_page === 1 ? "pointer-events-none opacity-50" : ""}
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
                                  isActive={page === posts.current_page}
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
                                if (posts.current_page < posts.last_page) {
                                  handlePageChange(posts.current_page + 1)
                                }
                              }}
                              className={posts.current_page === posts.last_page ? "pointer-events-none opacity-50" : ""}
                            />
                          </PaginationItem>
                        </PaginationContent>
                      </Pagination>
                    )}
                  </CardFooter>
                </Card>
              </TabsContent>

              <TabsContent value="draft">
                <Card>
                  <CardHeader>
                    <CardTitle>Blog Posts</CardTitle>
                    <CardDescription>
                      Manage your blog posts and view their performance.
                    </CardDescription>
                  </CardHeader>
                  <CardContent>
                    <Table>
                      <TableHeader>
                        <TableRow>
                          <TableHead className="hidden w-[100px] sm:table-cell">
                            <span className="sr-only">Thumbnail</span>
                          </TableHead>
                          <TableHead>Title</TableHead>
                          <TableHead>Category</TableHead>
                          <TableHead>Status</TableHead>
                          <TableHead className="hidden md:table-cell">
                            Author
                          </TableHead>
                          <TableHead className="hidden md:table-cell">
                            Published
                          </TableHead>
                          <TableHead>
                            <span className="sr-only">Actions</span>
                          </TableHead>
                        </TableRow>
                      </TableHeader>
                      <TableBody>
                        {posts.data.map((post) => (
                          <TableRow key={post.id}>
                            <TableCell className="hidden sm:table-cell">
                              <img
                                alt="Post thumbnail"
                                className="aspect-square rounded-md object-cover"
                                height="64"
                                src={post.featured_image_url || "/placeholder.svg"}
                                width="64"
                              />
                            </TableCell>
                            <TableCell className="font-medium">
                              <div className="flex flex-col gap-1">
                                <span>{post.title}</span>
                                {post.is_featured && (
                                  <Badge variant="secondary" className="w-fit text-xs">
                                    Featured
                                  </Badge>
                                )}
                              </div>
                            </TableCell>
                            <TableCell>
                              <Badge variant="outline">
                                {post.category?.name}
                              </Badge>
                            </TableCell>
                            <TableCell>
                              {getStatusBadge(post.status)}
                            </TableCell>
                            <TableCell className="hidden md:table-cell">
                              <div className="flex items-center gap-2">
                                <img
                                  alt="Author avatar"
                                  className="rounded-full"
                                  height="24"
                                  src="/placeholder.svg"
                                  width="24"
                                />
                                <span className="text-sm">{post.user.name}</span>
                              </div>
                            </TableCell>
                            <TableCell className="hidden md:table-cell">
                              {post.published_at ? formatDate(post.published_at) : "Not published"}
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
                                    onClick={() => router.get(route('dashboard.posts.show', post.slug))}
                                  >
                                    <Eye className="mr-2 h-4 w-4" />
                                    View
                                  </DropdownMenuItem>
                                  <DropdownMenuItem
                                    onClick={() => router.get(route('dashboard.posts.edit', post.slug))}
                                  >
                                    <Edit className="mr-2 h-4 w-4" />
                                    Edit
                                  </DropdownMenuItem>
                                  <DropdownMenuSeparator />
                                  <DropdownMenuItem
                                    className="text-red-600"
                                    onClick={() => handleDelete(post)}
                                  >
                                    <Trash2 className="mr-2 h-4 w-4" />
                                    Delete
                                  </DropdownMenuItem>
                                </DropdownMenuContent>
                              </DropdownMenu>
                            </TableCell>
                          </TableRow>
                        ))}
                      </TableBody>
                    </Table>
                  </CardContent>
                  <CardFooter className="flex flex-col sm:flex-row items-center justify-between gap-4">
                    <div className="text-xs text-muted-foreground">
                      {posts?.current_page && posts?.per_page && posts?.total ? (
                        <>
                          Showing <strong>{((posts.current_page - 1) * posts.per_page) + 1}-{Math.min(posts.current_page * posts.per_page, posts.total)}</strong> of <strong>{posts.total}</strong>{" "}
                          posts
                        </>
                      ) : (
                        <>Showing <strong>0</strong> posts</>
                      )}
                    </div>

                    {posts.last_page > 1 && (
                      <Pagination>
                        <PaginationContent>
                          <PaginationItem>
                            <PaginationPrevious
                              href="#"
                              onClick={(e) => {
                                e.preventDefault()
                                if (posts.current_page > 1) {
                                  handlePageChange(posts.current_page - 1)
                                }
                              }}
                              className={posts.current_page === 1 ? "pointer-events-none opacity-50" : ""}
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
                                  isActive={page === posts.current_page}
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
                                if (posts.current_page < posts.last_page) {
                                  handlePageChange(posts.current_page + 1)
                                }
                              }}
                              className={posts.current_page === posts.last_page ? "pointer-events-none opacity-50" : ""}
                            />
                          </PaginationItem>
                        </PaginationContent>
                      </Pagination>
                    )}
                  </CardFooter>
                </Card>
              </TabsContent>

              <TabsContent value="archived">
                <Card>
                  <CardHeader>
                    <CardTitle>Blog Posts</CardTitle>
                    <CardDescription>
                      Manage your blog posts and view their performance.
                    </CardDescription>
                  </CardHeader>
                  <CardContent>
                    <Table>
                      <TableHeader>
                        <TableRow>
                          <TableHead className="hidden w-[100px] sm:table-cell">
                            <span className="sr-only">Thumbnail</span>
                          </TableHead>
                          <TableHead>Title</TableHead>
                          <TableHead>Category</TableHead>
                          <TableHead>Status</TableHead>
                          <TableHead className="hidden md:table-cell">
                            Author
                          </TableHead>
                          <TableHead className="hidden md:table-cell">
                            Published
                          </TableHead>
                          <TableHead>
                            <span className="sr-only">Actions</span>
                          </TableHead>
                        </TableRow>
                      </TableHeader>
                      <TableBody>
                        {posts.data.map((post) => (
                          <TableRow key={post.id}>
                            <TableCell className="hidden sm:table-cell">
                              <img
                                alt="Post thumbnail"
                                className="aspect-square rounded-md object-cover"
                                height="64"
                                src={post.featured_image_url || "/placeholder.svg"}
                                width="64"
                              />
                            </TableCell>
                            <TableCell className="font-medium">
                              <div className="flex flex-col gap-1">
                                <span>{post.title}</span>
                                {post.is_featured && (
                                  <Badge variant="secondary" className="w-fit text-xs">
                                    Featured
                                  </Badge>
                                )}
                              </div>
                            </TableCell>
                            <TableCell>
                              <Badge variant="outline">
                                {post.category?.name}
                              </Badge>
                            </TableCell>
                            <TableCell>
                              {getStatusBadge(post.status)}
                            </TableCell>
                            <TableCell className="hidden md:table-cell">
                              <div className="flex items-center gap-2">
                                <img
                                  alt="Author avatar"
                                  className="rounded-full"
                                  height="24"
                                  src="/placeholder.svg"
                                  width="24"
                                />
                                <span className="text-sm">{post.user.name}</span>
                              </div>
                            </TableCell>
                            <TableCell className="hidden md:table-cell">
                              {post.published_at ? formatDate(post.published_at) : "Not published"}
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
                                    onClick={() => router.get(route('dashboard.posts.show', post.slug))}
                                  >
                                    <Eye className="mr-2 h-4 w-4" />
                                    View
                                  </DropdownMenuItem>
                                  <DropdownMenuItem
                                    onClick={() => router.get(route('dashboard.posts.edit', post.slug))}
                                  >
                                    <Edit className="mr-2 h-4 w-4" />
                                    Edit
                                  </DropdownMenuItem>
                                  <DropdownMenuSeparator />
                                  <DropdownMenuItem
                                    className="text-red-600"
                                    onClick={() => handleDelete(post)}
                                  >
                                    <Trash2 className="mr-2 h-4 w-4" />
                                    Delete
                                  </DropdownMenuItem>
                                </DropdownMenuContent>
                              </DropdownMenu>
                            </TableCell>
                          </TableRow>
                        ))}
                      </TableBody>
                    </Table>
                  </CardContent>
                  <CardFooter className="flex flex-col sm:flex-row items-center justify-between gap-4">
                    <div className="text-xs text-muted-foreground">
                      {posts?.current_page && posts?.per_page && posts?.total ? (
                        <>
                          Showing <strong>{((posts.current_page - 1) * posts.per_page) + 1}-{Math.min(posts.current_page * posts.per_page, posts.total)}</strong> of <strong>{posts.total}</strong>{" "}
                          posts
                        </>
                      ) : (
                        <>Showing <strong>0</strong> posts</>
                      )}
                    </div>

                    {posts.last_page > 1 && (
                      <Pagination>
                        <PaginationContent>
                          <PaginationItem>
                            <PaginationPrevious
                              href="#"
                              onClick={(e) => {
                                e.preventDefault()
                                if (posts.current_page > 1) {
                                  handlePageChange(posts.current_page - 1)
                                }
                              }}
                              className={posts.current_page === 1 ? "pointer-events-none opacity-50" : ""}
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
                                  isActive={page === posts.current_page}
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
                                if (posts.current_page < posts.last_page) {
                                  handlePageChange(posts.current_page + 1)
                                }
                              }}
                              className={posts.current_page === posts.last_page ? "pointer-events-none opacity-50" : ""}
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
