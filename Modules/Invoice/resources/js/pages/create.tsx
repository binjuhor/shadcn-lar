import { AuthenticatedLayout } from '@/layouts'
import { Main } from '@/components/layout/main'
import { InvoiceForm } from './components/invoice-form'

export default function CreateInvoice() {
  return (
    <AuthenticatedLayout title="Create Invoice">
      <Main>
        <div className="mb-6">
          <h2 className="text-2xl font-bold tracking-tight">Create Invoice</h2>
          <p className="text-muted-foreground">
            Fill in the details below to create a new invoice.
          </p>
        </div>
        <InvoiceForm />
      </Main>
    </AuthenticatedLayout>
  )
}
