import { Button } from '@/components/ui/button'
import {
  Card,
  CardContent,
  CardFooter,
  CardHeader,
  CardTitle,
} from '@/components/ui/card'
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu'
import { Badge } from '@/components/ui/badge'
import {
  MoreHorizontal,
  Pencil,
  Trash2,
  Wallet,
  CreditCard,
  TrendingUp,
  Banknote,
  Building,
  HelpCircle,
  Smartphone,
} from 'lucide-react'
import type { Account, AccountType } from '@modules/Finance/types/finance'

interface AccountCardProps {
  account: Account
  onEdit: (account: Account) => void
  onDelete: (account: Account) => void
}

const accountTypeIcons: Record<AccountType, React.ElementType> = {
  bank: Building,
  credit_card: CreditCard,
  investment: TrendingUp,
  cash: Banknote,
  e_wallet: Smartphone,
  loan: Wallet,
  other: HelpCircle,
}

const accountTypeLabels: Record<AccountType, string> = {
  bank: 'Bank',
  credit_card: 'Credit Card',
  investment: 'Investment',
  cash: 'Cash',
  e_wallet: 'E-Wallet',
  loan: 'Loan',
  other: 'Other',
}

function formatMoney(amount: number, currencyCode = 'VND'): string {
  return new Intl.NumberFormat('vi-VN', {
    style: 'currency',
    currency: currencyCode,
  }).format(amount)
}

export function AccountCard({ account, onEdit, onDelete }: AccountCardProps) {
  const Icon = accountTypeIcons[account.account_type] || Wallet
  const isNegative = account.balance < 0

  return (
    <Card className={!account.is_active ? 'opacity-60' : ''}>
      <CardHeader className="flex flex-row items-center justify-between pb-2">
        <div className="flex items-center gap-3">
          <div
            className="rounded-lg p-2"
            style={{ backgroundColor: account.color ? `${account.color}20` : '#f3f4f6' }}
          >
            <Icon
              className="h-5 w-5"
              style={{ color: account.color || '#6b7280' }}
            />
          </div>
          <div>
            <CardTitle className="text-base">{account.name}</CardTitle>
            <p className="text-xs text-muted-foreground">
              {accountTypeLabels[account.account_type]}
            </p>
          </div>
        </div>
        <DropdownMenu>
          <DropdownMenuTrigger asChild>
            <Button variant="ghost" size="icon" className="h-8 w-8">
              <MoreHorizontal className="h-4 w-4" />
            </Button>
          </DropdownMenuTrigger>
          <DropdownMenuContent align="end">
            <DropdownMenuItem onClick={() => onEdit(account)}>
              <Pencil className="mr-2 h-4 w-4" />
              Edit
            </DropdownMenuItem>
            <DropdownMenuSeparator />
            <DropdownMenuItem
              onClick={() => onDelete(account)}
              className="text-red-600"
            >
              <Trash2 className="mr-2 h-4 w-4" />
              Delete
            </DropdownMenuItem>
          </DropdownMenuContent>
        </DropdownMenu>
      </CardHeader>
      <CardContent>
        <div
          className={`text-2xl font-bold ${
            isNegative ? 'text-red-600' : ''
          }`}
        >
          {formatMoney(account.balance, account.currency_code)}
        </div>
        {account.description && (
          <p className="text-sm text-muted-foreground mt-1 line-clamp-2">
            {account.description}
          </p>
        )}
      </CardContent>
      <CardFooter className="flex gap-2">
        {!account.is_active && (
          <Badge variant="secondary">Inactive</Badge>
        )}
        {account.exclude_from_total && (
          <Badge variant="outline">Excluded from total</Badge>
        )}
      </CardFooter>
    </Card>
  )
}
