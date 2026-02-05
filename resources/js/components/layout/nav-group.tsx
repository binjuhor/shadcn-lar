import { ReactNode } from 'react'
import { Link, usePage } from "@inertiajs/react"
import { ChevronRight, ChevronDown } from 'lucide-react'
import { useTranslation } from 'react-i18next'
import {
  Collapsible,
  CollapsibleContent,
  CollapsibleTrigger,
} from '@/components/ui/collapsible'
import {
  SidebarGroup,
  SidebarGroupLabel,
  SidebarMenu,
  SidebarMenuButton,
  SidebarMenuItem,
  SidebarMenuSub,
  SidebarMenuSubButton,
  SidebarMenuSubItem,
  useSidebar,
} from '@/components/ui/sidebar'
import { Badge } from '../ui/badge'
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuLabel,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from '../ui/dropdown-menu'
import { NavCollapsible, NavItem, NavLink, type NavGroup } from './types'

interface NavGroupProps extends NavGroup {
  isCollapsed?: boolean
  onToggle?: () => void
}

type TranslateFunc = (title: string, titleKey?: string) => string

export function NavGroup({ title, titleKey, items, collapsible, isCollapsed, onToggle }: NavGroupProps) {
  const { state } = useSidebar()
  const { url: href } = usePage()
  const { t } = useTranslation()

  const getTitle = (itemTitle: string, itemTitleKey?: string): string => {
    return itemTitleKey ? t(itemTitleKey, { defaultValue: itemTitle }) : itemTitle
  }

  const displayTitle = getTitle(title, titleKey)

  const content = (
    <SidebarMenu>
      {items.map((item) => {
        const key = `${item.title}-${item.url}`

        if (!item.items)
          return <SidebarMenuLink key={key} item={item} href={href} getTitle={getTitle} />

        if (state === 'collapsed')
          return (
            <SidebarMenuCollapsedDropdown key={key} item={item} href={href} getTitle={getTitle} />
          )

        return <SidebarMenuCollapsible key={key} item={item} href={href} getTitle={getTitle} />
      })}
    </SidebarMenu>
  )

  if (collapsible && onToggle) {
    return (
      <Collapsible open={!isCollapsed} onOpenChange={() => onToggle()} className="group/nav-collapsible">
        <SidebarGroup>
          <CollapsibleTrigger asChild>
            <SidebarGroupLabel className="cursor-pointer hover:bg-sidebar-accent hover:text-sidebar-accent-foreground rounded-md transition-colors">
              <span className="flex-1">{displayTitle}</span>
              <ChevronRight className="ml-auto size-4 shrink-0 transition-transform duration-200 group-data-[state=open]/nav-collapsible:rotate-90" />
            </SidebarGroupLabel>
          </CollapsibleTrigger>
          <CollapsibleContent>
            {content}
          </CollapsibleContent>
        </SidebarGroup>
      </Collapsible>
    )
  }

  return (
    <SidebarGroup>
      <SidebarGroupLabel>{displayTitle}</SidebarGroupLabel>
      {content}
    </SidebarGroup>
  )
}

const NavBadge = ({ children }: { children: ReactNode }) => (
  <Badge className='text-xs rounded-full px-1 py-0'>{children}</Badge>
)

const SidebarMenuLink = ({ item, href, getTitle }: { item: NavLink; href: string; getTitle: TranslateFunc }) => {
  const { setOpenMobile } = useSidebar()
  const displayTitle = getTitle(item.title, item.titleKey)
  return (
    <SidebarMenuItem>
      <SidebarMenuButton
        asChild
        isActive={checkIsActive(href, item)}
        tooltip={displayTitle}
      >
        <Link href={item.url} onClick={() => setOpenMobile(false)}>
          {item.icon && <item.icon />}
          <span>{displayTitle}</span>
          {item.badge && <NavBadge>{item.badge}</NavBadge>}
        </Link>
      </SidebarMenuButton>
    </SidebarMenuItem>
  )
}

const SidebarMenuCollapsible = ({
  item,
  href,
  getTitle,
}: {
  item: NavCollapsible
  href: string
  getTitle: TranslateFunc
}) => {
  const { setOpenMobile } = useSidebar()
  const displayTitle = getTitle(item.title, item.titleKey)
  return (
    <Collapsible
      asChild
      defaultOpen={checkIsActive(href, item, true)}
      className='group/collapsible'
    >
      <SidebarMenuItem>
        <CollapsibleTrigger asChild>
          <SidebarMenuButton tooltip={displayTitle}>
            {item.icon && <item.icon />}
            <span>{displayTitle}</span>
            {item.badge && <NavBadge>{item.badge}</NavBadge>}
            <ChevronRight className='ml-auto transition-transform duration-200 group-data-[state=open]/collapsible:rotate-90' />
          </SidebarMenuButton>
        </CollapsibleTrigger>
        <CollapsibleContent className='CollapsibleContent'>
          <SidebarMenuSub>
            {item.items.map((subItem) => (
              <SidebarMenuSubItem key={subItem.title}>
                <SidebarMenuSubButton
                  asChild
                  isActive={checkIsActive(href, subItem)}
                >
                  <Link href={subItem.url} onClick={() => setOpenMobile(false)}>
                    {subItem.icon && <subItem.icon />}
                    <span>{getTitle(subItem.title, subItem.titleKey)}</span>
                    {subItem.badge && <NavBadge>{subItem.badge}</NavBadge>}
                  </Link>
                </SidebarMenuSubButton>
              </SidebarMenuSubItem>
            ))}
          </SidebarMenuSub>
        </CollapsibleContent>
      </SidebarMenuItem>
    </Collapsible>
  )
}

const SidebarMenuCollapsedDropdown = ({
  item,
  href,
  getTitle,
}: {
  item: NavCollapsible
  href: string
  getTitle: TranslateFunc
}) => {
  const displayTitle = getTitle(item.title, item.titleKey)
  return (
    <SidebarMenuItem>
      <DropdownMenu>
        <DropdownMenuTrigger asChild>
          <SidebarMenuButton
            tooltip={displayTitle}
            isActive={checkIsActive(href, item)}
          >
            {item.icon && <item.icon />}
            <span>{displayTitle}</span>
            {item.badge && <NavBadge>{item.badge}</NavBadge>}
            <ChevronRight className='ml-auto transition-transform duration-200 group-data-[state=open]/collapsible:rotate-90' />
          </SidebarMenuButton>
        </DropdownMenuTrigger>
        <DropdownMenuContent side='right' align='start' sideOffset={4}>
          <DropdownMenuLabel>
            {displayTitle} {item.badge ? `(${item.badge})` : ''}
          </DropdownMenuLabel>
          <DropdownMenuSeparator />
          {item.items.map((sub) => (
            <DropdownMenuItem key={`${sub.title}-${sub.url}`} asChild>
              <Link
                href={sub.url}
                className={`${checkIsActive(href, sub) ? 'bg-secondary' : ''}`}
              >
                {sub.icon && <sub.icon />}
                <span className='max-w-52 text-wrap'>{getTitle(sub.title, sub.titleKey)}</span>
                {sub.badge && (
                  <span className='ml-auto text-xs'>{sub.badge}</span>
                )}
              </Link>
            </DropdownMenuItem>
          ))}
        </DropdownMenuContent>
      </DropdownMenu>
    </SidebarMenuItem>
  )
}

function checkIsActive(href: string, item: NavItem, mainNav = false) {
  return (
    href === item.url || // /endpint?search=param
    href.split('?')[0] === item.url || // endpoint
    !!item?.items?.filter((i) => i.url === href).length || // if child nav is active
    (mainNav &&
      href.split('/')[1] !== '' &&
      href.split('/')[1] === item?.url?.split('/')[1])
  )
}
