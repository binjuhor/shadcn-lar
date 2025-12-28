import { AuthenticatedLayout } from "@/layouts"
import { ChevronLeft } from "lucide-react"
import { Input } from "@/components/ui/input"
import { Label } from "@/components/ui/label"
import { Button } from "@/components/ui/button"
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card"
import { Main } from "@/components/layout"
import { useState } from "react"
import { router } from "@inertiajs/react"
import { PageProps } from "@/types"
import { useToast } from "@/hooks/use-toast"

interface Permission {
  id: number
  name: string
  guard_name: string
}

interface EditPermissionPageProps extends PageProps {
  permission: Permission
}

export default function EditPermission({ permission }: EditPermissionPageProps) {
  const [name, setName] = useState(permission.name)
  const [processing, setProcessing] = useState(false)
  const { toast } = useToast()

  const handleSubmit = () => {
    if (!name.trim()) {
      toast({
        variant: "destructive",
        title: "Validation Error",
        description: "Permission name is required.",
      })
      return
    }

    setProcessing(true)
    router.put(route('dashboard.permissions.update', permission.id), {
      name: name,
    }, {
      onSuccess: () => {
        setProcessing(false)
        toast({
          title: "Permission updated!",
          description: "The permission has been updated successfully.",
        })
      },
      onError: (errors) => {
        setProcessing(false)
        toast({
          variant: "destructive",
          title: "Error updating permission",
          description: Object.values(errors)[0] as string || "Please check your form and try again.",
        })
      }
    })
  }

  return (
    <AuthenticatedLayout title="Edit Permission">
      <Main>
        <div className="grid flex-1 items-start gap-4 md:gap-8">
          <div className="grid flex-1 auto-rows-max gap-4">
            <div className="flex items-center gap-4">
              <Button variant="outline" size="icon" className="h-7 w-7" onClick={() => window.history.back()}>
                <ChevronLeft className="h-4 w-4" />
                <span className="sr-only">Back</span>
              </Button>
              <h1 className="flex-1 shrink-0 whitespace-nowrap text-xl font-semibold tracking-tight sm:grow-0">
                Edit Permission
              </h1>
              <div className="hidden items-center gap-2 md:ml-auto md:flex">
                <Button variant="outline" onClick={() => router.get(route('dashboard.permissions.index'))}>
                  Cancel
                </Button>
                <Button size="sm" onClick={handleSubmit} disabled={processing}>
                  {processing ? 'Saving...' : 'Save Changes'}
                </Button>
              </div>
            </div>

            <div className="grid gap-4 md:grid-cols-[1fr_350px] lg:gap-8">
              <div className="grid auto-rows-max items-start gap-4 lg:gap-8">
                <Card>
                  <CardHeader>
                    <CardTitle>Permission Details</CardTitle>
                    <CardDescription>
                      Edit the permission name. Use the format: group.action
                    </CardDescription>
                  </CardHeader>
                  <CardContent>
                    <div className="grid gap-3">
                      <Label htmlFor="name">Name</Label>
                      <Input
                        id="name"
                        type="text"
                        className="w-full font-mono"
                        placeholder="e.g., users.view"
                        value={name}
                        onChange={(e) => setName(e.target.value.toLowerCase().replace(/[^a-z0-9.-]/g, ''))}
                      />
                      <p className="text-xs text-muted-foreground">
                        Only lowercase letters, numbers, dots, and hyphens are allowed.
                      </p>
                    </div>
                  </CardContent>
                </Card>
              </div>

              <div className="grid auto-rows-max items-start gap-4 lg:gap-8">
                <Card>
                  <CardHeader>
                    <CardTitle>Current Permission</CardTitle>
                  </CardHeader>
                  <CardContent className="space-y-4">
                    <div>
                      <p className="text-sm text-muted-foreground">Original Name</p>
                      <code className="text-sm font-mono bg-muted px-2 py-1 rounded">
                        {permission.name}
                      </code>
                    </div>
                    <div>
                      <p className="text-sm text-muted-foreground">Guard</p>
                      <p className="font-medium">{permission.guard_name}</p>
                    </div>
                  </CardContent>
                </Card>

                <Card>
                  <CardHeader>
                    <CardTitle>Warning</CardTitle>
                  </CardHeader>
                  <CardContent className="text-sm text-muted-foreground space-y-2">
                    <p>Changing a permission name will affect all roles that use this permission.</p>
                    <p>Make sure to update any code that references this permission name.</p>
                  </CardContent>
                </Card>
              </div>
            </div>

            <div className="flex items-center justify-center gap-2 md:hidden">
              <Button variant="outline" onClick={() => router.get(route('dashboard.permissions.index'))}>
                Cancel
              </Button>
              <Button size="sm" onClick={handleSubmit} disabled={processing}>
                {processing ? 'Saving...' : 'Save Changes'}
              </Button>
            </div>
          </div>
        </div>
      </Main>
    </AuthenticatedLayout>
  )
}
