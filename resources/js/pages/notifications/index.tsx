import { AuthenticatedLayout } from '@/layouts'
import { Main } from '@/components/layout'
import {
  Bell,
  Check,
  CheckCheck,
  ListFilter,
  MoreHorizontal,
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
import {
  Tabs,
  TabsContent,
  TabsList,
  TabsTrigger,
} from '@/components/ui/tabs'
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
import { Notification, NotificationFilters, NotificationCategory } from '@/types/notification'
import { PageProps } from '@/types'
import { cn } from '@/lib/utils'

interface NotificationsPageProps extends PageProps {
  notifications: {
    data: Notification[]
    current_page: number
    last_page: number
    per_page: number
    total: number
  }
  filters: NotificationFilters
  unread_count: number
}

const categories: NotificationCategory[] = [
  { value: 'communication', label: 'Communication' },
  { value: 'marketing', label: 'Marketing' },
  { value: 'security', label: 'Security' },
  { value: 'system', label: 'System' },
  { value: 'transactional', label: 'Transactional' },
]

export default function NotificationsIndex({
  notifications,
  filters: initialFilters,
  unread_count,
}: NotificationsPageProps) {
  const [filters, setFilters] = useState<NotificationFilters>(initialFilters)
  const { toast } = useToast()

  const handleFilterChange = (newFilters: Partial<NotificationFilters>) => {
    const updatedFilters = { ...filters, ...newFilters }
    setFilters(updatedFilters)
    router.get(route('dashboard.notifications.index'), updatedFilters, {
      preserveState: true,
      replace: true,
    })
  }

  const handleTabChange = (status: string) => {
    const newStatus = status === 'all' ? undefined : (status as 'read' | 'unread')
    handleFilterChange({ status: newStatus })
  }

  const handleMarkAsRead = async (id: string) => {
    router.post(
      `/api/v1/notification/${id}/mark-read`,
      {},
      {
        preserveState: true,
        onSuccess: () => {
          toast({ title: 'Notification marked as read.' })
        },
      }
    )
  }

  const handleMarkAllAsRead = async () => {
    router.post(
      '/api/v1/notification/mark-all-read',
      {},
      {
        preserveState: true,
        onSuccess: () => {
          toast({ title: 'All notifications marked as read.' })
          router.reload()
        },
      }
    )
  }

  const handleDelete = async (id: string) => {
    if (!confirm('Are you sure you want to delete this notification?')) return

    router.delete(`/api/v1/notification/${id}`, {
      preserveState: true,
      onSuccess: () => {
        toast({ title: 'Notification deleted.' })
        router.reload()
      },
    })
  }

  const handlePageChange = (page: number) => {
    router.get(
      route('dashboard.notifications.index'),
      { ...filters, page },
      { preserveState: true, replace: true }
    )
  }

  const getCategoryIcon = (category: string | null) => {
    switch (category) {
      case 'communication':
        return '💬'
      case 'marketing':
        return '📣'
      case 'security':
        return '🛡️'
      case 'system':
        return '⚙️'
      case 'transactional':
        return '🧾'
      default:
        return '🔔'
    }
  }

  return (
    <AuthenticatedLayout title='Notifications'>
      <Main>
        <div className='grid flex-1 items-start gap-4 md:gap-8'>
          <Tabs defaultValue={filters.status || 'all'} onValueChange={handleTabChange}>
            <div className='flex items-center'>
              <TabsList>
                <TabsTrigger value='all'>All</TabsTrigger>
                <TabsTrigger value='unread'>
                  Unread
                  {unread_count > 0 && (
                    <Badge variant='secondary' className='ml-2'>
                      {unread_count}
                    </Badge>
                  )}
                </TabsTrigger>
                <TabsTrigger value='read'>Read</TabsTrigger>
              </TabsList>
              <div className='ml-auto flex items-center gap-2'>
                <DropdownMenu>
                  <DropdownMenuTrigger asChild>
                    <Button variant='outline' size='sm' className='h-7 gap-1'>
                      <ListFilter className='h-3.5 w-3.5' />
                      <span className='sr-only sm:not-sr-only sm:whitespace-nowrap'>
                        Filter
                      </span>
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
                  </DropdownMenuContent>
                </DropdownMenu>
                {unread_count > 0 && (
                  <Button size='sm' className='h-7 gap-1' onClick={handleMarkAllAsRead}>
                    <CheckCheck className='h-3.5 w-3.5' />
                    <span className='sr-only sm:not-sr-only sm:whitespace-nowrap'>
                      Mark All Read
                    </span>
                  </Button>
                )}
              </div>
            </div>

            <TabsContent value='all'>
              <NotificationsList
                notifications={notifications}
                onMarkAsRead={handleMarkAsRead}
                onDelete={handleDelete}
                onPageChange={handlePageChange}
                getCategoryIcon={getCategoryIcon}
              />
            </TabsContent>
            <TabsContent value='unread'>
              <NotificationsList
                notifications={notifications}
                onMarkAsRead={handleMarkAsRead}
                onDelete={handleDelete}
                onPageChange={handlePageChange}
                getCategoryIcon={getCategoryIcon}
              />
            </TabsContent>
            <TabsContent value='read'>
              <NotificationsList
                notifications={notifications}
                onMarkAsRead={handleMarkAsRead}
                onDelete={handleDelete}
                onPageChange={handlePageChange}
                getCategoryIcon={getCategoryIcon}
              />
            </TabsContent>
          </Tabs>
        </div>
      </Main>
    </AuthenticatedLayout>
  )
}

interface NotificationsListProps {
  notifications: {
    data: Notification[]
    current_page: number
    last_page: number
    per_page: number
    total: number
  }
  onMarkAsRead: (id: string) => void
  onDelete: (id: string) => void
  onPageChange: (page: number) => void
  getCategoryIcon: (category: string | null) => string
}

function NotificationsList({
  notifications,
  onMarkAsRead,
  onDelete,
  onPageChange,
  getCategoryIcon,
}: NotificationsListProps) {
  const generatePageNumbers = () => {
    const pages: (number | string)[] = []
    const delta = 2
    const rangeStart = Math.max(2, notifications.current_page - delta)
    const rangeEnd = Math.min(notifications.last_page - 1, notifications.current_page + delta)

    if (notifications.last_page > 1) pages.push(1)
    if (rangeStart > 2) pages.push('...')
    for (let i = rangeStart; i <= rangeEnd; i++) {
      if (i !== 1 && i !== notifications.last_page) pages.push(i)
    }
    if (rangeEnd < notifications.last_page - 1) pages.push('...')
    if (notifications.last_page > 1 && notifications.last_page !== 1) {
      pages.push(notifications.last_page)
    }

    return pages
  }

  return (
    <Card>
      <CardHeader>
        <CardTitle>Notifications</CardTitle>
        <CardDescription>View and manage your notifications.</CardDescription>
      </CardHeader>
      <CardContent>
        {notifications.data.length === 0 ? (
          <div className='flex flex-col items-center justify-center py-12 text-center'>
            <Bell className='h-12 w-12 text-muted-foreground mb-4' />
            <p className='text-muted-foreground'>No notifications found.</p>
          </div>
        ) : (
          <div className='space-y-4'>
            {notifications.data.map((notification) => (
              <div
                key={notification.id}
                className={cn(
                  'flex items-start gap-4 p-4 rounded-lg border',
                  !notification.is_read && 'bg-muted/50'
                )}
              >
                <div className='text-2xl'>{getCategoryIcon(notification.category)}</div>
                <div className='flex-1 space-y-1'>
                  <div className='flex items-center gap-2'>
                    <p className='font-medium'>{notification.title || 'Notification'}</p>
                    {!notification.is_read && (
                      <Badge variant='default' className='h-5'>
                        New
                      </Badge>
                    )}
                  </div>
                  <p className='text-sm text-muted-foreground'>{notification.message}</p>
                  <div className='flex items-center gap-2 text-xs text-muted-foreground'>
                    <span>{notification.time_ago}</span>
                    {notification.category && (
                      <>
                        <span>•</span>
                        <Badge variant='outline' className='text-xs'>
                          {notification.category}
                        </Badge>
                      </>
                    )}
                  </div>
                  {notification.action_url && (
                    <Button variant='link' size='sm' className='p-0 h-auto' asChild>
                      <a href={notification.action_url}>
                        {notification.action_label || 'View Details'}
                      </a>
                    </Button>
                  )}
                </div>
                <DropdownMenu>
                  <DropdownMenuTrigger asChild>
                    <Button variant='ghost' size='icon'>
                      <MoreHorizontal className='h-4 w-4' />
                    </Button>
                  </DropdownMenuTrigger>
                  <DropdownMenuContent align='end'>
                    <DropdownMenuLabel>Actions</DropdownMenuLabel>
                    <DropdownMenuSeparator />
                    {!notification.is_read && (
                      <DropdownMenuItem onClick={() => onMarkAsRead(notification.id)}>
                        <Check className='mr-2 h-4 w-4' />
                        Mark as Read
                      </DropdownMenuItem>
                    )}
                    <DropdownMenuItem
                      className='text-red-600'
                      onClick={() => onDelete(notification.id)}
                    >
                      <Trash2 className='mr-2 h-4 w-4' />
                      Delete
                    </DropdownMenuItem>
                  </DropdownMenuContent>
                </DropdownMenu>
              </div>
            ))}
          </div>
        )}
      </CardContent>
      <CardFooter className='flex flex-col sm:flex-row items-center justify-between gap-4'>
        <div className='text-xs text-muted-foreground'>
          Showing{' '}
          <strong>
            {Math.min(
              (notifications.current_page - 1) * notifications.per_page + 1,
              notifications.total
            )}
            -{Math.min(notifications.current_page * notifications.per_page, notifications.total)}
          </strong>{' '}
          of <strong>{notifications.total}</strong> notifications
        </div>
        {notifications.last_page > 1 && (
          <Pagination>
            <PaginationContent>
              <PaginationItem>
                <PaginationPrevious
                  href='#'
                  onClick={(e) => {
                    e.preventDefault()
                    if (notifications.current_page > 1) {
                      onPageChange(notifications.current_page - 1)
                    }
                  }}
                  className={
                    notifications.current_page === 1 ? 'pointer-events-none opacity-50' : ''
                  }
                />
              </PaginationItem>
              {generatePageNumbers().map((page, index) => (
                <PaginationItem key={index}>
                  {page === '...' ? (
                    <span className='flex h-9 w-9 items-center justify-center text-sm'>...</span>
                  ) : (
                    <PaginationLink
                      href='#'
                      onClick={(e) => {
                        e.preventDefault()
                        onPageChange(page as number)
                      }}
                      isActive={page === notifications.current_page}
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
                    if (notifications.current_page < notifications.last_page) {
                      onPageChange(notifications.current_page + 1)
                    }
                  }}
                  className={
                    notifications.current_page === notifications.last_page
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
  )
}
