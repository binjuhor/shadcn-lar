import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { IconPlus, IconTrash } from '@tabler/icons-react'
import { LineItem } from '../data/schema'

interface Props {
  items: LineItem[]
  onItemChange: (index: number, field: keyof LineItem, value: string | number) => void
  onAddItem: () => void
  onRemoveItem: (index: number) => void
}

export function LineItemsInput({ items, onItemChange, onAddItem, onRemoveItem }: Props) {
  return (
    <div className="space-y-4">
      <div className="grid grid-cols-12 gap-2 font-medium text-sm text-muted-foreground">
        <div className="col-span-5">Description</div>
        <div className="col-span-2">Qty</div>
        <div className="col-span-2">Unit Price</div>
        <div className="col-span-2 text-right">Amount</div>
        <div className="col-span-1"></div>
      </div>

      {items.map((item, index) => (
        <div key={item.id || index} className="grid grid-cols-12 gap-2 items-center">
          <Input
            className="col-span-5"
            value={item.description}
            onChange={e => onItemChange(index, 'description', e.target.value)}
            placeholder="Item description"
          />
          <Input
            className="col-span-2"
            type="number"
            step="0.01"
            min="0.01"
            value={item.quantity}
            onChange={e => onItemChange(index, 'quantity', parseFloat(e.target.value) || 0)}
          />
          <Input
            className="col-span-2"
            type="number"
            step="0.01"
            min="0"
            value={item.unit_price}
            onChange={e => onItemChange(index, 'unit_price', parseFloat(e.target.value) || 0)}
          />
          <div className="col-span-2 text-right font-medium">
            ${(item.quantity * item.unit_price).toFixed(2)}
          </div>
          <Button
            type="button"
            variant="ghost"
            size="icon"
            className="col-span-1"
            onClick={() => onRemoveItem(index)}
            disabled={items.length === 1}
          >
            <IconTrash size={16} />
          </Button>
        </div>
      ))}

      <Button type="button" variant="outline" onClick={onAddItem} className="w-full">
        <IconPlus size={16} className="mr-2" /> Add Item
      </Button>
    </div>
  )
}
