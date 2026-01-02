import { useForm, router, Link } from '@inertiajs/react'
import { AuthenticatedLayout } from '@/layouts'
import { Main } from '@/components/layout/main'
import { Button } from '@/components/ui/button'
import {
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from '@/components/ui/card'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select'
import { DatePicker } from '@/components/ui/date-picker'
import { ArrowLeft, ArrowRightLeft } from 'lucide-react'
import type { Currency } from '@modules/Finance/types/finance'

interface Props {
  currencies: Currency[]
}

export default function ExchangeRateCreate({ currencies }: Props) {
  const { data, setData, post, processing, errors, reset } = useForm({
    base_currency: '',
    target_currency: '',
    rate: '',
    bid_rate: '',
    ask_rate: '',
    rate_date: new Date().toISOString().split('T')[0],
  })

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault()
    post(route('dashboard.finance.exchange-rates.store'), {
      preserveState: false,
      onSuccess: () => reset(),
    })
  }

  return (
    <AuthenticatedLayout title="Add Exchange Rate">
      <Main>
        <div className="mb-4">
          <Button variant="ghost" size="sm" asChild>
            <Link href={route('dashboard.finance.exchange-rates.index')}>
              <ArrowLeft className="mr-2 h-4 w-4" />
              Back to Exchange Rates
            </Link>
          </Button>
        </div>

        <Card className="max-w-2xl">
          <CardHeader>
            <CardTitle className="flex items-center gap-2">
              <ArrowRightLeft className="h-5 w-5" />
              Add Exchange Rate
            </CardTitle>
            <CardDescription>
              Add a new exchange rate manually
            </CardDescription>
          </CardHeader>
          <CardContent>
            <form onSubmit={handleSubmit} className="space-y-6">
              <div className="grid grid-cols-2 gap-4">
                <div className="space-y-2">
                  <Label htmlFor="base_currency">Base Currency</Label>
                  <Select
                    value={data.base_currency}
                    onValueChange={(value) => setData('base_currency', value)}
                  >
                    <SelectTrigger>
                      <SelectValue placeholder="Select currency" />
                    </SelectTrigger>
                    <SelectContent>
                      {currencies.map((c) => (
                        <SelectItem key={c.code} value={c.code}>
                          {c.code} - {c.name}
                        </SelectItem>
                      ))}
                    </SelectContent>
                  </Select>
                  {errors.base_currency && (
                    <p className="text-sm text-red-600">{errors.base_currency}</p>
                  )}
                </div>

                <div className="space-y-2">
                  <Label htmlFor="target_currency">Target Currency</Label>
                  <Select
                    value={data.target_currency}
                    onValueChange={(value) => setData('target_currency', value)}
                  >
                    <SelectTrigger>
                      <SelectValue placeholder="Select currency" />
                    </SelectTrigger>
                    <SelectContent>
                      {currencies.map((c) => (
                        <SelectItem key={c.code} value={c.code}>
                          {c.code} - {c.name}
                        </SelectItem>
                      ))}
                    </SelectContent>
                  </Select>
                  {errors.target_currency && (
                    <p className="text-sm text-red-600">{errors.target_currency}</p>
                  )}
                </div>
              </div>

              <div className="space-y-2">
                <Label htmlFor="rate">Exchange Rate</Label>
                <Input
                  id="rate"
                  type="number"
                  step="0.0000000001"
                  value={data.rate}
                  onChange={(e) => setData('rate', e.target.value)}
                  placeholder="e.g., 24500 for 1 USD = 24,500 VND"
                />
                {errors.rate && (
                  <p className="text-sm text-red-600">{errors.rate}</p>
                )}
                <p className="text-xs text-muted-foreground">
                  The rate should represent how many units of target currency equals 1 unit of base currency
                </p>
              </div>

              <div className="grid grid-cols-2 gap-4">
                <div className="space-y-2">
                  <Label htmlFor="bid_rate">Bid Rate (Optional)</Label>
                  <Input
                    id="bid_rate"
                    type="number"
                    step="0.0000000001"
                    value={data.bid_rate}
                    onChange={(e) => setData('bid_rate', e.target.value)}
                    placeholder="Buy rate"
                  />
                  {errors.bid_rate && (
                    <p className="text-sm text-red-600">{errors.bid_rate}</p>
                  )}
                </div>

                <div className="space-y-2">
                  <Label htmlFor="ask_rate">Ask Rate (Optional)</Label>
                  <Input
                    id="ask_rate"
                    type="number"
                    step="0.0000000001"
                    value={data.ask_rate}
                    onChange={(e) => setData('ask_rate', e.target.value)}
                    placeholder="Sell rate"
                  />
                  {errors.ask_rate && (
                    <p className="text-sm text-red-600">{errors.ask_rate}</p>
                  )}
                </div>
              </div>

              <div className="space-y-2">
                <Label htmlFor="rate_date">Rate Date</Label>
                <DatePicker
                  value={data.rate_date ? new Date(data.rate_date) : undefined}
                  onChange={(date: Date | undefined) =>
                    setData('rate_date', date?.toISOString().split('T')[0] || '')
                  }
                />
                {errors.rate_date && (
                  <p className="text-sm text-red-600">{errors.rate_date}</p>
                )}
              </div>

              <div className="flex gap-4">
                <Button type="submit" disabled={processing}>
                  {processing ? 'Saving...' : 'Add Exchange Rate'}
                </Button>
                <Button
                  type="button"
                  variant="outline"
                  onClick={() =>
                    router.visit(route('dashboard.finance.exchange-rates.index'))
                  }
                >
                  Cancel
                </Button>
              </div>
            </form>
          </CardContent>
        </Card>
      </Main>
    </AuthenticatedLayout>
  )
}
