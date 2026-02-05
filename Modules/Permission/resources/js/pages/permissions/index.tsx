import { AuthenticatedLayout } from "@/layouts"
import { useTranslation } from "react-i18next"
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
  const { t } = useTranslation()
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
    if (confirm(t('page.permissions.delete_confirm', { name: permission.name }))) {
      router.delete(route('dashboard.permissions.destroy', permission.id), {
        onSuccess: () => {
          toast({
            title: t('page.permissions.deleted'),
            description: t('page.permissions.deleted_description', { name: permission.name }),
          })
        },
        onError: (errors) => {
          toast({
            variant: "destructive",
            title: t('page.permissions.delete_error'),
            description: Object.values(errors)[0] as string || t('common.error'),
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
    <AuthenticatedLayout title={t('page.permissions.title')}>
      <Main>
        <div className="grid flex-1 items-start gap-4 md:gap-8">
          <div className="md:flex items-center justify-between">
            <div className="mb-4">
              <h2 className="text-2xl font-bold tracking-tight">{t('page.permissions.title')}</h2>
              <p className="text-muted-foreground">
                {t('page.permissions.description')}
              </p>
            </div>
            <div className="flex items-center gap-2">
              <div className="relative">
                <Input
                  placeholder={t('page.permissions.search_placeholder')}
                  value={searchTerm}
                  onChange={(e) => handleSearch(e.target.value)}
                  className="md:w-64"
                />
              </div>
              <Select value={selectedGroup || 'all'} onValueChange={handleGroupFilter}>
                <SelectTrigger className="w-40">
                  <SelectValue placeholder={t('page.permissions.filter_by_group')} />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="all">{t('page.permissions.all_groups')}</SelectItem>
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
                  {t('page.permissions.add')}
                </span>
              </Button>
            </div>
          </div>

          <Card>
            <CardHeader>
              <CardTitle>{t('page.permissions.all')}</CardTitle>
              <CardDescription>
                {t('page.permissions.all_description')}
              </CardDescription>
            </CardHeader>
            <CardContent>
              <Table>
                <TableHeader>
                  <TableRow>
                    <TableHead>{t('table.name')}</TableHead>
                    <TableHead>{t('page.permissions.group')}</TableHead>
                    <TableHead>{t('page.permissions.action')}</TableHead>
                    <TableHead>{t('page.permissions.assigned_to_roles')}</TableHead>
                    <TableHead className="hidden md:table-cell">{t('table.created')}</TableHead>
                    <TableHead>
                      <span className="sr-only">{t('table.actions')}</span>
                    </TableHead>
                  </TableRow>
                </TableHeader>
                <TableBody>
                  {!permissions.data || permissions.data.length === 0 ? (
                    <TableRow>
                      <TableCell colSpan={6} className="h-24 text-center">
                        {t('page.permissions.no_permissions')}
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
                          <Badge variant="secondary">{permission.roles_count} {t('page.permissions.roles')}</Badge>
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
                              <DropdownMenuLabel>{t('table.actions')}</DropdownMenuLabel>
                              <DropdownMenuSeparator />
                              <DropdownMenuItem
                                onClick={() => router.get(route('dashboard.permissions.edit', permission.id))}
                              >
                                <Edit className="mr-2 h-4 w-4" />
                                {t('action.edit')}
                              </DropdownMenuItem>
                              <DropdownMenuSeparator />
                              <DropdownMenuItem
                                className="text-red-600"
                                onClick={() => handleDelete(permission)}
                              >
                                <Trash2 className="mr-2 h-4 w-4" />
                                {t('action.delete')}
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
                    {t('page.permissions.showing', {
                      from: ((permissions.current_page - 1) * permissions.per_page) + 1,
                      to: Math.min(permissions.current_page * permissions.per_page, permissions.total),
                      total: permissions.total
                    })}
                  </>
                ) : (
                  <>{t('page.permissions.showing_zero')}</>
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
