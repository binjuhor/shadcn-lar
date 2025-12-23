# Phase 03: Frontend Components

**Status:** Pending
**Estimated Effort:** 5-6 hours
**Depends On:** Phase 01, Phase 02

---

## Context Links

- [Main Plan](./plan.md)
- [Phase 02: Backend API](./phase-02-backend-api.md)
- [React UI Patterns Research](./research/researcher-02-react-invoice-ui-patterns.md)
- Pattern reference: `/Users/binjuhor/Development/shadcn-admin/resources/js/pages/users/`

---

## Overview

Create React/TypeScript components following users module pattern. Main components: list page with TanStack Table, create/edit forms with dynamic line items, show page with PDF download. Use Inertia.js useForm hook, ShadcnUI components, Zod validation.

---

## Key Insights

- Follow users module structure exactly (index.tsx, components/, context/, data/)
- Use `type` not `interface` for Inertia form data (better TypeScript compat)
- Line items: immutable array operations (map/filter/concat)
- Calculated totals via useMemo, never store in state
- Context provider for dialog state management
- TanStack React Table for list with sorting/filtering

---

## Requirements

1. Invoice list page (index.tsx) with table
2. Create/edit form with dynamic line items
3. Show page with invoice details
4. Context provider for dialogs
5. Zod schemas for validation
6. Status badge component

---

## Architecture

### Folder Structure

```
resources/js/pages/invoices/
├── index.tsx                    # List page
├── create.tsx                   # Create form page
├── edit.tsx                     # Edit form page
├── show.tsx                     # Invoice detail page
├── components/
│   ├── invoices-table.tsx       # TanStack table
│   ├── invoices-columns.tsx     # Column definitions
│   ├── invoices-dialogs.tsx     # Dialog wrapper
│   ├── invoices-delete-dialog.tsx
│   ├── invoices-primary-buttons.tsx
│   ├── invoice-form.tsx         # Shared form component
│   ├── line-items-input.tsx     # Dynamic line items
│   ├── line-item-row.tsx        # Single line item
│   ├── invoice-summary.tsx      # Totals display
│   ├── status-badge.tsx         # Status indicator
│   ├── data-table-toolbar.tsx   # Filters
│   ├── data-table-pagination.tsx
│   ├── data-table-row-actions.tsx
│   └── data-table-column-header.tsx
├── context/
│   └── invoices-context.tsx     # Dialog state
└── data/
    ├── schema.ts                # Zod schemas
    └── data.ts                  # Status colors, etc.
```

---

## Related Code Files

**Create:**
- `/Users/binjuhor/Development/shadcn-admin/resources/js/pages/invoices/index.tsx`
- `/Users/binjuhor/Development/shadcn-admin/resources/js/pages/invoices/create.tsx`
- `/Users/binjuhor/Development/shadcn-admin/resources/js/pages/invoices/edit.tsx`
- `/Users/binjuhor/Development/shadcn-admin/resources/js/pages/invoices/show.tsx`
- All components in `/Users/binjuhor/Development/shadcn-admin/resources/js/pages/invoices/components/`
- `/Users/binjuhor/Development/shadcn-admin/resources/js/pages/invoices/context/invoices-context.tsx`
- `/Users/binjuhor/Development/shadcn-admin/resources/js/pages/invoices/data/schema.ts`
- `/Users/binjuhor/Development/shadcn-admin/resources/js/pages/invoices/data/data.ts`

**Reference (copy patterns from):**
- `/Users/binjuhor/Development/shadcn-admin/resources/js/pages/users/index.tsx`
- `/Users/binjuhor/Development/shadcn-admin/resources/js/pages/users/components/users-table.tsx`
- `/Users/binjuhor/Development/shadcn-admin/resources/js/pages/users/components/users-columns.tsx`
- `/Users/binjuhor/Development/shadcn-admin/resources/js/pages/users/components/users-action-dialog.tsx`
- `/Users/binjuhor/Development/shadcn-admin/resources/js/pages/users/context/users-context.tsx`
- `/Users/binjuhor/Development/shadcn-admin/resources/js/pages/users/data/schema.ts`

---

## Implementation Steps

### 1. Create Zod Schemas

```typescript
// data/schema.ts
import { z } from 'zod'

export const invoiceStatusSchema = z.enum([
  'draft', 'sent', 'paid', 'overdue', 'cancelled'
])
export type InvoiceStatus = z.infer<typeof invoiceStatusSchema>

export const lineItemSchema = z.object({
  id: z.string().optional(),
  description: z.string().min(1, 'Description required'),
  quantity: z.number().min(0.01, 'Min 0.01'),
  unit_price: z.number().min(0, 'Min 0'),
  amount: z.number().optional(),
})
export type LineItem = z.infer<typeof lineItemSchema>

export const invoiceSchema = z.object({
  id: z.number(),
  invoice_number: z.string(),
  invoice_date: z.string(),
  due_date: z.string(),
  status: invoiceStatusSchema,
  from_name: z.string(),
  from_address: z.string().nullable(),
  from_email: z.string().nullable(),
  from_phone: z.string().nullable(),
  to_name: z.string(),
  to_address: z.string().nullable(),
  to_email: z.string().nullable(),
  subtotal: z.number(),
  tax_rate: z.number(),
  tax_amount: z.number(),
  total: z.number(),
  notes: z.string().nullable(),
  items: z.array(lineItemSchema),
  created_at: z.string(),
  updated_at: z.string(),
})
export type Invoice = z.infer<typeof invoiceSchema>

export const invoiceFormSchema = z.object({
  invoice_date: z.string().min(1, 'Invoice date required'),
  due_date: z.string().min(1, 'Due date required'),
  from_name: z.string().min(1, 'Business name required'),
  from_address: z.string().optional(),
  from_email: z.string().email().optional().or(z.literal('')),
  from_phone: z.string().optional(),
  to_name: z.string().min(1, 'Client name required'),
  to_address: z.string().optional(),
  to_email: z.string().email().optional().or(z.literal('')),
  tax_rate: z.number().min(0).max(1),
  notes: z.string().optional(),
  status: invoiceStatusSchema.optional(),
  items: z.array(lineItemSchema).min(1, 'At least one item required'),
})
export type InvoiceFormData = z.infer<typeof invoiceFormSchema>
```

### 2. Create Status Data

```typescript
// data/data.ts
import { IconCircle, IconCircleCheck, IconClock, IconAlertCircle, IconCircleX } from '@tabler/icons-react'

export const invoiceStatuses = [
  { value: 'draft', label: 'Draft', icon: IconCircle, color: 'text-gray-500 bg-gray-100' },
  { value: 'sent', label: 'Sent', icon: IconClock, color: 'text-blue-500 bg-blue-100' },
  { value: 'paid', label: 'Paid', icon: IconCircleCheck, color: 'text-green-500 bg-green-100' },
  { value: 'overdue', label: 'Overdue', icon: IconAlertCircle, color: 'text-red-500 bg-red-100' },
  { value: 'cancelled', label: 'Cancelled', icon: IconCircleX, color: 'text-gray-400 bg-gray-100' },
]

export const statusColors = new Map(
  invoiceStatuses.map(s => [s.value, s.color])
)
```

### 3. Create Context

```typescript
// context/invoices-context.tsx
import React, { useState } from 'react'
import useDialogState from '@/hooks/use-dialog-state'
import { Invoice } from '../data/schema'

type InvoicesDialogType = 'delete'

interface InvoicesContextType {
  open: InvoicesDialogType | null
  setOpen: (str: InvoicesDialogType | null) => void
  currentRow: Invoice | null
  setCurrentRow: React.Dispatch<React.SetStateAction<Invoice | null>>
}

const InvoicesContext = React.createContext<InvoicesContextType | null>(null)

export default function InvoicesProvider({ children }: { children: React.ReactNode }) {
  const [open, setOpen] = useDialogState<InvoicesDialogType>(null)
  const [currentRow, setCurrentRow] = useState<Invoice | null>(null)

  return (
    <InvoicesContext.Provider value={{ open, setOpen, currentRow, setCurrentRow }}>
      {children}
    </InvoicesContext.Provider>
  )
}

export const useInvoices = () => {
  const ctx = React.useContext(InvoicesContext)
  if (!ctx) throw new Error('useInvoices must be within InvoicesProvider')
  return ctx
}
```

### 4. Create Line Items Component

```typescript
// components/line-items-input.tsx
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { IconPlus, IconTrash } from '@tabler/icons-react'
import { LineItem } from '../data/schema'

interface Props {
  items: LineItem[]
  onItemChange: (index: number, field: keyof LineItem, value: string | number) => void
  onAddItem: () => void
  onRemoveItem: (index: number) => void
  errors?: Record<string, string>
}

export function LineItemsInput({ items, onItemChange, onAddItem, onRemoveItem, errors }: Props) {
  return (
    <div className="space-y-4">
      <div className="grid grid-cols-12 gap-2 font-medium text-sm">
        <div className="col-span-5">Description</div>
        <div className="col-span-2">Qty</div>
        <div className="col-span-2">Unit Price</div>
        <div className="col-span-2">Amount</div>
        <div className="col-span-1"></div>
      </div>

      {items.map((item, index) => (
        <div key={item.id || index} className="grid grid-cols-12 gap-2 items-center">
          <Input
            className="col-span-5"
            value={item.description}
            onChange={e => onItemChange(index, 'description', e.target.value)}
            placeholder="Item description"
          />
          <Input
            className="col-span-2"
            type="number"
            step="0.01"
            value={item.quantity}
            onChange={e => onItemChange(index, 'quantity', parseFloat(e.target.value) || 0)}
          />
          <Input
            className="col-span-2"
            type="number"
            step="0.01"
            value={item.unit_price}
            onChange={e => onItemChange(index, 'unit_price', parseFloat(e.target.value) || 0)}
          />
          <div className="col-span-2 text-right">
            ${(item.quantity * item.unit_price).toFixed(2)}
          </div>
          <Button
            type="button"
            variant="ghost"
            size="icon"
            className="col-span-1"
            onClick={() => onRemoveItem(index)}
            disabled={items.length === 1}
          >
            <IconTrash size={16} />
          </Button>
        </div>
      ))}

      <Button type="button" variant="outline" onClick={onAddItem}>
        <IconPlus size={16} className="mr-2" /> Add Item
      </Button>
    </div>
  )
}
```

### 5. Create Invoice Form Component

```typescript
// components/invoice-form.tsx
import { useMemo } from 'react'
import { useForm } from '@inertiajs/react'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Textarea } from '@/components/ui/textarea'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Label } from '@/components/ui/label'
import { LineItemsInput } from './line-items-input'
import { InvoiceSummary } from './invoice-summary'
import { Invoice, LineItem } from '../data/schema'

interface Props {
  invoice?: Invoice
}

export function InvoiceForm({ invoice }: Props) {
  const isEdit = !!invoice

  const { data, setData, post, put, processing, errors } = useForm({
    invoice_date: invoice?.invoice_date || new Date().toISOString().split('T')[0],
    due_date: invoice?.due_date || '',
    from_name: invoice?.from_name || '',
    from_address: invoice?.from_address || '',
    from_email: invoice?.from_email || '',
    from_phone: invoice?.from_phone || '',
    to_name: invoice?.to_name || '',
    to_address: invoice?.to_address || '',
    to_email: invoice?.to_email || '',
    tax_rate: invoice?.tax_rate || 0.1,
    notes: invoice?.notes || '',
    status: invoice?.status || 'draft',
    items: invoice?.items || [{ id: crypto.randomUUID(), description: '', quantity: 1, unit_price: 0 }],
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
    setData('items', [...data.items, { id: crypto.randomUUID(), description: '', quantity: 1, unit_price: 0 }])
  }

  const removeItem = (index: number) => {
    setData('items', data.items.filter((_, i) => i !== index))
  }

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault()
    if (isEdit) {
      put(route('invoices.update', invoice.id))
    } else {
      post(route('invoices.store'))
    }
  }

  return (
    <form onSubmit={handleSubmit} className="space-y-6">
      <div className="grid grid-cols-2 gap-6">
        {/* From Section */}
        <Card>
          <CardHeader><CardTitle>From</CardTitle></CardHeader>
          <CardContent className="space-y-4">
            <div>
              <Label>Business Name *</Label>
              <Input value={data.from_name} onChange={e => setData('from_name', e.target.value)} />
              {errors.from_name && <p className="text-sm text-red-500">{errors.from_name}</p>}
            </div>
            <div>
              <Label>Address</Label>
              <Textarea value={data.from_address} onChange={e => setData('from_address', e.target.value)} />
            </div>
            <div className="grid grid-cols-2 gap-4">
              <div>
                <Label>Email</Label>
                <Input type="email" value={data.from_email} onChange={e => setData('from_email', e.target.value)} />
              </div>
              <div>
                <Label>Phone</Label>
                <Input value={data.from_phone} onChange={e => setData('from_phone', e.target.value)} />
              </div>
            </div>
          </CardContent>
        </Card>

        {/* To Section */}
        <Card>
          <CardHeader><CardTitle>To</CardTitle></CardHeader>
          <CardContent className="space-y-4">
            <div>
              <Label>Client Name *</Label>
              <Input value={data.to_name} onChange={e => setData('to_name', e.target.value)} />
              {errors.to_name && <p className="text-sm text-red-500">{errors.to_name}</p>}
            </div>
            <div>
              <Label>Address</Label>
              <Textarea value={data.to_address} onChange={e => setData('to_address', e.target.value)} />
            </div>
            <div>
              <Label>Email</Label>
              <Input type="email" value={data.to_email} onChange={e => setData('to_email', e.target.value)} />
            </div>
          </CardContent>
        </Card>
      </div>

      {/* Dates */}
      <Card>
        <CardContent className="pt-6">
          <div className="grid grid-cols-3 gap-4">
            <div>
              <Label>Invoice Date *</Label>
              <Input type="date" value={data.invoice_date} onChange={e => setData('invoice_date', e.target.value)} />
            </div>
            <div>
              <Label>Due Date *</Label>
              <Input type="date" value={data.due_date} onChange={e => setData('due_date', e.target.value)} />
            </div>
            <div>
              <Label>Tax Rate (%)</Label>
              <Input
                type="number"
                step="0.01"
                value={data.tax_rate * 100}
                onChange={e => setData('tax_rate', parseFloat(e.target.value) / 100 || 0)}
              />
            </div>
          </div>
        </CardContent>
      </Card>

      {/* Line Items */}
      <Card>
        <CardHeader><CardTitle>Items</CardTitle></CardHeader>
        <CardContent>
          <LineItemsInput
            items={data.items}
            onItemChange={handleItemChange}
            onAddItem={addItem}
            onRemoveItem={removeItem}
            errors={errors}
          />
        </CardContent>
      </Card>

      {/* Summary & Notes */}
      <div className="grid grid-cols-2 gap-6">
        <Card>
          <CardHeader><CardTitle>Notes</CardTitle></CardHeader>
          <CardContent>
            <Textarea
              value={data.notes}
              onChange={e => setData('notes', e.target.value)}
              placeholder="Additional notes..."
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
```

### 6. Create Index Page

```typescript
// index.tsx
import { AuthenticatedLayout } from '@/layouts'
import { Main } from '@/components/layout/main'
import { columns } from './components/invoices-columns'
import { InvoicesDialogs } from './components/invoices-dialogs'
import { InvoicesPrimaryButtons } from './components/invoices-primary-buttons'
import { InvoicesTable } from './components/invoices-table'
import InvoicesProvider from './context/invoices-context'
import { Invoice } from './data/schema'

interface Props {
  invoices: {
    data: Invoice[]
    // pagination props
  }
}

export default function Invoices({ invoices }: Props) {
  return (
    <InvoicesProvider>
      <AuthenticatedLayout title="Invoices">
        <Main>
          <div className="mb-2 flex items-center justify-between space-y-2 flex-wrap">
            <div>
              <h2 className="text-2xl font-bold tracking-tight">Invoices</h2>
              <p className="text-muted-foreground">
                Manage your invoices here.
              </p>
            </div>
            <InvoicesPrimaryButtons />
          </div>
          <div className="-mx-4 flex-1 overflow-auto px-4 py-1">
            <InvoicesTable data={invoices.data} columns={columns} />
          </div>
        </Main>
        <InvoicesDialogs />
      </AuthenticatedLayout>
    </InvoicesProvider>
  )
}
```

---

## Todo List

- [ ] Create data/schema.ts with Zod schemas
- [ ] Create data/data.ts with status colors
- [ ] Create context/invoices-context.tsx
- [ ] Create components/invoices-table.tsx (copy users-table pattern)
- [ ] Create components/invoices-columns.tsx
- [ ] Create components/invoices-primary-buttons.tsx
- [ ] Create components/invoices-dialogs.tsx
- [ ] Create components/invoices-delete-dialog.tsx
- [ ] Create components/line-items-input.tsx
- [ ] Create components/line-item-row.tsx
- [ ] Create components/invoice-summary.tsx
- [ ] Create components/invoice-form.tsx
- [ ] Create components/status-badge.tsx
- [ ] Create index.tsx (list page)
- [ ] Create create.tsx
- [ ] Create edit.tsx
- [ ] Create show.tsx
- [ ] Copy data-table-* components from users
- [ ] Test all components render

---

## Success Criteria

1. List page shows invoices with sorting/filtering
2. Create form saves invoice with line items
3. Edit form loads and updates correctly
4. Show page displays invoice details
5. PDF download button works
6. Delete dialog works
7. Totals calculate correctly in real-time
8. Form validation shows errors

---

## Risk Assessment

| Risk | Likelihood | Impact | Mitigation |
|------|------------|--------|------------|
| Complex form state | Medium | Medium | Follow research patterns |
| TypeScript errors | Medium | Low | Test incrementally |
| Styling inconsistencies | Low | Low | Copy existing patterns |

---

## Security Considerations

- Client-side validation is UX only; server validates
- Sanitize display data (already handled by React)
- No sensitive data exposed to client

---

## Next Steps

After completing Phase 03:
1. Test all UI flows manually
2. Proceed to Phase 04: Integration & Testing
