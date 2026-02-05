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
import { useTranslation } from 'react-i18next'

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
  const { t } = useTranslation()
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
    if (!confirm(t('page.notifications.templates.confirm_delete', { name: template.name }))) return

    router.delete(route('dashboard.notifications.templates.destroy', template.id), {
      onSuccess: () => {
        toast({ title: t('common.messages.delete_success') })
      },
      onError: () => {
        toast({ variant: 'destructive', title: t('common.messages.delete_failed') })
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
            title: template.is_active
              ? t('page.notifications.templates.deactivated')
              : t('page.notifications.templates.activated'),
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
    <AuthenticatedLayout title={t('page.notifications.templates.title')}>
      <Main>
        <div className='grid flex-1 items-start gap-4 md:gap-8'>
          <Card>
            <CardHeader>
              <div className='flex items-center justify-between'>
                <div>
                  <CardTitle>{t('page.notifications.templates.title')}</CardTitle>
                  <CardDescription>
                    {t('page.notifications.templates.description')}
                  </CardDescription>
                </div>
                <Button
                  size='sm'
                  className='h-8 gap-1'
                  onClick={() => router.get(route('dashboard.notifications.templates.create'))}
                >
                  <PlusCircle className='h-3.5 w-3.5' />
                  <span>{t('page.notifications.templates.add_template')}</span>
                </Button>
              </div>
            </CardHeader>
            <CardContent>
              <div className='flex items-center gap-4 mb-4'>
                <Input
                  placeholder={t('page.notifications.templates.search_placeholder')}
                  value={searchTerm}
                  onChange={(e) => handleSearch(e.target.value)}
                  className='max-w-sm'
                />
                <DropdownMenu>
                  <DropdownMenuTrigger asChild>
                    <Button variant='outline' size='sm' className='h-8 gap-1'>
                      <ListFilter className='h-3.5 w-3.5' />
                      <span>{t('common.actions.filter')}</span>
                    </Button>
                  </DropdownMenuTrigger>
                  <DropdownMenuContent align='end' className='w-48'>
                    <DropdownMenuLabel>{t('common.fields.category')}</DropdownMenuLabel>
                    <DropdownMenuSeparator />
                    <DropdownMenuCheckboxItem
                      checked={!filters.category}
                      onCheckedChange={() => handleFilterChange({ category: undefined })}
                    >
                      {t('page.notifications.templates.all_categories')}
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
                    <DropdownMenuLabel>{t('common.fields.status')}</DropdownMenuLabel>
                    <DropdownMenuSeparator />
                    <DropdownMenuCheckboxItem
                      checked={!filters.status}
                      onCheckedChange={() => handleFilterChange({ status: undefined })}
                    >
                      {t('page.notifications.templates.all')}
                    </DropdownMenuCheckboxItem>
                    <DropdownMenuCheckboxItem
                      checked={filters.status === 'active'}
                      onCheckedChange={() =>
                        handleFilterChange({
                          status: filters.status === 'active' ? undefined : 'active',
                        })
                      }
                    >
                      {t('common.statuses.active')}
                    </DropdownMenuCheckboxItem>
                    <DropdownMenuCheckboxItem
                      checked={filters.status === 'inactive'}
                      onCheckedChange={() =>
                        handleFilterChange({
                          status: filters.status === 'inactive' ? undefined : 'inactive',
                        })
                      }
                    >
                      {t('common.statuses.inactive')}
                    </DropdownMenuCheckboxItem>
                  </DropdownMenuContent>
                </DropdownMenu>
              </div>

              <Table>
                <TableHeader>
                  <TableRow>
                    <TableHead>{t('common.fields.name')}</TableHead>
                    <TableHead>{t('page.notifications.templates.subject')}</TableHead>
                    <TableHead>{t('common.fields.category')}</TableHead>
                    <TableHead>{t('page.notifications.templates.channels')}</TableHead>
                    <TableHead>{t('common.fields.status')}</TableHead>
                    <TableHead>
                      <span className='sr-only'>{t('common.actions.actions')}</span>
                    </TableHead>
                  </TableRow>
                </TableHeader>
                <TableBody>
                  {templates.data.length === 0 ? (
                    <TableRow>
                      <TableCell colSpan={6} className='text-center py-8 text-muted-foreground'>
                        {t('page.notifications.templates.empty')}
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
                            <Badge className='bg-green-500'>{t('common.statuses.active')}</Badge>
                          ) : (
                            <Badge variant='secondary'>{t('common.statuses.inactive')}</Badge>
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
                              <DropdownMenuLabel>{t('common.actions.actions')}</DropdownMenuLabel>
                              <DropdownMenuSeparator />
                              <DropdownMenuItem
                                onClick={() =>
                                  router.get(route('dashboard.notifications.templates.edit', template.id))
                                }
                              >
                                <Edit className='mr-2 h-4 w-4' />
                                {t('common.actions.edit')}
                              </DropdownMenuItem>
                              <DropdownMenuItem onClick={() => handleToggleStatus(template)}>
                                <Power className='mr-2 h-4 w-4' />
                                {template.is_active ? t('page.notifications.templates.deactivate') : t('page.notifications.templates.activate')}
                              </DropdownMenuItem>
                              <DropdownMenuSeparator />
                              <DropdownMenuItem
                                className='text-red-600'
                                onClick={() => handleDelete(template)}
                              >
                                <Trash2 className='mr-2 h-4 w-4' />
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
            </CardContent>
            <CardFooter className='flex flex-col sm:flex-row items-center justify-between gap-4'>
              <div className='text-xs text-muted-foreground'>
                {t('page.notifications.templates.pagination.showing', {
                  from: Math.min(
                    (templates.current_page - 1) * templates.per_page + 1,
                    templates.total
                  ),
                  to: Math.min(templates.current_page * templates.per_page, templates.total),
                  total: templates.total
                })}
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
