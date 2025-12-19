import { AuthenticatedLayout } from "@/layouts"
import { ArrowLeft, Users } from "lucide-react"
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
import { Main } from "@/components/layout"
import { Role, PageProps } from "@/types"
import { router, useForm } from "@inertiajs/react"
import { useToast } from "@/hooks/use-toast"
import { FormEventHandler } from "react"

interface CreateUserPageProps extends PageProps {
  roles: { data: Role[] }
}

export default function CreateUser({ roles }: CreateUserPageProps) {
  const { toast } = useToast()

  const { data, setData, post, processing, errors } = useForm({
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
    roles: [] as string[],
  })

  const handleRoleChange = (roleName: string, checked: boolean) => {
    if (checked) {
      setData('roles', [...data.roles, roleName])
    } else {
      setData('roles', data.roles.filter(r => r !== roleName))
    }
  }

  const submit: FormEventHandler = (e) => {
    e.preventDefault()
    post(route('users.store'), {
      onSuccess: () => {
        toast({
          title: "User created!",
          description: `"${data.name}" has been created successfully.`,
        })
      },
      onError: () => {
        toast({
          variant: "destructive",
          title: "Error creating user",
          description: "Please check the form and try again.",
        })
      }
    })
  }

  return (
    <AuthenticatedLayout title="Create User">
      <Main>
        <div className="grid flex-1 items-start gap-4 md:gap-8">
          <div className="flex items-center gap-4">
            <Button
              variant="outline"
              size="icon"
              onClick={() => router.get(route('users.index'))}
            >
              <ArrowLeft className="h-4 w-4" />
            </Button>
            <h1 className="text-xl font-semibold">Create New User</h1>
          </div>

          <form onSubmit={submit}>
            <div className="grid gap-4 md:grid-cols-2">
              {/* User Details Card */}
              <Card>
                <CardHeader>
                  <CardTitle className="flex items-center gap-2">
                    <Users className="h-5 w-5" />
                    User Details
                  </CardTitle>
                  <CardDescription>
                    Enter the user's information.
                  </CardDescription>
                </CardHeader>
                <CardContent className="space-y-4">
                  <div className="space-y-2">
                    <Label htmlFor="name">Name</Label>
                    <Input
                      id="name"
                      value={data.name}
                      onChange={(e) => setData('name', e.target.value)}
                      placeholder="John Doe"
                    />
                    {errors.name && (
                      <p className="text-sm text-red-500">{errors.name}</p>
                    )}
                  </div>

                  <div className="space-y-2">
                    <Label htmlFor="email">Email</Label>
                    <Input
                      id="email"
                      type="email"
                      value={data.email}
                      onChange={(e) => setData('email', e.target.value)}
                      placeholder="john@example.com"
                    />
                    {errors.email && (
                      <p className="text-sm text-red-500">{errors.email}</p>
                    )}
                  </div>

                  <div className="space-y-2">
                    <Label htmlFor="password">Password</Label>
                    <Input
                      id="password"
                      type="password"
                      value={data.password}
                      onChange={(e) => setData('password', e.target.value)}
                      placeholder="••••••••"
                    />
                    {errors.password && (
                      <p className="text-sm text-red-500">{errors.password}</p>
                    )}
                  </div>

                  <div className="space-y-2">
                    <Label htmlFor="password_confirmation">Confirm Password</Label>
                    <Input
                      id="password_confirmation"
                      type="password"
                      value={data.password_confirmation}
                      onChange={(e) => setData('password_confirmation', e.target.value)}
                      placeholder="••••••••"
                    />
                  </div>
                </CardContent>
              </Card>

              {/* Roles Card */}
              <Card>
                <CardHeader>
                  <CardTitle>Assign Roles</CardTitle>
                  <CardDescription>
                    Select the roles for this user.
                  </CardDescription>
                </CardHeader>
                <CardContent>
                  <div className="space-y-3">
                    {roles.data.map((role) => (
                      <div key={role.id} className="flex items-center space-x-2">
                        <Checkbox
                          id={`role-${role.id}`}
                          checked={data.roles.includes(role.name)}
                          onCheckedChange={(checked) => {
                            handleRoleChange(role.name, checked as boolean)
                          }}
                        />
                        <Label
                          htmlFor={`role-${role.id}`}
                          className="text-sm font-normal cursor-pointer flex items-center gap-2"
                        >
                          {role.name}
                          {role.permissions_count !== undefined && (
                            <span className="text-muted-foreground text-xs">
                              ({role.name === 'Super Admin' ? 'All' : role.permissions_count} permissions)
                            </span>
                          )}
                        </Label>
                      </div>
                    ))}
                  </div>

                  {errors.roles && (
                    <p className="text-sm text-red-500 mt-2">{errors.roles}</p>
                  )}

                  <div className="pt-6">
                    <Button type="submit" disabled={processing} className="w-full">
                      {processing ? 'Creating...' : 'Create User'}
                    </Button>
                  </div>
                </CardContent>
              </Card>
            </div>
          </form>
        </div>
      </Main>
    </AuthenticatedLayout>
  )
}
