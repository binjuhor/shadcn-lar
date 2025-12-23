import {
  ChevronLeftIcon,
  ChevronRightIcon,
  DoubleArrowLeftIcon,
  DoubleArrowRightIcon,
} from '@radix-ui/react-icons'
import { Table } from '@tanstack/react-table'
import { router } from '@inertiajs/react'
import { Button } from '@/components/ui/button'
import { PaginatedUsers } from '../data/schema'

interface DataTablePaginationProps<TData> {
  table: Table<TData>
  pagination: PaginatedUsers
  filters?: {
    search?: string
    role?: string
  }
}

export function DataTablePagination<TData>({
  table,
  pagination,
  filters = {},
}: DataTablePaginationProps<TData>) {
  const handlePageChange = (page: number) => {
    router.get(
      route('dashboard.users'),
      { ...filters, page },
      { preserveState: true, replace: true }
    )
  }

  const current_page = pagination?.current_page ?? 1
  const last_page = pagination?.last_page ?? 1
  const per_page = pagination?.per_page ?? 10
  const total = pagination?.total ?? 0

  const startItem = total > 0 ? (current_page - 1) * per_page + 1 : 0
  const endItem = Math.min(current_page * per_page, total)

  return (
    <div className="flex items-center justify-between overflow-auto px-2">
      <div className="hidden flex-1 text-sm text-muted-foreground sm:block">
        {table.getFilteredSelectedRowModel().rows.length} of {total} row(s)
        selected.
      </div>
      <div className="flex items-center sm:space-x-6 lg:space-x-8">
        <div className="flex items-center space-x-2">
          <p className="hidden text-sm font-medium sm:block">
            {total > 0 ? `Showing ${startItem}-${endItem} of ${total}` : 'No results'}
          </p>
        </div>
        <div className="flex w-[100px] items-center justify-center text-sm font-medium">
          Page {current_page} of {last_page}
        </div>
        <div className="flex items-center space-x-2">
          <Button
            variant="outline"
            className="hidden h-8 w-8 p-0 lg:flex"
            onClick={() => handlePageChange(1)}
            disabled={current_page === 1}
          >
            <span className="sr-only">Go to first page</span>
            <DoubleArrowLeftIcon className="h-4 w-4" />
          </Button>
          <Button
            variant="outline"
            className="h-8 w-8 p-0"
            onClick={() => handlePageChange(current_page - 1)}
            disabled={current_page === 1}
          >
            <span className="sr-only">Go to previous page</span>
            <ChevronLeftIcon className="h-4 w-4" />
          </Button>
          <Button
            variant="outline"
            className="h-8 w-8 p-0"
            onClick={() => handlePageChange(current_page + 1)}
            disabled={current_page === last_page}
          >
            <span className="sr-only">Go to next page</span>
            <ChevronRightIcon className="h-4 w-4" />
          </Button>
          <Button
            variant="outline"
            className="hidden h-8 w-8 p-0 lg:flex"
            onClick={() => handlePageChange(last_page)}
            disabled={current_page === last_page}
          >
            <span className="sr-only">Go to last page</span>
            <DoubleArrowRightIcon className="h-4 w-4" />
          </Button>
        </div>
      </div>
    </div>
  )
}
