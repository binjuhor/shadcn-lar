import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'

interface Props {
  totals: {
    subtotal: number
    tax: number
    total: number
  }
  taxRate: number
}

export function InvoiceSummary({ totals, taxRate }: Props) {
  return (
    <Card>
      <CardHeader>
        <CardTitle>Summary</CardTitle>
      </CardHeader>
      <CardContent className="space-y-3">
        <div className="flex justify-between text-sm">
          <span className="text-muted-foreground">Subtotal</span>
          <span className="font-medium">${totals.subtotal.toFixed(2)}</span>
        </div>
        <div className="flex justify-between text-sm">
          <span className="text-muted-foreground">Tax ({(taxRate * 100).toFixed(0)}%)</span>
          <span className="font-medium">${totals.tax.toFixed(2)}</span>
        </div>
        <div className="border-t pt-3 flex justify-between">
          <span className="font-semibold">Total</span>
          <span className="font-bold text-lg">${totals.total.toFixed(2)}</span>
        </div>
      </CardContent>
    </Card>
  )
}
