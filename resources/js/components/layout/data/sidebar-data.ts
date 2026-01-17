import {
  IconBarrierBlock,
  IconBrowserCheck,
  IconBug,
  IconCalendarEvent,
  IconChecklist,
  IconError404,
  IconFileInvoice,
  IconHelp,
  IconLayoutDashboard,
  IconLock,
  IconLockAccess,
  IconMessages,
  IconNotification,
  IconPackages,
  IconPalette,
  IconServerOff,
  IconSettings,
  IconSparkles,
  IconTool,
  IconUserCog,
  IconUserOff,
  IconUsers,
  IconChartBar,
  IconShoppingBag,
  IconShoppingBagDiscount,
  IconShoe,
  IconRobot,
  IconMail,
  IconArticle,
  IconCategory,
  IconTags,
  IconEdit,
  IconShield,
  IconKey,
  IconWallet,
  IconBuildingBank,
  IconArrowsExchange,
  IconPigMoney,
  IconCurrencyDollar,
  IconTarget,
  IconRepeat,
} from '@tabler/icons-react'
import {
  AudioWaveform,
  Command,
  GalleryVerticalEnd
} from 'lucide-react'
import { type SidebarData } from '../types'

export const sidebarData: SidebarData = {
  user: {
    name: 'binjuhor',
    email: 'hi@binjuhor.com',
    avatar: '/avatars/shadcn.jpg',
  },
  teams: [
    {
      name: 'Shadcn Admin',
      logo: Command,
      plan: 'Vite + ShadcnUI',
    },
    {
      name: 'Acme Inc',
      logo: GalleryVerticalEnd,
      plan: 'Enterprise',
    },
    {
      name: 'Acme Corp.',
      logo: AudioWaveform,
      plan: 'Startup',
    },
  ],
  navGroups: [
    {
      title: 'General',
      items: [
        {
          title: 'Dashboard',
          url: '/dashboard',
          icon: IconLayoutDashboard,
        },
        {
          title: 'Tasks',
          url: '/dashboard/tasks',
          icon: IconChecklist,
        },
        {
          title: 'Mail',
          url: '/dashboard/mail',
          icon: IconMail,
        },
        {
          title: 'Apps',
          url: '/dashboard/apps',
          icon: IconPackages,
        },
        {
          title: 'Chats',
          url: '/dashboard/chats',
          badge: '3',
          icon: IconMessages,
        },
        {
          title: 'Ai Chats',
          url: '/dashboard/chat-ai',
          icon: IconRobot,
        },
        {
          title: 'Charts',
          url: '/dashboard/charts',
          icon: IconChartBar,
        },
      ],
    },
    {
      title: 'Invoices',
      collapsible: true,
      requiresModule: 'Invoice',
      items: [
        {
          title: 'All Invoices',
          url: '/dashboard/invoices',
          icon: IconFileInvoice,
          permission: 'invoices.view',
        },
        {
          title: 'Reports',
          url: '/dashboard/invoices-reports',
          icon: IconChartBar,
        },
      ],
    },
    {
      title: 'Finance',
      collapsible: true,
      requiresModule: 'Finance',
      items: [
        {
          title: 'Dashboard',
          url: '/dashboard/finance',
          icon: IconWallet,
        },
        {
          title: 'Accounts',
          url: '/dashboard/finance/accounts',
          icon: IconBuildingBank,
        },
        {
          title: 'Reports',
          url: '/dashboard/finance/reports',
          icon: IconChartBar,
        },
        {
          title: 'Transactions',
          url: '/dashboard/finance/transactions',
          icon: IconArrowsExchange,
        },
        {
          title: 'Smart Input',
          url: '/dashboard/finance/smart-input',
          icon: IconSparkles,
        },
        {
          title: 'Budgets',
          url: '/dashboard/finance/budgets',
          icon: IconPigMoney,
        },
        {
          title: 'Savings Goals',
          url: '/dashboard/finance/savings-goals',
          icon: IconTarget,
        },
        {
          title: 'Financial Plans',
          url: '/dashboard/finance/plans',
          icon: IconCalendarEvent,
        },
        {
          title: 'Recurring',
          url: '/dashboard/finance/recurring-transactions',
          icon: IconRepeat,
        },
        {
          title: 'Categories',
          url: '/dashboard/finance/categories',
          icon: IconCategory,
        },
        {
          title: 'Exchange Rates',
          url: '/dashboard/finance/exchange-rates',
          icon: IconCurrencyDollar,
        },
      ],
    },
    {
      title: 'Access Control',
      collapsible: true,
      items: [
        {
          title: 'Roles',
          url: '/dashboard/roles',
          icon: IconShield,
          permission: 'roles.view',
        },
        {
          title: 'Permissions',
          url: '/dashboard/permissions',
          icon: IconKey,
          permission: 'permissions.view',
        },
        {
          title: 'Users',
          url: '/dashboard/users',
          icon: IconUsers,
          permission: 'users.view',
        },
      ],
    },
    {
      title: 'Ecommerce',
      collapsible: true,
      requiresModule: 'Ecommerce',
      items: [
        {
          title: 'Products',
          url: '/dashboard/ecommerce/products',
          icon: IconShoppingBagDiscount,
          permission: 'products.view',
        },
        {
          title: 'Add Product',
          url: '/dashboard/ecommerce/products/create',
          icon: IconShoe,
          permission: 'products.create',
        },
        {
          title: 'Categories',
          url: '/dashboard/ecommerce/product-categories',
          icon: IconCategory,
          permission: 'product-categories.view',
        },
        {
          title: 'Tags',
          url: '/dashboard/ecommerce/product-tags',
          icon: IconTags,
          permission: 'product-tags.view',
        },
        {
          title: 'Orders',
          url: '/dashboard/ecommerce/orders',
          icon: IconShoppingBag,
          permission: 'orders.view',
        },
      ],
    },
    {
      title: 'Blog',
      collapsible: true,
      requiresModule: 'Blog',
      items: [
        {
          title: 'Posts',
          url: '/dashboard/posts',
          icon: IconArticle,
          permission: 'posts.view',
        },
        {
          title: 'Add Post',
          url: '/dashboard/posts/create',
          icon: IconEdit,
          permission: 'posts.create',
        },
        {
          title: 'Categories',
          url: '/dashboard/categories',
          icon: IconCategory,
          permission: 'categories.view',
        },
        {
          title: 'Tags',
          url: '/dashboard/tags',
          icon: IconTags,
          permission: 'tags.view',
        },
      ],
    },
    {
      title: 'Pages',
      collapsible: true,
      items: [
        {
          title: 'Auth',
          icon: IconLockAccess,
          items: [
            {
              title: 'Sign In',
              url: '/sign-in',
            },
            {
              title: 'Sign In (2 Col)',
              url: '/sign-in-2',
            },
            {
              title: 'Sign Up',
              url: '/sign-up',
            },
            {
              title: 'Forgot Password',
              url: '/forgot-pass',
            },
            {
              title: 'OTP',
              url: '/otp',
            },
          ],
        },
        {
          title: 'Errors',
          icon: IconBug,
          items: [
            {
              title: 'Unauthorized',
              url: '/401',
              icon: IconLock,
            },
            {
              title: 'Forbidden',
              url: '/403',
              icon: IconUserOff,
            },
            {
              title: 'Not Found',
              url: '/404',
              icon: IconError404,
            },
            {
              title: 'Internal Server Error',
              url: '/500',
              icon: IconServerOff,
            },
            {
              title: 'Maintenance Error',
              url: '/503',
              icon: IconBarrierBlock,
            },
          ],
        },
      ],
    },
    {
      title: 'Other',
      collapsible: true,
      items: [
        {
          title: 'Settings',
          icon: IconSettings,
          items: [
            {
              title: 'Profile',
              url: '/dashboard/settings',
              icon: IconUserCog,
            },
            {
              title: 'Account',
              url: '/dashboard/settings/account',
              icon: IconTool,
            },
            {
              title: 'Appearance',
              url: '/dashboard/settings/appearance',
              icon: IconPalette,
            },
            {
              title: 'Notifications',
              url: '/dashboard/settings/notifications',
              icon: IconNotification,
            },
            {
              title: 'Display',
              url: '/dashboard/settings/display',
              icon: IconBrowserCheck,
            },
            {
              title: 'Finance',
              url: '/dashboard/settings/finance',
              icon: IconCurrencyDollar,
            },
            {
              title: 'Modules',
              url: '/dashboard/settings/modules',
              icon: IconPackages,
            },
          ],
        },
        {
          title: 'Help Center',
          url: '/dashboard/help-center',
          icon: IconHelp,
        },
      ],
    },
  ],
}
