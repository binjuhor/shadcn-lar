import { AuthenticatedLayout } from "@/layouts"
import { useTranslation } from "react-i18next"
import {
  File,
  ListFilter,
  MoreHorizontal,
  PlusCircle,
  Edit,
  Trash2,
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

interface Role {
  id: number
  name: string
  guard_name: string
  permissions: { id: number; name: string }[]
  users_count: number
  created_at: string
  updated_at: string
}

interface RolesPageProps extends PageProps {
  roles: {
    data: Role[]
    current_page: number
    last_page: number
    per_page: number
    total: number
  }
  filters: {
    search?: string
  }
}

export default function Roles({ roles, filters: initialFilters = {} }: RolesPageProps) {
  const { t } = useTranslation()
  const [searchTerm, setSearchTerm] = useState(initialFilters?.search || "")
  const { toast } = useToast()

  const handleSearch = (value: string) => {
    setSearchTerm(value)
    router.get(route('dashboard.roles.index'), { search: value }, {
      preserveState: true,
      replace: true,
    })
  }

  const handleDelete = (role: Role) => {
    if (role.name === 'Super Admin') {
      toast({
        variant: "destructive",
        title: t('page.roles.cannot_delete'),
        description: t('page.roles.cannot_delete_super_admin'),
      })
      return
    }

    if (confirm(t('page.roles.delete_confirm', { name: role.name }))) {
      router.delete(route('dashboard.roles.destroy', role.id), {
        onSuccess: () => {
          toast({
            title: t('page.roles.deleted'),
            description: t('page.roles.deleted_description', { name: role.name }),
          })
        },
        onError: (errors) => {
          toast({
            variant: "destructive",
            title: t('page.roles.delete_error'),
            description: Object.values(errors)[0] as string || t('common.error'),
          })
        }
      })
    }
  }

  const handlePageChange = (page: number) => {
    router.get(route('dashboard.roles.index'), { ...initialFilters, page }, {
      preserveState: true,
      replace: true,
    })
  }

  const generatePageNumbers = () => {
    const pages: (number | string)[] = []
    const delta = 2
    const rangeStart = Math.max(2, roles.current_page - delta)
    const rangeEnd = Math.min(roles.last_page - 1, roles.current_page + delta)

    if (roles.last_page > 1) {
      pages.push(1)
    }

    if (rangeStart > 2) {
      pages.push('...')
    }

    for (let i = rangeStart; i <= rangeEnd; i++) {
      if (i !== 1 && i !== roles.last_page) {
        pages.push(i)
      }
    }

    if (rangeEnd < roles.last_page - 1) {
      pages.push('...')
    }

    if (roles.last_page > 1 && roles.last_page !== 1) {
      pages.push(roles.last_page)
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

  return (
    <AuthenticatedLayout title={t('page.roles.title')}>
      <Main>
        <div className="grid flex-1 items-start gap-4 md:gap-8">
          <div className="md:flex items-center justify-between">
            <div className="mb-4">
              <h2 className="text-2xl font-bold tracking-tight">{t('page.roles.title')}</h2>
              <p className="text-muted-foreground">
                {t('page.roles.description')}
              </p>
            </div>
            <div className="flex items-center gap-2">
              <div className="relative">
                <Input
                  placeholder={t('page.roles.search_placeholder')}
                  value={searchTerm}
                  onChange={(e) => handleSearch(e.target.value)}
                  className="w-64"
                />
              </div>
              <Button
                size="sm"
                className="h-9 gap-1"
                onClick={() => router.get(route('dashboard.roles.create'))}
              >
                <PlusCircle className="h-3.5 w-3.5" />
                <span className="sr-only sm:not-sr-only sm:whitespace-nowrap">
                  {t('page.roles.add')}
                </span>
              </Button>
            </div>
          </div>

          <Card>
            <CardHeader>
              <CardTitle>{t('page.roles.all')}</CardTitle>
              <CardDescription>
                {t('page.roles.all_description')}
              </CardDescription>
            </CardHeader>
            <CardContent>
              <Table>
                <TableHeader>
                  <TableRow>
                    <TableHead>{t('table.name')}</TableHead>
                    <TableHead>{t('table.permissions')}</TableHead>
                    <TableHead>{t('table.users')}</TableHead>
                    <TableHead className="hidden md:table-cell">{t('table.created')}</TableHead>
                    <TableHead>
                      <span className="sr-only">{t('table.actions')}</span>
                    </TableHead>
                  </TableRow>
                </TableHeader>
                <TableBody>
                  {!roles.data || roles.data.length === 0 ? (
                    <TableRow>
                      <TableCell colSpan={5} className="h-24 text-center">
                        {t('page.roles.no_roles')}
                      </TableCell>
                    </TableRow>
                  ) : (
                    roles.data.map((role) => (
                      <TableRow key={role.id}>
                        <TableCell className="font-medium">
                          <div className="flex items-center gap-2">
                            <Shield className="h-4 w-4 text-muted-foreground" />
                            <span>{role.name}</span>
                            {role.name === 'Super Admin' && (
                              <Badge variant="secondary" className="text-xs">{t('common.system')}</Badge>
                            )}
                          </div>
                        </TableCell>
                        <TableCell>
                          <div className="flex flex-wrap gap-1 max-w-md">
                            {role.permissions.slice(0, 3).map((permission) => (
                              <Badge key={permission.id} variant="outline" className="text-xs">
                                {permission.name}
                              </Badge>
                            ))}
                            {role.permissions.length > 3 && (
                              <Badge variant="secondary" className="text-xs">
                                {t('page.roles.more', { count: role.permissions.length - 3 })}
                              </Badge>
                            )}
                            {role.permissions.length === 0 && (
                              <span className="text-sm text-muted-foreground">{t('page.roles.no_permissions')}</span>
                            )}
                          </div>
                        </TableCell>
                        <TableCell>
                          <Badge variant="outline">{role.users_count} {t('page.roles.users')}</Badge>
                        </TableCell>
                        <TableCell className="hidden md:table-cell">
                          {formatDate(role.created_at)}
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
                              <DropdownMenuLabel>{t('table.actions')}</DropdownMenuLabel>
                              <DropdownMenuSeparator />
                              <DropdownMenuItem
                                onClick={() => router.get(route('dashboard.roles.edit', role.id))}
                              >
                                <Edit className="mr-2 h-4 w-4" />
                                {t('action.edit')}
                              </DropdownMenuItem>
                              {role.name !== 'Super Admin' && (
                                <>
                                  <DropdownMenuSeparator />
                                  <DropdownMenuItem
                                    className="text-red-600"
                                    onClick={() => handleDelete(role)}
                                  >
                                    <Trash2 className="mr-2 h-4 w-4" />
                                    {t('action.delete')}
                                  </DropdownMenuItem>
                                </>
                              )}
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
                {roles?.current_page && roles?.per_page && roles?.total ? (
                  <>
                    {t('page.roles.showing', {
                      from: ((roles.current_page - 1) * roles.per_page) + 1,
                      to: Math.min(roles.current_page * roles.per_page, roles.total),
                      total: roles.total
                    })}
                  </>
                ) : (
                  <>{t('page.roles.showing_zero')}</>
                )}
              </div>

              {roles.last_page > 1 && (
                <Pagination>
                  <PaginationContent>
                    <PaginationItem>
                      <PaginationPrevious
                        href="#"
                        onClick={(e) => {
                          e.preventDefault()
                          if (roles.current_page > 1) {
                            handlePageChange(roles.current_page - 1)
                          }
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
                          if (roles.current_page < roles.last_page) {
                            handlePageChange(roles.current_page + 1)
                          }
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
