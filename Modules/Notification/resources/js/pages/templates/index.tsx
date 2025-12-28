import { AuthenticatedLayout } from '@/layouts'
import { Main } from '@/components/layout'
import {
  Edit,
  ListFilter,
  MoreHorizontal,
  PlusCircle,
  Power,
  Trash2,
} from 'lucide-react'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import {
  Card,
  CardContent,
  CardDescription,
  CardFooter,
  CardHeader,
  CardTitle,
} from '@/components/ui/card'
import {
  DropdownMenu,
  DropdownMenuCheckboxItem,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuLabel,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu'
import { Input } from '@/components/ui/input'
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table'
import {
  Pagination,
  PaginationContent,
  PaginationItem,
  PaginationLink,
  PaginationNext,
  PaginationPrevious,
} from '@/components/ui/pagination'
import { useState } from 'react'
import { router } from '@inertiajs/react'
import { useToast } from '@/hooks/use-toast'
import { NotificationTemplate, NotificationTemplateFilters, NotificationCategory } from '@/types/notification'
import { PageProps } from '@/types'

interface TemplatesPageProps extends PageProps {
  templates: {
    data: NotificationTemplate[]
    current_page: number
    last_page: number
    per_page: number
    total: number
  }
  filters: NotificationTemplateFilters
  categories: NotificationCategory[]
}

export default function TemplatesIndex({
  templates,
  filters: initialFilters,
  categories,
}: TemplatesPageProps) {
  const [filters, setFilters] = useState<NotificationTemplateFilters>(initialFilters)
  const [searchTerm, setSearchTerm] = useState(initialFilters.search || '')
  const { toast } = useToast()

  const handleSearch = (value: string) => {
    setSearchTerm(value)
    router.get(route('dashboard.notifications.templates.index'), { ...filters, search: value }, {
      preserveState: true,
      replace: true,
    })
  }

  const handleFilterChange = (newFilters: Partial<NotificationTemplateFilters>) => {
    const updatedFilters = { ...filters, ...newFilters }
    setFilters(updatedFilters)
    router.get(route('dashboard.notifications.templates.index'), updatedFilters, {
      preserveState: true,
      replace: true,
    })
  }

  const handleDelete = (template: NotificationTemplate) => {
    if (!confirm(`Are you sure you want to delete "${template.name}"?`)) return

    router.delete(route('dashboard.notifications.templates.destroy', template.id), {
      onSuccess: () => {
        toast({ title: 'Template deleted successfully!' })
      },
      onError: () => {
        toast({ variant: 'destructive', title: 'Failed to delete template.' })
      },
    })
  }

  const handleToggleStatus = async (template: NotificationTemplate) => {
    router.post(
      `/api/v1/notification/templates/${template.id}/toggle-status`,
      {},
      {
        preserveState: true,
        onSuccess: () => {
          toast({
            title: template.is_active ? 'Template deactivated.' : 'Template activated.',
          })
          router.reload()
        },
      }
    )
  }

  const handlePageChange = (page: number) => {
    router.get(route('dashboard.notifications.templates.index'), { ...filters, page }, {
      preserveState: true,
      replace: true,
    })
  }

  const generatePageNumbers = () => {
    const pages: (number | string)[] = []
    const delta = 2
    const rangeStart = Math.max(2, templates.current_page - delta)
    const rangeEnd = Math.min(templates.last_page - 1, templates.current_page + delta)

    if (templates.last_page > 1) pages.push(1)
    if (rangeStart > 2) pages.push('...')
    for (let i = rangeStart; i <= rangeEnd; i++) {
      if (i !== 1 && i !== templates.last_page) pages.push(i)
    }
    if (rangeEnd < templates.last_page - 1) pages.push('...')
    if (templates.last_page > 1 && templates.last_page !== 1) {
      pages.push(templates.last_page)
    }

    return pages
  }

  return (
    <AuthenticatedLayout title='Notification Templates'>
      <Main>
        <div className='grid flex-1 items-start gap-4 md:gap-8'>
          <Card>
            <CardHeader>
              <div className='flex items-center justify-between'>
                <div>
                  <CardTitle>Notification Templates</CardTitle>
                  <CardDescription>
                    Manage reusable notification templates for sending to users.
                  </CardDescription>
                </div>
                <Button
                  size='sm'
                  className='h-8 gap-1'
                  onClick={() => router.get(route('dashboard.notifications.templates.create'))}
                >
                  <PlusCircle className='h-3.5 w-3.5' />
                  <span>Add Template</span>
                </Button>
              </div>
            </CardHeader>
            <CardContent>
              <div className='flex items-center gap-4 mb-4'>
                <Input
                  placeholder='Search templates...'
                  value={searchTerm}
                  onChange={(e) => handleSearch(e.target.value)}
                  className='max-w-sm'
                />
                <DropdownMenu>
                  <DropdownMenuTrigger asChild>
                    <Button variant='outline' size='sm' className='h-8 gap-1'>
                      <ListFilter className='h-3.5 w-3.5' />
                      <span>Filter</span>
                    </Button>
                  </DropdownMenuTrigger>
                  <DropdownMenuContent align='end' className='w-48'>
                    <DropdownMenuLabel>Category</DropdownMenuLabel>
                    <DropdownMenuSeparator />
                    <DropdownMenuCheckboxItem
                      checked={!filters.category}
                      onCheckedChange={() => handleFilterChange({ category: undefined })}
                    >
                      All Categories
                    </DropdownMenuCheckboxItem>
                    {categories.map((cat) => (
                      <DropdownMenuCheckboxItem
                        key={cat.value}
                        checked={filters.category === cat.value}
                        onCheckedChange={() =>
                          handleFilterChange({
                            category: filters.category === cat.value ? undefined : cat.value,
                          })
                        }
                      >
                        {cat.label}
                      </DropdownMenuCheckboxItem>
                    ))}
                    <DropdownMenuSeparator />
                    <DropdownMenuLabel>Status</DropdownMenuLabel>
                    <DropdownMenuSeparator />
                    <DropdownMenuCheckboxItem
                      checked={!filters.status}
                      onCheckedChange={() => handleFilterChange({ status: undefined })}
                    >
                      All
                    </DropdownMenuCheckboxItem>
                    <DropdownMenuCheckboxItem
                      checked={filters.status === 'active'}
                      onCheckedChange={() =>
                        handleFilterChange({
                          status: filters.status === 'active' ? undefined : 'active',
                        })
                      }
                    >
                      Active
                    </DropdownMenuCheckboxItem>
                    <DropdownMenuCheckboxItem
                      checked={filters.status === 'inactive'}
                      onCheckedChange={() =>
                        handleFilterChange({
                          status: filters.status === 'inactive' ? undefined : 'inactive',
                        })
                      }
                    >
                      Inactive
                    </DropdownMenuCheckboxItem>
                  </DropdownMenuContent>
                </DropdownMenu>
              </div>

              <Table>
                <TableHeader>
                  <TableRow>
                    <TableHead>Name</TableHead>
                    <TableHead>Subject</TableHead>
                    <TableHead>Category</TableHead>
                    <TableHead>Channels</TableHead>
                    <TableHead>Status</TableHead>
                    <TableHead>
                      <span className='sr-only'>Actions</span>
                    </TableHead>
                  </TableRow>
                </TableHeader>
                <TableBody>
                  {templates.data.length === 0 ? (
                    <TableRow>
                      <TableCell colSpan={6} className='text-center py-8 text-muted-foreground'>
                        No templates found.
                      </TableCell>
                    </TableRow>
                  ) : (
                    templates.data.map((template) => (
                      <TableRow key={template.id}>
                        <TableCell className='font-medium'>{template.name}</TableCell>
                        <TableCell className='max-w-xs truncate'>{template.subject}</TableCell>
                        <TableCell>
                          <Badge variant='outline'>{template.category_label}</Badge>
                        </TableCell>
                        <TableCell>
                          <div className='flex gap-1'>
                            {template.channels.map((channel) => (
                              <Badge key={channel} variant='secondary' className='text-xs'>
                                {channel}
                              </Badge>
                            ))}
                          </div>
                        </TableCell>
                        <TableCell>
                          {template.is_active ? (
                            <Badge className='bg-green-500'>Active</Badge>
                          ) : (
                            <Badge variant='secondary'>Inactive</Badge>
                          )}
                        </TableCell>
                        <TableCell>
                          <DropdownMenu>
                            <DropdownMenuTrigger asChild>
                              <Button variant='ghost' size='icon'>
                                <MoreHorizontal className='h-4 w-4' />
                              </Button>
                            </DropdownMenuTrigger>
                            <DropdownMenuContent align='end'>
                              <DropdownMenuLabel>Actions</DropdownMenuLabel>
                              <DropdownMenuSeparator />
                              <DropdownMenuItem
                                onClick={() =>
                                  router.get(route('dashboard.notifications.templates.edit', template.id))
                                }
                              >
                                <Edit className='mr-2 h-4 w-4' />
                                Edit
                              </DropdownMenuItem>
                              <DropdownMenuItem onClick={() => handleToggleStatus(template)}>
                                <Power className='mr-2 h-4 w-4' />
                                {template.is_active ? 'Deactivate' : 'Activate'}
                              </DropdownMenuItem>
                              <DropdownMenuSeparator />
                              <DropdownMenuItem
                                className='text-red-600'
                                onClick={() => handleDelete(template)}
                              >
                                <Trash2 className='mr-2 h-4 w-4' />
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
            </CardContent>
            <CardFooter className='flex flex-col sm:flex-row items-center justify-between gap-4'>
              <div className='text-xs text-muted-foreground'>
                Showing{' '}
                <strong>
                  {Math.min(
                    (templates.current_page - 1) * templates.per_page + 1,
                    templates.total
                  )}
                  -{Math.min(templates.current_page * templates.per_page, templates.total)}
                </strong>{' '}
                of <strong>{templates.total}</strong> templates
              </div>
              {templates.last_page > 1 && (
                <Pagination>
                  <PaginationContent>
                    <PaginationItem>
                      <PaginationPrevious
                        href='#'
                        onClick={(e) => {
                          e.preventDefault()
                          if (templates.current_page > 1) {
                            handlePageChange(templates.current_page - 1)
                          }
                        }}
                        className={
                          templates.current_page === 1 ? 'pointer-events-none opacity-50' : ''
                        }
                      />
                    </PaginationItem>
                    {generatePageNumbers().map((page, index) => (
                      <PaginationItem key={index}>
                        {page === '...' ? (
                          <span className='flex h-9 w-9 items-center justify-center text-sm'>
                            ...
                          </span>
                        ) : (
                          <PaginationLink
                            href='#'
                            onClick={(e) => {
                              e.preventDefault()
                              handlePageChange(page as number)
                            }}
                            isActive={page === templates.current_page}
                          >
                            {page}
                          </PaginationLink>
                        )}
                      </PaginationItem>
                    ))}
                    <PaginationItem>
                      <PaginationNext
                        href='#'
                        onClick={(e) => {
                          e.preventDefault()
                          if (templates.current_page < templates.last_page) {
                            handlePageChange(templates.current_page + 1)
                          }
                        }}
                        className={
                          templates.current_page === templates.last_page
                            ? 'pointer-events-none opacity-50'
                            : ''
                        }
                      />
                    </PaginationItem>
                  </PaginationContent>
                </Pagination>
              )}
            </CardFooter>
          </Card>
        </div>
      </Main>
    </AuthenticatedLayout>
  )
}
