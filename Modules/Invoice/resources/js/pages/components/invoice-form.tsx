import { useMemo, useState } from 'react'
import { useForm } from '@inertiajs/react'
import { format } from 'date-fns'
import { CalendarIcon } from 'lucide-react'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Textarea } from '@/components/ui/textarea'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Label } from '@/components/ui/label'
import { Calendar } from '@/components/ui/calendar'
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover'
import { SelectDropdown } from '@/components/select-dropdown'
import { LineItemsInput } from './line-items-input'
import { InvoiceSummary } from './invoice-summary'
import { Invoice, LineItem } from '../data/schema'
import { invoiceStatuses } from '../data/data'
import { cn } from '@/lib/utils'

function generateId(): string {
  return `${Date.now()}-${Math.random().toString(36).substring(2, 11)}`
}

interface Props {
  invoice?: Invoice
}

export function InvoiceForm({ invoice }: Props) {
  const isEdit = !!invoice

  const parseDate = (dateStr: string | undefined): Date | undefined => {
    if (!dateStr) return undefined
    const d = new Date(dateStr)
    return isNaN(d.getTime()) ? undefined : d
  }

  const [invoiceDate, setInvoiceDate] = useState<Date | undefined>(
    parseDate(invoice?.invoice_date) || new Date()
  )
  const [dueDate, setDueDate] = useState<Date | undefined>(
    parseDate(invoice?.due_date)
  )

  const { data, setData, post, put, processing, errors } = useForm({
    invoice_date: invoiceDate ? format(invoiceDate, 'yyyy-MM-dd') : '',
    due_date: dueDate ? format(dueDate, 'yyyy-MM-dd') : '',
    from_name: invoice?.from_name || '',
    from_address: invoice?.from_address || '',
    from_email: invoice?.from_email || '',
    from_phone: invoice?.from_phone || '',
    to_name: invoice?.to_name || '',
    to_address: invoice?.to_address || '',
    to_email: invoice?.to_email || '',
    tax_rate: invoice?.tax_rate ? Number(invoice.tax_rate) : 0.1,
    notes: invoice?.notes || '',
    status: invoice?.status || 'draft',
    items: invoice?.items?.map(item => ({
      id: String(item.id || generateId()),
      description: item.description,
      quantity: Number(item.quantity),
      unit_price: Number(item.unit_price),
    })) || [{ id: generateId(), description: '', quantity: 1, unit_price: 0 }],
  })

  const totals = useMemo(() => {
    const subtotal = data.items.reduce((sum, item) => sum + (item.quantity * item.unit_price), 0)
    const tax = subtotal * data.tax_rate
    return { subtotal, tax, total: subtotal + tax }
  }, [data.items, data.tax_rate])

  const handleItemChange = (index: number, field: keyof LineItem, value: string | number) => {
    const updated = data.items.map((item, i) =>
      i === index ? { ...item, [field]: value } : item
    )
    setData('items', updated)
  }

  const addItem = () => {
    setData('items', [...data.items, { id: generateId(), description: '', quantity: 1, unit_price: 0 }])
  }

  const removeItem = (index: number) => {
    setData('items', data.items.filter((_, i) => i !== index))
  }

  const handleInvoiceDateChange = (date: Date | undefined) => {
    setInvoiceDate(date)
    setData('invoice_date', date ? format(date, 'yyyy-MM-dd') : '')
  }

  const handleDueDateChange = (date: Date | undefined) => {
    setDueDate(date)
    setData('due_date', date ? format(date, 'yyyy-MM-dd') : '')
  }

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault()
    if (isEdit && invoice) {
      put(route('dashboard.invoices.update', invoice.id))
    } else {
      post(route('dashboard.invoices.store'))
    }
  }

  return (
    <form onSubmit={handleSubmit} className="space-y-6">
      <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
        <Card>
          <CardHeader><CardTitle>From</CardTitle></CardHeader>
          <CardContent className="space-y-4">
            <div>
              <Label>Business Name *</Label>
              <Input
                value={data.from_name}
                onChange={e => setData('from_name', e.target.value)}
                placeholder="Your business name"
              />
              {errors.from_name && <p className="text-sm text-red-500 mt-1">{errors.from_name}</p>}
            </div>
            <div>
              <Label>Address</Label>
              <Textarea
                value={data.from_address}
                onChange={e => setData('from_address', e.target.value)}
                placeholder="Street, City, State, ZIP"
                rows={3}
              />
            </div>
            <div className="grid grid-cols-2 gap-4">
              <div>
                <Label>Email</Label>
                <Input
                  type="email"
                  value={data.from_email}
                  onChange={e => setData('from_email', e.target.value)}
                  placeholder="email@example.com"
                />
              </div>
              <div>
                <Label>Phone</Label>
                <Input
                  value={data.from_phone}
                  onChange={e => setData('from_phone', e.target.value)}
                  placeholder="+1 234 567 890"
                />
              </div>
            </div>
          </CardContent>
        </Card>

        <Card>
          <CardHeader><CardTitle>Bill To</CardTitle></CardHeader>
          <CardContent className="space-y-4">
            <div>
              <Label>Client Name *</Label>
              <Input
                value={data.to_name}
                onChange={e => setData('to_name', e.target.value)}
                placeholder="Client or company name"
              />
              {errors.to_name && <p className="text-sm text-red-500 mt-1">{errors.to_name}</p>}
            </div>
            <div>
              <Label>Address</Label>
              <Textarea
                value={data.to_address}
                onChange={e => setData('to_address', e.target.value)}
                placeholder="Street, City, State, ZIP"
                rows={3}
              />
            </div>
            <div>
              <Label>Email</Label>
              <Input
                type="email"
                value={data.to_email}
                onChange={e => setData('to_email', e.target.value)}
                placeholder="client@example.com"
              />
            </div>
          </CardContent>
        </Card>
      </div>

      <Card>
        <CardContent className="pt-6">
          <div className="grid grid-cols-1 md:grid-cols-4 gap-4">
            <div className="space-y-2">
              <Label>Invoice Date *</Label>
              <Popover>
                <PopoverTrigger asChild>
                  <Button
                    variant="outline"
                    className={cn(
                      "w-full justify-start text-left font-normal",
                      !invoiceDate && "text-muted-foreground"
                    )}
                  >
                    <CalendarIcon className="mr-2 h-4 w-4" />
                    {invoiceDate ? format(invoiceDate, "PPP") : <span>Pick a date</span>}
                  </Button>
                </PopoverTrigger>
                <PopoverContent className="w-auto p-0" align="start">
                  <Calendar
                    mode="single"
                    selected={invoiceDate}
                    defaultMonth={invoiceDate}
                    onSelect={handleInvoiceDateChange}
                    initialFocus
                    captionLayout="dropdown"
                    fromYear={2000}
                    toYear={new Date().getFullYear() + 10}
                  />
                </PopoverContent>
              </Popover>
              {errors.invoice_date && <p className="text-sm text-red-500">{errors.invoice_date}</p>}
            </div>
            <div className="space-y-2">
              <Label>Due Date *</Label>
              <Popover>
                <PopoverTrigger asChild>
                  <Button
                    variant="outline"
                    className={cn(
                      "w-full justify-start text-left font-normal",
                      !dueDate && "text-muted-foreground"
                    )}
                  >
                    <CalendarIcon className="mr-2 h-4 w-4" />
                    {dueDate ? format(dueDate, "PPP") : <span>Pick a date</span>}
                  </Button>
                </PopoverTrigger>
                <PopoverContent className="w-auto p-0" align="start">
                  <Calendar
                    mode="single"
                    selected={dueDate}
                    defaultMonth={dueDate}
                    onSelect={handleDueDateChange}
                    initialFocus
                    captionLayout="dropdown"
                    fromYear={2000}
                    toYear={new Date().getFullYear() + 10}
                  />
                </PopoverContent>
              </Popover>
              {errors.due_date && <p className="text-sm text-red-500">{errors.due_date}</p>}
            </div>
            <div>
              <Label>Tax Rate (%)</Label>
              <Input
                type="number"
                step="0.01"
                min="0"
                max="100"
                value={data.tax_rate * 100}
                onChange={e => setData('tax_rate', (parseFloat(e.target.value) || 0) / 100)}
              />
            </div>
            {isEdit && (
              <div>
                <Label>Status</Label>
                <SelectDropdown
                  defaultValue={data.status}
                  onValueChange={(value) => setData('status', value as typeof data.status)}
                  placeholder="Select status"
                  items={invoiceStatuses.map(s => ({ label: s.label, value: s.value }))}
                />
              </div>
            )}
          </div>
        </CardContent>
      </Card>

      <Card>
        <CardHeader><CardTitle>Items</CardTitle></CardHeader>
        <CardContent>
          <LineItemsInput
            items={data.items}
            onItemChange={handleItemChange}
            onAddItem={addItem}
            onRemoveItem={removeItem}
          />
          {errors.items && <p className="text-sm text-red-500 mt-2">{errors.items}</p>}
        </CardContent>
      </Card>

      <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
        <Card>
          <CardHeader><CardTitle>Notes</CardTitle></CardHeader>
          <CardContent>
            <Textarea
              value={data.notes}
              onChange={e => setData('notes', e.target.value)}
              placeholder="Additional notes or payment instructions..."
              rows={4}
            />
          </CardContent>
        </Card>
        <InvoiceSummary totals={totals} taxRate={data.tax_rate} />
      </div>

      <div className="flex justify-end gap-4">
        <Button type="button" variant="outline" onClick={() => history.back()}>
          Cancel
        </Button>
        <Button type="submit" disabled={processing}>
          {processing ? 'Saving...' : (isEdit ? 'Update Invoice' : 'Create Invoice')}
        </Button>
      </div>
    </form>
  )
}
