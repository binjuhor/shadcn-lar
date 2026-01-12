import { format } from 'date-fns'
import { router } from '@inertiajs/react'
import { useState } from 'react'
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
import {
  Card,
  CardContent,
  CardDescription,
  CardFooter,
  CardHeader,
  CardTitle,
} from '@/components/ui/card'
import { Tabs, TabsList, TabsTrigger } from '@/components/ui/tabs'
import { Save, RotateCcw, TrendingUp, TrendingDown, ArrowLeftRight } from 'lucide-react'
import type { Account, Category, ParsedTransaction, TransactionType } from '@modules/Finance/types/finance'

interface TransactionPreviewProps {
  parsed: ParsedTransaction
  accounts: Account[]
  categories: Category[]
  onReset: () => void
}

function formatMoney(amount: number, currencyCode = 'VND'): string {
  return new Intl.NumberFormat('vi-VN', {
    style: 'currency',
    currency: currencyCode,
  }).format(amount)
}

export function TransactionPreview({
  parsed,
  accounts,
  categories,
  onReset,
}: TransactionPreviewProps) {
  const [processing, setProcessing] = useState(false)
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
  const [notes, setNotes] = useState('')

  const incomeCategories = categories.filter((c) => c.type === 'income' || c.type === 'both')
  const expenseCategories = categories.filter((c) => c.type === 'expense' || c.type === 'both')
  const currentCategories = type === 'income' ? incomeCategories : expenseCategories

  const confidencePercent = Math.round((parsed.confidence || 0) * 100)

  const handleTypeChange = (newType: TransactionType) => {
    setType(newType)
    setCategoryId('')
  }

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault()
    setProcessing(true)

    router.post(
      route('dashboard.finance.smart-input.store'),
      {
        type,
        amount,
        description,
        account_id: parseInt(accountId),
        category_id: categoryId ? parseInt(categoryId) : null,
        transaction_date: transactionDate,
        notes: notes || null,
      },
      {
        onSuccess: () => {
          setProcessing(false)
          onReset()
        },
        onError: () => {
          setProcessing(false)
        },
      }
    )
  }

  return (
    <Card>
      <CardHeader>
        <div className="flex items-center justify-between">
          <div>
            <CardTitle>Extracted Transaction</CardTitle>
            <CardDescription>
              Review and edit before saving
            </CardDescription>
          </div>
          <div className="text-right">
            <p className="text-sm text-muted-foreground">Confidence</p>
            <p className="text-lg font-semibold">{confidencePercent}%</p>
          </div>
        </div>
        <Progress value={confidencePercent} className="h-2" />
      </CardHeader>

      <form onSubmit={handleSubmit}>
        <CardContent className="space-y-4">
          {/* Transaction Type */}
          <div className="space-y-2">
            <Label>Type</Label>
            <Tabs value={type} onValueChange={(v) => handleTypeChange(v as TransactionType)}>
              <TabsList className="w-full grid grid-cols-3">
                <TabsTrigger value="income" className="flex items-center gap-2">
                  <TrendingUp className="h-4 w-4 text-green-600" />
                  Income
                </TabsTrigger>
                <TabsTrigger value="expense" className="flex items-center gap-2">
                  <TrendingDown className="h-4 w-4 text-red-600" />
                  Expense
                </TabsTrigger>
                <TabsTrigger value="transfer" className="flex items-center gap-2">
                  <ArrowLeftRight className="h-4 w-4" />
                  Transfer
                </TabsTrigger>
              </TabsList>
            </Tabs>
          </div>

          {/* Amount */}
          <div className="space-y-2">
            <Label htmlFor="amount">Amount (VND)</Label>
            <Input
              id="amount"
              type="number"
              min={1}
              value={amount || ''}
              onChange={(e) => setAmount(Number(e.target.value))}
              placeholder="0"
              className="text-lg font-semibold"
            />
            <p className="text-sm text-muted-foreground">
              {formatMoney(amount)}
            </p>
          </div>

          {/* Description */}
          <div className="space-y-2">
            <Label htmlFor="description">Description</Label>
            <Input
              id="description"
              value={description}
              onChange={(e) => setDescription(e.target.value)}
              placeholder="Transaction description"
            />
          </div>

          {/* Account */}
          <div className="space-y-2">
            <Label htmlFor="account">Account</Label>
            <Select value={accountId} onValueChange={setAccountId}>
              <SelectTrigger>
                <SelectValue placeholder="Select account" />
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
            <div className="space-y-2">
              <Label htmlFor="category">Category</Label>
              <Select value={categoryId} onValueChange={setCategoryId}>
                <SelectTrigger>
                  <SelectValue placeholder="Select category (optional)" />
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
          <div className="space-y-2">
            <Label>Date</Label>
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
          <div className="space-y-2">
            <Label htmlFor="notes">Notes (optional)</Label>
            <Textarea
              id="notes"
              value={notes}
              onChange={(e) => setNotes(e.target.value)}
              placeholder="Additional notes..."
              rows={2}
            />
          </div>

          {/* Raw text for debugging */}
          {parsed.raw_text && (
            <div className="p-3 rounded-lg bg-muted text-xs">
              <p className="font-medium mb-1">Extracted text:</p>
              <p className="text-muted-foreground">{parsed.raw_text}</p>
            </div>
          )}
        </CardContent>

        <CardFooter className="flex gap-3">
          <Button
            type="button"
            variant="outline"
            onClick={onReset}
            disabled={processing}
            className="flex-1"
          >
            <RotateCcw className="mr-2 h-4 w-4" />
            Reset
          </Button>
          <Button type="submit" disabled={processing} className="flex-1">
            <Save className="mr-2 h-4 w-4" />
            {processing ? 'Saving...' : 'Save Transaction'}
          </Button>
        </CardFooter>
      </form>
    </Card>
  )
}
