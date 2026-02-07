import { useTranslation } from 'react-i18next'
import { AppSidebar } from '@/components/layout/app-sidebar'
import { Header } from '@/components/layout/header'
import { TopNav } from '@/components/layout/top-nav'
import { ProfileDropdown } from '@/components/profile-dropdown'
import { Search } from '@/components/search'
import { Head, usePage } from '@inertiajs/react'
import { SidebarInset, SidebarProvider } from '@/components/ui/sidebar'
import { ThemeSwitch } from '@/components/theme-switch'
import { LanguageSwitcher } from '@/components/language-switcher'
import { Separator } from '@/components/ui/separator'
import SidebarNav from '@/pages/settings/components/sidebar-nav'
import { Main } from '@/components/layout'
import { settingsNavItems, type SettingsNavItem } from '@/pages/settings/data/nav-items'
import { usePermission } from '@/hooks/use-permission'
import { PageProps } from '@/types'

export function SettingLayout({ children, title }: { children: React.ReactNode; title?: string }) {
  const { t } = useTranslation()
  const { isSuperAdmin } = usePermission()
  const { url, props } = usePage<PageProps>()
  const enabledModules = props.enabledModules ?? []

  const navItems = [
    { title: t('nav.accounts'), href: '/dashboard/finance/accounts' },
    { title: t('nav.transactions'), href: '/dashboard/finance/transactions' },
    { title: t('nav.smart_input'), href: '/dashboard/finance/smart-input' },
    { title: t('nav.settings'), href: '/dashboard/settings' },
  ]

  const topNav = navItems.map(item => ({
    ...item,
    isActive: url.startsWith(item.href),
    disabled: false,
  }))

  const filteredNavItems = settingsNavItems.filter(item => {
    if (item.superAdminOnly && !isSuperAdmin()) {
      return false
    }

    if (item.requiresModule) {
      const moduleEnabled = enabledModules.some(
        m => m.toLowerCase() === item.requiresModule?.toLowerCase()
      )
      if (!moduleEnabled) {
        return false
      }
    }

    return true
  })

  return (
    <>
      <Head title={title ?? 'Settings'} />
      <SidebarProvider>
        <AppSidebar />
        <SidebarInset>
          <Header>
            <TopNav links={topNav} />
            <div className='ml-auto flex items-center space-x-4'>
              <Search />
              <LanguageSwitcher />
              <ThemeSwitch />
              <ProfileDropdown />
            </div>
          </Header>

          <Main fixed>
            <div className='space-y-0.5'>
              <h1 className='text-2xl font-bold tracking-tight md:text-3xl'>
                {t('settings.title')}
              </h1>
              <p className='text-muted-foreground'>
                {t('settings.description')}
              </p>
            </div>
            <Separator className='my-4 lg:my-6' />
            <div className='flex flex-1 flex-col space-y-2 md:space-y-2 overflow-hidden lg:flex-row lg:space-x-12 lg:space-y-0'>
              <aside className='top-0 lg:sticky lg:w-1/5'>
                <SidebarNav items={filteredNavItems} />
              </aside>
              <div className='flex w-full p-1 pr-4 overflow-y-hidden'>
                {children}
              </div>
            </div>
          </Main>
        </SidebarInset>
      </SidebarProvider>
    </>
  )
}
