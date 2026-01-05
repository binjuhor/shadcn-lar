import { useState } from 'react'
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
import { Download, FileSpreadsheet, FileText } from 'lucide-react'

interface ExportDialogProps {
  open: boolean
  onOpenChange: (open: boolean) => void
}

type PeriodType = 'custom' | 'month' | 'year'
type FormatType = 'csv' | 'excel'

const currentYear = new Date().getFullYear()
const years = Array.from({ length: 5 }, (_, i) => currentYear - i)
const months = [
  { value: '01', label: 'January' },
  { value: '02', label: 'February' },
  { value: '03', label: 'March' },
  { value: '04', label: 'April' },
  { value: '05', label: 'May' },
  { value: '06', label: 'June' },
  { value: '07', label: 'July' },
  { value: '08', label: 'August' },
  { value: '09', label: 'September' },
  { value: '10', label: 'October' },
  { value: '11', label: 'November' },
  { value: '12', label: 'December' },
]

export function ExportDialog({ open, onOpenChange }: ExportDialogProps) {
  const [period, setPeriod] = useState<PeriodType>('month')
  const [exportFormat, setExportFormat] = useState<FormatType>('csv')
  const [dateFrom, setDateFrom] = useState('')
  const [dateTo, setDateTo] = useState('')
  const [selectedMonth, setSelectedMonth] = useState(format(new Date(), 'MM'))
  const [selectedYear, setSelectedYear] = useState(String(currentYear))
  const [isExporting, setIsExporting] = useState(false)

  const handleExport = async () => {
    setIsExporting(true)

    const params = new URLSearchParams({
      format: exportFormat,
      period,
    })

    if (period === 'custom') {
      params.append('date_from', dateFrom)
      params.append('date_to', dateTo)
    } else if (period === 'month') {
      params.append('month', `${selectedYear}-${selectedMonth}`)
    } else if (period === 'year') {
      params.append('year', selectedYear)
    }

    try {
      const url = route('dashboard.finance.transactions.export') + '?' + params.toString()
      window.location.href = url
      onOpenChange(false)
    } finally {
      setIsExporting(false)
    }
  }

  const isValid = () => {
    if (period === 'custom') {
      return dateFrom && dateTo
    }
    if (period === 'month') {
      return selectedMonth && selectedYear
    }
    if (period === 'year') {
      return selectedYear
    }
    return false
  }

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="sm:max-w-md">
        <DialogHeader>
          <DialogTitle>Export Transactions</DialogTitle>
          <DialogDescription>
            Choose period and format to export your transactions
          </DialogDescription>
        </DialogHeader>

        <div className="space-y-4 py-4">
          <div className="space-y-2">
            <Label>Period</Label>
            <Select value={period} onValueChange={(v) => setPeriod(v as PeriodType)}>
              <SelectTrigger>
                <SelectValue />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="month">By Month</SelectItem>
                <SelectItem value="year">By Year</SelectItem>
                <SelectItem value="custom">Custom Date Range</SelectItem>
              </SelectContent>
            </Select>
          </div>

          {period === 'month' && (
            <div className="grid grid-cols-2 gap-4">
              <div className="space-y-2">
                <Label>Month</Label>
                <Select value={selectedMonth} onValueChange={setSelectedMonth}>
                  <SelectTrigger>
                    <SelectValue />
                  </SelectTrigger>
                  <SelectContent>
                    {months.map((m) => (
                      <SelectItem key={m.value} value={m.value}>
                        {m.label}
                      </SelectItem>
                    ))}
                  </SelectContent>
                </Select>
              </div>
              <div className="space-y-2">
                <Label>Year</Label>
                <Select value={selectedYear} onValueChange={setSelectedYear}>
                  <SelectTrigger>
                    <SelectValue />
                  </SelectTrigger>
                  <SelectContent>
                    {years.map((y) => (
                      <SelectItem key={y} value={String(y)}>
                        {y}
                      </SelectItem>
                    ))}
                  </SelectContent>
                </Select>
              </div>
            </div>
          )}

          {period === 'year' && (
            <div className="space-y-2">
              <Label>Year</Label>
              <Select value={selectedYear} onValueChange={setSelectedYear}>
                <SelectTrigger>
                  <SelectValue />
                </SelectTrigger>
                <SelectContent>
                  {years.map((y) => (
                    <SelectItem key={y} value={String(y)}>
                      {y}
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
            </div>
          )}

          {period === 'custom' && (
            <div className="grid grid-cols-2 gap-4">
              <div className="space-y-2">
                <Label>From</Label>
                <DatePicker
                  value={dateFrom}
                  onChange={(date) => setDateFrom(date ? format(date, 'yyyy-MM-dd') : '')}
                  placeholder="Start date"
                />
              </div>
              <div className="space-y-2">
                <Label>To</Label>
                <DatePicker
                  value={dateTo}
                  onChange={(date) => setDateTo(date ? format(date, 'yyyy-MM-dd') : '')}
                  placeholder="End date"
                />
              </div>
            </div>
          )}

          <div className="space-y-2">
            <Label>Format</Label>
            <div className="grid grid-cols-2 gap-2">
              <Button
                type="button"
                variant={exportFormat === 'csv' ? 'default' : 'outline'}
                className="justify-start"
                onClick={() => setExportFormat('csv')}
              >
                <FileText className="mr-2 h-4 w-4" />
                CSV
              </Button>
              <Button
                type="button"
                variant={exportFormat === 'excel' ? 'default' : 'outline'}
                className="justify-start"
                onClick={() => setExportFormat('excel')}
              >
                <FileSpreadsheet className="mr-2 h-4 w-4" />
                Excel
              </Button>
            </div>
          </div>
        </div>

        <DialogFooter>
          <Button variant="outline" onClick={() => onOpenChange(false)}>
            Cancel
          </Button>
          <Button onClick={handleExport} disabled={!isValid() || isExporting}>
            <Download className="mr-2 h-4 w-4" />
            {isExporting ? 'Exporting...' : 'Export'}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  )
}
