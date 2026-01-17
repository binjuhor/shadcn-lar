import { useState } from 'react'
import { format } from 'date-fns'
import { router } from '@inertiajs/react'
import { CalendarIcon } from 'lucide-react'
import { Button } from '@/components/ui/button'
import { Calendar } from '@/components/ui/calendar'
import {
  Popover,
  PopoverContent,
  PopoverTrigger,
} from '@/components/ui/popover'
import { cn } from '@/lib/utils'

type DateRangePreset = '30d' | '6m' | '12m' | 'ytd' | 'custom'

interface ReportFilters {
  range: DateRangePreset
  startDate: string
  endDate: string
}

interface DateRangePickerProps {
  filters: ReportFilters
}

const presets: { value: DateRangePreset; label: string }[] = [
  { value: '30d', label: '30 Days' },
  { value: '6m', label: '6 Months' },
  { value: '12m', label: '12 Months' },
  { value: 'ytd', label: 'YTD' },
  { value: 'custom', label: 'Custom' },
]

export function DateRangePicker({ filters }: DateRangePickerProps) {
  const [showCustom, setShowCustom] = useState(filters.range === 'custom')
  const [startDate, setStartDate] = useState<Date | undefined>(
    filters.startDate ? new Date(filters.startDate) : undefined
  )
  const [endDate, setEndDate] = useState<Date | undefined>(
    filters.endDate ? new Date(filters.endDate) : undefined
  )
  const [startDateOpen, setStartDateOpen] = useState(false)
  const [endDateOpen, setEndDateOpen] = useState(false)

  const handlePresetChange = (preset: DateRangePreset) => {
    if (preset === 'custom') {
      setShowCustom(true)
      return
    }

    setShowCustom(false)
    router.get(
      route('dashboard.invoices.reports'),
      { range: preset },
      { preserveState: true, preserveScroll: true }
    )
  }

  const handleCustomRangeApply = () => {
    if (!startDate || !endDate) return

    router.get(
      route('dashboard.invoices.reports'),
      {
        range: 'custom',
        start: format(startDate, 'yyyy-MM-dd'),
        end: format(endDate, 'yyyy-MM-dd'),
      },
      { preserveState: true, preserveScroll: true }
    )
  }

  return (
    <div className="flex flex-wrap items-center gap-2">
      <div className="flex rounded-lg border bg-muted/50 p-1">
        {presets.map((preset) => (
          <Button
            key={preset.value}
            variant={filters.range === preset.value ? 'secondary' : 'ghost'}
            size="sm"
            onClick={() => handlePresetChange(preset.value)}
            className="px-3"
          >
            {preset.label}
          </Button>
        ))}
      </div>

      {showCustom && (
        <div className="flex items-center gap-2">
          <Popover open={startDateOpen} onOpenChange={setStartDateOpen}>
            <PopoverTrigger asChild>
              <Button
                variant="outline"
                size="sm"
                className={cn(
                  'w-[130px] justify-start text-left font-normal',
                  !startDate && 'text-muted-foreground'
                )}
              >
                <CalendarIcon className="mr-2 h-4 w-4" />
                {startDate ? format(startDate, 'MMM d, yyyy') : 'Start date'}
              </Button>
            </PopoverTrigger>
            <PopoverContent className="w-auto p-0" align="start">
              <Calendar
                mode="single"
                selected={startDate}
                defaultMonth={startDate}
                onSelect={(date) => {
                  setStartDate(date)
                  setStartDateOpen(false)
                }}
                disabled={(date) => endDate ? date > endDate : false}
                captionLayout="dropdown"
                fromYear={2000}
                toYear={new Date().getFullYear() + 1}
              />
            </PopoverContent>
          </Popover>

          <span className="text-muted-foreground">to</span>

          <Popover open={endDateOpen} onOpenChange={setEndDateOpen}>
            <PopoverTrigger asChild>
              <Button
                variant="outline"
                size="sm"
                className={cn(
                  'w-[130px] justify-start text-left font-normal',
                  !endDate && 'text-muted-foreground'
                )}
              >
                <CalendarIcon className="mr-2 h-4 w-4" />
                {endDate ? format(endDate, 'MMM d, yyyy') : 'End date'}
              </Button>
            </PopoverTrigger>
            <PopoverContent className="w-auto p-0" align="start">
              <Calendar
                mode="single"
                selected={endDate}
                defaultMonth={endDate}
                onSelect={(date) => {
                  setEndDate(date)
                  setEndDateOpen(false)
                }}
                disabled={(date) => startDate ? date < startDate : false}
                captionLayout="dropdown"
                fromYear={2000}
                toYear={new Date().getFullYear() + 1}
              />
            </PopoverContent>
          </Popover>

          <Button size="sm" onClick={handleCustomRangeApply} disabled={!startDate || !endDate}>
            Apply
          </Button>
        </div>
      )}
    </div>
  )
}
