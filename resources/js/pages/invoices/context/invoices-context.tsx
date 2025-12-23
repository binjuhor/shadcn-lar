import React, { useState } from 'react'
import useDialogState from '@/hooks/use-dialog-state'
import { Invoice } from '../data/schema'

type InvoicesDialogType = 'delete'

interface InvoicesContextType {
  open: InvoicesDialogType | null
  setOpen: (str: InvoicesDialogType | null) => void
  currentRow: Invoice | null
  setCurrentRow: React.Dispatch<React.SetStateAction<Invoice | null>>
}

const InvoicesContext = React.createContext<InvoicesContextType | null>(null)

interface Props {
  children: React.ReactNode
}

export default function InvoicesProvider({ children }: Props) {
  const [open, setOpen] = useDialogState<InvoicesDialogType>(null)
  const [currentRow, setCurrentRow] = useState<Invoice | null>(null)

  return (
    <InvoicesContext.Provider value={{ open, setOpen, currentRow, setCurrentRow }}>
      {children}
    </InvoicesContext.Provider>
  )
}

// eslint-disable-next-line react-refresh/only-export-components
export const useInvoices = () => {
  const invoicesContext = React.useContext(InvoicesContext)

  if (!invoicesContext) {
    throw new Error('useInvoices has to be used within <InvoicesContext>')
  }

  return invoicesContext
}
