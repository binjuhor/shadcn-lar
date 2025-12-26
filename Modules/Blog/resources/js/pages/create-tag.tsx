import { AuthenticatedLayout } from "@/layouts"
import { ChevronLeft } from "lucide-react"
import { Input } from "@/components/ui/input"
import { Label } from "@/components/ui/label"
import { Textarea } from "@/components/ui/textarea"
import { Button } from "@/components/ui/button"
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card"
import { Switch } from "@/components/ui/switch"
import { Main } from "@/components/layout"
import { useState } from "react"
import { router } from "@inertiajs/react"
import { useToast } from "@/hooks/use-toast"

interface TagFormData {
  name: string
  slug: string
  description: string
  color: string
  is_active: boolean
}

export default function CreateTag() {
  const [data, setData] = useState<TagFormData>({
    name: "",
    slug: "",
    description: "",
    color: "",
    is_active: true,
  })

  const [processing, setProcessing] = useState(false)
  const { toast } = useToast()

  const handleSubmit = () => {
    setProcessing(true)
    const formData = new FormData()

    formData.append('name', data.name)
    if (data.slug && data.slug.trim()) formData.append('slug', data.slug)
    if (data.description) formData.append('description', data.description)
    if (data.color) formData.append('color', data.color)
    formData.append('is_active', data.is_active ? '1' : '0')

    router.post(route('dashboard.tags.store'), formData, {
      forceFormData: true,
      onSuccess: () => {
        setProcessing(false)
        toast({
          title: "Tag created!",
          description: "Your tag has been created successfully.",
        })
      },
      onError: (errors) => {
        setProcessing(false)
        console.error('Validation errors:', errors)
        toast({
          variant: "destructive",
          title: "Error creating tag",
          description: Object.values(errors)[0] as string || "Please check your form and try again.",
        })
      }
    })
  }

  return (
    <AuthenticatedLayout title="Create Tag">
      <Main>
        <div className="grid flex-1 items-start gap-4 md:gap-8">
          <div className="grid flex-1 auto-rows-max gap-4">
            <div className="flex items-center gap-4">
              <Button variant="outline" size="icon" className="h-7 w-7" onClick={() => window.history.back()}>
                <ChevronLeft className="h-4 w-4" />
                <span className="sr-only">Back</span>
              </Button>
              <h1 className="flex-1 shrink-0 whitespace-nowrap text-xl font-semibold tracking-tight sm:grow-0">
                Create Tag
              </h1>
              <div className="hidden items-center gap-2 md:ml-auto md:flex">
                <Button variant="outline" onClick={() => router.get(route('dashboard.tags.index'))}>
                  Cancel
                </Button>
                <Button size="sm" onClick={() => handleSubmit()} disabled={processing}>
                  {processing ? 'Creating...' : 'Create Tag'}
                </Button>
              </div>
            </div>

            <div className="grid gap-4 md:grid-cols-[1fr_350px] lg:gap-8">
              <div className="grid auto-rows-max items-start gap-4 lg:gap-8">
                <Card>
                  <CardHeader>
                    <CardTitle>Tag Details</CardTitle>
                    <CardDescription>Fill in the basic information for your tag</CardDescription>
                  </CardHeader>
                  <CardContent>
                    <div className="grid gap-6">
                      <div className="grid gap-3">
                        <Label htmlFor="name">Name</Label>
                        <Input id="name" type="text" className="w-full" placeholder="Enter tag name..."
                          value={data.name} onChange={(e) => setData(prev => ({ ...prev, name: e.target.value }))} />
                      </div>
                      <div className="grid gap-3">
                        <Label htmlFor="slug">Slug (optional)</Label>
                        <Input id="slug" type="text" placeholder="auto-generated-from-name"
                          value={data.slug} onChange={(e) => setData(prev => ({ ...prev, slug: e.target.value }))} />
                        <p className="text-xs text-muted-foreground">Leave empty to auto-generate from name</p>
                      </div>
                      <div className="grid gap-3">
                        <Label htmlFor="description">Description</Label>
                        <Textarea id="description" placeholder="Enter a brief description..." className="min-h-20"
                          value={data.description} onChange={(e) => setData(prev => ({ ...prev, description: e.target.value }))} />
                      </div>
                      <div className="grid gap-3">
                        <Label htmlFor="color">Color (optional)</Label>
                        <div className="flex gap-2">
                          <Input id="color" type="color" className="w-20 h-10"
                            value={data.color || "#000000"} onChange={(e) => setData(prev => ({ ...prev, color: e.target.value }))} />
                          <Input type="text" placeholder="#000000"
                            value={data.color} onChange={(e) => setData(prev => ({ ...prev, color: e.target.value }))} />
                        </div>
                      </div>
                    </div>
                  </CardContent>
                </Card>
              </div>

              <div className="grid auto-rows-max items-start gap-4 lg:gap-8">
                <Card>
                  <CardHeader>
                    <CardTitle>Settings</CardTitle>
                  </CardHeader>
                  <CardContent className="space-y-4">
                    <div className="flex items-center space-x-2">
                      <Switch id="is_active" checked={data.is_active}
                        onCheckedChange={(checked) => setData(prev => ({ ...prev, is_active: checked }))} />
                      <Label htmlFor="is_active" className="text-sm font-medium">Active</Label>
                    </div>
                  </CardContent>
                </Card>
              </div>
            </div>

            <div className="flex items-center justify-center gap-2 md:hidden">
              <Button variant="outline" onClick={() => router.get(route('dashboard.tags.index'))}>
                Cancel
              </Button>
              <Button size="sm" onClick={() => handleSubmit()} disabled={processing}>{processing ? 'Creating...' : 'Create Tag'}</Button>
            </div>
          </div>
        </div>
      </Main>
    </AuthenticatedLayout>
  )
}
