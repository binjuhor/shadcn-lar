import { useState } from 'react'
import { router } from '@inertiajs/react'
import { format } from 'date-fns'
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog'
import { Button } from '@/components/ui/button'
import { Label } from '@/components/ui/label'
import { DatePicker } from '@/components/ui/date-picker'
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select'
import { Pencil } from 'lucide-react'
import type { Account, Category } from '@modules/Finance/types/finance'

interface BulkEditDialogProps {
  open: boolean
  onOpenChange: (open: boolean) => void
  selectedIds: number[]
  accounts: Account[]
  categories: Category[]
  onSuccess: () => void
}

export function BulkEditDialog({
  open,
  onOpenChange,
  selectedIds,
  accounts,
  categories,
  onSuccess,
}: BulkEditDialogProps) {
  const [accountId, setAccountId] = useState<string>('')
  const [categoryId, setCategoryId] = useState<string>('')
  const [transactionDate, setTransactionDate] = useState<string>('')
  const [isSubmitting, setIsSubmitting] = useState(false)

  const handleSubmit = () => {
    if (!hasChanges()) return

    setIsSubmitting(true)

    const data: {
      transaction_ids: number[]
      account_id?: number
      category_id?: number
      transaction_date?: string
    } = {
      transaction_ids: selectedIds,
    }

    if (accountId && accountId !== 'none') {
      data.account_id = parseInt(accountId)
    }
    if (categoryId && categoryId !== 'none') {
      data.category_id = parseInt(categoryId)
    }
    if (transactionDate) {
      data.transaction_date = transactionDate
    }

    router.post(route('dashboard.finance.transactions.bulk-update'), data, {
      preserveScroll: true,
      onSuccess: () => {
        resetForm()
        onOpenChange(false)
        onSuccess()
      },
      onFinish: () => {
        setIsSubmitting(false)
      },
    })
  }

  const resetForm = () => {
    setAccountId('')
    setCategoryId('')
    setTransactionDate('')
  }

  const handleClose = (open: boolean) => {
    if (!open) {
      resetForm()
    }
    onOpenChange(open)
  }

  const hasChanges = () => {
    return (accountId && accountId !== 'none') ||
           (categoryId && categoryId !== 'none') ||
           transactionDate
  }

  return (
    <Dialog open={open} onOpenChange={handleClose}>
      <DialogContent className="sm:max-w-md">
        <DialogHeader>
          <DialogTitle>Bulk Edit Transactions</DialogTitle>
          <DialogDescription>
            Update {selectedIds.length} selected transaction{selectedIds.length !== 1 ? 's' : ''}.
            Leave fields empty to keep their current values.
          </DialogDescription>
        </DialogHeader>

        <div className="space-y-4 py-4">
          <div className="space-y-2">
            <Label>Account</Label>
            <Select value={accountId} onValueChange={setAccountId}>
              <SelectTrigger>
                <SelectValue placeholder="Keep current account" />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="none">Keep current account</SelectItem>
                {accounts.map((account) => (
                  <SelectItem key={account.id} value={String(account.id)}>
                    {account.name}
                  </SelectItem>
                ))}
              </SelectContent>
            </Select>
          </div>

          <div className="space-y-2">
            <Label>Category</Label>
            <Select value={categoryId} onValueChange={setCategoryId}>
              <SelectTrigger>
                <SelectValue placeholder="Keep current category" />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="none">Keep current category</SelectItem>
                {categories.map((category) => (
                  <SelectItem key={category.id} value={String(category.id)}>
                    {category.name}
                  </SelectItem>
                ))}
              </SelectContent>
            </Select>
          </div>

          <div className="space-y-2">
            <Label>Transaction Date</Label>
            <DatePicker
              value={transactionDate}
              onChange={(date) => setTransactionDate(date ? format(date, 'yyyy-MM-dd') : '')}
              placeholder="Keep current date"
            />
          </div>

          <p className="text-sm text-muted-foreground">
            Note: Transfer transactions will be skipped automatically.
          </p>
        </div>

        <DialogFooter>
          <Button variant="outline" onClick={() => handleClose(false)}>
            Cancel
          </Button>
          <Button onClick={handleSubmit} disabled={!hasChanges() || isSubmitting}>
            <Pencil className="mr-2 h-4 w-4" />
            {isSubmitting ? 'Updating...' : 'Update Selected'}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  )
}
