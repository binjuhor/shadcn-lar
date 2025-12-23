import { Link } from '@inertiajs/react'
import { Button } from '@/components/ui/button'
import { IconPlus } from '@tabler/icons-react'

export function InvoicesPrimaryButtons() {
  return (
    <div className='flex gap-2'>
      <Button asChild>
        <Link href={route('dashboard.invoices.create')}>
          <IconPlus size={18} className='mr-1' /> New Invoice
        </Link>
      </Button>
    </div>
  )
}
