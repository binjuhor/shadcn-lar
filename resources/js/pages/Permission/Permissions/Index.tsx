import { AuthenticatedLayout } from "@/layouts"
import { Key } from "lucide-react"
import { Badge } from "@/components/ui/badge"
import {
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from "@/components/ui/card"
import {
  Accordion,
  AccordionContent,
  AccordionItem,
  AccordionTrigger,
} from "@/components/ui/accordion"
import { Input } from "@/components/ui/input"
import { Main } from "@/components/layout"
import { useState } from "react"
import { Permission, PageProps, GroupedPermissions, PaginatedData } from "@/types"

interface PermissionsPageProps extends PageProps {
  permissions: PaginatedData<Permission>
  groupedPermissions: GroupedPermissions
  filters?: { search?: string }
}

export default function PermissionsIndex({
  groupedPermissions,
  filters: initialFilters = {}
}: PermissionsPageProps) {
  const [searchTerm, setSearchTerm] = useState(initialFilters?.search || "")

  const filteredGroups = Object.keys(groupedPermissions).reduce((acc, resource) => {
    if (!searchTerm) {
      acc[resource] = groupedPermissions[resource]
      return acc
    }

    const filtered = groupedPermissions[resource].filter(p =>
      p.name.toLowerCase().includes(searchTerm.toLowerCase()) ||
      p.action.toLowerCase().includes(searchTerm.toLowerCase()) ||
      resource.toLowerCase().includes(searchTerm.toLowerCase())
    )

    if (filtered.length > 0) {
      acc[resource] = filtered
    }

    return acc
  }, {} as GroupedPermissions)

  const totalPermissions = Object.values(groupedPermissions).flat().length
  const filteredCount = Object.values(filteredGroups).flat().length

  const formatResourceName = (resource: string) => {
    return resource
      .split('-')
      .map(word => word.charAt(0).toUpperCase() + word.slice(1))
      .join(' ')
  }

  const getActionColor = (action: string) => {
    switch (action) {
      case 'view':
        return 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-300'
      case 'create':
        return 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-300'
      case 'edit':
        return 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-300'
      case 'delete':
        return 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-300'
      default:
        return ''
    }
  }

  return (
    <AuthenticatedLayout title="Permissions">
      <Main>
        <div className="grid flex-1 items-start gap-4 md:gap-8">
          <Card>
            <CardHeader>
              <div className="flex items-center justify-between">
                <div>
                  <CardTitle className="flex items-center gap-2">
                    <Key className="h-5 w-5" />
                    Permissions
                  </CardTitle>
                  <CardDescription>
                    View all available permissions in the system. Permissions are managed through roles.
                  </CardDescription>
                </div>
                <div className="flex items-center gap-2">
                  <Input
                    placeholder="Search permissions..."
                    value={searchTerm}
                    onChange={(e) => setSearchTerm(e.target.value)}
                    className="w-64"
                  />
                </div>
              </div>
            </CardHeader>
            <CardContent>
              <div className="mb-4">
                <p className="text-sm text-muted-foreground">
                  {searchTerm
                    ? `Showing ${filteredCount} of ${totalPermissions} permissions`
                    : `Total: ${totalPermissions} permissions across ${Object.keys(groupedPermissions).length} resources`}
                </p>
              </div>

              {Object.keys(filteredGroups).length === 0 ? (
                <div className="text-center py-8 text-muted-foreground">
                  No permissions found matching "{searchTerm}"
                </div>
              ) : (
                <Accordion type="multiple" defaultValue={Object.keys(filteredGroups)} className="w-full">
                  {Object.keys(filteredGroups).map((resource) => (
                    <AccordionItem value={resource} key={resource}>
                      <AccordionTrigger className="hover:no-underline">
                        <div className="flex items-center gap-2">
                          <span className="font-medium">
                            {formatResourceName(resource)}
                          </span>
                          <Badge variant="secondary">
                            {filteredGroups[resource].length}
                          </Badge>
                        </div>
                      </AccordionTrigger>
                      <AccordionContent>
                        <div className="grid gap-2 pt-2">
                          {filteredGroups[resource].map((permission) => (
                            <div
                              key={permission.name}
                              className="flex items-center justify-between p-2 rounded-md border"
                            >
                              <div className="flex items-center gap-2">
                                <code className="text-sm bg-muted px-2 py-1 rounded">
                                  {permission.name}
                                </code>
                              </div>
                              <Badge className={getActionColor(permission.action)}>
                                {permission.action}
                              </Badge>
                            </div>
                          ))}
                        </div>
                      </AccordionContent>
                    </AccordionItem>
                  ))}
                </Accordion>
              )}
            </CardContent>
          </Card>
        </div>
      </Main>
    </AuthenticatedLayout>
  )
}
