import { AuthenticatedLayout } from '@/layouts'
import { Main } from '@/components/layout/main'
import { PageProps } from '@/types'
import { InvoiceForm } from './components/invoice-form'
import { Invoice } from './data/schema'

interface EditInvoiceProps extends PageProps {
  invoice: Invoice
}

export default function EditInvoice({ invoice }: EditInvoiceProps) {
  return (
    <AuthenticatedLayout title={`Edit Invoice #${invoice.invoice_number}`}>
      <Main>
        <div className="mb-6">
          <h2 className="text-2xl font-bold tracking-tight">
            Edit Invoice #{invoice.invoice_number}
          </h2>
          <p className="text-muted-foreground">
            Update the invoice details below.
          </p>
        </div>
        <InvoiceForm invoice={invoice} />
      </Main>
    </AuthenticatedLayout>
  )
}
