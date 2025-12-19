import { AuthenticatedLayout } from "@/layouts"
import { MoreHorizontal, PlusCircle, Edit, Trash2, Shield, Users } from "lucide-react"
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
import { Input } from "@/components/ui/input"
import { Main } from "@/components/layout"
import { useState } from "react"
import { Role, PageProps, PaginatedData } from "@/types"
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

interface RolesPageProps extends PageProps {
  roles: PaginatedData<Role>
  filters?: { search?: string }
}

export default function RolesIndex({ roles, filters: initialFilters = {} }: RolesPageProps) {
  const [searchTerm, setSearchTerm] = useState(initialFilters?.search || "")
  const { toast } = useToast()

  const handleSearch = (value: string) => {
    setSearchTerm(value)
    router.get(route('roles.index'), { search: value }, {
      preserveState: true,
      replace: true,
    })
  }

  const handleDelete = (role: Role) => {
    if (role.name === 'Super Admin') {
      toast({
        variant: "destructive",
        title: "Cannot delete",
        description: "Super Admin role cannot be deleted.",
      })
      return
    }

    if (confirm(`Are you sure you want to delete the "${role.name}" role?`)) {
      router.delete(route('roles.destroy', role.id), {
        onSuccess: () => {
          toast({
            title: "Role deleted!",
            description: `"${role.name}" has been deleted successfully.`,
          })
        },
        onError: (errors) => {
          toast({
            variant: "destructive",
            title: "Error deleting role",
            description: Object.values(errors)[0] as string || "Something went wrong.",
          })
        }
      })
    }
  }

  const handlePageChange = (page: number) => {
    router.get(route('roles.index'), { ...initialFilters, page }, {
      preserveState: true,
      replace: true,
    })
  }

  const generatePageNumbers = () => {
    const pages: (number | string)[] = []
    const delta = 2
    const rangeStart = Math.max(2, roles.current_page - delta)
    const rangeEnd = Math.min(roles.last_page - 1, roles.current_page + delta)

    if (roles.last_page > 1) pages.push(1)
    if (rangeStart > 2) pages.push('...')
    for (let i = rangeStart; i <= rangeEnd; i++) {
      if (i !== 1 && i !== roles.last_page) pages.push(i)
    }
    if (rangeEnd < roles.last_page - 1) pages.push('...')
    if (roles.last_page > 1 && roles.last_page !== 1) pages.push(roles.last_page)

    return pages
  }

  return (
    <AuthenticatedLayout title="Roles">
      <Main>
        <div className="grid flex-1 items-start gap-4 md:gap-8">
          <Card>
            <CardHeader>
              <div className="flex items-center justify-between">
                <div>
                  <CardTitle className="flex items-center gap-2">
                    <Shield className="h-5 w-5" />
                    Roles
                  </CardTitle>
                  <CardDescription>
                    Manage user roles and their associated permissions.
                  </CardDescription>
                </div>
                <div className="flex items-center gap-2">
                  <Input
                    placeholder="Search roles..."
                    value={searchTerm}
                    onChange={(e) => handleSearch(e.target.value)}
                    className="w-64"
                  />
                  <Button
                    size="sm"
                    className="gap-1"
                    onClick={() => router.get(route('roles.create'))}
                  >
                    <PlusCircle className="h-4 w-4" />
                    Add Role
                  </Button>
                </div>
              </div>
            </CardHeader>
            <CardContent>
              <Table>
                <TableHeader>
                  <TableRow>
                    <TableHead>Name</TableHead>
                    <TableHead>Permissions</TableHead>
                    <TableHead>Users</TableHead>
                    <TableHead>Guard</TableHead>
                    <TableHead>
                      <span className="sr-only">Actions</span>
                    </TableHead>
                  </TableRow>
                </TableHeader>
                <TableBody>
                  {!roles.data || roles.data.length === 0 ? (
                    <TableRow>
                      <TableCell colSpan={5} className="h-24 text-center">
                        No roles found.
                      </TableCell>
                    </TableRow>
                  ) : (
                    roles.data.map((role) => (
                      <TableRow key={role.id}>
                        <TableCell className="font-medium">
                          <div className="flex items-center gap-2">
                            {role.name}
                            {role.name === 'Super Admin' && (
                              <Badge variant="secondary">System</Badge>
                            )}
                          </div>
                        </TableCell>
                        <TableCell>
                          <Badge variant="outline">
                            {role.name === 'Super Admin' ? 'All' : role.permissions_count || 0}
                          </Badge>
                        </TableCell>
                        <TableCell>
                          <div className="flex items-center gap-1">
                            <Users className="h-4 w-4 text-muted-foreground" />
                            <span>{role.users_count || 0}</span>
                          </div>
                        </TableCell>
                        <TableCell>
                          <Badge variant="outline">{role.guard_name}</Badge>
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
                                onClick={() => router.get(route('roles.edit', role.id))}
                              >
                                <Edit className="mr-2 h-4 w-4" />
                                Edit
                              </DropdownMenuItem>
                              <DropdownMenuSeparator />
                              <DropdownMenuItem
                                className="text-red-600"
                                onClick={() => handleDelete(role)}
                                disabled={role.name === 'Super Admin'}
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
                Showing <strong>{((roles.current_page - 1) * roles.per_page) + 1}-{Math.min(roles.current_page * roles.per_page, roles.total)}</strong> of <strong>{roles.total}</strong> roles
              </div>

              {roles.last_page > 1 && (
                <Pagination>
                  <PaginationContent>
                    <PaginationItem>
                      <PaginationPrevious
                        href="#"
                        onClick={(e) => {
                          e.preventDefault()
                          if (roles.current_page > 1) handlePageChange(roles.current_page - 1)
                        }}
                        className={roles.current_page === 1 ? "pointer-events-none opacity-50" : ""}
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
                            isActive={page === roles.current_page}
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
                          if (roles.current_page < roles.last_page) handlePageChange(roles.current_page + 1)
                        }}
                        className={roles.current_page === roles.last_page ? "pointer-events-none opacity-50" : ""}
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
