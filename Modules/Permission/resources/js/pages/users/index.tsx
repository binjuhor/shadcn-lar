import { AuthenticatedLayout } from '@/layouts'
import { useTranslation } from 'react-i18next'
import { Main } from '@/components/layout/main'
import { PageProps, Role } from '@/types'
import { columns } from './components/users-columns'
import { UsersDialogs } from './components/users-dialogs'
import { UsersPrimaryButtons } from './components/users-primary-buttons'
import { UsersTable } from './components/users-table'
import UsersProvider from './context/users-context'
import { PaginatedUsers } from './data/schema'

interface UsersPageProps extends PageProps {
  users: PaginatedUsers
  roles: Role[]
  filters?: {
    search?: string
    role?: string
  }
}

// Default pagination values for safety
const defaultPagination: PaginatedUsers = {
  data: [],
  current_page: 1,
  last_page: 1,
  per_page: 10,
  total: 0,
}

export default function Users({
  users = defaultPagination,
  roles = [],
  filters = {},
}: UsersPageProps) {
  const { t } = useTranslation()

  // Ensure users has proper structure
  const safeUsers: PaginatedUsers = {
    data: users?.data ?? [],
    current_page: users?.current_page ?? 1,
    last_page: users?.last_page ?? 1,
    per_page: users?.per_page ?? 10,
    total: users?.total ?? 0,
  }

  return (
    <UsersProvider>
      <AuthenticatedLayout title={t('page.users.title')}>
        <Main>
          <div className="mb-2 flex items-center justify-between space-y-2 flex-wrap">
            <div>
              <h2 className="text-2xl font-bold tracking-tight">{t('page.users.list')}</h2>
              <p className="text-muted-foreground">
                {t('page.users.description')}
              </p>
            </div>
            <UsersPrimaryButtons />
          </div>
          <div className="-mx-4 flex-1 overflow-auto px-4 py-1 lg:flex-row lg:space-x-12 lg:space-y-0">
            <UsersTable
              data={safeUsers.data}
              columns={columns}
              pagination={safeUsers}
              roles={roles ?? []}
              filters={filters ?? {}}
            />
          </div>
        </Main>

        <UsersDialogs />
      </AuthenticatedLayout>
    </UsersProvider>
  )
}
