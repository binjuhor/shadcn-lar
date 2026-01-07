import React, { useState } from 'react'
import useDialogState from '@/hooks/use-dialog-state'

type SettingsTab = 'profile' | 'account' | 'appearance' | 'notifications' | 'display' | 'finance' | 'modules'
type SettingsDialogType = 'save-confirm' | 'discard-confirm'

interface SettingsContextType {
  activeTab: SettingsTab
  setActiveTab: (tab: SettingsTab) => void
  isDirty: boolean
  setIsDirty: (dirty: boolean) => void
  open: SettingsDialogType | null
  setOpen: (str: SettingsDialogType | null) => void
}

const SettingsContext = React.createContext<SettingsContextType | null>(null)

interface Props {
  children: React.ReactNode
  defaultTab?: SettingsTab
}

export default function SettingsProvider({ children, defaultTab = 'profile' }: Props) {
  const [open, setOpen] = useDialogState<SettingsDialogType>(null)
  const [activeTab, setActiveTab] = useState<SettingsTab>(defaultTab)
  const [isDirty, setIsDirty] = useState(false)

  return (
    <SettingsContext.Provider value={{
      activeTab, setActiveTab,
      isDirty, setIsDirty,
      open, setOpen
    }}>
      {children}
    </SettingsContext.Provider>
  )
}

// eslint-disable-next-line react-refresh/only-export-components
export const useSettings = () => {
  const context = React.useContext(SettingsContext)

  if (!context) {
    throw new Error('useSettings must be used within <SettingsProvider>')
  }

  return context
}
