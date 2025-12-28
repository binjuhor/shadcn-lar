import { IconUserPlus } from '@tabler/icons-react'
import { router } from '@inertiajs/react'
import { Button } from '@/components/ui/button'

export function UsersPrimaryButtons() {
  return (
    <div className="flex gap-2">
      <Button
        className="space-x-1"
        onClick={() => router.get(route('dashboard.users.create'))}
      >
        <span>Add User</span> <IconUserPlus size={18} />
      </Button>
    </div>
  )
}
