import { useTranslation } from 'react-i18next'
import {AppSidebar} from "@/components/layout/app-sidebar"
import {Header} from '@/components/layout/header'
import {TopNav} from '@/components/layout/top-nav'
import {ProfileDropdown} from '@/components/profile-dropdown'
import {Search} from '@/components/search'

import {
  SidebarInset,
  SidebarProvider,
} from "@/components/ui/sidebar"
import {ThemeSwitch} from "@/components/theme-switch"
import {LanguageSwitcher} from "@/components/language-switcher"
import {Head, usePage} from "@inertiajs/react";
import {usePermission} from "@/hooks/use-permission";

export function AuthenticatedLayout({
    children,
    title,
    showHeader = true,
    withTopNav = true,
  }: any) {
  const { t } = useTranslation()
  const { url } = usePage()
  const { isFeatureEnabled } = usePermission()

  const navItems = [
    { title: t('nav.accounts'), href: '/dashboard/finance/accounts' },
    { title: t('nav.transactions'), href: '/dashboard/finance/transactions' },
    ...(isFeatureEnabled('assistant') ? [{ title: t('nav.ai_assistant'), href: '/dashboard/finance/assistant' }] : []),
    { title: t('nav.settings'), href: '/dashboard/settings' },
  ]

  const topNav = navItems.map(item => ({
    ...item,
    isActive: url.startsWith(item.href),
    disabled: false,
  }))

  return (
    <>
      <Head title={title ?? 'Dashboard'}/>
      <SidebarProvider>
        <AppSidebar/>
        <SidebarInset>
          {showHeader && <Header>
            {withTopNav && <TopNav links={topNav}/>}
            {!withTopNav && <Search/>}
            <div className='ml-auto flex items-center space-x-4'>
              {withTopNav && <Search/>}
              <LanguageSwitcher/>
              <ThemeSwitch/>
              <ProfileDropdown/>
            </div>
          </Header>}

          {children}
        </SidebarInset>
      </SidebarProvider>
    </>
  )
}
