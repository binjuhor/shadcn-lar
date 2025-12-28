import { AuthenticatedLayout } from "@/layouts"
import { ChevronLeft } from "lucide-react"
import { Input } from "@/components/ui/input"
import { Label } from "@/components/ui/label"
import { Button } from "@/components/ui/button"
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card"
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select"
import { Main } from "@/components/layout"
import { useState } from "react"
import { router } from "@inertiajs/react"
import { PageProps } from "@/types"
import { useToast } from "@/hooks/use-toast"

interface CreatePermissionPageProps extends PageProps {
  groups: string[]
}

const COMMON_ACTIONS = ['view', 'create', 'edit', 'delete', 'export', 'import']

export default function CreatePermission({ groups }: CreatePermissionPageProps) {
  const [selectedGroup, setSelectedGroup] = useState("")
  const [customGroup, setCustomGroup] = useState("")
  const [action, setAction] = useState("")
  const [customAction, setCustomAction] = useState("")
  const [processing, setProcessing] = useState(false)
  const { toast } = useToast()

  const getPermissionName = () => {
    const group = selectedGroup === 'custom' ? customGroup : selectedGroup
    const actionName = action === 'custom' ? customAction : action

    if (!group || !actionName) return ''
    return `${group}.${actionName}`
  }

  const handleSubmit = () => {
    const permissionName = getPermissionName()

    if (!permissionName) {
      toast({
        variant: "destructive",
        title: "Validation Error",
        description: "Please select or enter a group and action.",
      })
      return
    }

    setProcessing(true)
    router.post(route('dashboard.permissions.store'), {
      name: permissionName,
    }, {
      onSuccess: () => {
        setProcessing(false)
        toast({
          title: "Permission created!",
          description: "The permission has been created successfully.",
        })
      },
      onError: (errors) => {
        setProcessing(false)
        toast({
          variant: "destructive",
          title: "Error creating permission",
          description: Object.values(errors)[0] as string || "Please check your form and try again.",
        })
      }
    })
  }

  return (
    <AuthenticatedLayout title="Create Permission">
      <Main>
        <div className="grid flex-1 items-start gap-4 md:gap-8">
          <div className="grid flex-1 auto-rows-max gap-4">
            <div className="flex items-center gap-4">
              <Button variant="outline" size="icon" className="h-7 w-7" onClick={() => window.history.back()}>
                <ChevronLeft className="h-4 w-4" />
                <span className="sr-only">Back</span>
              </Button>
              <h1 className="flex-1 shrink-0 whitespace-nowrap text-xl font-semibold tracking-tight sm:grow-0">
                Create Permission
              </h1>
              <div className="hidden items-center gap-2 md:ml-auto md:flex">
                <Button variant="outline" onClick={() => router.get(route('dashboard.permissions.index'))}>
                  Cancel
                </Button>
                <Button size="sm" onClick={handleSubmit} disabled={processing}>
                  {processing ? 'Creating...' : 'Create Permission'}
                </Button>
              </div>
            </div>

            <div className="grid gap-4 md:grid-cols-[1fr_350px] lg:gap-8">
              <div className="grid auto-rows-max items-start gap-4 lg:gap-8">
                <Card>
                  <CardHeader>
                    <CardTitle>Permission Details</CardTitle>
                    <CardDescription>
                      Create a new permission using the format: group.action (e.g., users.view, posts.create)
                    </CardDescription>
                  </CardHeader>
                  <CardContent>
                    <div className="grid gap-6">
                      <div className="grid gap-3">
                        <Label htmlFor="group">Group</Label>
                        <Select value={selectedGroup} onValueChange={setSelectedGroup}>
                          <SelectTrigger>
                            <SelectValue placeholder="Select a group" />
                          </SelectTrigger>
                          <SelectContent>
                            {groups.map((group) => (
                              <SelectItem key={group} value={group} className="capitalize">
                                {group}
                              </SelectItem>
                            ))}
                            <SelectItem value="custom">Custom group...</SelectItem>
                          </SelectContent>
                        </Select>
                        {selectedGroup === 'custom' && (
                          <Input
                            placeholder="Enter custom group name"
                            value={customGroup}
                            onChange={(e) => setCustomGroup(e.target.value.toLowerCase().replace(/[^a-z0-9-]/g, ''))}
                          />
                        )}
                      </div>

                      <div className="grid gap-3">
                        <Label htmlFor="action">Action</Label>
                        <Select value={action} onValueChange={setAction}>
                          <SelectTrigger>
                            <SelectValue placeholder="Select an action" />
                          </SelectTrigger>
                          <SelectContent>
                            {COMMON_ACTIONS.map((act) => (
                              <SelectItem key={act} value={act} className="capitalize">
                                {act}
                              </SelectItem>
                            ))}
                            <SelectItem value="custom">Custom action...</SelectItem>
                          </SelectContent>
                        </Select>
                        {action === 'custom' && (
                          <Input
                            placeholder="Enter custom action name"
                            value={customAction}
                            onChange={(e) => setCustomAction(e.target.value.toLowerCase().replace(/[^a-z0-9-]/g, ''))}
                          />
                        )}
                      </div>
                    </div>
                  </CardContent>
                </Card>
              </div>

              <div className="grid auto-rows-max items-start gap-4 lg:gap-8">
                <Card>
                  <CardHeader>
                    <CardTitle>Preview</CardTitle>
                    <CardDescription>The permission name that will be created</CardDescription>
                  </CardHeader>
                  <CardContent>
                    <div className="p-4 rounded-md bg-muted">
                      <code className="text-sm font-mono">
                        {getPermissionName() || 'Select group and action'}
                      </code>
                    </div>
                  </CardContent>
                </Card>

                <Card>
                  <CardHeader>
                    <CardTitle>Tips</CardTitle>
                  </CardHeader>
                  <CardContent className="text-sm text-muted-foreground space-y-2">
                    <p>Permissions follow the format: <code className="bg-muted px-1 rounded">group.action</code></p>
                    <p>Common actions include: view, create, edit, delete</p>
                    <p>Examples: users.view, posts.create, orders.delete</p>
                  </CardContent>
                </Card>
              </div>
            </div>

            <div className="flex items-center justify-center gap-2 md:hidden">
              <Button variant="outline" onClick={() => router.get(route('dashboard.permissions.index'))}>
                Cancel
              </Button>
              <Button size="sm" onClick={handleSubmit} disabled={processing}>
                {processing ? 'Creating...' : 'Create Permission'}
              </Button>
            </div>
          </div>
        </div>
      </Main>
    </AuthenticatedLayout>
  )
}
