import { z } from 'zod'

export const invoiceStatusSchema = z.enum([
  'draft', 'sent', 'paid', 'overdue', 'cancelled'
])
export type InvoiceStatus = z.infer<typeof invoiceStatusSchema>

export const lineItemSchema = z.object({
  id: z.union([z.string(), z.number()]).optional(),
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
  subtotal: z.union([z.string(), z.number()]),
  tax_rate: z.union([z.string(), z.number()]),
  tax_amount: z.union([z.string(), z.number()]),
  total: z.union([z.string(), z.number()]),
  notes: z.string().nullable(),
  items: z.array(lineItemSchema),
  created_at: z.string(),
  updated_at: z.string(),
})
export type Invoice = z.infer<typeof invoiceSchema>

export const invoiceListSchema = z.array(invoiceSchema)
