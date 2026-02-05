import { useState } from 'react'
import { router } from '@inertiajs/react'
import { format } from 'date-fns'
import { useTranslation } from 'react-i18next'
import { AuthenticatedLayout } from '@/layouts'
import { Main } from '@/components/layout/main'
import { Button } from '@/components/ui/button'
import { Badge } from '@/components/ui/badge'
import { DatePicker } from '@/components/ui/date-picker'
import {
  Table, TableBody, TableCell, TableHead, TableHeader, TableRow,
} from '@/components/ui/table'
import {
  Select, SelectContent, SelectItem, SelectTrigger, SelectValue,
} from '@/components/ui/select'
import {
  AlertDialog, AlertDialogAction, AlertDialogCancel, AlertDialogContent,
  AlertDialogDescription, AlertDialogFooter, AlertDialogHeader, AlertDialogTitle,
} from '@/components/ui/alert-dialog'
import {
  History, Trash2, ArrowLeft, Type, Mic, ImageIcon, MessageSquare, Check, X, Inbox,
} from 'lucide-react'
import type { PaginatedData, SmartInputHistory } from '@modules/Finance/types/finance'

interface Props {
  histories: PaginatedData<SmartInputHistory>
  filters: {
    input_type?: string
    saved?: string
    date_from?: string
    date_to?: string
  }
}

const INPUT_TYPE_ICONS: Record<string, typeof Type> = {
  text: Type,
  voice: Mic,
  image: ImageIcon,
  text_image: MessageSquare,
}

function formatMoney(amount: number): string {
  return new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(amount)
}

export default function SmartInputHistoryIndex({ histories, filters }: Props) {
  const { t } = useTranslation()
  const [deleteId, setDeleteId] = useState<number | null>(null)

  const applyFilter = (key: string, value: string) => {
    router.get(route('dashboard.finance.smart-input-history.index'), {
      ...filters,
      [key]: value || undefined,
    }, { preserveState: true, preserveScroll: true })
  }

  const handleDelete = () => {
    if (!deleteId) return
    router.delete(route('dashboard.finance.smart-input-history.destroy', { history: deleteId }), {
      onFinish: () => setDeleteId(null),
    })
  }

  const goToPage = (page: number) => {
    router.get(route('dashboard.finance.smart-input-history.index'), {
      ...filters,
      page,
    }, { preserveState: true, preserveScroll: true })
  }

  return (
    <AuthenticatedLayout title={t('page.smart_input_history.title')}>
      <Main>
        {/* Header */}
        <div className="flex items-center justify-between mb-6">
          <div className="flex items-center gap-3">
            <History className="h-6 w-6 text-primary" />
            <div>
              <h1 className="text-2xl font-bold">{t('page.smart_input_history.heading')}</h1>
              <p className="text-sm text-muted-foreground">{t('page.smart_input_history.description')}</p>
            </div>
          </div>
          <Button variant="outline" size="sm" onClick={() => router.visit(route('dashboard.finance.smart-input'))}>
            <ArrowLeft className="h-4 w-4 mr-1" />
            {t('page.smart_input_history.back_to_smart_input')}
          </Button>
        </div>

        {/* Filters */}
        <div className="flex flex-wrap gap-3 mb-4">
          <Select value={filters.input_type || ''} onValueChange={(v) => applyFilter('input_type', v)}>
            <SelectTrigger className="w-[160px]">
              <SelectValue placeholder={t('page.smart_input_history.filter_type')} />
            </SelectTrigger>
            <SelectContent>
              <SelectItem value="all">{t('page.smart_input_history.all_types')}</SelectItem>
              <SelectItem value="text">{t('page.smart_input_history.type_text')}</SelectItem>
              <SelectItem value="voice">{t('page.smart_input_history.type_voice')}</SelectItem>
              <SelectItem value="image">{t('page.smart_input_history.type_image')}</SelectItem>
              <SelectItem value="text_image">{t('page.smart_input_history.type_text_image')}</SelectItem>
            </SelectContent>
          </Select>

          <Select value={filters.saved || ''} onValueChange={(v) => applyFilter('saved', v)}>
            <SelectTrigger className="w-[160px]">
              <SelectValue placeholder={t('page.smart_input_history.filter_saved')} />
            </SelectTrigger>
            <SelectContent>
              <SelectItem value="all">{t('page.smart_input_history.all_status')}</SelectItem>
              <SelectItem value="yes">{t('page.smart_input_history.saved_yes')}</SelectItem>
              <SelectItem value="no">{t('page.smart_input_history.saved_no')}</SelectItem>
            </SelectContent>
          </Select>

          <DatePicker
            className="w-[160px]"
            value={filters.date_from || undefined}
            onChange={(date) => applyFilter('date_from', date ? format(date, 'yyyy-MM-dd') : '')}
            placeholder={t('page.smart_input_history.date_from')}
          />
          <DatePicker
            className="w-[160px]"
            value={filters.date_to || undefined}
            onChange={(date) => applyFilter('date_to', date ? format(date, 'yyyy-MM-dd') : '')}
            placeholder={t('page.smart_input_history.date_to')}
          />
        </div>

        {/* Table */}
        {histories.data.length === 0 ? (
          <div className="flex flex-col items-center justify-center py-16 text-muted-foreground">
            <Inbox className="h-12 w-12 mb-3" />
            <p>{t('page.smart_input_history.empty')}</p>
          </div>
        ) : (
          <>
            <div className="rounded-md border">
              <Table>
                <TableHeader>
                  <TableRow>
                    <TableHead>{t('page.smart_input_history.col_date')}</TableHead>
                    <TableHead>{t('page.smart_input_history.col_type')}</TableHead>
                    <TableHead>{t('page.smart_input_history.col_input')}</TableHead>
                    <TableHead className="text-right">{t('page.smart_input_history.col_amount')}</TableHead>
                    <TableHead>{t('page.smart_input_history.col_confidence')}</TableHead>
                    <TableHead>{t('page.smart_input_history.col_status')}</TableHead>
                    <TableHead className="w-[50px]" />
                  </TableRow>
                </TableHeader>
                <TableBody>
                  {histories.data.map((entry) => {
                    const Icon = INPUT_TYPE_ICONS[entry.input_type] || Type
                    const parsed = entry.parsed_result as Record<string, unknown> | null
                    const amount = parsed?.amount as number | undefined

                    return (
                      <TableRow key={entry.id}>
                        <TableCell className="whitespace-nowrap">
                          {format(new Date(entry.created_at), 'dd/MM/yyyy HH:mm')}
                        </TableCell>
                        <TableCell>
                          <div className="flex items-center gap-1.5">
                            <Icon className="h-4 w-4 text-muted-foreground" />
                            <span className="text-xs capitalize">{entry.input_type.replace('_', '+')}</span>
                          </div>
                        </TableCell>
                        <TableCell className="max-w-[200px] truncate">
                          {entry.raw_text || '-'}
                        </TableCell>
                        <TableCell className="text-right whitespace-nowrap">
                          {amount ? formatMoney(amount) : '-'}
                        </TableCell>
                        <TableCell>
                          {entry.confidence != null ? (
                            <Badge variant={entry.confidence >= 0.8 ? 'default' : entry.confidence >= 0.5 ? 'secondary' : 'outline'}>
                              {Math.round(entry.confidence * 100)}%
                            </Badge>
                          ) : '-'}
                        </TableCell>
                        <TableCell>
                          {entry.transaction_saved ? (
                            <Badge variant="default" className="gap-1">
                              <Check className="h-3 w-3" />
                              {t('page.smart_input_history.saved')}
                            </Badge>
                          ) : (
                            <Badge variant="outline" className="gap-1">
                              <X className="h-3 w-3" />
                              {t('page.smart_input_history.unsaved')}
                            </Badge>
                          )}
                        </TableCell>
                        <TableCell>
                          <Button
                            variant="ghost"
                            size="icon"
                            onClick={() => setDeleteId(entry.id)}
                          >
                            <Trash2 className="h-4 w-4" />
                          </Button>
                        </TableCell>
                      </TableRow>
                    )
                  })}
                </TableBody>
              </Table>
            </div>

            {/* Pagination */}
            {histories.last_page > 1 && (
              <div className="flex items-center justify-between mt-4">
                <p className="text-sm text-muted-foreground">
                  {t('page.smart_input_history.showing', {
                    from: histories.from,
                    to: histories.to,
                    total: histories.total,
                  })}
                </p>
                <div className="flex gap-1">
                  {Array.from({ length: histories.last_page }, (_, i) => i + 1).map((page) => (
                    <Button
                      key={page}
                      variant={page === histories.current_page ? 'default' : 'outline'}
                      size="sm"
                      onClick={() => goToPage(page)}
                    >
                      {page}
                    </Button>
                  ))}
                </div>
              </div>
            )}
          </>
        )}

        {/* Delete dialog */}
        <AlertDialog open={deleteId !== null} onOpenChange={(open) => !open && setDeleteId(null)}>
          <AlertDialogContent>
            <AlertDialogHeader>
              <AlertDialogTitle>{t('page.smart_input_history.delete_title')}</AlertDialogTitle>
              <AlertDialogDescription>{t('page.smart_input_history.delete_description')}</AlertDialogDescription>
            </AlertDialogHeader>
            <AlertDialogFooter>
              <AlertDialogCancel>{t('common.cancel')}</AlertDialogCancel>
              <AlertDialogAction onClick={handleDelete}>{t('common.delete')}</AlertDialogAction>
            </AlertDialogFooter>
          </AlertDialogContent>
        </AlertDialog>
      </Main>
    </AuthenticatedLayout>
  )
}
