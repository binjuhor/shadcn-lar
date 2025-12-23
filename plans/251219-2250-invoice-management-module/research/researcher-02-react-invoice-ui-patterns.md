# Research Report: React/TypeScript Invoice Management UI Patterns & Components

**Research Date:** December 19, 2025
**Plan:** Invoice Management Module
**Sources Consulted:** 7 primary sources
**Key Focus:** TypeScript, ShadcnUI, Inertia.js compatibility

---

## Executive Summary

Invoice management UIs in React/TypeScript require systematic patterns for form state, dynamic line items, and data visualization. Best practices center on React Hook Form + Inertia.js `useForm` for forms, TanStack Table + ShadcnUI Table for lists, and `useMemo` for calculated totals. Three critical architectural patterns emerged: (1) controlled components with array map/filter for line item mutation, (2) separate container-component pattern for form logic isolation, (3) calculated totals via reducer functions rather than imperative updates. ShadcnUI provides semantic table primitives; advanced features (filtering, sorting) require TanStack React Table integration.

---

## Key Findings

### 1. Invoice Form State Management Patterns

**Inertia.js `useForm` Hook (Preferred)**
- Handles form state, validation, submission automatically
- **Critical TypeScript consideration**: Use `type` not `interface` for form data shapes
  - `interface` doesn't allow excess properties without index signature
  - `type` is flexible for Inertia's generic dynamic key access: `[key: string]: any`
- Built-in support for file uploads, loading states, error handling
- Alternative: `useInertiaForm` hook (community) adds nested form data support

**Type-Safe Form Definition Pattern**
```typescript
type InvoiceFormData = {
  invoiceNumber: string;
  clientId: string;
  dueDate: string;
  lineItems: LineItem[];
  taxRate: number;
  notes?: string;
};

type LineItem = {
  id: string;
  description: string;
  quantity: number;
  unitPrice: number;
};

const { data, setData, post, errors, processing } = useForm<InvoiceFormData>({
  invoiceNumber: '',
  clientId: '',
  dueDate: '',
  lineItems: [{ id: '1', description: '', quantity: 1, unitPrice: 0 }],
  taxRate: 0.1,
  notes: ''
});
```

### 2. Dynamic Line Items Implementation

**Array Mutation Pattern (Core)**
- Store line items as typed array: `lineItems: LineItem[]`
- Add items: `concat()` creates new array with blank item
- Remove items: `filter()` excludes target index
- Update items: `map()` creates modified copy, preserving immutability

**Handler Pattern Using Closures**
```typescript
const handleLineItemChange = (index: number, field: keyof LineItem) =>
  (value: string | number) => {
    const updated = data.lineItems.map((item, i) =>
      i === index ? { ...item, [field]: value } : item
    );
    setData('lineItems', updated);
  };

const addLineItem = () => {
  setData('lineItems', [
    ...data.lineItems,
    { id: crypto.randomUUID(), description: '', quantity: 1, unitPrice: 0 }
  ]);
};

const removeLineItem = (index: number) => {
  setData('lineItems', data.lineItems.filter((_, i) => i !== index));
};
```

**Controlled Component Pattern**
- Each line item input: `value={item.field} onChange={handler}`
- Closure captures both index and field name
- Single source of truth: form state only

### 3. Calculating Totals Dynamically

**Preferred Pattern: `useMemo` with Reducer Function**
- Recalculate only when `lineItems` or `taxRate` change
- Never mutate state for calculations; derive from data

```typescript
const totals = useMemo(() => {
  const subtotal = data.lineItems.reduce(
    (sum, item) => sum + (item.quantity * item.unitPrice),
    0
  );
  const tax = subtotal * data.taxRate;
  return { subtotal, tax, total: subtotal + tax };
}, [data.lineItems, data.taxRate]);
```

**Anti-pattern:** Redux actions for every calculation = unnecessary complexity
**Note:** For complex invoices with discounts/adjustments, create typed helpers:

```typescript
type TotalCalculation = {
  subtotal: number;
  discountAmount: number;
  subtotalAfterDiscount: number;
  taxAmount: number;
  total: number;
};

function calculateTotals(
  lineItems: LineItem[],
  taxRate: number,
  discountPercent: number = 0
): TotalCalculation {
  const subtotal = lineItems.reduce((sum, item) => sum + (item.quantity * item.unitPrice), 0);
  const discountAmount = subtotal * discountPercent;
  const subtotalAfterDiscount = subtotal - discountAmount;
  const taxAmount = subtotalAfterDiscount * taxRate;

  return {
    subtotal,
    discountAmount,
    subtotalAfterDiscount,
    taxAmount,
    total: subtotalAfterDiscount + taxAmount
  };
}
```

### 4. ShadcnUI Component Mapping for Invoice UIs

| Component | Invoice Use Case | Pattern |
|-----------|------------------|---------|
| **Table** | Invoice list/history | Base primitive; use TanStack Table for advanced features |
| **Form** + **Input** | Invoice form fields | Controlled inputs with Inertia hooks |
| **Select** | Client selection, payment terms | Dropdown with filtered options |
| **Badge** | Status indicators (Draft, Sent, Paid) | Styled with conditional colors: `variant="default"` (draft), `variant="secondary"` (sent), `variant="success"` (paid) |
| **Card** | Form sections, summary cards | Container for line items, totals section |
| **Dialog** | Confirm send/delete invoice | Modal workflows |
| **Textarea** | Notes/memo field | Unbounded text input |
| **Button** | Submit, Add Line Item, Delete Row | With variant: `"primary"` (submit), `"secondary"` (add item), `"destructive"` (delete) |

### 5. Invoice List/Table Pattern

**Basic Table with ShadcnUI**
```typescript
<Table>
  <TableCaption>Recent Invoices</TableCaption>
  <TableHeader>
    <TableRow>
      <TableHead>Invoice #</TableHead>
      <TableHead>Client</TableHead>
      <TableHead>Amount</TableHead>
      <TableHead>Status</TableHead>
      <TableHead>Due Date</TableHead>
      <TableHead>Actions</TableHead>
    </TableRow>
  </TableHeader>
  <TableBody>
    {invoices.map(inv => (
      <TableRow key={inv.id}>
        <TableCell>{inv.invoiceNumber}</TableCell>
        <TableCell>{inv.client.name}</TableCell>
        <TableCell>${inv.total.toFixed(2)}</TableCell>
        <TableCell>
          <Badge variant={statusVariant(inv.status)}>
            {inv.status}
          </Badge>
        </TableCell>
        <TableCell>{formatDate(inv.dueDate)}</TableCell>
        <TableCell>
          <Link href={route('invoices.show', inv.id)}>View</Link>
        </TableCell>
      </TableRow>
    ))}
  </TableBody>
</Table>
```

**For Filtering + Sorting:** Integrate TanStack React Table
- Enables multi-column sorting
- Client-side or server-side filtering (with Inertia)
- Pagination controls
- Row selection for bulk actions

---

## Component Architecture Recommendations

### Folder Structure
```
resources/js/pages/invoices/
├── InvoiceList.tsx          # Page listing all invoices
├── InvoiceForm.tsx          # Form for create/edit
├── InvoiceShow.tsx          # Single invoice view
└── components/
    ├── InvoiceTable.tsx     # Invoice list table
    ├── LineItemsInput.tsx   # Line items editor
    ├── InvoiceSummary.tsx   # Totals section
    ├── StatusBadge.tsx      # Status display
    └── LineItemRow.tsx      # Single line item row
```

### Separation of Concerns
1. **Page component** (InvoiceForm.tsx): Handles Inertia form submission, validation errors
2. **Form logic** (useInvoiceForm.ts custom hook): Encapsulates state mutations, calculations
3. **UI components** (LineItemsInput, InvoiceSummary): Render-only, receive props from parent

### State Flow (Inertia + React Pattern)
```
InvoiceForm (useForm from Inertia)
  ├── LineItemsInput (controlled inputs)
  │   ├── LineItemRow (individual row with handlers)
  │   └── AddButton
  ├── InvoiceSummary (useMemo totals)
  └── SubmitButton (posts via Inertia)
```

---

## Best Practices Summary

1. **Forms:** Use Inertia.js `useForm` with `type` (not `interface`) for form data shapes
2. **Line Items:** Immutable array operations (map/filter/concat), never mutate directly
3. **Calculations:** `useMemo` for totals, never store calculated values in state
4. **Components:** Separate form logic from UI components; pass handlers as props
5. **Validation:** Leverage Inertia's built-in error display; use Laravel Precognition for real-time validation
6. **Tables:** ShadcnUI Table + TanStack React Table for advanced features (filtering, sorting, pagination)
7. **Status Badges:** Map status strings to Badge variants for consistent styling
8. **TypeScript:** Strict typing for LineItem, InvoiceFormData, TotalCalculation types

---

## Code Example: Complete Invoice Form Component

```typescript
import { useForm } from '@inertiajs/react';
import { FormEvent, useMemo } from 'react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Card } from '@/components/ui/card';
import LineItemsInput from './LineItemsInput';
import InvoiceSummary from './InvoiceSummary';

type InvoiceFormData = {
  invoiceNumber: string;
  clientId: string;
  dueDate: string;
  lineItems: Array<{
    id: string;
    description: string;
    quantity: number;
    unitPrice: number;
  }>;
  taxRate: number;
};

export default function InvoiceForm() {
  const { data, setData, post, errors, processing } = useForm<InvoiceFormData>({
    invoiceNumber: '',
    clientId: '',
    dueDate: '',
    lineItems: [{ id: '1', description: '', quantity: 1, unitPrice: 0 }],
    taxRate: 0.1,
  });

  const totals = useMemo(() => {
    const subtotal = data.lineItems.reduce(
      (sum, item) => sum + (item.quantity * item.unitPrice),
      0
    );
    const tax = subtotal * data.taxRate;
    return { subtotal, tax, total: subtotal + tax };
  }, [data.lineItems, data.taxRate]);

  const handleLineItemChange = (index: number, field: string) => (value: string | number) => {
    const updated = data.lineItems.map((item, i) =>
      i === index ? { ...item, [field]: value } : item
    );
    setData('lineItems', updated);
  };

  const addLineItem = () => {
    setData('lineItems', [
      ...data.lineItems,
      { id: crypto.randomUUID(), description: '', quantity: 1, unitPrice: 0 }
    ]);
  };

  const removeLineItem = (index: number) => {
    setData('lineItems', data.lineItems.filter((_, i) => i !== index));
  };

  const handleSubmit = (e: FormEvent) => {
    e.preventDefault();
    post(route('invoices.store'));
  };

  return (
    <form onSubmit={handleSubmit} className="space-y-6">
      <Card className="p-6">
        <div className="grid grid-cols-2 gap-4">
          <Input
            label="Invoice #"
            value={data.invoiceNumber}
            onChange={(e) => setData('invoiceNumber', e.target.value)}
            error={errors.invoiceNumber}
          />
          <Input
            label="Client ID"
            value={data.clientId}
            onChange={(e) => setData('clientId', e.target.value)}
            error={errors.clientId}
          />
          <Input
            label="Due Date"
            type="date"
            value={data.dueDate}
            onChange={(e) => setData('dueDate', e.target.value)}
            error={errors.dueDate}
          />
          <Input
            label="Tax Rate (%)"
            type="number"
            value={data.taxRate * 100}
            onChange={(e) => setData('taxRate', parseFloat(e.target.value) / 100)}
            error={errors.taxRate}
          />
        </div>
      </Card>

      <LineItemsInput
        items={data.lineItems}
        onItemChange={handleLineItemChange}
        onAddItem={addLineItem}
        onRemoveItem={removeLineItem}
        errors={errors}
      />

      <InvoiceSummary totals={totals} />

      <Button type="submit" disabled={processing}>
        {processing ? 'Saving...' : 'Save Invoice'}
      </Button>
    </form>
  );
}
```

---

## Unresolved Questions

1. **Decimal precision handling:** Should calculations use `Decimal.js` or native floats for currency?
2. **Discount/adjustment line items:** Should discounts be separate line items or form field?
3. **Line item templates:** Database pre-defined templates vs. free-form entries?
4. **Draft autosave:** Client-side localStorage persistence or server-side API calls?
5. **PDF generation:** Preferred library (react-pdf vs. html2pdf vs. server-side)?

---

## Sources

- [Creating an Invoice Component with Dynamic Line Items using React](https://firxworx.com/blog/code/creating-an-invoice-component-with-dynamic-line-items-using-react/)
- [ShadcnUI Table Component Documentation](https://ui.shadcn.com/docs/components/table)
- [Inertia.js Forms Guide](https://inertiajs.com/docs/v2/the-basics/forms)
- [Type-Safe Shared Data and Page Props in Inertia.js](https://laravel-news.com/type-safe-shared-data-and-page-props-in-inertiajs)
- [useInertiaForm Community Hook](https://github.com/aviemet/useInertiaForm)
- [React Hook Form Best Practices 2025](https://medium.com/@farzanekazemi8517/best-practices-for-handling-forms-in-react-2025-edition-62572b14452f)
- [Building Professional Invoice Generator with React + Redux Toolkit](https://arhaanali.medium.com/building-a-professional-invoice-generator-app-with-react-js-and-redux-toolkit-681d225f230b)
