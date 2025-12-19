import { AuthenticatedLayout } from "@/layouts"
import {
  MoreHorizontal,
  PlusCircle,
  Edit,
  Trash2,
  Users,
  Shield,
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
import { User, Role, PageProps, PaginatedData } from "@/types"
import { router } from "@inertiajs/react"
import { useToast } from "@/hooks/use-toast"
import {
  Pagination,
  PaginationContent,
  PaginationItem,
  PaginationLink,
  PaginationNext,
  PaginationPrevious,
} from "@/components/ui/pagination"

interface UsersPageProps extends PageProps {
  users: PaginatedData<User>
  roles: { data: Role[] }
  filters?: { search?: string; role?: string }
}

export default function UsersIndex({
  users,
  roles,
  filters: initialFilters = {}
}: UsersPageProps) {
  const [searchTerm, setSearchTerm] = useState(initialFilters?.search || "")
  const [roleFilter, setRoleFilter] = useState(initialFilters?.role || "")
  const { toast } = useToast()

  const handleSearch = (value: string) => {
    setSearchTerm(value)
    router.get(route('users.index'), { search: value, role: roleFilter || undefined }, {
      preserveState: true,
      replace: true,
    })
  }

  const handleRoleFilter = (value: string) => {
    setRoleFilter(value)
    router.get(route('users.index'), { search: searchTerm || undefined, role: value === 'all' ? undefined : value }, {
      preserveState: true,
      replace: true,
    })
  }

  const handleDelete = (user: User) => {
    if (confirm(`Are you sure you want to delete "${user.name}"?`)) {
      router.delete(route('users.destroy', user.id), {
        onSuccess: () => {
          toast({
            title: "User deleted!",
            description: `"${user.name}" has been deleted successfully.`,
          })
        },
        onError: (errors) => {
          toast({
            variant: "destructive",
            title: "Error deleting user",
            description: Object.values(errors)[0] as string || "Something went wrong.",
          })
        }
      })
    }
  }

  const handlePageChange = (page: number) => {
    router.get(route('users.index'), { ...initialFilters, page }, {
      preserveState: true,
      replace: true,
    })
  }

  const generatePageNumbers = () => {
    const pages: (number | string)[] = []
    const delta = 2
    const rangeStart = Math.max(2, users.current_page - delta)
    const rangeEnd = Math.min(users.last_page - 1, users.current_page + delta)

    if (users.last_page > 1) pages.push(1)
    if (rangeStart > 2) pages.push('...')
    for (let i = rangeStart; i <= rangeEnd; i++) {
      if (i !== 1 && i !== users.last_page) pages.push(i)
    }
    if (rangeEnd < users.last_page - 1) pages.push('...')
    if (users.last_page > 1 && users.last_page !== 1) pages.push(users.last_page)

    return pages
  }

  const formatDate = (dateString?: string) => {
    if (!dateString) return 'N/A'
    return new Date(dateString).toLocaleDateString("en-US", {
      year: "numeric",
      month: "short",
      day: "numeric",
    })
  }

  return (
    <AuthenticatedLayout title="Users">
      <Main>
        <div className="grid flex-1 items-start gap-4 md:gap-8">
          <Card>
            <CardHeader>
              <div className="flex items-center justify-between">
                <div>
                  <CardTitle className="flex items-center gap-2">
                    <Users className="h-5 w-5" />
                    Users
                  </CardTitle>
                  <CardDescription>
                    Manage users and their role assignments.
                  </CardDescription>
                </div>
                <div className="flex items-center gap-2">
                  <Input
                    placeholder="Search users..."
                    value={searchTerm}
                    onChange={(e) => handleSearch(e.target.value)}
                    className="w-48"
                  />
                  <Select value={roleFilter || 'all'} onValueChange={handleRoleFilter}>
                    <SelectTrigger className="w-40">
                      <SelectValue placeholder="Filter by role" />
                    </SelectTrigger>
                    <SelectContent>
                      <SelectItem value="all">All Roles</SelectItem>
                      {roles.data.map((role) => (
                        <SelectItem key={role.id} value={role.name}>
                          {role.name}
                        </SelectItem>
                      ))}
                    </SelectContent>
                  </Select>
                  <Button
                    size="sm"
                    className="gap-1"
                    onClick={() => router.get(route('users.create'))}
                  >
                    <PlusCircle className="h-4 w-4" />
                    Add User
                  </Button>
                </div>
              </div>
            </CardHeader>
            <CardContent>
              <Table>
                <TableHeader>
                  <TableRow>
                    <TableHead>Name</TableHead>
                    <TableHead>Email</TableHead>
                    <TableHead>Roles</TableHead>
                    <TableHead>Verified</TableHead>
                    <TableHead>
                      <span className="sr-only">Actions</span>
                    </TableHead>
                  </TableRow>
                </TableHeader>
                <TableBody>
                  {!users.data || users.data.length === 0 ? (
                    <TableRow>
                      <TableCell colSpan={5} className="h-24 text-center">
                        No users found.
                      </TableCell>
                    </TableRow>
                  ) : (
                    users.data.map((user) => (
                      <TableRow key={user.id}>
                        <TableCell className="font-medium">
                          {user.name}
                        </TableCell>
                        <TableCell>{user.email}</TableCell>
                        <TableCell>
                          <div className="flex flex-wrap gap-1">
                            {user.role_names && user.role_names.length > 0 ? (
                              user.role_names.map((roleName) => (
                                <Badge
                                  key={roleName}
                                  variant={roleName === 'Super Admin' ? 'default' : 'secondary'}
                                  className="flex items-center gap-1"
                                >
                                  <Shield className="h-3 w-3" />
                                  {roleName}
                                </Badge>
                              ))
                            ) : (
                              <span className="text-muted-foreground text-sm">No roles</span>
                            )}
                          </div>
                        </TableCell>
                        <TableCell>
                          {user.email_verified_at ? (
                            <Badge variant="outline" className="text-green-600 border-green-600">
                              Verified
                            </Badge>
                          ) : (
                            <Badge variant="outline" className="text-yellow-600 border-yellow-600">
                              Pending
                            </Badge>
                          )}
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
                                onClick={() => router.get(route('users.edit', user.id))}
                              >
                                <Edit className="mr-2 h-4 w-4" />
                                Edit
                              </DropdownMenuItem>
                              <DropdownMenuSeparator />
                              <DropdownMenuItem
                                className="text-red-600"
                                onClick={() => handleDelete(user)}
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
                Showing <strong>{((users.current_page - 1) * users.per_page) + 1}-{Math.min(users.current_page * users.per_page, users.total)}</strong> of <strong>{users.total}</strong> users
              </div>

              {users.last_page > 1 && (
                <Pagination>
                  <PaginationContent>
                    <PaginationItem>
                      <PaginationPrevious
                        href="#"
                        onClick={(e) => {
                          e.preventDefault()
                          if (users.current_page > 1) handlePageChange(users.current_page - 1)
                        }}
                        className={users.current_page === 1 ? "pointer-events-none opacity-50" : ""}
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
                            isActive={page === users.current_page}
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
                          if (users.current_page < users.last_page) handlePageChange(users.current_page + 1)
                        }}
                        className={users.current_page === users.last_page ? "pointer-events-none opacity-50" : ""}
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
