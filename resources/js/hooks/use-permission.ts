import { usePage } from '@inertiajs/react'
import { PageProps } from '@/types'
import { type NavGroup, type NavItem } from '@/components/layout/types'

export function usePermission() {
  const { auth, enabledModules } = usePage<PageProps>().props

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

  const filterNavGroups = (groups: NavGroup[]): NavGroup[] => {
    return groups
      .filter(group => isModuleEnabled(group.requiresModule))
      .map(group => ({
        ...group,
        items: group.items
          .map(filterNavItem)
          .filter((item): item is NavItem => item !== null)
      }))
      .filter(group => group.items.length > 0)
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
  }
}
