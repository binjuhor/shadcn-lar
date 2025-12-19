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

interface Role {
  id: number
  name: string
}

interface CreateUserPageProps extends PageProps {
  roles: Role[]
}

export default function CreateUser({ roles }: CreateUserPageProps) {
  const [name, setName] = useState("")
  const [email, setEmail] = useState("")
  const [password, setPassword] = useState("")
  const [passwordConfirmation, setPasswordConfirmation] = useState("")
  const [selectedRoles, setSelectedRoles] = useState<string[]>([])
  const [processing, setProcessing] = useState(false)
  const [errors, setErrors] = useState<Record<string, string>>({})
  const { toast } = useToast()

  const handleRoleToggle = (roleName: string) => {
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

    if (!password) {
      setErrors(prev => ({ ...prev, password: "Password is required" }))
      return
    }

    if (password !== passwordConfirmation) {
      setErrors(prev => ({ ...prev, password_confirmation: "Passwords do not match" }))
      return
    }

    setProcessing(true)
    router.post(route('dashboard.users.store'), {
      name: name,
      email: email,
      password: password,
      password_confirmation: passwordConfirmation,
      roles: selectedRoles,
    }, {
      onSuccess: () => {
        setProcessing(false)
        toast({
          title: "User created!",
          description: "The user has been created successfully.",
        })
      },
      onError: (errors) => {
        setProcessing(false)
        setErrors(errors as Record<string, string>)
        toast({
          variant: "destructive",
          title: "Error creating user",
          description: Object.values(errors)[0] as string || "Please check your form and try again.",
        })
      }
    })
  }

  return (
    <AuthenticatedLayout title="Create User">
      <Main>
        <div className="grid flex-1 items-start gap-4 md:gap-8">
          <div className="grid flex-1 auto-rows-max gap-4">
            <div className="flex items-center gap-4">
              <Button variant="outline" size="icon" className="h-7 w-7" onClick={() => window.history.back()}>
                <ChevronLeft className="h-4 w-4" />
                <span className="sr-only">Back</span>
              </Button>
              <h1 className="flex-1 shrink-0 whitespace-nowrap text-xl font-semibold tracking-tight sm:grow-0">
                Create User
              </h1>
              <div className="hidden items-center gap-2 md:ml-auto md:flex">
                <Button variant="outline" onClick={() => router.get(route('dashboard.users.index'))}>
                  Cancel
                </Button>
                <Button size="sm" onClick={handleSubmit} disabled={processing}>
                  {processing ? 'Creating...' : 'Create User'}
                </Button>
              </div>
            </div>

            <div className="grid gap-4 md:grid-cols-[1fr_350px] lg:gap-8">
              <div className="grid auto-rows-max items-start gap-4 lg:gap-8">
                <Card>
                  <CardHeader>
                    <CardTitle>User Details</CardTitle>
                    <CardDescription>Enter the user information</CardDescription>
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

                      <div className="grid gap-3">
                        <Label htmlFor="password">Password</Label>
                        <Input
                          id="password"
                          type="password"
                          className="w-full"
                          placeholder="Enter password..."
                          value={password}
                          onChange={(e) => setPassword(e.target.value)}
                        />
                        {errors.password && (
                          <p className="text-sm text-red-500">{errors.password}</p>
                        )}
                      </div>

                      <div className="grid gap-3">
                        <Label htmlFor="password_confirmation">Confirm Password</Label>
                        <Input
                          id="password_confirmation"
                          type="password"
                          className="w-full"
                          placeholder="Confirm password..."
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
                    <CardDescription>Assign roles to this user</CardDescription>
                  </CardHeader>
                  <CardContent>
                    <div className="space-y-3">
                      {roles.map((role) => (
                        <div key={role.id} className="flex items-center space-x-2">
                          <Checkbox
                            id={`role-${role.id}`}
                            checked={selectedRoles.includes(role.name)}
                            onCheckedChange={() => handleRoleToggle(role.name)}
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
                    <CardTitle>Summary</CardTitle>
                  </CardHeader>
                  <CardContent className="space-y-4">
                    <div>
                      <p className="text-sm text-muted-foreground">Name</p>
                      <p className="font-medium">{name || '-'}</p>
                    </div>
                    <div>
                      <p className="text-sm text-muted-foreground">Email</p>
                      <p className="font-medium">{email || '-'}</p>
                    </div>
                    <div>
                      <p className="text-sm text-muted-foreground">Assigned Roles</p>
                      <p className="font-medium">
                        {selectedRoles.length > 0 ? selectedRoles.join(', ') : 'None'}
                      </p>
                    </div>
                  </CardContent>
                </Card>
              </div>
            </div>

            <div className="flex items-center justify-center gap-2 md:hidden">
              <Button variant="outline" onClick={() => router.get(route('dashboard.users.index'))}>
                Cancel
              </Button>
              <Button size="sm" onClick={handleSubmit} disabled={processing}>
                {processing ? 'Creating...' : 'Create User'}
              </Button>
            </div>
          </div>
        </div>
      </Main>
    </AuthenticatedLayout>
  )
}
