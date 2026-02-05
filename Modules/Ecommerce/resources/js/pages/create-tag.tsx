import {AuthenticatedLayout} from "@/layouts"
import { ChevronLeft } from "lucide-react"
import { Input } from "@/components/ui/input"
import { Label } from "@/components/ui/label"
import { Textarea } from "@/components/ui/textarea"
import {Button} from "@/components/ui/button"
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card"
import { Main } from "@/components/layout"
import { useState } from "react"
import { ProductTagFormData } from "@/types/ecommerce"
import { router } from "@inertiajs/react"
import { PageProps } from "@/types"
import { useToast } from "@/hooks/use-toast"
import { useTranslation } from "react-i18next"

export default function CreateTag({}: PageProps) {
  const { t } = useTranslation()
  const [data, setData] = useState<ProductTagFormData>({
    name: "",
    slug: "",
    description: "",
    color: "",
    is_active: true,
  })

  const [processing, setProcessing] = useState(false)
  const { toast} = useToast()

  const handleSubmit = () => {
    setProcessing(true)
    const formData = new FormData()

    formData.append('name', data.name)
    if (data.slug && data.slug.trim()) formData.append('slug', data.slug)
    if (data.description) formData.append('description', data.description)
    if (data.color) formData.append('color', data.color)
    formData.append('is_active', data.is_active ? '1' : '0')

    router.post(route('dashboard.ecommerce.product-tags.store'), formData, {
      forceFormData: true,
      onSuccess: () => {
        setProcessing(false)
        toast({
          title: t('page.ecommerce.tags.toast.create_success'),
          description: t('page.ecommerce.tags.toast.create_success_description'),
        })
        setTimeout(() => router.get(route('dashboard.ecommerce.product-tags.index')), 1000)
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

  return (
    <>
      <AuthenticatedLayout title={t('page.ecommerce.tags.create.title')}>
        <Main>
          <div className="grid flex-1 items-start gap-4 md:gap-8">
            <div className="grid flex-1 auto-rows-max gap-4">
              <div className="flex items-center gap-4">
                <Button variant="outline" size="icon" className="h-7 w-7" onClick={() => window.history.back()}>
                  <ChevronLeft className="h-4 w-4" />
                  <span className="sr-only">{t('common.actions.back')}</span>
                </Button>
                <h1 className="flex-1 shrink-0 whitespace-nowrap text-xl font-semibold tracking-tight sm:grow-0">
                  {t('page.ecommerce.tags.create.title')}
                </h1>
                <div className="hidden items-center gap-2 md:ml-auto md:flex">
                  <Button size="sm" onClick={() => handleSubmit()} disabled={processing}>
                    {processing ? t('page.ecommerce.tags.create.creating') : t('page.ecommerce.tags.create.button')}
                  </Button>
                </div>
              </div>

              <div className="grid gap-4 md:grid-cols-[1fr_350px] lg:gap-8">
                <div className="grid auto-rows-max items-start gap-4 lg:gap-8">
                  <Card>
                    <CardHeader>
                      <CardTitle>{t('page.ecommerce.tags.form.details_title')}</CardTitle>
                      <CardDescription>{t('page.ecommerce.tags.form.details_description')}</CardDescription>
                    </CardHeader>
                    <CardContent>
                      <div className="grid gap-6">
                        <div className="grid gap-3">
                          <Label htmlFor="name">{t('common.fields.name')}</Label>
                          <Input id="name" type="text" className="w-full" placeholder={t('page.ecommerce.tags.form.name_placeholder')}
                            value={data.name} onChange={(e) => setData(prev => ({ ...prev, name: e.target.value }))} />
                        </div>
                        <div className="grid gap-3">
                          <Label htmlFor="slug">{t('page.ecommerce.tags.form.slug_label')}</Label>
                          <Input id="slug" type="text" placeholder={t('page.ecommerce.tags.form.slug_placeholder')}
                            value={data.slug} onChange={(e) => setData(prev => ({ ...prev, slug: e.target.value }))} />
                          <p className="text-xs text-muted-foreground">{t('page.ecommerce.tags.form.slug_hint')}</p>
                        </div>
                        <div className="grid gap-3">
                          <Label htmlFor="description">{t('common.fields.description')}</Label>
                          <Textarea id="description" placeholder={t('page.ecommerce.tags.form.description_placeholder')} className="min-h-20"
                            value={data.description} onChange={(e) => setData(prev => ({ ...prev, description: e.target.value }))} />
                        </div>
                      </div>
                    </CardContent>
                  </Card>
                </div>

                <div className="grid auto-rows-max items-start gap-4 lg:gap-8">
                  <Card>
                    <CardHeader>
                      <CardTitle>{t('page.ecommerce.tags.form.color_title')}</CardTitle>
                      <CardDescription>{t('page.ecommerce.tags.form.color_description')}</CardDescription>
                    </CardHeader>
                    <CardContent>
                      <div className="grid gap-3">
                        <Label htmlFor="color">{t('page.ecommerce.tags.form.color_label')}</Label>
                        <div className="flex gap-2">
                          <Input id="color" type="color" className="w-20 h-10"
                            value={data.color} onChange={(e) => setData(prev => ({ ...prev, color: e.target.value }))} />
                          <Input type="text" placeholder="#000000"
                            value={data.color} onChange={(e) => setData(prev => ({ ...prev, color: e.target.value }))} />
                        </div>
                      </div>
                    </CardContent>
                  </Card>
                </div>
              </div>

              <div className="flex items-center justify-center gap-2 md:hidden">
                <Button size="sm" onClick={() => handleSubmit()} disabled={processing}>{processing ? t('page.ecommerce.tags.create.creating') : t('page.ecommerce.tags.create.button')}</Button>
              </div>
            </div>
          </div>
        </Main>
      </AuthenticatedLayout>
    </>
  )
}
