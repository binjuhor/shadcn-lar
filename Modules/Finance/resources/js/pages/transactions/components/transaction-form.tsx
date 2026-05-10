import { useEffect, useRef, useState } from 'react'
import { router } from '@inertiajs/react'
import { useTranslation } from 'react-i18next'
import { format } from 'date-fns'
import { Paperclip, X } from 'lucide-react'
import { VisuallyHidden } from '@radix-ui/react-visually-hidden'
import { Dialog, DialogContent, DialogTitle } from '@/components/ui/dialog'
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
import { Tabs, TabsList, TabsTrigger } from '@/components/ui/tabs'
import type {
  Account,
  Category,
  Transaction,
  TransactionBill,
  TransactionType,
} from '@modules/Finance/types/finance'

const MAX_BILLS = 10
const ACCEPTED_MIME = 'image/jpeg,image/png,image/webp,image/heic,image/heif,application/pdf'

interface TransactionFormProps {
  open: boolean
  onOpenChange: (open: boolean) => void
  accounts: Account[]
  categories: Category[]
  transaction?: Transaction | null
  duplicateFrom?: Transaction | null
  onSuccess?: () => void
}

interface FormState {
  type: TransactionType
  account_id: string
  category_id: string
  amount: string
  description: string
  notes: string
  transaction_date: string
  transfer_account_id: string
}

const emptyForm = (): FormState => ({
  type: 'expense',
  account_id: '',
  category_id: '',
  amount: '',
  description: '',
  notes: '',
  transaction_date: new Date().toISOString().split('T')[0],
  transfer_account_id: '',
})

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
  const [errors, setErrors] = useState<Record<string, string>>({})
  const [data, setForm] = useState<FormState>(emptyForm)
  const [newBills, setNewBills] = useState<File[]>([])
  const [removedBillIds, setRemovedBillIds] = useState<number[]>([])
  const [dragActive, setDragActive] = useState(false)
  const [converting, setConverting] = useState(false)
  const fileInputRef = useRef<HTMLInputElement | null>(null)

  const setData = <K extends keyof FormState>(key: K, value: FormState[K]) => {
    setForm((prev) => ({ ...prev, [key]: value }))
  }

  // Existing bills minus those the user marked for removal in this session.
  const existingBills: TransactionBill[] = (transaction?.bills ?? []).filter(
    (b) => !removedBillIds.includes(b.id),
  )

  // Populate form when editing or duplicating
  useEffect(() => {
    if (transaction && open) {
      setForm({
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
      setForm({
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
      setForm(emptyForm())
      setNewBills([])
      setRemovedBillIds([])
      setErrors({})
    }
  }, [transaction, duplicateFrom, open])

  const incomeCategories = categories.filter((c) => c.type === 'income')
  const expenseCategories = categories.filter((c) => c.type === 'expense')
  const currentCategories = data.type === 'income' ? incomeCategories : expenseCategories

  // iOS exports photos as HEIC by default. Browsers can't render them, so we convert
  // to JPEG client-side before upload — saves the server from needing libheif tooling.
  const isHeicFile = (file: File): boolean => {
    const name = file.name.toLowerCase()
    return (
      file.type === 'image/heic' ||
      file.type === 'image/heif' ||
      name.endsWith('.heic') ||
      name.endsWith('.heif')
    )
  }

  // heic2any sometimes fails on iPhone 15+ HEICs (multi-image / HDR variants).
  // Try JPEG first, fall back to PNG. If both fail, return null so the caller can
  // upload the raw HEIC — the server has libheif-tools and will convert there.
  const convertHeicInBrowser = async (file: File): Promise<File | null> => {
    const { default: heic2any } = await import('heic2any')
    const tryFormat = async (toType: 'image/jpeg' | 'image/png'): Promise<File> => {
      const result = await heic2any({ blob: file, toType, quality: 0.9 })
      const blob = Array.isArray(result) ? result[0] : result
      const ext = toType === 'image/jpeg' ? '.jpg' : '.png'
      const newName = file.name.replace(/\.(heic|heif)$/i, ext)
      return new File([blob], newName, { type: toType, lastModified: file.lastModified })
    }

    try {
      return await tryFormat('image/jpeg')
    } catch (jpegErr) {
      console.warn('heic2any JPEG conversion failed, trying PNG', jpegErr)
      try {
        return await tryFormat('image/png')
      } catch (pngErr) {
        console.error('heic2any PNG conversion also failed', pngErr)
        return null
      }
    }
  }

  const handleFilesPicked = async (files: FileList | null) => {
    if (!files) return
    const remaining = MAX_BILLS - existingBills.length - newBills.length
    const incoming = Array.from(files).slice(0, Math.max(remaining, 0))
    if (incoming.length === 0) return

    const hasHeic = incoming.some(isHeicFile)
    if (hasHeic) setConverting(true)

    const processed: File[] = []
    let serverFallbackUsed = false

    for (const file of incoming) {
      if (isHeicFile(file)) {
        const converted = await convertHeicInBrowser(file)
        if (converted) {
          processed.push(converted)
        } else {
          // Browser couldn't convert — let the server handle it via heif-convert.
          processed.push(file)
          serverFallbackUsed = true
        }
      } else {
        processed.push(file)
      }
    }

    if (processed.length > 0) {
      setNewBills((prev) => [...prev, ...processed])
      // Clear any prior bills error since we did accept the file(s).
      setErrors((prev) => {
        const { bills: _bills, ...rest } = prev
        return rest
      })
    }

    if (serverFallbackUsed) {
      // Soft notice — file is still attached, just converted server-side.
      console.info('HEIC will be converted server-side on save')
    }

    if (hasHeic) setConverting(false)
  }

  const removeNewBill = (idx: number) => {
    setNewBills((prev) => prev.filter((_, i) => i !== idx))
  }

  const removeExistingBill = (id: number) => {
    setRemovedBillIds((prev) => [...prev, id])
  }

  const buildFormData = (): FormData => {
    const fd = new FormData()
    if (isEditing) {
      fd.append('_method', 'put')
      fd.append('transaction_type', data.type)
    } else {
      fd.append('type', data.type)
    }
    fd.append('account_id', data.account_id)
    if (data.category_id) fd.append('category_id', data.category_id)
    fd.append('amount', data.amount || '0')
    fd.append('description', data.description ?? '')
    fd.append('notes', data.notes ?? '')
    fd.append('transaction_date', data.transaction_date)
    if (!isEditing && data.transfer_account_id) {
      fd.append('transfer_account_id', data.transfer_account_id)
    }
    newBills.forEach((file) => fd.append('bills[]', file))
    if (isEditing) {
      removedBillIds.forEach((id) => fd.append('removed_bill_ids[]', String(id)))
    }
    return fd
  }

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault()

    const onSuccessCallback = () => {
      setForm(emptyForm())
      setNewBills([])
      setRemovedBillIds([])
      setErrors({})
      onOpenChange(false)
      onSuccess?.()
    }

    setIsSubmitting(true)
    setErrors({})

    const url = isEditing
      ? route('dashboard.finance.transactions.update', transaction.id)
      : route('dashboard.finance.transactions.store')

    // POST with _method=put for edits since multipart + PUT isn't supported by PHP/$_POST.
    router.post(url, buildFormData(), {
      preserveScroll: true,
      forceFormData: true,
      onSuccess: onSuccessCallback,
      onError: (errs) => setErrors(errs as Record<string, string>),
      onFinish: () => setIsSubmitting(false),
    })
  }

  const handleClose = () => {
    setForm(emptyForm())
    setNewBills([])
    setRemovedBillIds([])
    setErrors({})
    onOpenChange(false)
  }

  const handleTypeChange = (type: TransactionType) => {
    setData('type', type)
    setData('category_id', '')
    if (type !== 'transfer') {
      setData('transfer_account_id', '')
    }
  }

  const processing = isSubmitting

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
            <div
              onDragEnter={(e) => {
                e.preventDefault()
                e.stopPropagation()
                setDragActive(true)
              }}
              onDragOver={(e) => {
                e.preventDefault()
                e.stopPropagation()
                if (!dragActive) setDragActive(true)
              }}
              onDragLeave={(e) => {
                e.preventDefault()
                e.stopPropagation()
                // Only deactivate when leaving the wrapper itself, not when moving between children.
                if (e.currentTarget === e.target) setDragActive(false)
              }}
              onDrop={(e) => {
                e.preventDefault()
                e.stopPropagation()
                setDragActive(false)
                handleFilesPicked(e.dataTransfer.files)
              }}
              className={`relative rounded-md border bg-background transition-colors ${
                dragActive ? 'border-primary ring-2 ring-primary/30' : 'border-input'
              }`}
            >
              <Textarea
                id="notes"
                value={data.notes}
                onChange={(e) => setData('notes', e.target.value)}
                placeholder={t('form.additional_notes')}
                rows={3}
                className="resize-none border-0 bg-transparent shadow-none focus-visible:ring-0"
              />

              {(existingBills.length > 0 || newBills.length > 0) && (
                <div className="flex flex-wrap gap-2 border-t px-2 py-2">
                  {existingBills.map((bill) => (
                    <BillThumb
                      key={`existing-${bill.id}`}
                      url={bill.url}
                      name={bill.name}
                      mimeType={bill.mime_type ?? null}
                      onRemove={() => removeExistingBill(bill.id)}
                    />
                  ))}
                  {newBills.map((file, idx) => (
                    <BillThumb
                      key={`new-${idx}`}
                      url={URL.createObjectURL(file)}
                      name={file.name}
                      mimeType={file.type}
                      onRemove={() => removeNewBill(idx)}
                      isLocal
                    />
                  ))}
                </div>
              )}

              <div className="flex items-center justify-between border-t px-2 py-1.5">
                <button
                  type="button"
                  onClick={() => fileInputRef.current?.click()}
                  disabled={existingBills.length + newBills.length >= MAX_BILLS || converting}
                  className="flex items-center gap-1 rounded-md p-1.5 text-muted-foreground transition-colors hover:bg-muted hover:text-foreground disabled:cursor-not-allowed disabled:opacity-50"
                  aria-label="Attach bill"
                  title="Attach bill (or drag & drop)"
                >
                  <Paperclip className="h-4 w-4" />
                </button>
                <span className="text-xs text-muted-foreground">
                  {converting ? 'Converting…' : `${existingBills.length + newBills.length}/${MAX_BILLS}`}
                </span>
              </div>

              {dragActive && (
                <div className="pointer-events-none absolute inset-0 flex items-center justify-center rounded-md bg-primary/10 text-sm font-medium text-primary">
                  Drop to attach
                </div>
              )}
            </div>

            <input
              ref={fileInputRef}
              type="file"
              accept={ACCEPTED_MIME}
              multiple
              className="hidden"
              onChange={(e) => {
                handleFilesPicked(e.target.files)
                e.target.value = ''
              }}
            />
            <p className="text-xs text-muted-foreground">
              Drop receipts/bills into the box, or click the paperclip. JPG, PNG, WebP, HEIC or PDF — up to {MAX_BILLS} files, max 10MB each.
            </p>
            {errors.bills && (
              <p className="text-sm text-red-600">{errors.bills}</p>
            )}
          </div>

          <SheetFooter className="gap-2 pt-4">
            <Button
              type="button"
              variant="outline"
              onClick={handleClose}
              disabled={processing}
            >
              {t('action.cancel')}
            </Button>
            <Button type="submit" disabled={processing}>
              {processing ? t('common.saving') : isEditing ? t('form.update_transaction_button') : t('form.save_transaction')}
            </Button>
          </SheetFooter>
        </form>
      </SheetContent>
    </Sheet>
  )
}

interface BillThumbProps {
  url: string
  name: string
  mimeType: string | null
  onRemove: () => void
  isLocal?: boolean
}

function BillThumb({ url, name, mimeType, onRemove, isLocal }: BillThumbProps) {
  const [previewOpen, setPreviewOpen] = useState(false)
  const isImage = (mimeType ?? '').startsWith('image/')

  return (
    <div className="group relative h-20 w-20 overflow-hidden rounded-md border bg-muted">
      {isImage ? (
        <button
          type="button"
          onClick={() => setPreviewOpen(true)}
          className="block h-full w-full cursor-zoom-in"
          aria-label={`Preview ${name}`}
        >
          <img src={url} alt={name} className="h-full w-full object-cover" />
        </button>
      ) : (
        <a
          href={isLocal ? undefined : url}
          target="_blank"
          rel="noreferrer"
          className="flex h-full w-full flex-col items-center justify-center gap-1 p-1 text-center text-[10px] text-muted-foreground"
          title={name}
        >
          <Paperclip className="h-5 w-5" />
          <span className="line-clamp-2 break-all">{name}</span>
        </a>
      )}

      <button
        type="button"
        onClick={(e) => {
          e.stopPropagation()
          onRemove()
        }}
        className="absolute right-0.5 top-0.5 rounded-full bg-black/60 p-0.5 text-white opacity-0 transition-opacity group-hover:opacity-100"
        aria-label="Remove"
      >
        <X className="h-3 w-3" />
      </button>

      {isImage && (
        <Dialog open={previewOpen} onOpenChange={setPreviewOpen}>
          <DialogContent className="max-w-[90vw] max-h-[90vh] w-auto p-0 border-0 bg-transparent shadow-none [&>button]:text-white [&>button]:bg-black/50 [&>button]:rounded-full [&>button]:p-1.5 [&>button]:top-2 [&>button]:right-2">
            <VisuallyHidden>
              <DialogTitle>{name}</DialogTitle>
            </VisuallyHidden>
            <img
              src={url}
              alt={name}
              className="max-w-[90vw] max-h-[85vh] object-contain rounded-lg"
            />
          </DialogContent>
        </Dialog>
      )}
    </div>
  )
}
