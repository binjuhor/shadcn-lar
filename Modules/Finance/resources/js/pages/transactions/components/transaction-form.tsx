import { useEffect, useState } from 'react'
import { useForm, router } from '@inertiajs/react'
import { useTranslation } from 'react-i18next'
import { format } from 'date-fns'
import { Button } from '@/components/ui/button'
import { DatePicker } from '@/components/ui/date-picker'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Textarea } from '@/components/ui/textarea'
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select'
import {
  Sheet,
  SheetContent,
  SheetDescription,
  SheetHeader,
  SheetTitle,
  SheetFooter,
} from '@/components/ui/sheet'
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs'
import type { Account, Category, Transaction, TransactionType } from '@modules/Finance/types/finance'

interface TransactionFormProps {
  open: boolean
  onOpenChange: (open: boolean) => void
  accounts: Account[]
  categories: Category[]
  transaction?: Transaction | null
  duplicateFrom?: Transaction | null
  onSuccess?: () => void
}

export function TransactionForm({
  open,
  onOpenChange,
  accounts,
  categories,
  transaction,
  duplicateFrom,
  onSuccess,
}: TransactionFormProps) {
  const { t } = useTranslation()
  const isEditing = !!transaction
  const [isSubmitting, setIsSubmitting] = useState(false)

  const { data, setData, post, processing, errors, reset } = useForm({
    type: 'expense' as TransactionType,
    account_id: '',
    category_id: '',
    amount: '',
    description: '',
    notes: '',
    transaction_date: new Date().toISOString().split('T')[0],
    transfer_account_id: '',
  })

  // Populate form when editing or duplicating
  useEffect(() => {
    if (transaction && open) {
      setData({
        type: transaction.type,
        account_id: String(transaction.account_id),
        category_id: transaction.category_id ? String(transaction.category_id) : '',
        amount: String(transaction.amount),
        description: transaction.description || '',
        notes: transaction.notes || '',
        transaction_date: transaction.transaction_date,
        transfer_account_id: transaction.transfer_account_id ? String(transaction.transfer_account_id) : '',
      })
    } else if (duplicateFrom && open) {
      setData({
        type: duplicateFrom.type === 'transfer' ? 'expense' : duplicateFrom.type,
        account_id: String(duplicateFrom.account_id),
        category_id: duplicateFrom.category_id ? String(duplicateFrom.category_id) : '',
        amount: String(duplicateFrom.amount),
        description: duplicateFrom.description || '',
        notes: duplicateFrom.notes || '',
        transaction_date: new Date().toISOString().split('T')[0],
        transfer_account_id: '',
      })
    } else if (!open) {
      reset()
    }
  }, [transaction, duplicateFrom, open])

  const incomeCategories = categories.filter((c) => c.type === 'income')
  const expenseCategories = categories.filter((c) => c.type === 'expense')
  const currentCategories = data.type === 'income' ? incomeCategories : expenseCategories

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault()

    const onSuccessCallback = () => {
      reset()
      onOpenChange(false)
      onSuccess?.()
    }

    if (isEditing) {
      setIsSubmitting(true)
      // Use router.put directly with explicit data mapping
      router.put(route('dashboard.finance.transactions.update', transaction.id), {
        transaction_type: data.type,
        account_id: parseInt(data.account_id),
        category_id: data.category_id ? parseInt(data.category_id) : null,
        amount: parseFloat(data.amount || '0'),
        description: data.description,
        notes: data.notes,
        transaction_date: data.transaction_date,
      }, {
        preserveScroll: true,
        onSuccess: onSuccessCallback,
        onFinish: () => setIsSubmitting(false),
      })
    } else {
      // Create new transaction
      const formData = {
        ...data,
        amount: parseFloat(data.amount || '0'),
        account_id: parseInt(data.account_id),
        category_id: data.category_id ? parseInt(data.category_id) : null,
        transfer_account_id: data.transfer_account_id ? parseInt(data.transfer_account_id) : null,
      }

      post(route('dashboard.finance.transactions.store'), {
        ...formData,
        onSuccess: onSuccessCallback,
      })
    }
  }

  const handleClose = () => {
    reset()
    onOpenChange(false)
  }

  const handleTypeChange = (type: TransactionType) => {
    setData('type', type)
    setData('category_id', '')
    if (type !== 'transfer') {
      setData('transfer_account_id', '')
    }
  }

  return (
    <Sheet open={open} onOpenChange={onOpenChange}>
      <SheetContent className="overflow-y-auto">
        <SheetHeader>
          <SheetTitle>{isEditing ? t('form.transaction.edit') : t('form.transaction.create')}</SheetTitle>
          <SheetDescription>
            {isEditing ? t('form.transaction.edit_description') : t('form.transaction.create_description')}
          </SheetDescription>
        </SheetHeader>

        <form onSubmit={handleSubmit} className="space-y-4 mt-4">
          {/* Transaction Type Tabs - disabled for transfer transactions when editing */}
          {(!isEditing || (isEditing && transaction?.type !== 'transfer')) && (
            <Tabs value={data.type} onValueChange={(v) => handleTypeChange(v as TransactionType)}>
              <TabsList className={`grid w-full ${isEditing ? 'grid-cols-2' : 'grid-cols-3'}`}>
                <TabsTrigger value="expense" className="text-red-600 dark:text-red-400 data-[state=active]:bg-red-100 dark:data-[state=active]:bg-red-900/50 data-[state=active]:text-red-700 dark:data-[state=active]:text-red-300">
                  {t('transaction.expense')}
                </TabsTrigger>
                <TabsTrigger value="income" className="text-green-600 dark:text-green-400 data-[state=active]:bg-green-100 dark:data-[state=active]:bg-green-900/50 data-[state=active]:text-green-700 dark:data-[state=active]:text-green-300">
                  {t('transaction.income')}
                </TabsTrigger>
                {!isEditing && (
                  <TabsTrigger value="transfer" className="text-blue-600 dark:text-blue-400 data-[state=active]:bg-blue-100 dark:data-[state=active]:bg-blue-900/50 data-[state=active]:text-blue-700 dark:data-[state=active]:text-blue-300">
                    {t('transaction.transfer')}
                  </TabsTrigger>
                )}
              </TabsList>
            </Tabs>
          )}

          <div className="space-y-2">
            <Label htmlFor="amount">{t('form.amount')}</Label>
            <Input
              id="amount"
              type="number"
              step="0.01"
              min="0"
              value={data.amount}
              onChange={(e) => setData('amount', e.target.value)}
              placeholder={t('form.balance_placeholder')}
              className="text-2xl font-bold h-14"
            />
            {errors.amount && (
              <p className="text-sm text-red-600">{errors.amount}</p>
            )}
          </div>

          <div className="space-y-2">
            <Label htmlFor="account_id">
              {data.type === 'transfer' ? t('form.from_account') : t('form.account')}
            </Label>
            <Select
              value={data.account_id}
              onValueChange={(value) => setData('account_id', value)}
            >
              <SelectTrigger>
                <SelectValue placeholder={t('form.select_account')} />
              </SelectTrigger>
              <SelectContent>
                {accounts.filter(a => a.is_active).map((account) => (
                  <SelectItem key={account.id} value={String(account.id)}>
                    {account.name}
                  </SelectItem>
                ))}
              </SelectContent>
            </Select>
            {errors.account_id && (
              <p className="text-sm text-red-600">{errors.account_id}</p>
            )}
          </div>

          {data.type === 'transfer' && (
            <div className="space-y-2">
              <Label htmlFor="transfer_account_id">{t('form.to_account')}</Label>
              <Select
                value={data.transfer_account_id}
                onValueChange={(value) => setData('transfer_account_id', value)}
              >
                <SelectTrigger>
                  <SelectValue placeholder={t('form.select_destination_account')} />
                </SelectTrigger>
                <SelectContent>
                  {accounts
                    .filter(a => a.is_active && String(a.id) !== data.account_id)
                    .map((account) => (
                      <SelectItem key={account.id} value={String(account.id)}>
                        {account.name}
                      </SelectItem>
                    ))}
                </SelectContent>
              </Select>
              {errors.transfer_account_id && (
                <p className="text-sm text-red-600">{errors.transfer_account_id}</p>
              )}
            </div>
          )}

          {data.type !== 'transfer' && (
            <div className="space-y-2">
              <Label htmlFor="category_id">{t('form.category')}</Label>
              <Select
                value={data.category_id}
                onValueChange={(value) => setData('category_id', value)}
              >
                <SelectTrigger>
                  <SelectValue placeholder={t('form.select_category')} />
                </SelectTrigger>
                <SelectContent>
                  {currentCategories.map((category) => (
                    <SelectItem key={category.id} value={String(category.id)}>
                      {category.name}
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
              {errors.category_id && (
                <p className="text-sm text-red-600">{errors.category_id}</p>
              )}
            </div>
          )}

          <div className="space-y-2">
            <Label>{t('form.date')}</Label>
            <DatePicker
              value={data.transaction_date}
              onChange={(date) => setData('transaction_date', date ? format(date, 'yyyy-MM-dd') : '')}
              placeholder={t('filter.select_date')}
            />
            {errors.transaction_date && (
              <p className="text-sm text-red-600">{errors.transaction_date}</p>
            )}
          </div>

          <div className="space-y-2">
            <Label htmlFor="description">{t('form.description')}</Label>
            <Input
              id="description"
              value={data.description}
              onChange={(e) => setData('description', e.target.value)}
              placeholder={t('form.description_placeholder_transaction')}
            />
            {errors.description && (
              <p className="text-sm text-red-600">{errors.description}</p>
            )}
          </div>

          <div className="space-y-2">
            <Label htmlFor="notes">{t('form.notes_optional')}</Label>
            <Textarea
              id="notes"
              value={data.notes}
              onChange={(e) => setData('notes', e.target.value)}
              placeholder={t('form.additional_notes')}
              rows={2}
            />
          </div>

          <SheetFooter className="gap-2 pt-4">
            <Button
              type="button"
              variant="outline"
              onClick={handleClose}
              disabled={processing || isSubmitting}
            >
              {t('action.cancel')}
            </Button>
            <Button type="submit" disabled={processing || isSubmitting}>
              {(processing || isSubmitting) ? t('common.saving') : isEditing ? t('form.update_transaction_button') : t('form.save_transaction')}
            </Button>
          </SheetFooter>
        </form>
      </SheetContent>
    </Sheet>
  )
}
