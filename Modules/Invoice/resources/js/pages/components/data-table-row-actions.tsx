import { Row } from '@tanstack/react-table'
import { Link } from '@inertiajs/react'
import { Button } from '@/components/ui/button'
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu'
import { IconDots, IconEdit, IconEye, IconFileTypePdf, IconTrash } from '@tabler/icons-react'
import { Invoice } from '../data/schema'
import { useInvoices } from '../context/invoices-context'

interface DataTableRowActionsProps {
  row: Row<Invoice>
}

export function DataTableRowActions({ row }: DataTableRowActionsProps) {
  const { setOpen, setCurrentRow } = useInvoices()

  const handleDelete = () => {
    setCurrentRow(row.original)
    setOpen('delete')
  }

  return (
    <DropdownMenu>
      <DropdownMenuTrigger asChild>
        <Button
          variant='ghost'
          className='flex h-8 w-8 p-0 data-[state=open]:bg-muted'
        >
          <IconDots className='h-4 w-4' />
          <span className='sr-only'>Open menu</span>
        </Button>
      </DropdownMenuTrigger>
      <DropdownMenuContent align='end' className='w-[160px]'>
        <DropdownMenuItem asChild>
          <Link href={route('dashboard.invoices.show', row.original.id)}>
            <IconEye className='mr-2 h-4 w-4' />
            View
          </Link>
        </DropdownMenuItem>
        <DropdownMenuItem asChild>
          <Link href={route('dashboard.invoices.edit', row.original.id)}>
            <IconEdit className='mr-2 h-4 w-4' />
            Edit
          </Link>
        </DropdownMenuItem>
        <DropdownMenuItem
          onClick={() => {
            window.open(route('dashboard.invoices.pdf', row.original.id), '_blank')
          }}
        >
          <IconFileTypePdf className='mr-2 h-4 w-4' />
          Download PDF
        </DropdownMenuItem>
        <DropdownMenuSeparator />
        <DropdownMenuItem onClick={handleDelete} className='text-red-600'>
          <IconTrash className='mr-2 h-4 w-4' />
          Delete
        </DropdownMenuItem>
      </DropdownMenuContent>
    </DropdownMenu>
  )
}
