import { useForm } from '@inertiajs/react'
import {
  AlertDialog,
  AlertDialogAction,
  AlertDialogCancel,
  AlertDialogContent,
  AlertDialogDescription,
  AlertDialogFooter,
  AlertDialogHeader,
  AlertDialogTitle,
} from '@/components/ui/alert-dialog'
import { Invoice } from '../data/schema'

interface Props {
  invoice: Invoice
  open: boolean
  onOpenChange: (open: boolean) => void
}

export function InvoicesDeleteDialog({ invoice, open, onOpenChange }: Props) {
  const { delete: destroy, processing } = useForm()

  const handleDelete = () => {
    destroy(route('dashboard.invoices.destroy', invoice.id), {
      onSuccess: () => {
        onOpenChange(false)
      },
    })
  }

  return (
    <AlertDialog open={open} onOpenChange={onOpenChange}>
      <AlertDialogContent>
        <AlertDialogHeader>
          <AlertDialogTitle>Delete Invoice</AlertDialogTitle>
          <AlertDialogDescription>
            Are you sure you want to delete invoice{' '}
            <strong>{invoice.invoice_number}</strong>? This action cannot be
            undone.
          </AlertDialogDescription>
        </AlertDialogHeader>
        <AlertDialogFooter>
          <AlertDialogCancel>Cancel</AlertDialogCancel>
          <AlertDialogAction
            onClick={handleDelete}
            disabled={processing}
            className='bg-red-600 hover:bg-red-700'
          >
            {processing ? 'Deleting...' : 'Delete'}
          </AlertDialogAction>
        </AlertDialogFooter>
      </AlertDialogContent>
    </AlertDialog>
  )
}
