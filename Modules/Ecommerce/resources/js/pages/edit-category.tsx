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
import { useTranslation } from "react-i18next"

interface EditCategoryPageProps extends PageProps {
  category: ProductCategory
  categories?: ProductCategory[]
}

export default function EditCategory({ category, categories = [] }: EditCategoryPageProps) {
  const { t } = useTranslation()
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
          title: t('page.ecommerce.categories.toast.update_success'),
          description: t('page.ecommerce.categories.toast.update_success_description'),
        })
      },
      onError: (errors) => {
        setProcessing(false)
        console.error('Validation errors:', errors)
        toast({
          variant: "destructive",
          title: t('common.messages.error'),
          description: t('common.messages.error_try_again'),
        })
      }
    })
  }

  const availableParentCategories = categories.filter(c => c.id !== category.id)

  return (
    <>
      <AuthenticatedLayout title={t('page.ecommerce.categories.edit.title', { name: category.name })}>
        <Main>
          <div className="grid flex-1 items-start gap-4 md:gap-8">
            <div className="grid flex-1 auto-rows-max gap-4">
              <div className="flex items-center gap-4">
                <Button variant="outline" size="icon" className="h-7 w-7" onClick={() => window.history.back()}>
                  <ChevronLeft className="h-4 w-4" />
                  <span className="sr-only">{t('common.actions.back')}</span>
                </Button>
                <h1 className="flex-1 shrink-0 whitespace-nowrap text-xl font-semibold tracking-tight sm:grow-0">
                  {t('page.ecommerce.categories.edit.heading')}
                </h1>
                <div className="hidden items-center gap-2 md:ml-auto md:flex">
                  <Button size="sm" onClick={() => handleSubmit()} disabled={processing}>
                    {processing ? t('page.ecommerce.categories.edit.updating') : t('page.ecommerce.categories.edit.button')}
                  </Button>
                </div>
              </div>

              <div className="grid gap-4 md:grid-cols-[1fr_350px] lg:gap-8">
                <div className="grid auto-rows-max items-start gap-4 lg:gap-8">
                  <Card>
                    <CardHeader>
                      <CardTitle>{t('page.ecommerce.categories.form.details_title')}</CardTitle>
                      <CardDescription>{t('page.ecommerce.categories.form.edit_details_description')}</CardDescription>
                    </CardHeader>
                    <CardContent>
                      <div className="grid gap-6">
                        <div className="grid gap-3">
                          <Label htmlFor="name">{t('common.fields.name')}</Label>
                          <Input id="name" type="text" className="w-full" placeholder={t('page.ecommerce.categories.form.name_placeholder')}
                            value={data.name} onChange={(e) => setData(prev => ({ ...prev, name: e.target.value }))} />
                        </div>
                        <div className="grid gap-3">
                          <Label htmlFor="slug">{t('page.ecommerce.categories.form.slug_label')}</Label>
                          <Input id="slug" type="text" placeholder={t('page.ecommerce.categories.form.slug_placeholder')}
                            value={data.slug} onChange={(e) => setData(prev => ({ ...prev, slug: e.target.value }))} />
                          <p className="text-xs text-muted-foreground">{t('page.ecommerce.categories.form.slug_hint')}</p>
                        </div>
                        <div className="grid gap-3">
                          <Label htmlFor="description">{t('common.fields.description')}</Label>
                          <Textarea id="description" placeholder={t('page.ecommerce.categories.form.description_placeholder')} className="min-h-20"
                            value={data.description} onChange={(e) => setData(prev => ({ ...prev, description: e.target.value }))} />
                        </div>
                        <div className="grid gap-6 sm:grid-cols-2">
                          <div className="grid gap-3">
                            <Label htmlFor="color">{t('page.ecommerce.categories.form.color_label')}</Label>
                            <div className="flex gap-2">
                              <Input id="color" type="color" className="w-20 h-10"
                                value={data.color} onChange={(e) => setData(prev => ({ ...prev, color: e.target.value }))} />
                              <Input type="text" placeholder="#000000"
                                value={data.color} onChange={(e) => setData(prev => ({ ...prev, color: e.target.value }))} />
                            </div>
                          </div>
                          <div className="grid gap-3">
                            <Label htmlFor="icon">{t('page.ecommerce.categories.form.icon_label')}</Label>
                            <Input id="icon" type="text" placeholder={t('page.ecommerce.categories.form.icon_placeholder')}
                              value={data.icon} onChange={(e) => setData(prev => ({ ...prev, icon: e.target.value }))} />
                          </div>
                        </div>
                      </div>
                    </CardContent>
                  </Card>

                  <Card>
                    <CardHeader>
                      <CardTitle>{t('page.ecommerce.categories.form.seo_title')}</CardTitle>
                      <CardDescription>{t('page.ecommerce.categories.form.seo_description')}</CardDescription>
                    </CardHeader>
                    <CardContent>
                      <div className="grid gap-6">
                        <div className="grid gap-3">
                          <Label htmlFor="meta_title">{t('page.ecommerce.categories.form.meta_title_label')}</Label>
                          <Input id="meta_title" type="text" placeholder={t('page.ecommerce.categories.form.meta_title_placeholder')}
                            value={data.meta_title} onChange={(e) => setData(prev => ({ ...prev, meta_title: e.target.value }))} />
                        </div>
                        <div className="grid gap-3">
                          <Label htmlFor="meta_description">{t('page.ecommerce.categories.form.meta_description_label')}</Label>
                          <Textarea id="meta_description" placeholder={t('page.ecommerce.categories.form.meta_description_placeholder')} className="min-h-20"
                            value={data.meta_description} onChange={(e) => setData(prev => ({ ...prev, meta_description: e.target.value }))} />
                        </div>
                      </div>
                    </CardContent>
                  </Card>
                </div>

                <div className="grid auto-rows-max items-start gap-4 lg:gap-8">
                  <Card>
                    <CardHeader>
                      <CardTitle>{t('page.ecommerce.categories.form.settings_title')}</CardTitle>
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
                      <CardTitle>{t('page.ecommerce.categories.form.parent_title')}</CardTitle>
                      <CardDescription>{t('page.ecommerce.categories.form.parent_description')}</CardDescription>
                    </CardHeader>
                    <CardContent>
                      <Select value={data.parent_id?.toString() || ""} onValueChange={(value) => setData(prev => ({ ...prev, parent_id: value ? parseInt(value) : undefined }))}>
                        <SelectTrigger><SelectValue placeholder={t('page.ecommerce.categories.form.parent_none')} /></SelectTrigger>
                        <SelectContent>
                          <SelectItem value="">{t('page.ecommerce.categories.form.parent_none')}</SelectItem>
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
                <Button size="sm" onClick={() => handleSubmit()} disabled={processing}>{processing ? t('page.ecommerce.categories.edit.updating') : t('page.ecommerce.categories.edit.button')}</Button>
              </div>
            </div>
          </div>
        </Main>
      </AuthenticatedLayout>
    </>
  )
}
