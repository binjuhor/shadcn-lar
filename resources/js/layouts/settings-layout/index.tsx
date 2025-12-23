import { AppSidebar } from '@/components/layout/app-sidebar'
import { Header } from '@/components/layout/header'
import { TopNav } from '@/components/layout/top-nav'
import { ProfileDropdown } from '@/components/profile-dropdown'
import { Search } from '@/components/search'
import { Head } from '@inertiajs/react'
import { SidebarInset, SidebarProvider } from '@/components/ui/sidebar'
import { ThemeSwitch } from '@/components/theme-switch'
import { Separator } from '@/components/ui/separator'
import SidebarNav from '@/pages/settings/components/sidebar-nav'
import { Main } from '@/components/layout'
import { settingsNavItems } from '@/pages/settings/data/nav-items'

const topNav = [
  {
    title: 'Overview',
    href: '/dashboard',
    isActive: true,
    disabled: false,
  },
  {
    title: 'Customers',
    href: '/dashboard/users',
    isActive: false,
    disabled: true,
  },
  {
    title: 'Products',
    href: '/dashboard/products',
    isActive: false,
    disabled: true,
  },
  {
    title: 'Settings',
    href: '/dashboard/settings',
    isActive: false,
    disabled: true,
  },
]

export function SettingLayout({ children, title }: { children: React.ReactNode; title?: string }) {
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
              <ThemeSwitch />
              <ProfileDropdown />
            </div>
          </Header>

          <Main fixed>
            <div className='space-y-0.5'>
              <h1 className='text-2xl font-bold tracking-tight md:text-3xl'>
                Settings
              </h1>
              <p className='text-muted-foreground'>
                Manage your account settings and set e-mail preferences.
              </p>
            </div>
            <Separator className='my-4 lg:my-6' />
            <div className='flex flex-1 flex-col space-y-2 md:space-y-2 overflow-hidden lg:flex-row lg:space-x-12 lg:space-y-0'>
              <aside className='top-0 lg:sticky lg:w-1/5'>
                <SidebarNav items={settingsNavItems} />
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
