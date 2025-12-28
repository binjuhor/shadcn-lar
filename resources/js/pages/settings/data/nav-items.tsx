import {
  IconBrowserCheck,
  IconNotification,
  IconPackages,
  IconPalette,
  IconTool,
  IconUser,
} from '@tabler/icons-react'

export interface SettingsNavItem {
  title: string
  icon: React.ReactNode
  href: string
  superAdminOnly?: boolean
}

export const settingsNavItems: SettingsNavItem[] = [
  {
    title: 'Profile',
    icon: <IconUser size={18} />,
    href: '/dashboard/settings',
  },
  {
    title: 'Account',
    icon: <IconTool size={18} />,
    href: '/dashboard/settings/account',
  },
  {
    title: 'Appearance',
    icon: <IconPalette size={18} />,
    href: '/dashboard/settings/appearance',
  },
  {
    title: 'Notifications',
    icon: <IconNotification size={18} />,
    href: '/dashboard/settings/notifications',
  },
  {
    title: 'Display',
    icon: <IconBrowserCheck size={18} />,
    href: '/dashboard/settings/display',
  },
  {
    title: 'Modules',
    icon: <IconPackages size={18} />,
    href: '/dashboard/settings/modules',
    superAdminOnly: true,
  },
]
