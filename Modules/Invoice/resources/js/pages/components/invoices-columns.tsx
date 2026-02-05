import { ColumnDef } from '@tanstack/react-table'
import { TFunction } from 'i18next'
import { cn } from '@/lib/utils'
import { Badge } from '@/components/ui/badge'
import { Checkbox } from '@/components/ui/checkbox'
import { Invoice, InvoiceStatus } from '../data/schema'
import { statusColors } from '../data/data'
import { DataTableColumnHeader } from './data-table-column-header'
import { DataTableRowActions } from './data-table-row-actions'

export const getColumns = (t: TFunction): ColumnDef<Invoice>[] => [
  {
    id: 'select',
    header: ({ table }) => (
      <Checkbox
        checked={
          table.getIsAllPageRowsSelected() ||
          (table.getIsSomePageRowsSelected() && 'indeterminate')
        }
        onCheckedChange={(value) => table.toggleAllPageRowsSelected(!!value)}
        aria-label={t('page.invoice.table.select_all')}
        className='translate-y-[2px]'
      />
    ),
    cell: ({ row }) => (
      <Checkbox
        checked={row.getIsSelected()}
        onCheckedChange={(value) => row.toggleSelected(!!value)}
        aria-label={t('page.invoice.table.select_row')}
        className='translate-y-[2px]'
      />
    ),
    enableSorting: false,
    enableHiding: false,
  },
  {
    accessorKey: 'invoice_number',
    header: ({ column }) => (
      <DataTableColumnHeader column={column} title={t('page.invoice.table.invoice_number')} />
    ),
    cell: ({ row }) => (
      <span className='font-medium'>{row.getValue('invoice_number')}</span>
    ),
    enableHiding: false,
  },
  {
    accessorKey: 'to_name',
    header: ({ column }) => (
      <DataTableColumnHeader column={column} title={t('page.invoice.table.client')} />
    ),
    cell: ({ row }) => (
      <div className='max-w-[200px] truncate'>{row.getValue('to_name')}</div>
    ),
  },
  {
    accessorKey: 'invoice_date',
    header: ({ column }) => (
      <DataTableColumnHeader column={column} title={t('page.invoice.table.date')} />
    ),
    cell: ({ row }) => {
      const date = new Date(row.getValue('invoice_date'))
      return <div>{date.toLocaleDateString()}</div>
    },
  },
  {
    accessorKey: 'due_date',
    header: ({ column }) => (
      <DataTableColumnHeader column={column} title={t('page.invoice.table.due_date')} />
    ),
    cell: ({ row }) => {
      const date = new Date(row.getValue('due_date'))
      return <div>{date.toLocaleDateString()}</div>
    },
  },
  {
    accessorKey: 'total',
    header: ({ column }) => (
      <DataTableColumnHeader column={column} title={t('page.invoice.table.total')} />
    ),
    cell: ({ row }) => {
      const total = Number(row.getValue('total'))
      return <div className='font-medium'>${total.toFixed(2)}</div>
    },
  },
  {
    accessorKey: 'status',
    header: ({ column }) => (
      <DataTableColumnHeader column={column} title={t('common.fields.status')} />
    ),
    cell: ({ row }) => {
      const status = row.getValue('status') as InvoiceStatus
      const colorClass = statusColors.get(status)
      return (
        <Badge variant='outline' className={cn('capitalize', colorClass)}>
          {status}
        </Badge>
      )
    },
    filterFn: (row, id, value) => {
      return value.includes(row.getValue(id))
    },
    enableSorting: false,
  },
  {
    id: 'actions',
    cell: DataTableRowActions,
  },
]
