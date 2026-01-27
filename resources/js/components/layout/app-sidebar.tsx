import { useMemo } from 'react'
import { usePage } from '@inertiajs/react'
import {
  Sidebar,
  SidebarContent,
  SidebarFooter,
  SidebarHeader,
  SidebarRail,
} from '@/components/ui/sidebar'
import { NavGroup } from '@/components/layout/nav-group'
import { NavUser } from '@/components/layout/nav-user'
import { TeamSwitcher } from '@/components/layout/team-switcher'
import { sidebarData } from './data/sidebar-data'
import { usePermission } from '@/hooks/use-permission'
import { useCollapsibleGroups } from '@/hooks/use-collapsible-groups'

export function AppSidebar({ ...props }: React.ComponentProps<typeof Sidebar>) {
  const { auth } = usePage().props as { auth: { user: { name: string; email: string; avatar_url?: string } } }
  const { filterNavGroups, enabledModules, moduleOrder } = usePermission()
  const { isCollapsed, toggleGroup } = useCollapsibleGroups()

  const filteredNavGroups = useMemo(
    () => filterNavGroups(sidebarData.navGroups),
    [filterNavGroups, enabledModules, moduleOrder]
  )

  return (
    <Sidebar collapsible='icon' variant='floating' {...props}>
      <SidebarHeader>
        <TeamSwitcher teams={sidebarData.teams} />
      </SidebarHeader>
      <SidebarContent>
        {filteredNavGroups.map((group) => (
          <NavGroup
            key={group.title}
            {...group}
            isCollapsed={group.collapsible ? isCollapsed(group.title) : undefined}
            onToggle={group.collapsible ? () => toggleGroup(group.title) : undefined}
          />
        ))}
      </SidebarContent>
      <SidebarFooter>
        <NavUser user={{
          name: auth?.user?.name ?? 'User',
          email: auth?.user?.email ?? '',
          avatar: auth?.user?.avatar_url ?? '/avatars/shadcn.jpg',
        }} />
      </SidebarFooter>
      <SidebarRail />
    </Sidebar>
  )
}
