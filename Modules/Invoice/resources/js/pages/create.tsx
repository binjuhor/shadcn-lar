import { AuthenticatedLayout } from '@/layouts'
import { Main } from '@/components/layout/main'
import { InvoiceForm } from './components/invoice-form'

interface InvoiceDefaults {
  from_name?: string
  from_address?: string
  from_email?: string
  from_phone?: string
  tax_rate?: number
  payment_terms?: number
}

interface Props {
  defaults?: InvoiceDefaults
}

export default function CreateInvoice({ defaults }: Props) {
  return (
    <AuthenticatedLayout title="Create Invoice">
      <Main>
        <div className="mb-6">
          <h2 className="text-2xl font-bold tracking-tight">Create Invoice</h2>
          <p className="text-muted-foreground">
            Fill in the details below to create a new invoice.
          </p>
        </div>
        <InvoiceForm defaults={defaults} />
      </Main>
    </AuthenticatedLayout>
  )
}
