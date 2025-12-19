import { AuthenticatedLayout } from "@/layouts"
import { ChevronLeft } from "lucide-react"
import { Input } from "@/components/ui/input"
import { Label } from "@/components/ui/label"
import { Button } from "@/components/ui/button"
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card"
import { Checkbox } from "@/components/ui/checkbox"
import { Main } from "@/components/layout"
import { useState } from "react"
import { router } from "@inertiajs/react"
import { PageProps } from "@/types"
import { useToast } from "@/hooks/use-toast"
import { ScrollArea } from "@/components/ui/scroll-area"

interface Permission {
  id: number
  name: string
}

interface GroupedPermissions {
  [key: string]: {
    name: string
    action: string
  }[]
}

interface CreateRolePageProps extends PageProps {
  permissions: Permission[]
  groupedPermissions: GroupedPermissions
}

export default function CreateRole({ permissions, groupedPermissions }: CreateRolePageProps) {
  const [name, setName] = useState("")
  const [selectedPermissions, setSelectedPermissions] = useState<string[]>([])
  const [processing, setProcessing] = useState(false)
  const { toast } = useToast()

  const handlePermissionToggle = (permissionName: string) => {
    setSelectedPermissions(prev =>
      prev.includes(permissionName)
        ? prev.filter(p => p !== permissionName)
        : [...prev, permissionName]
    )
  }

  const handleGroupToggle = (group: string, permissions: { name: string }[]) => {
    const groupPermissionNames = permissions.map(p => p.name)
    const allSelected = groupPermissionNames.every(p => selectedPermissions.includes(p))

    if (allSelected) {
      setSelectedPermissions(prev => prev.filter(p => !groupPermissionNames.includes(p)))
    } else {
      setSelectedPermissions(prev => [...new Set([...prev, ...groupPermissionNames])])
    }
  }

  const handleSelectAll = () => {
    if (selectedPermissions.length === permissions.length) {
      setSelectedPermissions([])
    } else {
      setSelectedPermissions(permissions.map(p => p.name))
    }
  }

  const handleSubmit = () => {
    if (!name.trim()) {
      toast({
        variant: "destructive",
        title: "Validation Error",
        description: "Role name is required.",
      })
      return
    }

    setProcessing(true)
    router.post(route('dashboard.roles.store'), {
      name: name,
      permissions: selectedPermissions,
    }, {
      onSuccess: () => {
        setProcessing(false)
        toast({
          title: "Role created!",
          description: "The role has been created successfully.",
        })
      },
      onError: (errors) => {
        setProcessing(false)
        toast({
          variant: "destructive",
          title: "Error creating role",
          description: Object.values(errors)[0] as string || "Please check your form and try again.",
        })
      }
    })
  }

  const isGroupFullySelected = (permissions: { name: string }[]) => {
    return permissions.every(p => selectedPermissions.includes(p.name))
  }

  const isGroupPartiallySelected = (permissions: { name: string }[]) => {
    return permissions.some(p => selectedPermissions.includes(p.name)) && !isGroupFullySelected(permissions)
  }

  return (
    <AuthenticatedLayout title="Create Role">
      <Main>
        <div className="grid flex-1 items-start gap-4 md:gap-8">
          <div className="grid flex-1 auto-rows-max gap-4">
            <div className="flex items-center gap-4">
              <Button variant="outline" size="icon" className="h-7 w-7" onClick={() => window.history.back()}>
                <ChevronLeft className="h-4 w-4" />
                <span className="sr-only">Back</span>
              </Button>
              <h1 className="flex-1 shrink-0 whitespace-nowrap text-xl font-semibold tracking-tight sm:grow-0">
                Create Role
              </h1>
              <div className="hidden items-center gap-2 md:ml-auto md:flex">
                <Button variant="outline" onClick={() => router.get(route('dashboard.roles.index'))}>
                  Cancel
                </Button>
                <Button size="sm" onClick={handleSubmit} disabled={processing}>
                  {processing ? 'Creating...' : 'Create Role'}
                </Button>
              </div>
            </div>

            <div className="grid gap-4 md:grid-cols-[1fr_350px] lg:gap-8">
              <div className="grid auto-rows-max items-start gap-4 lg:gap-8">
                <Card>
                  <CardHeader>
                    <CardTitle>Role Details</CardTitle>
                    <CardDescription>Enter the role name</CardDescription>
                  </CardHeader>
                  <CardContent>
                    <div className="grid gap-3">
                      <Label htmlFor="name">Name</Label>
                      <Input
                        id="name"
                        type="text"
                        className="w-full"
                        placeholder="Enter role name..."
                        value={name}
                        onChange={(e) => setName(e.target.value)}
                      />
                    </div>
                  </CardContent>
                </Card>

                <Card>
                  <CardHeader>
                    <div className="flex items-center justify-between">
                      <div>
                        <CardTitle>Permissions</CardTitle>
                        <CardDescription>Select permissions for this role</CardDescription>
                      </div>
                      <Button variant="outline" size="sm" onClick={handleSelectAll}>
                        {selectedPermissions.length === permissions.length ? 'Deselect All' : 'Select All'}
                      </Button>
                    </div>
                  </CardHeader>
                  <CardContent>
                    <ScrollArea className="h-[500px] pr-4">
                      <div className="space-y-6">
                        {Object.entries(groupedPermissions).map(([group, perms]) => (
                          <div key={group} className="space-y-3">
                            <div className="flex items-center space-x-2 border-b pb-2">
                              <Checkbox
                                id={`group-${group}`}
                                checked={isGroupFullySelected(perms)}
                                ref={(ref) => {
                                  if (ref) {
                                    (ref as HTMLButtonElement & { indeterminate: boolean }).indeterminate = isGroupPartiallySelected(perms)
                                  }
                                }}
                                onCheckedChange={() => handleGroupToggle(group, perms)}
                              />
                              <Label
                                htmlFor={`group-${group}`}
                                className="text-sm font-semibold capitalize cursor-pointer"
                              >
                                {group}
                              </Label>
                              <span className="text-xs text-muted-foreground">
                                ({perms.filter(p => selectedPermissions.includes(p.name)).length}/{perms.length})
                              </span>
                            </div>
                            <div className="grid grid-cols-2 md:grid-cols-3 gap-3 pl-6">
                              {perms.map((permission) => (
                                <div key={permission.name} className="flex items-center space-x-2">
                                  <Checkbox
                                    id={permission.name}
                                    checked={selectedPermissions.includes(permission.name)}
                                    onCheckedChange={() => handlePermissionToggle(permission.name)}
                                  />
                                  <Label
                                    htmlFor={permission.name}
                                    className="text-sm font-normal cursor-pointer"
                                  >
                                    {permission.action}
                                  </Label>
                                </div>
                              ))}
                            </div>
                          </div>
                        ))}
                      </div>
                    </ScrollArea>
                  </CardContent>
                </Card>
              </div>

              <div className="grid auto-rows-max items-start gap-4 lg:gap-8">
                <Card>
                  <CardHeader>
                    <CardTitle>Summary</CardTitle>
                  </CardHeader>
                  <CardContent className="space-y-4">
                    <div>
                      <p className="text-sm text-muted-foreground">Role Name</p>
                      <p className="font-medium">{name || '-'}</p>
                    </div>
                    <div>
                      <p className="text-sm text-muted-foreground">Selected Permissions</p>
                      <p className="font-medium">{selectedPermissions.length} of {permissions.length}</p>
                    </div>
                  </CardContent>
                </Card>
              </div>
            </div>

            <div className="flex items-center justify-center gap-2 md:hidden">
              <Button variant="outline" onClick={() => router.get(route('dashboard.roles.index'))}>
                Cancel
              </Button>
              <Button size="sm" onClick={handleSubmit} disabled={processing}>
                {processing ? 'Creating...' : 'Create Role'}
              </Button>
            </div>
          </div>
        </div>
      </Main>
    </AuthenticatedLayout>
  )
}
