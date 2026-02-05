import { router } from '@inertiajs/react'
import { useTranslation } from 'react-i18next'
import { Button } from '@/components/ui/button'
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select'
import {
  IconChevronLeft,
  IconChevronRight,
  IconChevronsLeft,
  IconChevronsRight,
} from '@tabler/icons-react'

interface PaginationMeta {
  current_page: number
  last_page: number
  per_page: number
  total: number
  from: number | null
  to: number | null
}

interface Filters {
  search?: string
  status?: string
  date_from?: string
  date_to?: string
  amount_from?: string
  amount_to?: string
  per_page?: number
}

interface ServerPaginationProps {
  meta: PaginationMeta
  filters?: Filters
}

export function ServerPagination({ meta, filters = {} }: ServerPaginationProps) {
  const { t } = useTranslation()

  const goToPage = (page: number) => {
    router.get(route('dashboard.invoices.index'), { ...filters, page, per_page: meta.per_page })
  }

  const changePerPage = (perPage: string) => {
    router.get(route('dashboard.invoices.index'), { ...filters, page: 1, per_page: Number(perPage) })
  }

  const canGoPrevious = meta.current_page > 1
  const canGoNext = meta.current_page < meta.last_page

  // Generate page numbers with ellipsis
  const getPageNumbers = (): (number | string)[] => {
    const pages: (number | string)[] = []
    const current = meta.current_page
    const last = meta.last_page
    const delta = 2

    // Always show first page
    pages.push(1)

    // Left ellipsis
    if (current - delta > 2) {
      pages.push('...')
    }

    // Pages around current
    for (let i = Math.max(2, current - delta); i <= Math.min(last - 1, current + delta); i++) {
      if (!pages.includes(i)) pages.push(i)
    }

    // Right ellipsis
    if (current + delta < last - 1) {
      pages.push('...')
    }

    // Always show last page
    if (last > 1 && !pages.includes(last)) {
      pages.push(last)
    }

    return pages
  }

  if (meta.last_page <= 1) {
    return (
      <div className='flex items-center justify-between px-2'>
        <div className='text-sm text-muted-foreground'>
          {t('pagination.total_invoices', { count: meta.total })}
        </div>
        <div className='flex items-center space-x-2'>
          <p className='hidden sm:block text-sm font-medium'>{t('pagination.rows_per_page')}</p>
          <Select value={`${meta.per_page}`} onValueChange={changePerPage}>
            <SelectTrigger className='h-8 w-[70px]'>
              <SelectValue placeholder={meta.per_page} />
            </SelectTrigger>
            <SelectContent side='top'>
              {[10, 25, 50, 100].map((pageSize) => (
                <SelectItem key={pageSize} value={`${pageSize}`}>
                  {pageSize}
                </SelectItem>
              ))}
            </SelectContent>
          </Select>
        </div>
      </div>
    )
  }

  return (
    <div className='flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between'>
      <div className='flex items-center justify-between gap-4 sm:justify-start'>
        <div className='text-sm text-muted-foreground'>
          {t('pagination.showing_invoices', { from: meta.from, to: meta.to, total: meta.total })}
        </div>
        <div className='flex items-center space-x-2'>
          <p className='hidden sm:block text-sm font-medium'>{t('pagination.rows_per_page')}</p>
          <Select value={`${meta.per_page}`} onValueChange={changePerPage}>
            <SelectTrigger className='h-8 w-[70px]'>
              <SelectValue placeholder={meta.per_page} />
            </SelectTrigger>
            <SelectContent side='top'>
              {[10, 25, 50, 100].map((pageSize) => (
                <SelectItem key={pageSize} value={`${pageSize}`}>
                  {pageSize}
                </SelectItem>
              ))}
            </SelectContent>
          </Select>
        </div>
      </div>

      <div className='flex items-center justify-center gap-1'>
        {/* First */}
        <Button
          variant='outline'
          size='icon'
          className='h-8 w-8'
          onClick={() => goToPage(1)}
          disabled={!canGoPrevious}
        >
          <IconChevronsLeft className='h-4 w-4' />
        </Button>

        {/* Previous */}
        <Button
          variant='outline'
          size='icon'
          className='h-8 w-8'
          onClick={() => goToPage(meta.current_page - 1)}
          disabled={!canGoPrevious}
        >
          <IconChevronLeft className='h-4 w-4' />
        </Button>

        {/* Page numbers - hidden on mobile, show compact indicator instead */}
        <span className='sm:hidden px-2 text-sm text-muted-foreground whitespace-nowrap'>
          {meta.current_page} / {meta.last_page}
        </span>
        <div className='hidden sm:flex items-center gap-1'>
          {getPageNumbers().map((page, idx) =>
            page === '...' ? (
              <span key={`ellipsis-${idx}`} className='px-2 text-muted-foreground'>
                ...
              </span>
            ) : (
              <Button
                key={page}
                variant={page === meta.current_page ? 'default' : 'outline'}
                size='sm'
                className='h-8 w-8'
                onClick={() => goToPage(page as number)}
              >
                {page}
              </Button>
            )
          )}
        </div>

        {/* Next */}
        <Button
          variant='outline'
          size='icon'
          className='h-8 w-8'
          onClick={() => goToPage(meta.current_page + 1)}
          disabled={!canGoNext}
        >
          <IconChevronRight className='h-4 w-4' />
        </Button>

        {/* Last */}
        <Button
          variant='outline'
          size='icon'
          className='h-8 w-8'
          onClick={() => goToPage(meta.last_page)}
          disabled={!canGoNext}
        >
          <IconChevronsRight className='h-4 w-4' />
        </Button>
      </div>
    </div>
  )
}
