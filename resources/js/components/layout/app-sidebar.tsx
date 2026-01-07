import { useMemo } from 'react'
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
  const { filterNavGroups, enabledModules } = usePermission()
  const { isCollapsed, toggleGroup } = useCollapsibleGroups()

  const filteredNavGroups = useMemo(
    () => filterNavGroups(sidebarData.navGroups),
    [filterNavGroups, enabledModules]
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
        <NavUser user={sidebarData.user} />
      </SidebarFooter>
      <SidebarRail />
    </Sidebar>
  )
}
