'use client'

import { useState } from 'react'
import { IconAlertTriangle } from '@tabler/icons-react'
import { router } from '@inertiajs/react'
import { toast } from '@/hooks/use-toast'
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { ConfirmDialog } from '@/components/confirm-dialog'
import { User } from '../data/schema'

interface Props {
  open: boolean
  onOpenChange: (open: boolean) => void
  currentRow: User
}

export function UsersDeleteDialog({ open, onOpenChange, currentRow }: Props) {
  const [value, setValue] = useState('')

  const handleDelete = () => {
    if (value.trim() !== currentRow.name) return

    router.delete(route('dashboard.users.destroy', currentRow.id), {
      onSuccess: () => {
        onOpenChange(false)
        setValue('')
        toast({
          title: 'User deleted!',
          description: `"${currentRow.name}" has been deleted successfully.`,
        })
      },
      onError: (errors) => {
        toast({
          variant: 'destructive',
          title: 'Error deleting user',
          description:
            (Object.values(errors)[0] as string) || 'Something went wrong.',
        })
      },
    })
  }

  const roleNames = currentRow.role_names?.join(', ') || 'No roles'

  return (
    <ConfirmDialog
      open={open}
      onOpenChange={(isOpen) => {
        onOpenChange(isOpen)
        if (!isOpen) setValue('')
      }}
      handleConfirm={handleDelete}
      disabled={value.trim() !== currentRow.name}
      title={
        <span className="text-destructive">
          <IconAlertTriangle
            className="mr-1 inline-block stroke-destructive"
            size={18}
          />{' '}
          Delete User
        </span>
      }
      desc={
        <div className="space-y-4">
          <p className="mb-2">
            Are you sure you want to delete{' '}
            <span className="font-bold">{currentRow.name}</span>?
            <br />
            This action will permanently remove the user with the role of{' '}
            <span className="font-bold">{roleNames}</span> from the system. This
            cannot be undone.
          </p>

          <Label className="my-2">
            Name:
            <Input
              value={value}
              onChange={(e) => setValue(e.target.value)}
              placeholder="Enter user name to confirm deletion."
            />
          </Label>

          <Alert variant="destructive">
            <AlertTitle>Warning!</AlertTitle>
            <AlertDescription>
              Please be careful, this operation cannot be rolled back.
            </AlertDescription>
          </Alert>
        </div>
      }
      confirmText="Delete"
      destructive
    />
  )
}
