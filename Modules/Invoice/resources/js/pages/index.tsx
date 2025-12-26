import { AuthenticatedLayout } from '@/layouts'
import { Main } from '@/components/layout/main'
import { PageProps } from '@/types'
import { columns } from './components/invoices-columns'
import { InvoicesDialogs } from './components/invoices-dialogs'
import { InvoicesPrimaryButtons } from './components/invoices-primary-buttons'
import { InvoicesTable } from './components/invoices-table'
import InvoicesProvider from './context/invoices-context'
import { Invoice } from './data/schema'

interface PaginatedInvoices {
  data: Invoice[]
  current_page: number
  last_page: number
  per_page: number
  total: number
}

interface InvoicesPageProps extends PageProps {
  invoices: PaginatedInvoices
}

const defaultPagination: PaginatedInvoices = {
  data: [],
  current_page: 1,
  last_page: 1,
  per_page: 15,
  total: 0,
}

export default function Invoices({ invoices = defaultPagination }: InvoicesPageProps) {
  const safeInvoices: PaginatedInvoices = {
    data: invoices?.data ?? [],
    current_page: invoices?.current_page ?? 1,
    last_page: invoices?.last_page ?? 1,
    per_page: invoices?.per_page ?? 15,
    total: invoices?.total ?? 0,
  }

  return (
    <InvoicesProvider>
      <AuthenticatedLayout title="Invoices">
        <Main>
          <div className="mb-2 flex items-center justify-between space-y-2 flex-wrap">
            <div>
              <h2 className="text-2xl font-bold tracking-tight">Invoices</h2>
              <p className="text-muted-foreground">
                Manage your invoices and billing here.
              </p>
            </div>
            <InvoicesPrimaryButtons />
          </div>
          <div className="-mx-4 flex-1 overflow-auto px-4 py-1 lg:flex-row lg:space-x-12 lg:space-y-0">
            <InvoicesTable data={safeInvoices.data} columns={columns} />
          </div>
        </Main>

        <InvoicesDialogs />
      </AuthenticatedLayout>
    </InvoicesProvider>
  )
}
