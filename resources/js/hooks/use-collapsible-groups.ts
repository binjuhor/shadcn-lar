import { useState, useCallback, useEffect } from 'react'

const STORAGE_KEY = 'sidebar-collapsed-groups'

export function useCollapsibleGroups() {
  const [collapsedGroups, setCollapsedGroups] = useState<Set<string>>(() => {
    if (typeof window === 'undefined') return new Set()
    const stored = localStorage.getItem(STORAGE_KEY)
    if (stored) {
      try {
        return new Set(JSON.parse(stored))
      } catch {
        return new Set()
      }
    }
    return new Set()
  })

  useEffect(() => {
    localStorage.setItem(STORAGE_KEY, JSON.stringify([...collapsedGroups]))
  }, [collapsedGroups])

  const isCollapsed = useCallback(
    (groupTitle: string) => collapsedGroups.has(groupTitle),
    [collapsedGroups]
  )

  const toggleGroup = useCallback((groupTitle: string) => {
    setCollapsedGroups(prev => {
      const next = new Set(prev)
      if (next.has(groupTitle)) {
        next.delete(groupTitle)
      } else {
        next.add(groupTitle)
      }
      return next
    })
  }, [])

  const setGroupCollapsed = useCallback((groupTitle: string, collapsed: boolean) => {
    setCollapsedGroups(prev => {
      const next = new Set(prev)
      if (collapsed) {
        next.add(groupTitle)
      } else {
        next.delete(groupTitle)
      }
      return next
    })
  }, [])

  return {
    isCollapsed,
    toggleGroup,
    setGroupCollapsed,
  }
}
