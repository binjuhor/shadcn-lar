import { useState } from 'react'
import { format } from 'date-fns'
import { useTranslation } from 'react-i18next'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Textarea } from '@/components/ui/textarea'
import { DatePicker } from '@/components/ui/date-picker'
import { Progress } from '@/components/ui/progress'
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select'
import { Tabs, TabsList, TabsTrigger } from '@/components/ui/tabs'
import {
  TrendingUp,
  TrendingDown,
  ArrowLeftRight,
  Save,
  Pencil,
  Check,
  ChevronDown,
  ChevronUp,
} from 'lucide-react'
import { cn } from '@/lib/utils'
import type {
  Account,
  Category,
  ParsedTransaction,
  TransactionType,
} from '@modules/Finance/types/finance'

interface ChatTransactionCardProps {
  messageId: string
  parsed: ParsedTransaction
  accounts: Account[]
  categories: Category[]
  isSaved: boolean
  onSave: (messageId: string, data: Record<string, unknown>) => void
}

function formatMoney(amount: number, currencyCode = 'VND'): string {
  const locale = currencyCode === 'VND' ? 'vi-VN' : 'en-US'
  return new Intl.NumberFormat(locale, {
    style: 'currency',
    currency: currencyCode,
  }).format(amount)
}

export function ChatTransactionCard({
  messageId,
  parsed,
  accounts,
  categories,
  isSaved,
  onSave,
}: ChatTransactionCardProps) {
  const { t } = useTranslation()
  const [isEditing, setIsEditing] = useState(false)
  const [isSaving, setIsSaving] = useState(false)
  const [type, setType] = useState<TransactionType>(parsed.type || 'expense')
  const [amount, setAmount] = useState(parsed.amount || 0)
  const [description, setDescription] = useState(parsed.description || '')
  const [accountId, setAccountId] = useState(
    parsed.suggested_account?.id?.toString() || accounts[0]?.id?.toString() || ''
  )
  const [categoryId, setCategoryId] = useState(
    parsed.suggested_category?.id?.toString() || ''
  )
  const [transactionDate, setTransactionDate] = useState(
    parsed.transaction_date || format(new Date(), 'yyyy-MM-dd')
  )
  const [notes, setNotes] = useState(parsed.notes || '')

  const selectedAccount = accounts.find((a) => a.id.toString() === accountId)
  const incomeCategories = categories.filter((c) => c.type === 'income' || c.type === 'both')
  const expenseCategories = categories.filter((c) => c.type === 'expense' || c.type === 'both')
  const currentCategories = type === 'income' ? incomeCategories : expenseCategories
  const confidencePercent = Math.round((parsed.confidence || 0) * 100)

  const handleTypeChange = (newType: TransactionType) => {
    setType(newType)
    setCategoryId('')
  }

  const handleSave = async () => {
    setIsSaving(true)
    await onSave(messageId, {
      type,
      amount,
      description,
      account_id: parseInt(accountId),
      category_id: categoryId ? parseInt(categoryId) : null,
      transaction_date: transactionDate,
      notes: notes || null,
    })
    setIsSaving(false)
    setIsEditing(false)
  }

  const TypeIcon = type === 'income' ? TrendingUp : type === 'transfer' ? ArrowLeftRight : TrendingDown
  const typeColor = type === 'income' ? 'text-green-600' : type === 'transfer' ? 'text-blue-600' : 'text-red-600'
  const typeBgColor = type === 'income' ? 'bg-green-50 dark:bg-green-950' : type === 'transfer' ? 'bg-blue-50 dark:bg-blue-950' : 'bg-red-50 dark:bg-red-950'

  return (
    <div className="w-full max-w-[98%] rounded-lg border bg-card shadow-sm">
      {/* Summary */}
      <div className="p-3">
        <div className="flex items-start justify-between gap-2">
          <div className="flex items-center gap-2 min-w-0">
            <div className={cn('shrink-0 rounded-md p-1.5', typeBgColor)}>
              <TypeIcon className={cn('h-4 w-4', typeColor)} />
            </div>
            <div className="min-w-0">
              <p className="font-semibold text-sm truncate">
                {formatMoney(amount, selectedAccount?.currency_code)}
              </p>
              <p className="text-xs text-muted-foreground truncate">
                {description || t('page.smart_input.transaction_description')}
              </p>
            </div>
          </div>
          <div className="flex items-center gap-1 shrink-0">
            {isSaved ? (
              <span className="inline-flex items-center gap-1 rounded-full bg-green-100 dark:bg-green-900 px-2 py-0.5 text-xs font-medium text-green-700 dark:text-green-300">
                <Check className="h-3 w-3" />
                {t('page.smart_input.chat_saved_badge')}
              </span>
            ) : (
              <>
                <Button
                  size="sm"
                  variant="ghost"
                  className="h-7 px-2 text-xs"
                  onClick={() => setIsEditing(!isEditing)}
                  disabled={isSaving}
                >
                  {isEditing ? <ChevronUp className="h-3.5 w-3.5" /> : <Pencil className="h-3.5 w-3.5" />}
                </Button>
                <Button
                  size="sm"
                  className="h-7 px-3 text-xs"
                  onClick={handleSave}
                  disabled={isSaving || !accountId || !description}
                >
                  <Save className="h-3.5 w-3.5 mr-1" />
                  {isSaving ? '...' : t('page.smart_input.chat_quick_save')}
                </Button>
              </>
            )}
          </div>
        </div>

        {/* Confidence bar */}
        <div className="mt-2 flex items-center gap-2">
          <Progress value={confidencePercent} className="h-1.5 flex-1" />
          <span className="text-[10px] text-muted-foreground shrink-0">{confidencePercent}%</span>
        </div>
      </div>

      {/* Expandable edit form */}
      {isEditing && !isSaved && (
        <div className="border-t p-3 space-y-3">
          {/* Type tabs */}
          <div className="space-y-1">
            <Label className="text-xs">{t('form.type')}</Label>
            <Tabs value={type} onValueChange={(v) => handleTypeChange(v as TransactionType)}>
              <TabsList className="w-full grid grid-cols-3 h-8">
                <TabsTrigger value="income" className="text-xs h-7">
                  <TrendingUp className="h-3 w-3 mr-1 text-green-600" />
                  {t('filter.income')}
                </TabsTrigger>
                <TabsTrigger value="expense" className="text-xs h-7">
                  <TrendingDown className="h-3 w-3 mr-1 text-red-600" />
                  {t('filter.expense')}
                </TabsTrigger>
                <TabsTrigger value="transfer" className="text-xs h-7">
                  <ArrowLeftRight className="h-3 w-3 mr-1" />
                  {t('filter.transfer')}
                </TabsTrigger>
              </TabsList>
            </Tabs>
          </div>

          {/* Amount */}
          <div className="space-y-1">
            <Label className="text-xs" htmlFor={`amount-${messageId}`}>{t('form.amount')}</Label>
            <Input
              id={`amount-${messageId}`}
              type="number"
              min={0.01}
              step="any"
              value={amount || ''}
              onChange={(e) => setAmount(Number(e.target.value))}
              className="h-8 text-sm"
            />
          </div>

          {/* Description */}
          <div className="space-y-1">
            <Label className="text-xs" htmlFor={`desc-${messageId}`}>{t('form.description')}</Label>
            <Input
              id={`desc-${messageId}`}
              value={description}
              onChange={(e) => setDescription(e.target.value)}
              className="h-8 text-sm"
            />
          </div>

          {/* Account */}
          <div className="space-y-1">
            <Label className="text-xs">{t('form.account')}</Label>
            <Select value={accountId} onValueChange={setAccountId}>
              <SelectTrigger className="h-8 text-sm">
                <SelectValue placeholder={t('page.smart_input.select_account')} />
              </SelectTrigger>
              <SelectContent>
                {accounts.map((account) => (
                  <SelectItem key={account.id} value={account.id.toString()}>
                    {account.name}
                  </SelectItem>
                ))}
              </SelectContent>
            </Select>
          </div>

          {/* Category */}
          {type !== 'transfer' && (
            <div className="space-y-1">
              <Label className="text-xs">{t('form.category')}</Label>
              <Select value={categoryId} onValueChange={setCategoryId}>
                <SelectTrigger className="h-8 text-sm">
                  <SelectValue placeholder={t('page.smart_input.select_category')} />
                </SelectTrigger>
                <SelectContent>
                  {currentCategories.map((category) => (
                    <SelectItem key={category.id} value={category.id.toString()}>
                      {category.name}
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
            </div>
          )}

          {/* Date */}
          <div className="space-y-1">
            <Label className="text-xs">{t('form.date')}</Label>
            <DatePicker
              value={new Date(transactionDate)}
              onChange={(date) => {
                if (date) {
                  setTransactionDate(format(date, 'yyyy-MM-dd'))
                }
              }}
            />
          </div>

          {/* Notes */}
          <div className="space-y-1">
            <Label className="text-xs">{t('page.smart_input.notes_optional')}</Label>
            <Textarea
              value={notes}
              onChange={(e) => setNotes(e.target.value)}
              placeholder={t('page.smart_input.additional_notes')}
              rows={2}
              className="text-sm"
            />
          </div>
        </div>
      )}
    </div>
  )
}
