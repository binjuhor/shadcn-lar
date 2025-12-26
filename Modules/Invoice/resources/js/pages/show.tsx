import { Link } from '@inertiajs/react'
import { AuthenticatedLayout } from '@/layouts'
import { Main } from '@/components/layout/main'
import { PageProps } from '@/types'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Badge } from '@/components/ui/badge'
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table'
import { IconEdit, IconDownload, IconArrowLeft } from '@tabler/icons-react'
import { cn } from '@/lib/utils'
import { Invoice } from './data/schema'
import { statusColors } from './data/data'

interface ShowInvoiceProps extends PageProps {
  invoice: Invoice
}

export default function ShowInvoice({ invoice }: ShowInvoiceProps) {
  const subtotal = Number(invoice.subtotal)
  const taxAmount = Number(invoice.tax_amount)
  const total = Number(invoice.total)

  return (
    <AuthenticatedLayout title={`Invoice #${invoice.invoice_number}`}>
      <Main>
        <div className="mb-6 flex items-center justify-between flex-wrap gap-4">
          <div className="flex items-center gap-4">
            <Button variant="ghost" size="icon" asChild>
              <Link href={route('dashboard.invoices.index')}>
                <IconArrowLeft size={20} />
              </Link>
            </Button>
            <div>
              <h2 className="text-2xl font-bold tracking-tight">
                Invoice #{invoice.invoice_number}
              </h2>
              <Badge
                variant="outline"
                className={cn('capitalize mt-1', statusColors.get(invoice.status))}
              >
                {invoice.status}
              </Badge>
            </div>
          </div>
          <div className="flex gap-2">
            <Button
              variant="outline"
              onClick={() => window.open(route('dashboard.invoices.pdf', invoice.id), '_blank')}
            >
              <IconDownload size={18} className="mr-1" /> Download PDF
            </Button>
            <Button asChild>
              <Link href={route('dashboard.invoices.edit', invoice.id)}>
                <IconEdit size={18} className="mr-1" /> Edit
              </Link>
            </Button>
          </div>
        </div>

        <div className="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
          <Card>
            <CardHeader>
              <CardTitle>From</CardTitle>
            </CardHeader>
            <CardContent className="space-y-1">
              <p className="font-medium">{invoice.from_name}</p>
              {invoice.from_address && (
                <p className="text-sm text-muted-foreground whitespace-pre-line">
                  {invoice.from_address}
                </p>
              )}
              {invoice.from_email && (
                <p className="text-sm text-muted-foreground">{invoice.from_email}</p>
              )}
              {invoice.from_phone && (
                <p className="text-sm text-muted-foreground">{invoice.from_phone}</p>
              )}
            </CardContent>
          </Card>

          <Card>
            <CardHeader>
              <CardTitle>Bill To</CardTitle>
            </CardHeader>
            <CardContent className="space-y-1">
              <p className="font-medium">{invoice.to_name}</p>
              {invoice.to_address && (
                <p className="text-sm text-muted-foreground whitespace-pre-line">
                  {invoice.to_address}
                </p>
              )}
              {invoice.to_email && (
                <p className="text-sm text-muted-foreground">{invoice.to_email}</p>
              )}
            </CardContent>
          </Card>
        </div>

        <Card className="mb-6">
          <CardContent className="pt-6">
            <div className="grid grid-cols-2 md:grid-cols-4 gap-4 text-sm">
              <div>
                <p className="text-muted-foreground">Invoice Date</p>
                <p className="font-medium">
                  {new Date(invoice.invoice_date).toLocaleDateString()}
                </p>
              </div>
              <div>
                <p className="text-muted-foreground">Due Date</p>
                <p className="font-medium">
                  {new Date(invoice.due_date).toLocaleDateString()}
                </p>
              </div>
              <div>
                <p className="text-muted-foreground">Tax Rate</p>
                <p className="font-medium">{(Number(invoice.tax_rate) * 100).toFixed(1)}%</p>
              </div>
              <div>
                <p className="text-muted-foreground">Created</p>
                <p className="font-medium">
                  {new Date(invoice.created_at).toLocaleDateString()}
                </p>
              </div>
            </div>
          </CardContent>
        </Card>

        <Card className="mb-6">
          <CardHeader>
            <CardTitle>Items</CardTitle>
          </CardHeader>
          <CardContent>
            <Table>
              <TableHeader>
                <TableRow>
                  <TableHead className="w-[50%]">Description</TableHead>
                  <TableHead className="text-right">Qty</TableHead>
                  <TableHead className="text-right">Unit Price</TableHead>
                  <TableHead className="text-right">Amount</TableHead>
                </TableRow>
              </TableHeader>
              <TableBody>
                {invoice.items.map((item, index) => {
                  const amount = Number(item.quantity) * Number(item.unit_price)
                  return (
                    <TableRow key={item.id || index}>
                      <TableCell>{item.description}</TableCell>
                      <TableCell className="text-right">{item.quantity}</TableCell>
                      <TableCell className="text-right">
                        ${Number(item.unit_price).toFixed(2)}
                      </TableCell>
                      <TableCell className="text-right font-medium">
                        ${amount.toFixed(2)}
                      </TableCell>
                    </TableRow>
                  )
                })}
              </TableBody>
            </Table>
          </CardContent>
        </Card>

        <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
          {invoice.notes && (
            <Card>
              <CardHeader>
                <CardTitle>Notes</CardTitle>
              </CardHeader>
              <CardContent>
                <p className="text-sm text-muted-foreground whitespace-pre-line">
                  {invoice.notes}
                </p>
              </CardContent>
            </Card>
          )}

          <Card className={invoice.notes ? '' : 'md:col-start-2'}>
            <CardHeader>
              <CardTitle>Summary</CardTitle>
            </CardHeader>
            <CardContent className="space-y-2">
              <div className="flex justify-between">
                <span className="text-muted-foreground">Subtotal</span>
                <span>${subtotal.toFixed(2)}</span>
              </div>
              <div className="flex justify-between">
                <span className="text-muted-foreground">
                  Tax ({(Number(invoice.tax_rate) * 100).toFixed(1)}%)
                </span>
                <span>${taxAmount.toFixed(2)}</span>
              </div>
              <div className="flex justify-between border-t pt-2 font-bold text-lg">
                <span>Total</span>
                <span>${total.toFixed(2)}</span>
              </div>
            </CardContent>
          </Card>
        </div>
      </Main>
    </AuthenticatedLayout>
  )
}
