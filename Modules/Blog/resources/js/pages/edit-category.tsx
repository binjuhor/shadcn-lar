import { AuthenticatedLayout } from "@/layouts"
import { ChevronLeft } from "lucide-react"
import { useTranslation } from 'react-i18next'
import { Input } from "@/components/ui/input"
import { Label } from "@/components/ui/label"
import { Textarea } from "@/components/ui/textarea"
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select"
import { Button } from "@/components/ui/button"
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card"
import { Switch } from "@/components/ui/switch"
import { Main } from "@/components/layout"
import { useState } from "react"
import { router } from "@inertiajs/react"
import { PageProps } from "@/types"
import { useToast } from "@/hooks/use-toast"

interface Category {
  id: number
  name: string
  slug: string
  description: string | null
  color: string | null
  icon: string | null
  parent_id: number | null
  is_active: boolean
  meta_title: string | null
  meta_description: string | null
}

interface CategoryFormData {
  name: string
  slug: string
  description: string
  color: string
  icon: string
  parent_id: number | undefined
  is_active: boolean
  meta_title: string
  meta_description: string
}

interface EditCategoryPageProps extends PageProps {
  category: Category
  categories?: Category[]
}

export default function EditCategory({ category, categories = [] }: EditCategoryPageProps) {
  const { t } = useTranslation()
  const [data, setData] = useState<CategoryFormData>({
    name: category.name,
    slug: category.slug || "",
    description: category.description || "",
    color: category.color || "",
    icon: category.icon || "",
    parent_id: category.parent_id || undefined,
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
    formData.append('_method', 'PUT')
    if (data.slug && data.slug.trim()) formData.append('slug', data.slug)
    if (data.description) formData.append('description', data.description)
    if (data.color) formData.append('color', data.color)
    if (data.icon) formData.append('icon', data.icon)
    if (data.parent_id) formData.append('parent_id', data.parent_id.toString())
    formData.append('is_active', data.is_active ? '1' : '0')
    if (data.meta_title) formData.append('meta_title', data.meta_title)
    if (data.meta_description) formData.append('meta_description', data.meta_description)

    router.post(route('dashboard.categories.update', category.id), formData, {
      forceFormData: true,
      onSuccess: () => {
        setProcessing(false)
        toast({
          title: t('page.blog.categories.toast.updated_title'),
          description: t('page.blog.categories.toast.updated'),
        })
      },
      onError: (errors) => {
        setProcessing(false)
        console.error('Validation errors:', errors)
        toast({
          variant: "destructive",
          title: t('page.blog.categories.toast.error_updating'),
          description: Object.values(errors)[0] as string || t('common.messages.error_try_again'),
        })
      }
    })
  }

  const handleDelete = () => {
    if (confirm(t('page.blog.categories.toast.delete_confirm'))) {
      router.delete(route('dashboard.categories.destroy', category.id), {
        onSuccess: () => {
          toast({
            title: t('page.blog.categories.toast.deleted_title'),
            description: t('page.blog.categories.toast.deleted'),
          })
        },
        onError: (errors) => {
          toast({
            variant: "destructive",
            title: t('page.blog.categories.toast.error_deleting'),
            description: Object.values(errors)[0] as string || t('page.blog.categories.toast.cannot_delete'),
          })
        }
      })
    }
  }

  return (
    <AuthenticatedLayout title={t('page.blog.categories.edit.title')}>
      <Main>
        <div className="grid flex-1 items-start gap-4 md:gap-8">
          <div className="grid flex-1 auto-rows-max gap-4">
            <div className="flex items-center gap-4">
              <Button variant="outline" size="icon" className="h-7 w-7" onClick={() => window.history.back()}>
                <ChevronLeft className="h-4 w-4" />
                <span className="sr-only">{t('common.actions.back')}</span>
              </Button>
              <h1 className="flex-1 shrink-0 whitespace-nowrap text-xl font-semibold tracking-tight sm:grow-0">
                {t('page.blog.categories.edit.title')}
              </h1>
              <div className="hidden items-center gap-2 md:ml-auto md:flex">
                <Button variant="outline" onClick={() => router.get(route('dashboard.categories.index'))}>
                  {t('common.actions.cancel')}
                </Button>
                <Button variant="destructive" onClick={handleDelete}>
                  {t('common.actions.delete')}
                </Button>
                <Button size="sm" onClick={() => handleSubmit()} disabled={processing}>
                  {processing ? t('page.blog.categories.edit.saving') : t('page.blog.categories.edit.submit')}
                </Button>
              </div>
            </div>

            <div className="grid gap-4 md:grid-cols-[1fr_350px] lg:gap-8">
              <div className="grid auto-rows-max items-start gap-4 lg:gap-8">
                <Card>
                  <CardHeader>
                    <CardTitle>{t('page.blog.categories.form.details_title')}</CardTitle>
                    <CardDescription>{t('page.blog.categories.edit.description')}</CardDescription>
                  </CardHeader>
                  <CardContent>
                    <div className="grid gap-6">
                      <div className="grid gap-3">
                        <Label htmlFor="name">{t('common.fields.name')}</Label>
                        <Input id="name" type="text" className="w-full" placeholder={t('page.blog.categories.form.name_placeholder')}
                          value={data.name} onChange={(e) => setData(prev => ({ ...prev, name: e.target.value }))} />
                      </div>
                      <div className="grid gap-3">
                        <Label htmlFor="slug">{t('common.fields.slug')}</Label>
                        <Input id="slug" type="text" placeholder={t('page.blog.categories.form.slug_placeholder')}
                          value={data.slug} onChange={(e) => setData(prev => ({ ...prev, slug: e.target.value }))} />
                      </div>
                      <div className="grid gap-3">
                        <Label htmlFor="description">{t('common.fields.description')}</Label>
                        <Textarea id="description" placeholder={t('page.blog.categories.form.description_placeholder')} className="min-h-20"
                          value={data.description} onChange={(e) => setData(prev => ({ ...prev, description: e.target.value }))} />
                      </div>
                      <div className="grid gap-6 sm:grid-cols-2">
                        <div className="grid gap-3">
                          <Label htmlFor="color">{t('page.blog.categories.form.color_optional')}</Label>
                          <div className="flex gap-2">
                            <Input id="color" type="color" className="w-20 h-10"
                              value={data.color || "#000000"} onChange={(e) => setData(prev => ({ ...prev, color: e.target.value }))} />
                            <Input type="text" placeholder="#000000"
                              value={data.color} onChange={(e) => setData(prev => ({ ...prev, color: e.target.value }))} />
                          </div>
                        </div>
                        <div className="grid gap-3">
                          <Label htmlFor="icon">{t('page.blog.categories.form.icon_optional')}</Label>
                          <Input id="icon" type="text" placeholder={t('page.blog.categories.form.icon_placeholder')}
                            value={data.icon} onChange={(e) => setData(prev => ({ ...prev, icon: e.target.value }))} />
                        </div>
                      </div>
                    </div>
                  </CardContent>
                </Card>

                <Card>
                  <CardHeader>
                    <CardTitle>{t('page.blog.categories.form.seo_title')}</CardTitle>
                    <CardDescription>{t('page.blog.categories.form.seo_description')}</CardDescription>
                  </CardHeader>
                  <CardContent>
                    <div className="grid gap-6">
                      <div className="grid gap-3">
                        <Label htmlFor="meta_title">{t('page.blog.categories.form.meta_title_optional')}</Label>
                        <Input id="meta_title" type="text" placeholder={t('page.blog.categories.form.meta_title_placeholder')}
                          value={data.meta_title} onChange={(e) => setData(prev => ({ ...prev, meta_title: e.target.value }))} />
                      </div>
                      <div className="grid gap-3">
                        <Label htmlFor="meta_description">{t('page.blog.categories.form.meta_description_optional')}</Label>
                        <Textarea id="meta_description" placeholder={t('page.blog.categories.form.meta_description_placeholder')} className="min-h-20"
                          value={data.meta_description} onChange={(e) => setData(prev => ({ ...prev, meta_description: e.target.value }))} />
                      </div>
                    </div>
                  </CardContent>
                </Card>
              </div>

              <div className="grid auto-rows-max items-start gap-4 lg:gap-8">
                <Card>
                  <CardHeader>
                    <CardTitle>{t('page.blog.categories.form.settings_title')}</CardTitle>
                  </CardHeader>
                  <CardContent className="space-y-4">
                    <div className="flex items-center space-x-2">
                      <Switch id="is_active" checked={data.is_active}
                        onCheckedChange={(checked) => setData(prev => ({ ...prev, is_active: checked }))} />
                      <Label htmlFor="is_active" className="text-sm font-medium">{t('common.fields.active')}</Label>
                    </div>
                  </CardContent>
                </Card>

                <Card>
                  <CardHeader>
                    <CardTitle>{t('page.blog.categories.form.parent_category')}</CardTitle>
                    <CardDescription>{t('page.blog.categories.form.parent_edit_description')}</CardDescription>
                  </CardHeader>
                  <CardContent>
                    <Select value={data.parent_id?.toString() || ""} onValueChange={(value) => setData(prev => ({ ...prev, parent_id: value ? parseInt(value) : undefined }))}>
                      <SelectTrigger><SelectValue placeholder={t('page.blog.categories.form.none_top_level')} /></SelectTrigger>
                      <SelectContent>
                        <SelectItem value="">{t('page.blog.categories.form.none_top_level')}</SelectItem>
                        {categories.map((cat) => (
                          <SelectItem key={cat.id} value={cat.id.toString()}>{cat.name}</SelectItem>
                        ))}
                      </SelectContent>
                    </Select>
                  </CardContent>
                </Card>
              </div>
            </div>

            <div className="flex items-center justify-center gap-2 md:hidden">
              <Button variant="outline" onClick={() => router.get(route('dashboard.categories.index'))}>
                {t('common.actions.cancel')}
              </Button>
              <Button variant="destructive" onClick={handleDelete}>
                {t('common.actions.delete')}
              </Button>
              <Button size="sm" onClick={() => handleSubmit()} disabled={processing}>{processing ? t('page.blog.categories.edit.saving') : t('page.blog.categories.edit.submit')}</Button>
            </div>
          </div>
        </div>
      </Main>
    </AuthenticatedLayout>
  )
}
