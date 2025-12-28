import { useState } from 'react'
import { Cross2Icon } from '@radix-ui/react-icons'
import { Table } from '@tanstack/react-table'
import { router } from '@inertiajs/react'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select'
import { Role } from '@/types'
import { DataTableViewOptions } from './data-table-view-options'

interface DataTableToolbarProps<TData> {
  table: Table<TData>
  roles: Role[]
  filters?: {
    search?: string
    role?: string
  }
}

export function DataTableToolbar<TData>({
  table,
  roles,
  filters = {},
}: DataTableToolbarProps<TData>) {
  const [searchTerm, setSearchTerm] = useState(filters.search || '')
  const [selectedRole, setSelectedRole] = useState(filters.role || '')

  const isFiltered = filters.search || filters.role

  const handleSearch = (value: string) => {
    setSearchTerm(value)
    router.get(
      route('dashboard.users'),
      { search: value || undefined, role: selectedRole || undefined },
      { preserveState: true, replace: true }
    )
  }

  const handleRoleFilter = (value: string) => {
    const role = value === 'all' ? '' : value
    setSelectedRole(role)
    router.get(
      route('dashboard.users'),
      { search: searchTerm || undefined, role: role || undefined },
      { preserveState: true, replace: true }
    )
  }

  const handleReset = () => {
    setSearchTerm('')
    setSelectedRole('')
    router.get(route('dashboard.users'), {}, { preserveState: true, replace: true })
  }

  return (
    <div className="flex items-center justify-between">
      <div className="flex flex-1 flex-col-reverse items-start gap-y-2 sm:flex-row sm:items-center sm:space-x-2">
        <Input
          placeholder="Search users..."
          value={searchTerm}
          onChange={(event) => handleSearch(event.target.value)}
          className="h-8 w-[150px] lg:w-[250px]"
        />
        <div className="flex gap-x-2">
          <Select value={selectedRole || 'all'} onValueChange={handleRoleFilter}>
            <SelectTrigger className="h-8 w-[140px]">
              <SelectValue placeholder="Filter by role" />
            </SelectTrigger>
            <SelectContent>
              <SelectItem value="all">All Roles</SelectItem>
              {(roles ?? []).map((role) => (
                <SelectItem key={role.id} value={role.name}>
                  {role.name}
                </SelectItem>
              ))}
            </SelectContent>
          </Select>
        </div>
        {isFiltered && (
          <Button
            variant="ghost"
            onClick={handleReset}
            className="h-8 px-2 lg:px-3"
          >
            Reset
            <Cross2Icon className="ml-2 h-4 w-4" />
          </Button>
        )}
      </div>
      <DataTableViewOptions table={table} />
    </div>
  )
}
