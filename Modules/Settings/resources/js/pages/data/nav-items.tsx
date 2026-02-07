import {
  IconBrowserCheck,
  IconCurrencyDollar,
  IconFileInvoice,
  IconNotification,
  IconPackages,
  IconPalette,
  IconTool,
  IconUser,
} from '@tabler/icons-react'

export interface SettingsNavItem {
  title: string
  titleKey?: string
  icon: React.ReactNode
  href: string
  superAdminOnly?: boolean
  requiresModule?: string
}

export const settingsNavItems: SettingsNavItem[] = [
  {
    title: 'Profile',
    titleKey: 'settings.nav.profile',
    icon: <IconUser size={18} />,
    href: '/dashboard/settings',
  },
  {
    title: 'Account',
    titleKey: 'settings.nav.account',
    icon: <IconTool size={18} />,
    href: '/dashboard/settings/account',
  },
  {
    title: 'Appearance',
    titleKey: 'settings.nav.appearance',
    icon: <IconPalette size={18} />,
    href: '/dashboard/settings/appearance',
  },
  {
    title: 'Notifications',
    titleKey: 'settings.nav.notifications',
    icon: <IconNotification size={18} />,
    href: '/dashboard/settings/notifications',
  },
  {
    title: 'Display',
    titleKey: 'settings.nav.display',
    icon: <IconBrowserCheck size={18} />,
    href: '/dashboard/settings/display',
  },
  {
    title: 'Finance',
    titleKey: 'settings.nav.finance',
    icon: <IconCurrencyDollar size={18} />,
    href: '/dashboard/settings/finance',
    requiresModule: 'Finance',
  },
  {
    title: 'Invoice',
    titleKey: 'settings.nav.invoice',
    icon: <IconFileInvoice size={18} />,
    href: '/dashboard/settings/invoice',
    requiresModule: 'Invoice',
    superAdminOnly: true,
  },
  {
    title: 'Modules',
    titleKey: 'settings.nav.modules',
    icon: <IconPackages size={18} />,
    href: '/dashboard/settings/modules',
    superAdminOnly: true,
  },
]
