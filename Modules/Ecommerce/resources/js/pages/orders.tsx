import {AuthenticatedLayout} from "@/layouts"
import { File, ListFilter, MoreHorizontal, Eye } from "lucide-react"
import {Badge} from "@/components/ui/badge"
import {Button} from "@/components/ui/button"
import { Card, CardContent, CardDescription, CardFooter, CardHeader, CardTitle } from "@/components/ui/card"
import {
  DropdownMenu,
  DropdownMenuCheckboxItem,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuLabel,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu"
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@/components/ui/table"
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/components/ui/tabs"
import { Input } from "@/components/ui/input"
import { Main } from "@/components/layout"
import { useState } from "react"
import { Order, OrderFilters } from "@/types/ecommerce"
import { router } from "@inertiajs/react"
import { PageProps } from "@/types"
import { useToast } from "@/hooks/use-toast"
import { Pagination, PaginationContent, PaginationItem, PaginationLink, PaginationNext, PaginationPrevious } from "@/components/ui/pagination"
import { useTranslation } from "react-i18next"

interface OrdersPageProps extends PageProps {
  orders?: {
    data: Order[]
    current_page: number
    last_page: number
    per_page: number
    total: number
  }
  filters?: OrderFilters
}

export default function Orders({
  orders = { data: [], current_page: 1, last_page: 1, per_page: 15, total: 0 },
  filters: initialFilters = {}
}: OrdersPageProps) {
  const { t } = useTranslation()
  const [filters, setFilters] = useState<OrderFilters>(initialFilters)
  const [searchTerm, setSearchTerm] = useState(initialFilters?.search || "")
  const { toast } = useToast()

  const handleSearch = (value: string) => {
    setSearchTerm(value)
    router.get(route('dashboard.ecommerce.orders.index'), { ...filters, search: value }, {
      preserveState: true,
      replace: true,
    })
  }

  const handleFilterChange = (newFilters: Partial<OrderFilters>) => {
    const updatedFilters = { ...filters, ...newFilters }
    setFilters(updatedFilters)
    router.get(route('dashboard.ecommerce.orders.index'), updatedFilters, {
      preserveState: true,
      replace: true,
    })
  }

  const handleTabChange = (status: string) => {
    const newStatus = status === 'all' ? undefined : status as 'pending' | 'processing' | 'completed' | 'cancelled' | 'refunded'
    handleFilterChange({ status: newStatus })
  }

  const handlePageChange = (page: number) => {
    router.get(route('dashboard.ecommerce.orders.index'), { ...filters, page }, {
      preserveState: true,
      replace: true,
    })
  }

  const generatePageNumbers = () => {
    const pages = []
    const delta = 2
    const rangeStart = Math.max(2, orders.current_page - delta)
    const rangeEnd = Math.min(orders.last_page - 1, orders.current_page + delta)

    if (orders.last_page > 1) pages.push(1)
    if (rangeStart > 2) pages.push('...')
    for (let i = rangeStart; i <= rangeEnd; i++) {
      if (i !== 1 && i !== orders.last_page) pages.push(i)
    }
    if (rangeEnd < orders.last_page - 1) pages.push('...')
    if (orders.last_page > 1 && orders.last_page !== 1) pages.push(orders.last_page)
    return pages
  }

  const getStatusBadge = (status: string) => {
    switch (status) {
      case "completed":
        return <Badge variant="outline" className="text-green-600 border-green-600">{t('page.ecommerce.orders.status.completed')}</Badge>
      case "processing":
        return <Badge variant="outline" className="text-blue-600 border-blue-600">{t('page.ecommerce.orders.status.processing')}</Badge>
      case "pending":
        return <Badge variant="secondary">{t('page.ecommerce.orders.status.pending')}</Badge>
      case "cancelled":
        return <Badge variant="destructive">{t('page.ecommerce.orders.status.cancelled')}</Badge>
      case "refunded":
        return <Badge variant="outline">{t('page.ecommerce.orders.status.refunded')}</Badge>
      default:
        return <Badge>{status}</Badge>
    }
  }

  const getPaymentStatusBadge = (status: string) => {
    switch (status) {
      case "paid":
        return <Badge variant="outline" className="text-green-600 border-green-600">{t('page.ecommerce.orders.payment_status.paid')}</Badge>
      case "unpaid":
        return <Badge variant="secondary">{t('page.ecommerce.orders.payment_status.unpaid')}</Badge>
      case "refunded":
        return <Badge variant="outline">{t('page.ecommerce.orders.payment_status.refunded')}</Badge>
      default:
        return <Badge>{status}</Badge>
    }
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

  const renderOrdersTable = () => (
    <Table>
      <TableHeader>
        <TableRow>
          <TableHead>{t('page.ecommerce.orders.table.order_number')}</TableHead>
          <TableHead>{t('page.ecommerce.orders.table.customer')}</TableHead>
          <TableHead>{t('page.ecommerce.orders.table.status')}</TableHead>
          <TableHead>{t('page.ecommerce.orders.table.payment')}</TableHead>
          <TableHead>{t('page.ecommerce.orders.table.total')}</TableHead>
          <TableHead className="hidden md:table-cell">{t('page.ecommerce.orders.table.items')}</TableHead>
          <TableHead className="hidden md:table-cell">{t('page.ecommerce.orders.table.date')}</TableHead>
          <TableHead>
            <span className="sr-only">{t('common.actions.actions')}</span>
          </TableHead>
        </TableRow>
      </TableHeader>
      <TableBody>
        {orders.data.map((order) => (
          <TableRow key={order.id}>
            <TableCell className="font-medium font-mono text-sm">
              {order.order_number}
            </TableCell>
            <TableCell>
              <div className="flex flex-col">
                <span className="font-medium">{order.user.name}</span>
                <span className="text-sm text-muted-foreground">{order.user.email}</span>
              </div>
            </TableCell>
            <TableCell>
              {getStatusBadge(order.status)}
            </TableCell>
            <TableCell>
              {getPaymentStatusBadge(order.payment_status)}
            </TableCell>
            <TableCell className="font-medium">
              {formatPrice(order.total)}
            </TableCell>
            <TableCell className="hidden md:table-cell">
              {t('page.ecommerce.orders.table.items_count', { count: order.items?.length || 0 })}
            </TableCell>
            <TableCell className="hidden md:table-cell">
              {formatDate(order.created_at)}
            </TableCell>
            <TableCell>
              <DropdownMenu>
                <DropdownMenuTrigger asChild>
                  <Button aria-haspopup="true" size="icon" variant="ghost">
                    <MoreHorizontal className="h-4 w-4" />
                    <span className="sr-only">{t('page.ecommerce.orders.table.toggle_menu')}</span>
                  </Button>
                </DropdownMenuTrigger>
                <DropdownMenuContent align="end">
                  <DropdownMenuLabel>{t('common.actions.actions')}</DropdownMenuLabel>
                  <DropdownMenuSeparator />
                  <DropdownMenuItem onClick={() => router.get(route('dashboard.ecommerce.orders.show', order.id))}>
                    <Eye className="mr-2 h-4 w-4" />
                    {t('common.actions.view_details')}
                  </DropdownMenuItem>
                </DropdownMenuContent>
              </DropdownMenu>
            </TableCell>
          </TableRow>
        ))}
      </TableBody>
    </Table>
  )

  return (
    <>
      <AuthenticatedLayout title={t('page.ecommerce.orders.title')}>
        <Main>
          <div className="grid flex-1 items-start gap-4 md:gap-8">
            <Tabs defaultValue={filters.status || "all"} onValueChange={handleTabChange}>
              <div className="flex items-center">
                <TabsList>
                  <TabsTrigger value="all">{t('common.filters.all')}</TabsTrigger>
                  <TabsTrigger value="pending">{t('page.ecommerce.orders.status.pending')}</TabsTrigger>
                  <TabsTrigger value="processing">{t('page.ecommerce.orders.status.processing')}</TabsTrigger>
                  <TabsTrigger value="completed">{t('page.ecommerce.orders.status.completed')}</TabsTrigger>
                  <TabsTrigger value="cancelled" className="hidden sm:flex">{t('page.ecommerce.orders.status.cancelled')}</TabsTrigger>
                  <TabsTrigger value="refunded" className="hidden sm:flex">{t('page.ecommerce.orders.status.refunded')}</TabsTrigger>
                </TabsList>
                <div className="ml-auto flex items-center gap-2">
                  <div className="relative">
                    <Input placeholder={t('page.ecommerce.orders.search_placeholder')} value={searchTerm}
                      onChange={(e) => handleSearch(e.target.value)} className="w-64" />
                  </div>
                  <DropdownMenu>
                    <DropdownMenuTrigger asChild>
                      <Button variant="outline" size="sm" className="h-7 gap-1">
                        <ListFilter className="h-3.5 w-3.5" />
                        <span className="sr-only sm:not-sr-only sm:whitespace-nowrap">{t('common.actions.filter')}</span>
                      </Button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent align="end" className="w-56">
                      <DropdownMenuLabel>{t('page.ecommerce.orders.filter_payment_status')}</DropdownMenuLabel>
                      <DropdownMenuSeparator />
                      <DropdownMenuCheckboxItem checked={!filters.payment_status}
                        onCheckedChange={(checked) => { if (checked) handleFilterChange({ payment_status: undefined }) }}>
                        {t('page.ecommerce.orders.all_payment_status')}
                      </DropdownMenuCheckboxItem>
                      <DropdownMenuCheckboxItem checked={filters.payment_status === 'paid'}
                        onCheckedChange={(checked) => handleFilterChange({ payment_status: checked ? 'paid' : undefined })}>
                        {t('page.ecommerce.orders.payment_status.paid')}
                      </DropdownMenuCheckboxItem>
                      <DropdownMenuCheckboxItem checked={filters.payment_status === 'unpaid'}
                        onCheckedChange={(checked) => handleFilterChange({ payment_status: checked ? 'unpaid' : undefined })}>
                        {t('page.ecommerce.orders.payment_status.unpaid')}
                      </DropdownMenuCheckboxItem>
                      <DropdownMenuCheckboxItem checked={filters.payment_status === 'refunded'}
                        onCheckedChange={(checked) => handleFilterChange({ payment_status: checked ? 'refunded' : undefined })}>
                        {t('page.ecommerce.orders.payment_status.refunded')}
                      </DropdownMenuCheckboxItem>
                    </DropdownMenuContent>
                  </DropdownMenu>
                  <Button size="sm" variant="outline" className="h-7 gap-1">
                    <File className="h-3.5 w-3.5" />
                    <span className="sr-only sm:not-sr-only sm:whitespace-nowrap">{t('common.actions.export')}</span>
                  </Button>
                </div>
              </div>

              <TabsContent value="all">
                <Card>
                  <CardHeader>
                    <CardTitle>{t('page.ecommerce.orders.title')}</CardTitle>
                    <CardDescription>{t('page.ecommerce.orders.description')}</CardDescription>
                  </CardHeader>
                  <CardContent>
                    {renderOrdersTable()}
                  </CardContent>
                  <CardFooter className="flex flex-col sm:flex-row items-center justify-between gap-4">
                    <div className="text-xs text-muted-foreground">
                      {orders?.current_page && orders?.per_page && orders?.total ? (
                        <>
                          {t('page.ecommerce.orders.pagination.showing')} <strong>{((orders.current_page - 1) * orders.per_page) + 1}-{Math.min(orders.current_page * orders.per_page, orders.total)}</strong> {t('page.ecommerce.orders.pagination.of')} <strong>{orders.total}</strong> {t('page.ecommerce.orders.pagination.orders')}
                        </>
                      ) : (
                        <>{t('page.ecommerce.orders.pagination.showing')} <strong>0</strong> {t('page.ecommerce.orders.pagination.orders')}</>
                      )}
                    </div>

                    {orders.last_page > 1 && (
                      <Pagination>
                        <PaginationContent>
                          <PaginationItem>
                            <PaginationPrevious href="#" onClick={(e) => { e.preventDefault(); if (orders.current_page > 1) handlePageChange(orders.current_page - 1) }}
                              className={orders.current_page === 1 ? "pointer-events-none opacity-50" : ""} />
                          </PaginationItem>
                          {generatePageNumbers().map((page, index) => (
                            <PaginationItem key={index}>
                              {page === '...' ? (
                                <span className="flex h-9 w-9 items-center justify-center text-sm">...</span>
                              ) : (
                                <PaginationLink href="#" onClick={(e) => { e.preventDefault(); handlePageChange(page as number) }}
                                  isActive={page === orders.current_page}>
                                  {page}
                                </PaginationLink>
                              )}
                            </PaginationItem>
                          ))}
                          <PaginationItem>
                            <PaginationNext href="#" onClick={(e) => { e.preventDefault(); if (orders.current_page < orders.last_page) handlePageChange(orders.current_page + 1) }}
                              className={orders.current_page === orders.last_page ? "pointer-events-none opacity-50" : ""} />
                          </PaginationItem>
                        </PaginationContent>
                      </Pagination>
                    )}
                  </CardFooter>
                </Card>
              </TabsContent>

              {['pending', 'processing', 'completed', 'cancelled', 'refunded'].map((status) => (
                <TabsContent key={status} value={status}>
                  <Card>
                    <CardHeader>
                      <CardTitle>{t('page.ecommerce.orders.title')}</CardTitle>
                      <CardDescription>{t('page.ecommerce.orders.description')}</CardDescription>
                    </CardHeader>
                    <CardContent>
                      {renderOrdersTable()}
                    </CardContent>
                  </Card>
                </TabsContent>
              ))}
            </Tabs>
          </div>
        </Main>
      </AuthenticatedLayout>
    </>
  )
}
