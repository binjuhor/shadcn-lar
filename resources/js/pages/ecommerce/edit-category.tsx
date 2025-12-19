import {AuthenticatedLayout} from "@/layouts"
import { ChevronLeft } from "lucide-react"
import { Input } from "@/components/ui/input"
import { Label } from "@/components/ui/label"
import { Textarea } from "@/components/ui/textarea"
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select"
import {Button} from "@/components/ui/button"
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card"
import { Switch } from "@/components/ui/switch"
import { Main } from "@/components/layout"
import { useState } from "react"
import { ProductCategory, ProductCategoryFormData } from "@/types/ecommerce"
import { router } from "@inertiajs/react"
import { PageProps } from "@/types"
import { useToast } from "@/hooks/use-toast"

interface EditCategoryPageProps extends PageProps {
  category: ProductCategory
  categories?: ProductCategory[]
}

export default function EditCategory({ category, categories = [] }: EditCategoryPageProps) {
  const [data, setData] = useState<ProductCategoryFormData>({
    name: category.name,
    slug: category.slug,
    description: category.description || "",
    color: category.color || "",
    icon: category.icon || "",
    parent_id: category.parent_id,
    is_active: category.is_active,
    meta_title: category.meta_title || "",
    meta_description: category.meta_description || "",
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
    if (data.icon) formData.append('icon', data.icon)
    if (data.parent_id) formData.append('parent_id', data.parent_id.toString())
    formData.append('is_active', data.is_active ? '1' : '0')
    if (data.meta_title) formData.append('meta_title', data.meta_title)
    if (data.meta_description) formData.append('meta_description', data.meta_description)
    formData.append('_method', 'PUT')

    router.post(route('dashboard.ecommerce.product-categories.update', category.id), formData, {
      forceFormData: true,
      onSuccess: () => {
        setProcessing(false)
        toast({
          title: "Category updated!",
          description: "Your category has been updated successfully.",
        })
      },
      onError: (errors) => {
        setProcessing(false)
        console.error('Validation errors:', errors)
        toast({
          variant: "destructive",
          title: "Error updating category",
          description: "Please check your form and try again.",
        })
      }
    })
  }

  const availableParentCategories = categories.filter(c => c.id !== category.id)

  return (
    <>
      <AuthenticatedLayout title={`Edit: ${category.name}`}>
        <Main>
          <div className="grid flex-1 items-start gap-4 md:gap-8">
            <div className="grid flex-1 auto-rows-max gap-4">
              <div className="flex items-center gap-4">
                <Button variant="outline" size="icon" className="h-7 w-7" onClick={() => window.history.back()}>
                  <ChevronLeft className="h-4 w-4" />
                  <span className="sr-only">Back</span>
                </Button>
                <h1 className="flex-1 shrink-0 whitespace-nowrap text-xl font-semibold tracking-tight sm:grow-0">
                  Edit Category
                </h1>
                <div className="hidden items-center gap-2 md:ml-auto md:flex">
                  <Button size="sm" onClick={() => handleSubmit()} disabled={processing}>
                    {processing ? 'Updating...' : 'Update Category'}
                  </Button>
                </div>
              </div>

              <div className="grid gap-4 md:grid-cols-[1fr_350px] lg:gap-8">
                <div className="grid auto-rows-max items-start gap-4 lg:gap-8">
                  <Card>
                    <CardHeader>
                      <CardTitle>Category Details</CardTitle>
                      <CardDescription>Update the basic information for your category</CardDescription>
                    </CardHeader>
                    <CardContent>
                      <div className="grid gap-6">
                        <div className="grid gap-3">
                          <Label htmlFor="name">Name</Label>
                          <Input id="name" type="text" className="w-full" placeholder="Enter category name..."
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
                        <div className="grid gap-6 sm:grid-cols-2">
                          <div className="grid gap-3">
                            <Label htmlFor="color">Color (optional)</Label>
                            <div className="flex gap-2">
                              <Input id="color" type="color" className="w-20 h-10"
                                value={data.color} onChange={(e) => setData(prev => ({ ...prev, color: e.target.value }))} />
                              <Input type="text" placeholder="#000000"
                                value={data.color} onChange={(e) => setData(prev => ({ ...prev, color: e.target.value }))} />
                            </div>
                          </div>
                          <div className="grid gap-3">
                            <Label htmlFor="icon">Icon (optional)</Label>
                            <Input id="icon" type="text" placeholder="icon-name"
                              value={data.icon} onChange={(e) => setData(prev => ({ ...prev, icon: e.target.value }))} />
                          </div>
                        </div>
                      </div>
                    </CardContent>
                  </Card>

                  <Card>
                    <CardHeader>
                      <CardTitle>SEO Settings</CardTitle>
                      <CardDescription>Optimize for search engines</CardDescription>
                    </CardHeader>
                    <CardContent>
                      <div className="grid gap-6">
                        <div className="grid gap-3">
                          <Label htmlFor="meta_title">Meta Title (optional)</Label>
                          <Input id="meta_title" type="text" placeholder="SEO title..."
                            value={data.meta_title} onChange={(e) => setData(prev => ({ ...prev, meta_title: e.target.value }))} />
                        </div>
                        <div className="grid gap-3">
                          <Label htmlFor="meta_description">Meta Description (optional)</Label>
                          <Textarea id="meta_description" placeholder="SEO description..." className="min-h-20"
                            value={data.meta_description} onChange={(e) => setData(prev => ({ ...prev, meta_description: e.target.value }))} />
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

                  <Card>
                    <CardHeader>
                      <CardTitle>Parent Category</CardTitle>
                      <CardDescription>Select a parent to create a subcategory</CardDescription>
                    </CardHeader>
                    <CardContent>
                      <Select value={data.parent_id?.toString() || ""} onValueChange={(value) => setData(prev => ({ ...prev, parent_id: value ? parseInt(value) : undefined }))}>
                        <SelectTrigger><SelectValue placeholder="None (top-level)" /></SelectTrigger>
                        <SelectContent>
                          <SelectItem value="">None (top-level)</SelectItem>
                          {availableParentCategories.map((cat) => (
                            <SelectItem key={cat.id} value={cat.id.toString()}>{cat.name}</SelectItem>
                          ))}
                        </SelectContent>
                      </Select>
                    </CardContent>
                  </Card>
                </div>
              </div>

              <div className="flex items-center justify-center gap-2 md:hidden">
                <Button size="sm" onClick={() => handleSubmit()} disabled={processing}>{processing ? 'Updating...' : 'Update Category'}</Button>
              </div>
            </div>
          </div>
        </Main>
      </AuthenticatedLayout>
    </>
  )
}
