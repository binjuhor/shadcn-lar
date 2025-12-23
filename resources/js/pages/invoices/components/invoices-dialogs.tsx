import { useInvoices } from '../context/invoices-context'
import { InvoicesDeleteDialog } from './invoices-delete-dialog'

export function InvoicesDialogs() {
  const { open, setOpen, currentRow } = useInvoices()

  return (
    <>
      {currentRow && (
        <InvoicesDeleteDialog
          invoice={currentRow}
          open={open === 'delete'}
          onOpenChange={() => setOpen(null)}
        />
      )}
    </>
  )
}
