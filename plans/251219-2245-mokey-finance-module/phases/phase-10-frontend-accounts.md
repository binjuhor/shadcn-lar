# Phase 10: Frontend - Accounts Module

## Context
- Parent plan: [plan.md](../plan.md)
- Dependencies: Phase 09 (dashboard and types)

## Overview
- Priority: high
- Status: pending
- Description: Build accounts list page with data table, create/edit forms using react-hook-form + zod.

## Key Insights
From research: Follow posts.tsx pattern for data tables. Use shadcn form components. Inertia router for navigation.

## Requirements
### Functional
- Accounts list with type filter, balance display
- Create account form with all fields
- Edit account form
- Account detail view with recent transactions
- Delete confirmation

### Non-functional
- Use @tanstack/react-table
- Form validation with zod
- Optimistic UI updates

## Related Code Files
### Files to Create
```
resources/js/pages/mokey/
├── accounts.tsx
├── account.tsx
├── create-account.tsx
├── edit-account.tsx
└── components/
    ├── account-form.tsx
    ├── account-columns.tsx
    └── account-type-badge.tsx
```

## Implementation Steps

### 1. Create Account types
```typescript
// Add to resources/js/pages/mokey/types/mokey.ts
export interface Account {
  id: number
  account_type: 'checking' | 'savings' | 'credit_card' | 'cash' | 'investment'
  name: string
  currency_code: string
  account_number: string | null
  institution_name: string | null
  current_balance: number
  current_balance_formatted: string
  initial_balance: number
  is_active: boolean
  include_in_net_worth: boolean
  created_at: string
}

export interface Currency {
  code: string
  name: string
  symbol: string
}

export interface AccountFilters {
  type?: string
  active_only?: boolean
}
```

### 2. Create Account Form component
```tsx
// resources/js/pages/mokey/components/account-form.tsx
import { useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { z } from 'zod'
import { router } from '@inertiajs/react'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select'
import { Switch } from '@/components/ui/switch'
import { Account, Currency } from '../types/mokey'

const accountSchema = z.object({
  name: z.string().min(1, 'Name is required').max(255),
  account_type: z.enum(['checking', 'savings', 'credit_card', 'cash', 'investment']),
  currency_code: z.string().length(3, 'Select a currency'),
  account_number: z.string().max(255).optional().nullable(),
  institution_name: z.string().max(255).optional().nullable(),
  initial_balance: z.number().min(0, 'Balance cannot be negative'),
  is_active: z.boolean().default(true),
  include_in_net_worth: z.boolean().default(true),
})

type AccountFormData = z.infer<typeof accountSchema>

interface AccountFormProps {
  account?: Account
  currencies: Currency[]
  accountTypes: string[]
}

export function AccountForm({ account, currencies, accountTypes }: AccountFormProps) {
  const isEditing = !!account

  const { register, handleSubmit, setValue, watch, formState: { errors, isSubmitting } } = useForm<AccountFormData>({
    resolver: zodResolver(accountSchema),
    defaultValues: {
      name: account?.name ?? '',
      account_type: account?.account_type ?? 'checking',
      currency_code: account?.currency_code ?? 'USD',
      account_number: account?.account_number ?? '',
      institution_name: account?.institution_name ?? '',
      initial_balance: account ? account.initial_balance / 100 : 0,
      is_active: account?.is_active ?? true,
      include_in_net_worth: account?.include_in_net_worth ?? true,
    },
  })

  const onSubmit = (data: AccountFormData) => {
    // Convert balance to cents
    const payload = {
      ...data,
      initial_balance: Math.round(data.initial_balance * 100),
    }

    if (isEditing) {
      router.put(route('dashboard.mokey.accounts.update', account.id), payload)
    } else {
      router.post(route('dashboard.mokey.accounts.store'), payload)
    }
  }

  return (
    <form onSubmit={handleSubmit(onSubmit)} className="space-y-6">
      <div className="grid gap-4 md:grid-cols-2">
        <div className="space-y-2">
          <Label htmlFor="name">Account Name</Label>
          <Input id="name" {...register('name')} placeholder="e.g., Main Checking" />
          {errors.name && <p className="text-sm text-red-500">{errors.name.message}</p>}
        </div>

        <div className="space-y-2">
          <Label htmlFor="account_type">Account Type</Label>
          <Select value={watch('account_type')} onValueChange={(val) => setValue('account_type', val as any)}>
            <SelectTrigger>
              <SelectValue placeholder="Select type" />
            </SelectTrigger>
            <SelectContent>
              {accountTypes.map((type) => (
                <SelectItem key={type} value={type}>
                  {type.replace('_', ' ').replace(/\b\w/g, l => l.toUpperCase())}
                </SelectItem>
              ))}
            </SelectContent>
          </Select>
          {errors.account_type && <p className="text-sm text-red-500">{errors.account_type.message}</p>}
        </div>

        <div className="space-y-2">
          <Label htmlFor="currency_code">Currency</Label>
          <Select value={watch('currency_code')} onValueChange={(val) => setValue('currency_code', val)}>
            <SelectTrigger>
              <SelectValue placeholder="Select currency" />
            </SelectTrigger>
            <SelectContent>
              {currencies.map((currency) => (
                <SelectItem key={currency.code} value={currency.code}>
                  {currency.symbol} {currency.name} ({currency.code})
                </SelectItem>
              ))}
            </SelectContent>
          </Select>
          {errors.currency_code && <p className="text-sm text-red-500">{errors.currency_code.message}</p>}
        </div>

        <div className="space-y-2">
          <Label htmlFor="initial_balance">Initial Balance</Label>
          <Input
            id="initial_balance"
            type="number"
            step="0.01"
            {...register('initial_balance', { valueAsNumber: true })}
          />
          {errors.initial_balance && <p className="text-sm text-red-500">{errors.initial_balance.message}</p>}
        </div>

        <div className="space-y-2">
          <Label htmlFor="institution_name">Institution (optional)</Label>
          <Input id="institution_name" {...register('institution_name')} placeholder="e.g., Chase Bank" />
        </div>

        <div className="space-y-2">
          <Label htmlFor="account_number">Account Number (optional)</Label>
          <Input id="account_number" {...register('account_number')} placeholder="Last 4 digits" />
        </div>
      </div>

      <div className="flex items-center gap-6">
        <div className="flex items-center space-x-2">
          <Switch
            id="is_active"
            checked={watch('is_active')}
            onCheckedChange={(val) => setValue('is_active', val)}
          />
          <Label htmlFor="is_active">Active</Label>
        </div>

        <div className="flex items-center space-x-2">
          <Switch
            id="include_in_net_worth"
            checked={watch('include_in_net_worth')}
            onCheckedChange={(val) => setValue('include_in_net_worth', val)}
          />
          <Label htmlFor="include_in_net_worth">Include in Net Worth</Label>
        </div>
      </div>

      <div className="flex gap-4">
        <Button type="submit" disabled={isSubmitting}>
          {isEditing ? 'Update Account' : 'Create Account'}
        </Button>
        <Button type="button" variant="outline" onClick={() => router.get(route('dashboard.mokey.accounts.index'))}>
          Cancel
        </Button>
      </div>
    </form>
  )
}
```

### 3. Create Accounts List page
```tsx
// resources/js/pages/mokey/accounts.tsx
import { AuthenticatedLayout } from '@/layouts'
import { Main } from '@/components/layout'
import { usePage } from '@inertiajs/react'
import { router } from '@inertiajs/react'
import { useState } from 'react'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import {
  Table, TableBody, TableCell, TableHead, TableHeader, TableRow,
} from '@/components/ui/table'
import {
  DropdownMenu, DropdownMenuContent, DropdownMenuItem,
  DropdownMenuLabel, DropdownMenuSeparator, DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu'
import { Badge } from '@/components/ui/badge'
import { IconPlus, IconDotsVertical, IconEye, IconEdit, IconTrash } from '@tabler/icons-react'
import { Account, Currency, AccountFilters } from './types/mokey'
import { PageProps } from '@/types'
import { useToast } from '@/hooks/use-toast'

interface AccountsPageProps extends PageProps {
  accounts: {
    data: Account[]
    current_page: number
    last_page: number
    total: number
  }
  filters: AccountFilters
  currencies: Currency[]
  account_types: string[]
}

export default function AccountsPage() {
  const { accounts, filters, account_types } = usePage<AccountsPageProps>().props
  const { toast } = useToast()

  const handleDelete = (account: Account) => {
    if (confirm(`Delete account "${account.name}"?`)) {
      router.delete(route('dashboard.mokey.accounts.destroy', account.id), {
        onSuccess: () => toast({ title: 'Account deleted' }),
      })
    }
  }

  const getTypeBadge = (type: string) => {
    const colors: Record<string, string> = {
      checking: 'bg-blue-100 text-blue-800',
      savings: 'bg-green-100 text-green-800',
      credit_card: 'bg-purple-100 text-purple-800',
      cash: 'bg-yellow-100 text-yellow-800',
      investment: 'bg-indigo-100 text-indigo-800',
    }
    return <Badge className={colors[type] || ''}>{type.replace('_', ' ')}</Badge>
  }

  return (
    <AuthenticatedLayout title="Accounts">
      <Main>
        <div className="flex items-center justify-between mb-6">
          <div>
            <h2 className="text-3xl font-bold tracking-tight">Accounts</h2>
            <p className="text-muted-foreground">Manage your financial accounts</p>
          </div>
          <Button onClick={() => router.get(route('dashboard.mokey.accounts.create'))}>
            <IconPlus className="mr-2 h-4 w-4" /> Add Account
          </Button>
        </div>

        <Card>
          <CardHeader>
            <CardTitle>All Accounts</CardTitle>
          </CardHeader>
          <CardContent>
            <Table>
              <TableHeader>
                <TableRow>
                  <TableHead>Name</TableHead>
                  <TableHead>Type</TableHead>
                  <TableHead>Institution</TableHead>
                  <TableHead className="text-right">Balance</TableHead>
                  <TableHead>Status</TableHead>
                  <TableHead><span className="sr-only">Actions</span></TableHead>
                </TableRow>
              </TableHeader>
              <TableBody>
                {accounts.data.map((account) => (
                  <TableRow key={account.id}>
                    <TableCell className="font-medium">{account.name}</TableCell>
                    <TableCell>{getTypeBadge(account.account_type)}</TableCell>
                    <TableCell>{account.institution_name || '-'}</TableCell>
                    <TableCell className="text-right font-mono">
                      {account.current_balance_formatted}
                    </TableCell>
                    <TableCell>
                      <Badge variant={account.is_active ? 'default' : 'secondary'}>
                        {account.is_active ? 'Active' : 'Inactive'}
                      </Badge>
                    </TableCell>
                    <TableCell>
                      <DropdownMenu>
                        <DropdownMenuTrigger asChild>
                          <Button variant="ghost" size="icon">
                            <IconDotsVertical className="h-4 w-4" />
                          </Button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="end">
                          <DropdownMenuLabel>Actions</DropdownMenuLabel>
                          <DropdownMenuSeparator />
                          <DropdownMenuItem onClick={() => router.get(route('dashboard.mokey.accounts.show', account.id))}>
                            <IconEye className="mr-2 h-4 w-4" /> View
                          </DropdownMenuItem>
                          <DropdownMenuItem onClick={() => router.get(route('dashboard.mokey.accounts.edit', account.id))}>
                            <IconEdit className="mr-2 h-4 w-4" /> Edit
                          </DropdownMenuItem>
                          <DropdownMenuSeparator />
                          <DropdownMenuItem className="text-red-600" onClick={() => handleDelete(account)}>
                            <IconTrash className="mr-2 h-4 w-4" /> Delete
                          </DropdownMenuItem>
                        </DropdownMenuContent>
                      </DropdownMenu>
                    </TableCell>
                  </TableRow>
                ))}
              </TableBody>
            </Table>
          </CardContent>
        </Card>
      </Main>
    </AuthenticatedLayout>
  )
}
```

### 4. Create Account page and Edit page
Follow same pattern as create-post.tsx and edit-post.tsx.

## Todo List
- [ ] Add Account types to mokey.ts
- [ ] Create AccountForm component with zod validation
- [ ] Create accounts.tsx list page
- [ ] Create create-account.tsx page
- [ ] Create edit-account.tsx page
- [ ] Create account.tsx detail page
- [ ] Add account type badges
- [ ] Add balance formatting
- [ ] Test form validation
- [ ] Test CRUD operations

## Success Criteria
- [ ] Account list displays all accounts with proper formatting
- [ ] Create form validates and submits correctly
- [ ] Edit form pre-populates data
- [ ] Delete confirmation works
- [ ] Balance displayed with currency symbol

## Risk Assessment
- **Risk:** Currency not updating on existing accounts. **Mitigation:** Lock currency field if transactions exist.
- **Risk:** Decimal precision in form. **Mitigation:** Use step="0.01" and round on submit.

## Security Considerations
- Account number field shows placeholder, not full number
- All data filtered by user on backend

## Next Steps
Proceed to Phase 11: Frontend - Transactions Module
