import { useState, useMemo } from 'react'
import { router } from '@inertiajs/react'
import { useTranslation } from 'react-i18next'
import { AuthenticatedLayout } from '@/layouts'
import { Main } from '@/components/layout/main'
import { Button } from '@/components/ui/button'
import {
  AlertDialog,
  AlertDialogAction,
  AlertDialogCancel,
  AlertDialogContent,
  AlertDialogDescription,
  AlertDialogFooter,
  AlertDialogHeader,
  AlertDialogTitle,
} from '@/components/ui/alert-dialog'
import { Plus, Wallet } from 'lucide-react'
import { AccountCard } from './components/account-card'
import { AccountForm } from './components/account-form'
import type { Account, AccountSummary, Currency } from '@modules/Finance/types/finance'

interface Props {
  accounts: Account[]
  summary: AccountSummary
  currencies: Currency[]
}

function formatMoney(amount: number, currencyCode = 'VND'): string {
  return new Intl.NumberFormat('vi-VN', {
    style: 'currency',
    currency: currencyCode,
  }).format(amount)
}

export default function AccountsIndex({ accounts, summary, currencies }: Props) {
  const { t } = useTranslation()
  const [showForm, setShowForm] = useState(false)
  const [showDeleteDialog, setShowDeleteDialog] = useState(false)
  const [selectedAccount, setSelectedAccount] = useState<Account | null>(null)
  const [filterType, setFilterType] = useState('all')

  const filterTypes = [
    { value: 'all', label: t('filter.all') },
    { value: 'bank', label: t('filter.bank') },
    { value: 'credit_card', label: t('filter.credit_card') },
    { value: 'investment', label: t('filter.investment') },
    { value: 'cash', label: t('filter.cash') },
  ]

  const filteredAccounts = useMemo(() => {
    if (filterType === 'all') {
      return accounts
    }
    return accounts.filter((account) => account.account_type === filterType)
  }, [accounts, filterType])

  const activeAccounts = filteredAccounts.filter((a) => a.is_active)
  const inactiveAccounts = filteredAccounts.filter((a) => !a.is_active)

  const handleEdit = (account: Account) => {
    setSelectedAccount(account)
    setShowForm(true)
  }

  const handleDelete = (account: Account) => {
    setSelectedAccount(account)
    setShowDeleteDialog(true)
  }

  const confirmDelete = () => {
    if (selectedAccount) {
      router.delete(route('dashboard.finance.accounts.destroy', selectedAccount.id), {
        onSuccess: () => {
          setShowDeleteDialog(false)
          setSelectedAccount(null)
        },
      })
    }
  }

  const handleFormClose = () => {
    setShowForm(false)
    setSelectedAccount(null)
  }

  const handleSuccess = () => {
    router.reload({ only: ['accounts', 'summary'] })
  }

  return (
    <AuthenticatedLayout title={t('page.accounts.title')}>
      <Main>
        <div className="mb-4 md:flex items-center justify-between">
          <div className="mb-4">
            <h1 className="text-2xl font-bold tracking-tight">{t('page.accounts.title')}</h1>
            <p className="text-muted-foreground">
              {t('page.accounts.description')}
            </p>
          </div>
          <Button onClick={() => setShowForm(true)}>
            <Plus className="mr-2 h-4 w-4" />
            {t('page.accounts.new')}
          </Button>
        </div>

        {/* Summary */}
        <div className="mb-6 grid md:grid-cols-3 gap-4">
          <div className="rounded-lg border bg-card p-4">
            <p className="text-sm text-muted-foreground">{t('page.accounts.total_assets')}</p>
            <p className="text-2xl font-bold text-green-600">
              {formatMoney(summary.total_assets, summary.currency_code)}
            </p>
          </div>
          <div className="rounded-lg border bg-card p-4">
            <p className="text-sm text-muted-foreground">{t('page.accounts.total_liabilities')}</p>
            <p className="text-2xl font-bold text-red-600">
              {formatMoney(summary.total_liabilities, summary.currency_code)}
            </p>
          </div>
          <div className="rounded-lg border bg-card p-4">
            <p className="text-sm text-muted-foreground">{t('page.accounts.net_worth')}</p>
            <p className="text-2xl font-bold">
              {formatMoney(summary.net_worth, summary.currency_code)}
            </p>
          </div>
        </div>

        {/* Filters */}
        <div className="flex gap-2 flex-wrap mb-6">
          {filterTypes.map((type) => (
            <Button
              key={type.value}
              variant={filterType === type.value ? 'default' : 'outline'}
              size="sm"
              onClick={() => setFilterType(type.value)}
            >
              {type.label}
            </Button>
          ))}
        </div>

        {/* Active Accounts */}
        {activeAccounts.length > 0 && (
          <div className="mb-6">
            <h3 className="text-lg font-medium mb-4">{t('page.accounts.active_accounts')}</h3>
            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
              {activeAccounts.map((account) => (
                <AccountCard
                  key={account.id}
                  account={account}
                  onEdit={handleEdit}
                  onDelete={handleDelete}
                />
              ))}
            </div>
          </div>
        )}

        {/* Inactive Accounts */}
        {inactiveAccounts.length > 0 && (
          <div>
            <h3 className="text-lg font-medium text-muted-foreground mb-4">
              {t('page.accounts.inactive_accounts')}
            </h3>
            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
              {inactiveAccounts.map((account) => (
                <AccountCard
                  key={account.id}
                  account={account}
                  onEdit={handleEdit}
                  onDelete={handleDelete}
                />
              ))}
            </div>
          </div>
        )}

        {/* Empty State */}
        {activeAccounts.length === 0 && inactiveAccounts.length === 0 && (
          <div className="flex flex-col items-center justify-center py-16 text-center">
            <Wallet className="h-16 w-16 text-muted-foreground mb-4" />
            <h3 className="text-xl font-semibold mb-2">{t('page.accounts.no_accounts')}</h3>
            <p className="text-muted-foreground mb-4">
              {t('page.accounts.get_started')}
            </p>
            <Button onClick={() => setShowForm(true)}>
              <Plus className="mr-2 h-4 w-4" />
              {t('page.accounts.create')}
            </Button>
          </div>
        )}

        {/* Account Form */}
        <AccountForm
          open={showForm}
          onOpenChange={handleFormClose}
          account={selectedAccount}
          currencies={currencies}
          onSuccess={handleSuccess}
        />

        {/* Delete Confirmation */}
        <AlertDialog open={showDeleteDialog} onOpenChange={setShowDeleteDialog}>
          <AlertDialogContent>
            <AlertDialogHeader>
              <AlertDialogTitle>{t('page.accounts.delete_title')}</AlertDialogTitle>
              <AlertDialogDescription>
                {t('page.accounts.delete_description', { name: selectedAccount?.name || '' })}
              </AlertDialogDescription>
            </AlertDialogHeader>
            <AlertDialogFooter>
              <AlertDialogCancel>{t('common.cancel')}</AlertDialogCancel>
              <AlertDialogAction
                onClick={confirmDelete}
                className="bg-red-600 hover:bg-red-700"
              >
                {t('common.delete')}
              </AlertDialogAction>
            </AlertDialogFooter>
          </AlertDialogContent>
        </AlertDialog>
      </Main>
    </AuthenticatedLayout>
  )
}
