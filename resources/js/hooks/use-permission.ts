import { usePage } from '@inertiajs/react'
import { PageProps } from '@/types'
import { type NavGroup, type NavItem } from '@/components/layout/types'

export function usePermission() {
  const { auth, enabledModules, sidebarSettings } = usePage<PageProps>().props
  const moduleOrder = sidebarSettings?.module_order || []

  const can = (permission: string): boolean => {
    if (!auth.permissions) return false
    return auth.permissions.includes(permission)
  }

  const canAny = (permissions: string[]): boolean => {
    if (!auth.permissions) return false
    return permissions.some(p => auth.permissions.includes(p))
  }

  const canAll = (permissions: string[]): boolean => {
    if (!auth.permissions) return false
    return permissions.every(p => auth.permissions.includes(p))
  }

  const hasRole = (role: string): boolean => {
    if (!auth.roles) return false
    return auth.roles.includes(role)
  }

  const hasAnyRole = (roles: string[]): boolean => {
    if (!auth.roles) return false
    return roles.some(r => auth.roles.includes(r))
  }

  const isSuperAdmin = (): boolean => {
    return hasRole('Super Admin')
  }

  const isModuleEnabled = (moduleName?: string): boolean => {
    if (!moduleName) return true
    if (!enabledModules) return false
    return enabledModules.some(
      m => m.toLowerCase() === moduleName.toLowerCase()
    )
  }

  const checkPermission = (permission?: string | string[]): boolean => {
    if (!permission) return true
    if (hasRole('Super Admin')) return true
    if (Array.isArray(permission)) {
      return permission.some(p => can(p))
    }
    return can(permission)
  }

  const filterNavItem = (item: NavItem): NavItem | null => {
    if ('items' in item && item.items) {
      const filteredItems = item.items.filter(subItem =>
        checkPermission(subItem.permission)
      )
      if (filteredItems.length === 0) return null
      return { ...item, items: filteredItems }
    }

    if (!checkPermission(item.permission)) return null
    return item
  }

  const sortNavGroups = (groups: NavGroup[]): NavGroup[] => {
    if (moduleOrder.length === 0) return groups

    // Separate module groups from non-module groups
    const moduleGroups: NavGroup[] = []
    const nonModuleGroups: { group: NavGroup; originalIndex: number }[] = []

    groups.forEach((group, index) => {
      if (group.requiresModule) {
        moduleGroups.push(group)
      } else {
        nonModuleGroups.push({ group, originalIndex: index })
      }
    })

    // Sort module groups based on moduleOrder
    moduleGroups.sort((a, b) => {
      const aIndex = moduleOrder.findIndex(
        m => m.toLowerCase() === a.requiresModule!.toLowerCase()
      )
      const bIndex = moduleOrder.findIndex(
        m => m.toLowerCase() === b.requiresModule!.toLowerCase()
      )

      if (aIndex === -1 && bIndex === -1) return 0
      if (aIndex === -1) return 1
      if (bIndex === -1) return -1

      return aIndex - bIndex
    })

    // Rebuild the array: non-module groups in original positions, module groups in sorted order
    const result: NavGroup[] = []

    // First, find where the first module group was in the original array
    const firstModuleOriginalIndex = groups.findIndex(g => g.requiresModule)

    // Place non-module groups that come before all modules
    for (const { group, originalIndex } of nonModuleGroups) {
      if (originalIndex < firstModuleOriginalIndex || firstModuleOriginalIndex === -1) {
        result.push(group)
      }
    }

    // Place all sorted module groups together
    result.push(...moduleGroups)

    // Place non-module groups that come after modules
    for (const { group, originalIndex } of nonModuleGroups) {
      if (originalIndex >= firstModuleOriginalIndex && firstModuleOriginalIndex !== -1) {
        result.push(group)
      }
    }

    return result
  }

  const filterNavGroups = (groups: NavGroup[]): NavGroup[] => {
    const filtered = groups
      .filter(group => isModuleEnabled(group.requiresModule))
      .map(group => ({
        ...group,
        items: group.items
          .map(filterNavItem)
          .filter((item): item is NavItem => item !== null)
      }))
      .filter(group => group.items.length > 0)

    return sortNavGroups(filtered)
  }

  return {
    can,
    canAny,
    canAll,
    hasRole,
    hasAnyRole,
    isSuperAdmin,
    isModuleEnabled,
    checkPermission,
    filterNavGroups,
    permissions: auth.permissions || [],
    roles: auth.roles || [],
    enabledModules: enabledModules || [],
    moduleOrder,
  }
}
