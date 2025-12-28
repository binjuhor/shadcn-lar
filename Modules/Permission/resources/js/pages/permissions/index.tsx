import { AuthenticatedLayout } from "@/layouts"
import {
  MoreHorizontal,
  PlusCircle,
  Edit,
  Trash2,
  Key,
} from "lucide-react"

import { Badge } from "@/components/ui/badge"
import { Button } from "@/components/ui/button"
import {
  Card,
  CardContent,
  CardDescription,
  CardFooter,
  CardHeader,
  CardTitle,
} from "@/components/ui/card"
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuLabel,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu"
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from "@/components/ui/table"
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select"
import { Input } from "@/components/ui/input"
import { Main } from "@/components/layout"
import { useState } from "react"
import { router } from "@inertiajs/react"
import { PageProps } from "@/types"
import { useToast } from "@/hooks/use-toast"
import {
  Pagination,
  PaginationContent,
  PaginationItem,
  PaginationLink,
  PaginationNext,
  PaginationPrevious,
} from "@/components/ui/pagination"

interface Permission {
  id: number
  name: string
  guard_name: string
  roles_count: number
  created_at: string
  updated_at: string
}

interface PermissionsPageProps extends PageProps {
  permissions: {
    data: Permission[]
    current_page: number
    last_page: number
    per_page: number
    total: number
  }
  groups: string[]
  filters: {
    search?: string
    group?: string
  }
}

export default function Permissions({ permissions, groups, filters: initialFilters = {} }: PermissionsPageProps) {
  const [searchTerm, setSearchTerm] = useState(initialFilters?.search || "")
  const [selectedGroup, setSelectedGroup] = useState(initialFilters?.group || "")
  const { toast } = useToast()

  const handleSearch = (value: string) => {
    setSearchTerm(value)
    router.get(route('dashboard.permissions.index'), { search: value, group: selectedGroup }, {
      preserveState: true,
      replace: true,
    })
  }

  const handleGroupFilter = (value: string) => {
    const group = value === 'all' ? '' : value
    setSelectedGroup(group)
    router.get(route('dashboard.permissions.index'), { search: searchTerm, group }, {
      preserveState: true,
      replace: true,
    })
  }

  const handleDelete = (permission: Permission) => {
    if (confirm(`Are you sure you want to delete the permission "${permission.name}"?`)) {
      router.delete(route('dashboard.permissions.destroy', permission.id), {
        onSuccess: () => {
          toast({
            title: "Permission deleted!",
            description: `"${permission.name}" has been deleted successfully.`,
          })
        },
        onError: (errors) => {
          toast({
            variant: "destructive",
            title: "Error deleting permission",
            description: Object.values(errors)[0] as string || "Something went wrong.",
          })
        }
      })
    }
  }

  const handlePageChange = (page: number) => {
    router.get(route('dashboard.permissions.index'), { ...initialFilters, page }, {
      preserveState: true,
      replace: true,
    })
  }

  const generatePageNumbers = () => {
    const pages: (number | string)[] = []
    const delta = 2
    const rangeStart = Math.max(2, permissions.current_page - delta)
    const rangeEnd = Math.min(permissions.last_page - 1, permissions.current_page + delta)

    if (permissions.last_page > 1) {
      pages.push(1)
    }

    if (rangeStart > 2) {
      pages.push('...')
    }

    for (let i = rangeStart; i <= rangeEnd; i++) {
      if (i !== 1 && i !== permissions.last_page) {
        pages.push(i)
      }
    }

    if (rangeEnd < permissions.last_page - 1) {
      pages.push('...')
    }

    if (permissions.last_page > 1 && permissions.last_page !== 1) {
      pages.push(permissions.last_page)
    }

    return pages
  }

  const formatDate = (dateString: string) => {
    return new Date(dateString).toLocaleDateString("en-US", {
      year: "numeric",
      month: "short",
      day: "numeric",
    })
  }

  const getPermissionGroup = (name: string) => {
    return name.split('.')[0] || 'other'
  }

  const getPermissionAction = (name: string) => {
    return name.split('.')[1] || name
  }

  return (
    <AuthenticatedLayout title="Permissions">
      <Main>
        <div className="grid flex-1 items-start gap-4 md:gap-8">
          <div className="flex items-center justify-between">
            <div>
              <h2 className="text-2xl font-bold tracking-tight">Permissions</h2>
              <p className="text-muted-foreground">
                Manage permissions for your application
              </p>
            </div>
            <div className="flex items-center gap-2">
              <div className="relative">
                <Input
                  placeholder="Search permissions..."
                  value={searchTerm}
                  onChange={(e) => handleSearch(e.target.value)}
                  className="w-64"
                />
              </div>
              <Select value={selectedGroup || 'all'} onValueChange={handleGroupFilter}>
                <SelectTrigger className="w-40">
                  <SelectValue placeholder="Filter by group" />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="all">All Groups</SelectItem>
                  {groups.map((group) => (
                    <SelectItem key={group} value={group} className="capitalize">
                      {group}
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
              <Button
                size="sm"
                className="h-9 gap-1"
                onClick={() => router.get(route('dashboard.permissions.create'))}
              >
                <PlusCircle className="h-3.5 w-3.5" />
                <span className="sr-only sm:not-sr-only sm:whitespace-nowrap">
                  Add Permission
                </span>
              </Button>
            </div>
          </div>

          <Card>
            <CardHeader>
              <CardTitle>All Permissions</CardTitle>
              <CardDescription>
                A list of all permissions available in your application.
              </CardDescription>
            </CardHeader>
            <CardContent>
              <Table>
                <TableHeader>
                  <TableRow>
                    <TableHead>Name</TableHead>
                    <TableHead>Group</TableHead>
                    <TableHead>Action</TableHead>
                    <TableHead>Assigned to Roles</TableHead>
                    <TableHead className="hidden md:table-cell">Created</TableHead>
                    <TableHead>
                      <span className="sr-only">Actions</span>
                    </TableHead>
                  </TableRow>
                </TableHeader>
                <TableBody>
                  {!permissions.data || permissions.data.length === 0 ? (
                    <TableRow>
                      <TableCell colSpan={6} className="h-24 text-center">
                        No permissions found.
                      </TableCell>
                    </TableRow>
                  ) : (
                    permissions.data.map((permission) => (
                      <TableRow key={permission.id}>
                        <TableCell className="font-medium">
                          <div className="flex items-center gap-2">
                            <Key className="h-4 w-4 text-muted-foreground" />
                            <span className="font-mono text-sm">{permission.name}</span>
                          </div>
                        </TableCell>
                        <TableCell>
                          <Badge variant="outline" className="capitalize">
                            {getPermissionGroup(permission.name)}
                          </Badge>
                        </TableCell>
                        <TableCell>
                          <span className="text-sm capitalize">
                            {getPermissionAction(permission.name)}
                          </span>
                        </TableCell>
                        <TableCell>
                          <Badge variant="secondary">{permission.roles_count} roles</Badge>
                        </TableCell>
                        <TableCell className="hidden md:table-cell">
                          {formatDate(permission.created_at)}
                        </TableCell>
                        <TableCell>
                          <DropdownMenu>
                            <DropdownMenuTrigger asChild>
                              <Button
                                aria-haspopup="true"
                                size="icon"
                                variant="ghost"
                              >
                                <MoreHorizontal className="h-4 w-4" />
                                <span className="sr-only">Toggle menu</span>
                              </Button>
                            </DropdownMenuTrigger>
                            <DropdownMenuContent align="end">
                              <DropdownMenuLabel>Actions</DropdownMenuLabel>
                              <DropdownMenuSeparator />
                              <DropdownMenuItem
                                onClick={() => router.get(route('dashboard.permissions.edit', permission.id))}
                              >
                                <Edit className="mr-2 h-4 w-4" />
                                Edit
                              </DropdownMenuItem>
                              <DropdownMenuSeparator />
                              <DropdownMenuItem
                                className="text-red-600"
                                onClick={() => handleDelete(permission)}
                              >
                                <Trash2 className="mr-2 h-4 w-4" />
                                Delete
                              </DropdownMenuItem>
                            </DropdownMenuContent>
                          </DropdownMenu>
                        </TableCell>
                      </TableRow>
                    ))
                  )}
                </TableBody>
              </Table>
            </CardContent>
            <CardFooter className="flex flex-col sm:flex-row items-center justify-between gap-4">
              <div className="text-xs text-muted-foreground">
                {permissions?.current_page && permissions?.per_page && permissions?.total ? (
                  <>
                    Showing <strong>{((permissions.current_page - 1) * permissions.per_page) + 1}-{Math.min(permissions.current_page * permissions.per_page, permissions.total)}</strong> of <strong>{permissions.total}</strong> permissions
                  </>
                ) : (
                  <>Showing <strong>0</strong> permissions</>
                )}
              </div>

              {permissions.last_page > 1 && (
                <Pagination>
                  <PaginationContent>
                    <PaginationItem>
                      <PaginationPrevious
                        href="#"
                        onClick={(e) => {
                          e.preventDefault()
                          if (permissions.current_page > 1) {
                            handlePageChange(permissions.current_page - 1)
                          }
                        }}
                        className={permissions.current_page === 1 ? "pointer-events-none opacity-50" : ""}
                      />
                    </PaginationItem>

                    {generatePageNumbers().map((page, index) => (
                      <PaginationItem key={index}>
                        {page === '...' ? (
                          <span className="flex h-9 w-9 items-center justify-center text-sm">...</span>
                        ) : (
                          <PaginationLink
                            href="#"
                            onClick={(e) => {
                              e.preventDefault()
                              handlePageChange(page as number)
                            }}
                            isActive={page === permissions.current_page}
                          >
                            {page}
                          </PaginationLink>
                        )}
                      </PaginationItem>
                    ))}

                    <PaginationItem>
                      <PaginationNext
                        href="#"
                        onClick={(e) => {
                          e.preventDefault()
                          if (permissions.current_page < permissions.last_page) {
                            handlePageChange(permissions.current_page + 1)
                          }
                        }}
                        className={permissions.current_page === permissions.last_page ? "pointer-events-none opacity-50" : ""}
                      />
                    </PaginationItem>
                  </PaginationContent>
                </Pagination>
              )}
            </CardFooter>
          </Card>
        </div>
      </Main>
    </AuthenticatedLayout>
  )
}
