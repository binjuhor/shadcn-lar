import { AuthenticatedLayout } from "@/layouts"
import { ChevronLeft } from "lucide-react"
import { Input } from "@/components/ui/input"
import { Label } from "@/components/ui/label"
import { Button } from "@/components/ui/button"
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card"
import { Checkbox } from "@/components/ui/checkbox"
import { Main } from "@/components/layout"
import { useState } from "react"
import { router, usePage } from "@inertiajs/react"
import { PageProps } from "@/types"
import { useToast } from "@/hooks/use-toast"
import { Badge } from "@/components/ui/badge"

interface Role {
  id: number
  name: string
}

interface UserRole {
  id: number
  name: string
}

interface UserData {
  id: number
  name: string
  email: string
  created_at: string
  roles: UserRole[]
  role_names: string[]
}

interface EditUserPageProps extends PageProps {
  user: UserData
  roles: Role[]
  userRoles: string[]
}

export default function EditUser({ user, roles, userRoles }: EditUserPageProps) {
  const [name, setName] = useState(user.name)
  const [email, setEmail] = useState(user.email)
  const [password, setPassword] = useState("")
  const [passwordConfirmation, setPasswordConfirmation] = useState("")
  const [selectedRoles, setSelectedRoles] = useState<string[]>(userRoles || [])
  const [processing, setProcessing] = useState(false)
  const [errors, setErrors] = useState<Record<string, string>>({})
  const { toast } = useToast()
  const { auth } = usePage<PageProps>().props

  const isCurrentUser = user.id === auth.user?.id
  const isSuperAdmin = userRoles?.includes('Super Admin') || false

  const handleRoleToggle = (roleName: string) => {
    if (roleName === 'Super Admin' && isSuperAdmin && isCurrentUser) {
      toast({
        variant: "destructive",
        title: "Cannot remove",
        description: "You cannot remove your own Super Admin role.",
      })
      return
    }

    setSelectedRoles(prev =>
      prev.includes(roleName)
        ? prev.filter(r => r !== roleName)
        : [...prev, roleName]
    )
  }

  const handleSubmit = () => {
    setErrors({})

    if (!name.trim()) {
      setErrors(prev => ({ ...prev, name: "Name is required" }))
      return
    }

    if (!email.trim()) {
      setErrors(prev => ({ ...prev, email: "Email is required" }))
      return
    }

    if (password && password !== passwordConfirmation) {
      setErrors(prev => ({ ...prev, password_confirmation: "Passwords do not match" }))
      return
    }

    setProcessing(true)

    const formData = new FormData()
    formData.append('name', name)
    formData.append('email', email)
    formData.append('_method', 'PUT')
    selectedRoles.forEach((role, index) => {
      formData.append(`roles[${index}]`, role)
    })

    if (password) {
      formData.append('password', password)
      formData.append('password_confirmation', passwordConfirmation)
    }

    router.post(route('dashboard.users.update', user.id), formData, {
      onSuccess: () => {
        setProcessing(false)
        toast({
          title: "User updated!",
          description: "The user has been updated successfully.",
        })
      },
      onError: (errors) => {
        setProcessing(false)
        setErrors(errors as Record<string, string>)
        toast({
          variant: "destructive",
          title: "Error updating user",
          description: Object.values(errors)[0] as string || "Please check your form and try again.",
        })
      }
    })
  }

  const formatDate = (dateString: string) => {
    return new Date(dateString).toLocaleDateString("en-US", {
      year: "numeric",
      month: "long",
      day: "numeric",
      hour: "2-digit",
      minute: "2-digit",
    })
  }

  return (
    <AuthenticatedLayout title="Edit User">
      <Main>
        <div className="grid flex-1 items-start gap-4 md:gap-8">
          <div className="grid flex-1 auto-rows-max gap-4">
            <div className="flex items-center gap-4">
              <Button variant="outline" size="icon" className="h-7 w-7" onClick={() => window.history.back()}>
                <ChevronLeft className="h-4 w-4" />
                <span className="sr-only">Back</span>
              </Button>
              <h1 className="flex-1 shrink-0 whitespace-nowrap text-xl font-semibold tracking-tight sm:grow-0">
                Edit User
              </h1>
              {isCurrentUser && (
                <Badge variant="outline">Your Account</Badge>
              )}
              <div className="hidden items-center gap-2 md:ml-auto md:flex">
                <Button variant="outline" onClick={() => router.get(route('dashboard.users'))}>
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
                    <CardTitle>User Details</CardTitle>
                    <CardDescription>Update the user information</CardDescription>
                  </CardHeader>
                  <CardContent>
                    <div className="grid gap-6">
                      <div className="grid gap-3">
                        <Label htmlFor="name">Name</Label>
                        <Input
                          id="name"
                          type="text"
                          className="w-full"
                          placeholder="Enter full name..."
                          value={name}
                          onChange={(e) => setName(e.target.value)}
                        />
                        {errors.name && (
                          <p className="text-sm text-red-500">{errors.name}</p>
                        )}
                      </div>

                      <div className="grid gap-3">
                        <Label htmlFor="email">Email</Label>
                        <Input
                          id="email"
                          type="email"
                          className="w-full"
                          placeholder="Enter email address..."
                          value={email}
                          onChange={(e) => setEmail(e.target.value)}
                        />
                        {errors.email && (
                          <p className="text-sm text-red-500">{errors.email}</p>
                        )}
                      </div>
                    </div>
                  </CardContent>
                </Card>

                <Card>
                  <CardHeader>
                    <CardTitle>Change Password</CardTitle>
                    <CardDescription>Leave blank to keep the current password</CardDescription>
                  </CardHeader>
                  <CardContent>
                    <div className="grid gap-6">
                      <div className="grid gap-3">
                        <Label htmlFor="password">New Password</Label>
                        <Input
                          id="password"
                          type="password"
                          className="w-full"
                          placeholder="Enter new password..."
                          value={password}
                          onChange={(e) => setPassword(e.target.value)}
                        />
                        {errors.password && (
                          <p className="text-sm text-red-500">{errors.password}</p>
                        )}
                      </div>

                      <div className="grid gap-3">
                        <Label htmlFor="password_confirmation">Confirm New Password</Label>
                        <Input
                          id="password_confirmation"
                          type="password"
                          className="w-full"
                          placeholder="Confirm new password..."
                          value={passwordConfirmation}
                          onChange={(e) => setPasswordConfirmation(e.target.value)}
                        />
                        {errors.password_confirmation && (
                          <p className="text-sm text-red-500">{errors.password_confirmation}</p>
                        )}
                      </div>
                    </div>
                  </CardContent>
                </Card>
              </div>

              <div className="grid auto-rows-max items-start gap-4 lg:gap-8">
                <Card>
                  <CardHeader>
                    <CardTitle>Roles</CardTitle>
                    <CardDescription>Manage user roles</CardDescription>
                  </CardHeader>
                  <CardContent>
                    <div className="space-y-3">
                      {roles.map((role) => (
                        <div key={role.id} className="flex items-center space-x-2">
                          <Checkbox
                            id={`role-${role.id}`}
                            checked={selectedRoles.includes(role.name)}
                            onCheckedChange={() => handleRoleToggle(role.name)}
                            disabled={role.name === 'Super Admin' && isSuperAdmin && isCurrentUser}
                          />
                          <Label
                            htmlFor={`role-${role.id}`}
                            className="text-sm font-normal cursor-pointer"
                          >
                            {role.name}
                          </Label>
                        </div>
                      ))}
                      {roles.length === 0 && (
                        <p className="text-sm text-muted-foreground">No roles available</p>
                      )}
                    </div>
                  </CardContent>
                </Card>

                <Card>
                  <CardHeader>
                    <CardTitle>User Information</CardTitle>
                  </CardHeader>
                  <CardContent className="space-y-4">
                    <div>
                      <p className="text-sm text-muted-foreground">User ID</p>
                      <p className="font-medium">{user.id}</p>
                    </div>
                    <div>
                      <p className="text-sm text-muted-foreground">Created At</p>
                      <p className="font-medium">{formatDate(user.created_at)}</p>
                    </div>
                    <div>
                      <p className="text-sm text-muted-foreground">Current Roles</p>
                      <div className="flex flex-wrap gap-1 mt-1">
                        {userRoles?.map((role) => (
                          <Badge key={role} variant="secondary">
                            {role}
                          </Badge>
                        ))}
                        {(!userRoles || userRoles.length === 0) && (
                          <span className="text-sm text-muted-foreground">None</span>
                        )}
                      </div>
                    </div>
                  </CardContent>
                </Card>
              </div>
            </div>

            <div className="flex items-center justify-center gap-2 md:hidden">
              <Button variant="outline" onClick={() => router.get(route('dashboard.users'))}>
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
