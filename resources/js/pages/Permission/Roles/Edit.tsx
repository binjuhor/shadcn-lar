import { AuthenticatedLayout } from "@/layouts"
import { ArrowLeft, Shield } from "lucide-react"
import { Button } from "@/components/ui/button"
import {
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from "@/components/ui/card"
import { Input } from "@/components/ui/input"
import { Label } from "@/components/ui/label"
import { Checkbox } from "@/components/ui/checkbox"
import {
  Accordion,
  AccordionContent,
  AccordionItem,
  AccordionTrigger,
} from "@/components/ui/accordion"
import { Main } from "@/components/layout"
import { Role, Permission, PageProps, GroupedPermissions } from "@/types"
import { router, useForm } from "@inertiajs/react"
import { useToast } from "@/hooks/use-toast"
import { FormEventHandler } from "react"
import { Badge } from "@/components/ui/badge"

interface EditRolePageProps extends PageProps {
  role: Role
  permissions: { data: Permission[] }
  groupedPermissions: GroupedPermissions
  rolePermissions: string[]
}

export default function EditRole({ role, groupedPermissions, rolePermissions }: EditRolePageProps) {
  const { toast } = useToast()
  const isSuperAdmin = role.name === 'Super Admin'

  const { data, setData, put, processing, errors } = useForm({
    name: role.name,
    permissions: rolePermissions,
  })

  const handlePermissionChange = (permissionName: string, checked: boolean) => {
    if (isSuperAdmin) return

    if (checked) {
      setData('permissions', [...data.permissions, permissionName])
    } else {
      setData('permissions', data.permissions.filter(p => p !== permissionName))
    }
  }

  const handleSelectAllInGroup = (resource: string, checked: boolean) => {
    if (isSuperAdmin) return

    const groupPerms = groupedPermissions[resource]?.map(p => p.name) || []

    if (checked) {
      const newPerms = [...new Set([...data.permissions, ...groupPerms])]
      setData('permissions', newPerms)
    } else {
      setData('permissions', data.permissions.filter(p => !groupPerms.includes(p)))
    }
  }

  const isGroupFullySelected = (resource: string) => {
    if (isSuperAdmin) return true
    const groupPerms = groupedPermissions[resource]?.map(p => p.name) || []
    return groupPerms.every(p => data.permissions.includes(p))
  }

  const isGroupPartiallySelected = (resource: string) => {
    if (isSuperAdmin) return false
    const groupPerms = groupedPermissions[resource]?.map(p => p.name) || []
    const selectedCount = groupPerms.filter(p => data.permissions.includes(p)).length
    return selectedCount > 0 && selectedCount < groupPerms.length
  }

  const submit: FormEventHandler = (e) => {
    e.preventDefault()
    put(route('roles.update', role.id), {
      onSuccess: () => {
        toast({
          title: "Role updated!",
          description: `"${data.name}" has been updated successfully.`,
        })
      },
      onError: () => {
        toast({
          variant: "destructive",
          title: "Error updating role",
          description: "Please check the form and try again.",
        })
      }
    })
  }

  const formatResourceName = (resource: string) => {
    return resource
      .split('-')
      .map(word => word.charAt(0).toUpperCase() + word.slice(1))
      .join(' ')
  }

  return (
    <AuthenticatedLayout title={`Edit Role: ${role.name}`}>
      <Main>
        <div className="grid flex-1 items-start gap-4 md:gap-8">
          <div className="flex items-center gap-4">
            <Button
              variant="outline"
              size="icon"
              onClick={() => router.get(route('roles.index'))}
            >
              <ArrowLeft className="h-4 w-4" />
            </Button>
            <h1 className="text-xl font-semibold">Edit Role: {role.name}</h1>
            {isSuperAdmin && (
              <Badge variant="secondary">System Role</Badge>
            )}
          </div>

          <form onSubmit={submit}>
            <div className="grid gap-4 md:grid-cols-[1fr_2fr]">
              {/* Role Name Card */}
              <Card>
                <CardHeader>
                  <CardTitle className="flex items-center gap-2">
                    <Shield className="h-5 w-5" />
                    Role Details
                  </CardTitle>
                  <CardDescription>
                    {isSuperAdmin
                      ? "Super Admin has access to all permissions."
                      : "Update the role name and permissions."}
                  </CardDescription>
                </CardHeader>
                <CardContent className="space-y-4">
                  <div className="space-y-2">
                    <Label htmlFor="name">Role Name</Label>
                    <Input
                      id="name"
                      value={data.name}
                      onChange={(e) => setData('name', e.target.value)}
                      placeholder="e.g., Content Manager"
                      disabled={isSuperAdmin}
                    />
                    {errors.name && (
                      <p className="text-sm text-red-500">{errors.name}</p>
                    )}
                  </div>

                  <div className="pt-4">
                    <p className="text-sm text-muted-foreground">
                      {isSuperAdmin
                        ? "All permissions (via Gate::before bypass)"
                        : `Selected permissions: ${data.permissions.length}`}
                    </p>
                  </div>

                  <Button
                    type="submit"
                    disabled={processing || isSuperAdmin}
                    className="w-full"
                  >
                    {processing ? 'Saving...' : 'Save Changes'}
                  </Button>
                </CardContent>
              </Card>

              {/* Permissions Card */}
              <Card>
                <CardHeader>
                  <CardTitle>Permissions</CardTitle>
                  <CardDescription>
                    {isSuperAdmin
                      ? "Super Admin automatically has all permissions."
                      : "Select the permissions for this role."}
                  </CardDescription>
                </CardHeader>
                <CardContent>
                  <Accordion type="multiple" className="w-full">
                    {Object.keys(groupedPermissions).map((resource) => (
                      <AccordionItem value={resource} key={resource}>
                        <AccordionTrigger className="hover:no-underline">
                          <div className="flex items-center gap-2">
                            <Checkbox
                              checked={isGroupFullySelected(resource)}
                              ref={(el) => {
                                if (el) {
                                  (el as HTMLButtonElement & { indeterminate: boolean }).indeterminate = isGroupPartiallySelected(resource)
                                }
                              }}
                              onCheckedChange={(checked) => {
                                handleSelectAllInGroup(resource, checked as boolean)
                              }}
                              onClick={(e) => e.stopPropagation()}
                              disabled={isSuperAdmin}
                            />
                            <span className="font-medium">
                              {formatResourceName(resource)}
                            </span>
                            <span className="text-muted-foreground text-sm">
                              ({groupedPermissions[resource].length})
                            </span>
                          </div>
                        </AccordionTrigger>
                        <AccordionContent>
                          <div className="grid gap-2 pl-6 pt-2">
                            {groupedPermissions[resource].map((permission) => (
                              <div key={permission.name} className="flex items-center space-x-2">
                                <Checkbox
                                  id={permission.name}
                                  checked={isSuperAdmin || data.permissions.includes(permission.name)}
                                  onCheckedChange={(checked) => {
                                    handlePermissionChange(permission.name, checked as boolean)
                                  }}
                                  disabled={isSuperAdmin}
                                />
                                <Label
                                  htmlFor={permission.name}
                                  className="text-sm font-normal cursor-pointer"
                                >
                                  {permission.action.charAt(0).toUpperCase() + permission.action.slice(1)}
                                </Label>
                              </div>
                            ))}
                          </div>
                        </AccordionContent>
                      </AccordionItem>
                    ))}
                  </Accordion>
                </CardContent>
              </Card>
            </div>
          </form>
        </div>
      </Main>
    </AuthenticatedLayout>
  )
}
