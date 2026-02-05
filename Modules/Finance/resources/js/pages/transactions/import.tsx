import { useState, useCallback } from 'react'
import { useTranslation } from 'react-i18next'
import { router } from '@inertiajs/react'
import { AuthenticatedLayout } from '@/layouts'
import { Main } from '@/components/layout/main'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Switch } from '@/components/ui/switch'
import {
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from '@/components/ui/card'
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select'
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table'
import { Badge } from '@/components/ui/badge'
import { Alert, AlertDescription } from '@/components/ui/alert'
import { ArrowLeft, Upload, FileText, CheckCircle, AlertCircle } from 'lucide-react'
import type { Account, Category } from '@modules/Finance/types/finance'

interface Props {
  accounts: Account[]
  categories: Category[]
}

interface ParsedTransaction {
  currency: string
  transaction_date: string
  transaction_time: string
  timezone: string
  type: 'income' | 'expense'
  amount: number
  description: string
  running_balance: number
  suggested_category: string | null
  is_transfer: boolean
}

interface PreviewSummary {
  total: number
  income: number
  expense: number
  total_income: number
  total_expense: number
  date_range: {
    from: string
    to: string
  }
}

interface PreviewResponse {
  transactions: ParsedTransaction[]
  summary: PreviewSummary
  suggested_categories: string[]
}

export default function ImportTransactions({ accounts, categories }: Props) {
  const { t } = useTranslation()
  const [file, setFile] = useState<File | null>(null)
  const [accountId, setAccountId] = useState<string>('')
  const [source, setSource] = useState<string>('payoneer')
  const [skipDuplicates, setSkipDuplicates] = useState(true)
  const [preview, setPreview] = useState<PreviewResponse | null>(null)
  const [loading, setLoading] = useState(false)
  const [importing, setImporting] = useState(false)
  const [error, setError] = useState<string | null>(null)

  const handleFileChange = useCallback((e: React.ChangeEvent<HTMLInputElement>) => {
    const selectedFile = e.target.files?.[0]
    if (selectedFile) {
      setFile(selectedFile)
      setPreview(null)
      setError(null)
    }
  }, [])

  const handlePreview = useCallback(async () => {
    if (!file) return

    setLoading(true)
    setError(null)

    const formData = new FormData()
    formData.append('file', file)
    formData.append('source', source)

    try {
      const response = await fetch(route('dashboard.finance.transactions.import.preview'), {
        method: 'POST',
        body: formData,
        headers: {
          'X-XSRF-TOKEN': decodeURIComponent(
            document.cookie.split('; ').find(row => row.startsWith('XSRF-TOKEN='))?.split('=')[1] || ''
          ),
        },
        credentials: 'same-origin',
      })

      if (!response.ok) {
        throw new Error(t('page.import.failed_parse'))
      }

      const data = await response.json()
      setPreview(data)
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Failed to preview file')
    } finally {
      setLoading(false)
    }
  }, [file, source])

  const handleImport = useCallback(() => {
    if (!file || !accountId) return

    setImporting(true)

    const formData = new FormData()
    formData.append('file', file)
    formData.append('account_id', accountId)
    formData.append('source', source)
    formData.append('skip_duplicates', skipDuplicates ? '1' : '0')

    router.post(route('dashboard.finance.transactions.import.store'), formData, {
      forceFormData: true,
      onFinish: () => setImporting(false),
    })
  }, [file, accountId, source, skipDuplicates])

  const formatCurrency = (amount: number, currency = 'USD') => {
    try {
      const fractionDigits = currency === 'VND' ? 0 : 2
      // Use vi-VN locale for VND to put symbol at end
      const locale = currency === 'VND' ? 'vi-VN' : 'en-US'
      return new Intl.NumberFormat(locale, {
        style: 'currency',
        currency,
        minimumFractionDigits: fractionDigits,
        maximumFractionDigits: fractionDigits,
      }).format(amount)
    } catch {
      return `${amount.toLocaleString()} ${currency}`
    }
  }

  return (
    <AuthenticatedLayout title={t('page.import.title')}>
      <Main>
        <div className='mb-6 flex items-center gap-4'>
          <Button
            variant='ghost'
            size='icon'
            onClick={() => router.get(route('dashboard.finance.transactions.index'))}
          >
            <ArrowLeft className='h-4 w-4' />
          </Button>
          <div>
            <h2 className='text-2xl font-bold tracking-tight'>{t('page.import.title')}</h2>
            <p className='text-muted-foreground'>
              {t('page.import.description')}
            </p>
          </div>
        </div>

        <div className='grid gap-6 lg:grid-cols-3'>
          {/* Upload Section */}
          <Card className='lg:col-span-1'>
            <CardHeader>
              <CardTitle className='flex items-center gap-2'>
                <Upload className='h-5 w-5' />
                {t('page.import.upload_file')}
              </CardTitle>
              <CardDescription>
                {t('page.import.select_file')}
              </CardDescription>
            </CardHeader>
            <CardContent className='space-y-4'>
              <div className='space-y-2'>
                <Label htmlFor='source'>{t('page.import.source')}</Label>
                <Select value={source} onValueChange={(val) => {
                  setSource(val)
                  setFile(null)
                  setPreview(null)
                }}>
                  <SelectTrigger>
                    <SelectValue />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem value='payoneer'>Payoneer (CSV)</SelectItem>
                    <SelectItem value='techcombank'>Techcombank (Excel)</SelectItem>
                    <SelectItem value='techcombank_pdf'>Techcombank (PDF)</SelectItem>
                    <SelectItem value='generic'>{t('page.import.generic_csv')}</SelectItem>
                  </SelectContent>
                </Select>
              </div>

              <div className='space-y-2'>
                <Label htmlFor='file'>
                  {source === 'techcombank' ? t('page.import.excel_file') : source === 'techcombank_pdf' ? t('page.import.pdf_file') : t('page.import.csv_file')}
                </Label>
                <Input
                  id='file'
                  type='file'
                  accept={source === 'techcombank' ? '.xlsx,.xls' : source === 'techcombank_pdf' ? '.pdf' : '.csv,.txt'}
                  onChange={handleFileChange}
                />
                {file && (
                  <p className='text-sm text-muted-foreground flex items-center gap-2'>
                    <FileText className='h-4 w-4' />
                    {file.name}
                  </p>
                )}
              </div>

              <Button
                onClick={handlePreview}
                disabled={!file || loading}
                className='w-full'
              >
                {loading ? t('page.import.parsing') : t('page.import.preview_transactions')}
              </Button>

              {error && (
                <Alert variant='destructive'>
                  <AlertCircle className='h-4 w-4' />
                  <AlertDescription>{error}</AlertDescription>
                </Alert>
              )}

              {preview && (
                <>
                  <hr className='my-4' />

                  <div className='space-y-2'>
                    <Label htmlFor='account'>{t('page.import.target_account')}</Label>
                    <Select value={accountId} onValueChange={setAccountId}>
                      <SelectTrigger>
                        <SelectValue placeholder={t('page.import.select_account')} />
                      </SelectTrigger>
                      <SelectContent>
                        {accounts.map((account) => (
                          <SelectItem key={account.id} value={String(account.id)}>
                            {account.name} ({account.currency_code})
                          </SelectItem>
                        ))}
                      </SelectContent>
                    </Select>
                  </div>

                  <div className='flex items-center justify-between'>
                    <Label htmlFor='skip-duplicates'>{t('page.import.skip_duplicates')}</Label>
                    <Switch
                      id='skip-duplicates'
                      checked={skipDuplicates}
                      onCheckedChange={setSkipDuplicates}
                    />
                  </div>

                  <Button
                    onClick={handleImport}
                    disabled={!accountId || importing}
                    className='w-full'
                    variant='default'
                  >
                    {importing ? t('page.import.importing') : t('page.import.import_count', { count: preview.summary.total })}
                  </Button>
                </>
              )}
            </CardContent>
          </Card>

          {/* Preview Section */}
          <Card className='lg:col-span-2'>
            <CardHeader>
              <CardTitle className='flex items-center gap-2'>
                <FileText className='h-5 w-5' />
                {t('page.import.preview')}
              </CardTitle>
              {preview && (
                <CardDescription>
                  {t('page.import.transactions_from_to', { count: preview.summary.total, from: preview.summary.date_range.from, to: preview.summary.date_range.to })}
                </CardDescription>
              )}
            </CardHeader>
            <CardContent>
              {!preview ? (
                <div className='flex flex-col items-center justify-center py-12 text-center text-muted-foreground'>
                  <Upload className='h-12 w-12 mb-4 opacity-50' />
                  <p>{t('page.import.upload_prompt')}</p>
                </div>
              ) : (
                <div className='space-y-4'>
                  {/* Summary */}
                  {(() => {
                    const summaryCurrency = preview.transactions[0]?.currency || 'USD'
                    return (
                      <div className='grid grid-cols-2 md:grid-cols-4 gap-4'>
                        <div className='p-4 bg-muted rounded-lg'>
                          <p className='text-sm text-muted-foreground'>{t('page.import.total')}</p>
                          <p className='text-2xl font-bold'>{preview.summary.total}</p>
                        </div>
                        <div className='p-4 bg-green-50 dark:bg-green-900/20 rounded-lg'>
                          <p className='text-sm text-muted-foreground'>{t('page.import.income')}</p>
                          <p className='text-2xl font-bold text-green-600'>
                            {formatCurrency(preview.summary.total_income, summaryCurrency)}
                          </p>
                          <p className='text-sm text-muted-foreground'>{t('page.import.transactions_count', { count: preview.summary.income })}</p>
                        </div>
                        <div className='p-4 bg-red-50 dark:bg-red-900/20 rounded-lg'>
                          <p className='text-sm text-muted-foreground'>{t('page.import.expense')}</p>
                          <p className='text-2xl font-bold text-red-600'>
                            {formatCurrency(preview.summary.total_expense, summaryCurrency)}
                          </p>
                          <p className='text-sm text-muted-foreground'>{t('page.import.transactions_count', { count: preview.summary.expense })}</p>
                        </div>
                        <div className='p-4 bg-muted rounded-lg'>
                          <p className='text-sm text-muted-foreground'>{t('page.import.net')}</p>
                          <p className='text-2xl font-bold'>
                            {formatCurrency(preview.summary.total_income - preview.summary.total_expense, summaryCurrency)}
                          </p>
                        </div>
                      </div>
                    )
                  })()}

                  {/* Transactions Table */}
                  <div className='rounded-md border max-h-[400px] overflow-auto'>
                    <Table>
                      <TableHeader>
                        <TableRow>
                          <TableHead>{t('table.date')}</TableHead>
                          <TableHead>{t('table.type')}</TableHead>
                          <TableHead>{t('table.description')}</TableHead>
                          <TableHead>{t('table.category')}</TableHead>
                          <TableHead className='text-right'>{t('table.amount')}</TableHead>
                        </TableRow>
                      </TableHeader>
                      <TableBody>
                        {preview.transactions.map((tx, index) => (
                          <TableRow key={index}>
                            <TableCell className='whitespace-nowrap'>{tx.transaction_date}</TableCell>
                            <TableCell>
                              <Badge variant={tx.type === 'income' ? 'default' : 'secondary'}>
                                {tx.type}
                              </Badge>
                            </TableCell>
                            <TableCell className='max-w-[300px] truncate'>{tx.description}</TableCell>
                            <TableCell>
                              {tx.suggested_category ? (
                                <Badge variant='outline'>{tx.suggested_category}</Badge>
                              ) : (
                                <span className='text-muted-foreground'>-</span>
                              )}
                            </TableCell>
                            <TableCell className={`text-right font-medium ${tx.type === 'income' ? 'text-green-600' : 'text-red-600'}`}>
                              {tx.type === 'income' ? '+' : '-'}{formatCurrency(tx.amount, tx.currency)}
                            </TableCell>
                          </TableRow>
                        ))}
                      </TableBody>
                    </Table>
                  </div>
                </div>
              )}
            </CardContent>
          </Card>
        </div>
      </Main>
    </AuthenticatedLayout>
  )
}
