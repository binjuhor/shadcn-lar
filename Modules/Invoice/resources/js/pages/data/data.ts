import {
  IconCircle,
  IconCircleCheck,
  IconClock,
  IconAlertCircle,
  IconCircleX,
} from '@tabler/icons-react'
import { InvoiceStatus } from './schema'

export const invoiceStatuses = [
  { value: 'draft' as const, label: 'Draft', icon: IconCircle },
  { value: 'sent' as const, label: 'Sent', icon: IconClock },
  { value: 'paid' as const, label: 'Paid', icon: IconCircleCheck },
  { value: 'overdue' as const, label: 'Overdue', icon: IconAlertCircle },
  { value: 'cancelled' as const, label: 'Cancelled', icon: IconCircleX },
]

export const statusColors = new Map<InvoiceStatus, string>([
  ['draft', 'bg-gray-100 text-gray-700 border-gray-200'],
  ['sent', 'bg-blue-100 text-blue-700 border-blue-200'],
  ['paid', 'bg-green-100 text-green-700 border-green-200'],
  ['overdue', 'bg-red-100 text-red-700 border-red-200'],
  ['cancelled', 'bg-gray-100 text-gray-400 border-gray-200'],
])
