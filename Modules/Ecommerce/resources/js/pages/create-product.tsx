import {AuthenticatedLayout} from "@/layouts"
import { ChevronLeft, X } from "lucide-react"
import { Input } from "@/components/ui/input"
import { Label } from "@/components/ui/label"
import { Textarea } from "@/components/ui/textarea"
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select"
import {Badge} from "@/components/ui/badge"
import {Button} from "@/components/ui/button"
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card"
import { Switch } from "@/components/ui/switch"
import { Main } from "@/components/layout"
import { Content } from "@tiptap/react"
import { MinimalTiptapEditor } from "@/components/ui/minimal-tiptap"
import { useState } from "react"
import { ProductCategory, ProductTag, ProductFormData } from "@/types/ecommerce"
import { router } from "@inertiajs/react"
import { PageProps } from "@/types"
import { useToast } from "@/hooks/use-toast"
import { MediaUploader } from "@/components/MediaLibrary"
import { useTranslation } from "react-i18next"

interface CreateProductPageProps extends PageProps {
  categories?: ProductCategory[]
  tags?: ProductTag[]
}

export default function CreateProduct({ categories = [], tags = [] }: CreateProductPageProps) {
  const { t } = useTranslation()
  const [data, setData] = useState<ProductFormData>({
    name: "",
    content: "",
    description: "",
    sku: "",
    price: 0,
    sale_price: undefined,
    cost: undefined,
    stock_quantity: 0,
    low_stock_threshold: undefined,
    track_inventory: true,
    status: "draft",
    is_featured: false,
    category_id: undefined,
    tag_ids: [],
    meta_title: "",
    meta_description: "",
  })

  const [content, setContent] = useState<Content>("")
  const [featuredImageFiles, setFeaturedImageFiles] = useState<File[]>([])
  const [processing, setProcessing] = useState(false)
  const { toast} = useToast()

  const handleFeaturedImageChange = (files: File[]) => {
    setFeaturedImageFiles(files)
  }

  const handleTagToggle = (tagId: number) => {
    const updatedTags = data.tag_ids.includes(tagId)
      ? data.tag_ids.filter(id => id !== tagId)
      : [...data.tag_ids, tagId]
    setData(prev => ({ ...prev, tag_ids: updatedTags }))
  }

  const handleContentChange = (value: Content) => {
    setContent(value)
    setData(prev => ({ ...prev, content: value as string }))
  }

  const handleSubmit = (status?: 'draft' | 'active') => {
    setProcessing(true)
    const formData = new FormData()
    
    formData.append('name', data.name)
    if (data.content) formData.append('content', data.content)
    if (data.description) formData.append('description', data.description)
    if (data.sku && data.sku.trim()) formData.append('sku', data.sku)
    formData.append('price', data.price.toString())
    if (data.sale_price) formData.append('sale_price', data.sale_price.toString())
    if (data.cost) formData.append('cost', data.cost.toString())
    formData.append('stock_quantity', data.stock_quantity.toString())
    if (data.low_stock_threshold) formData.append('low_stock_threshold', data.low_stock_threshold.toString())
    formData.append('track_inventory', data.track_inventory ? '1' : '0')
    formData.append('status', status || data.status)
    formData.append('is_featured', data.is_featured ? '1' : '0')
    
    if (data.category_id) formData.append('category_id', data.category_id.toString())
    data.tag_ids.forEach((tagId, index) => formData.append(`tag_ids[${index}]`, tagId.toString()))
    if (data.meta_title) formData.append('meta_title', data.meta_title)
    if (data.meta_description) formData.append('meta_description', data.meta_description)
    if (featuredImageFiles.length > 0) formData.append('featured_image', featuredImageFiles[0])

    router.post(route('dashboard.ecommerce.products.store'), formData, {
      forceFormData: true,
      onSuccess: () => {
        setProcessing(false)
        toast({
          title: status === 'active' ? t('page.ecommerce.products.toast.create_success') : t('page.ecommerce.products.toast.draft_saved'),
          description: status === 'active' ? t('page.ecommerce.products.toast.create_success_description') : t('page.ecommerce.products.toast.draft_saved_description'),
        })
        setTimeout(() => router.get(route('dashboard.ecommerce.products.index')), 1000)
      },
      onError: (errors) => {
        setProcessing(false)
        console.error('Validation errors:', errors)
        toast({
          variant: "destructive",
          title: t('page.ecommerce.products.toast.create_error'),
          description: t('common.messages.error_try_again'),
        })
      }
    })
  }

  const selectedTagObjects = tags.filter(tag => data.tag_ids.includes(tag.id))

  return (
    <>
      <AuthenticatedLayout title={t('page.ecommerce.products.create.title')}>
        <Main>
          <div className="grid flex-1 items-start gap-4 md:gap-8">
            <div className="grid flex-1 auto-rows-max gap-4">
              <div className="flex items-center gap-4">
                <Button variant="outline" size="icon" className="h-7 w-7" onClick={() => window.history.back()}>
                  <ChevronLeft className="h-4 w-4" />
                  <span className="sr-only">{t('common.actions.back')}</span>
                </Button>
                <h1 className="flex-1 shrink-0 whitespace-nowrap text-xl font-semibold tracking-tight sm:grow-0">
                  {t('page.ecommerce.products.create.title')}
                </h1>
                <div className="hidden items-center gap-2 md:ml-auto md:flex">
                  <Button variant="outline" size="sm" onClick={() => handleSubmit('draft')} disabled={processing}>
                    {t('page.ecommerce.products.create.save_draft')}
                  </Button>
                  <Button size="sm" onClick={() => handleSubmit()} disabled={processing}>
                    {processing ? t('page.ecommerce.products.create.creating') : t('page.ecommerce.products.create.submit')}
                  </Button>
                </div>
              </div>

              <div className="grid gap-4 md:grid-cols-[1fr_350px] lg:gap-8">
                <div className="grid auto-rows-max items-start gap-4 lg:gap-8">
                  <Card>
                    <CardHeader>
                      <CardTitle>{t('page.ecommerce.products.form.details_title')}</CardTitle>
                      <CardDescription>{t('page.ecommerce.products.form.details_description')}</CardDescription>
                    </CardHeader>
                    <CardContent>
                      <div className="grid gap-6">
                        <div className="grid gap-3">
                          <Label htmlFor="name">{t('page.ecommerce.products.form.name')}</Label>
                          <Input id="name" type="text" className="w-full" placeholder={t('page.ecommerce.products.form.name_placeholder')}
                            value={data.name} onChange={(e) => setData(prev => ({ ...prev, name: e.target.value }))} />
                        </div>
                        <div className="grid gap-3">
                          <Label htmlFor="description">{t('page.ecommerce.products.form.description')}</Label>
                          <Textarea id="description" placeholder={t('page.ecommerce.products.form.description_placeholder')} className="min-h-20"
                            value={data.description} onChange={(e) => setData(prev => ({ ...prev, description: e.target.value }))} />
                        </div>
                        <div className="grid gap-3">
                          <Label htmlFor="content">{t('page.ecommerce.products.form.content')}</Label>
                          <MinimalTiptapEditor value={content} onChange={handleContentChange} className="w-full"
                            editorContentClassName="p-5" output="html" placeholder={t('page.ecommerce.products.form.content_placeholder')}
                            autofocus={false} editable={true} editorClassName="focus:outline-none min-h-[400px]" />
                        </div>
                      </div>
                    </CardContent>
                  </Card>

                  <Card>
                    <CardHeader>
                      <CardTitle>{t('page.ecommerce.products.form.pricing_inventory_title')}</CardTitle>
                    </CardHeader>
                    <CardContent>
                      <div className="grid gap-6 sm:grid-cols-2">
                        <div className="grid gap-3">
                          <Label htmlFor="price">{t('page.ecommerce.products.form.price')}</Label>
                          <Input id="price" type="number" step="0.01" placeholder="0.00"
                            value={data.price} onChange={(e) => setData(prev => ({ ...prev, price: parseFloat(e.target.value) }))} />
                        </div>
                        <div className="grid gap-3">
                          <Label htmlFor="sale_price">{t('page.ecommerce.products.form.sale_price')}</Label>
                          <Input id="sale_price" type="number" step="0.01" placeholder="0.00"
                            value={data.sale_price || ''} onChange={(e) => setData(prev => ({ ...prev, sale_price: e.target.value ? parseFloat(e.target.value) : undefined }))} />
                        </div>
                        <div className="grid gap-3">
                          <Label htmlFor="cost">{t('page.ecommerce.products.form.cost')}</Label>
                          <Input id="cost" type="number" step="0.01" placeholder="0.00"
                            value={data.cost || ''} onChange={(e) => setData(prev => ({ ...prev, cost: e.target.value ? parseFloat(e.target.value) : undefined }))} />
                        </div>
                        <div className="grid gap-3">
                          <Label htmlFor="sku">{t('page.ecommerce.products.form.sku')}</Label>
                          <Input id="sku" type="text" placeholder="PROD-001"
                            value={data.sku} onChange={(e) => setData(prev => ({ ...prev, sku: e.target.value }))} />
                        </div>
                        <div className="grid gap-3">
                          <Label htmlFor="stock_quantity">{t('page.ecommerce.products.form.stock_quantity')}</Label>
                          <Input id="stock_quantity" type="number" placeholder="0"
                            value={data.stock_quantity} onChange={(e) => setData(prev => ({ ...prev, stock_quantity: parseInt(e.target.value) }))} />
                        </div>
                        <div className="grid gap-3">
                          <Label htmlFor="low_stock_threshold">{t('page.ecommerce.products.form.low_stock_threshold')}</Label>
                          <Input id="low_stock_threshold" type="number" placeholder="10"
                            value={data.low_stock_threshold || ''} onChange={(e) => setData(prev => ({ ...prev, low_stock_threshold: e.target.value ? parseInt(e.target.value) : undefined }))} />
                        </div>
                      </div>
                      <div className="flex items-center space-x-2 mt-4">
                        <Switch id="track_inventory" checked={data.track_inventory}
                          onCheckedChange={(checked) => setData(prev => ({ ...prev, track_inventory: checked }))} />
                        <Label htmlFor="track_inventory" className="text-sm font-medium">{t('page.ecommerce.products.form.track_inventory')}</Label>
                      </div>
                    </CardContent>
                  </Card>
                </div>

                <div className="grid auto-rows-max items-start gap-4 lg:gap-8">
                  <Card>
                    <CardHeader>
                      <CardTitle>{t('page.ecommerce.products.form.publication_title')}</CardTitle>
                    </CardHeader>
                    <CardContent className="space-y-4">
                      <div className="grid gap-3">
                        <Label htmlFor="status">{t('common.fields.status')}</Label>
                        <Select value={data.status} onValueChange={(value: 'draft' | 'active' | 'archived') => setData(prev => ({ ...prev, status: value }))}>
                          <SelectTrigger id="status"><SelectValue /></SelectTrigger>
                          <SelectContent>
                            <SelectItem value="draft">{t('common.statuses.draft')}</SelectItem>
                            <SelectItem value="active">{t('common.statuses.active')}</SelectItem>
                            <SelectItem value="archived">{t('common.statuses.archived')}</SelectItem>
                          </SelectContent>
                        </Select>
                      </div>
                      <div className="flex items-center space-x-2">
                        <Switch id="featured" checked={data.is_featured} onCheckedChange={(checked) => setData(prev => ({ ...prev, is_featured: checked }))} />
                        <Label htmlFor="featured" className="text-sm font-medium">{t('page.ecommerce.products.form.featured_product')}</Label>
                      </div>
                    </CardContent>
                  </Card>

                  <Card>
                    <CardHeader>
                      <CardTitle>{t('page.ecommerce.products.form.featured_image_title')}</CardTitle>
                      <CardDescription>{t('page.ecommerce.products.form.featured_image_description')}</CardDescription>
                    </CardHeader>
                    <CardContent>
                      <MediaUploader name="featured_image" multiple={false} maxFiles={1}
                        acceptedFileTypes={['image/jpeg', 'image/png', 'image/webp', 'image/jpg']}
                        maxFileSize={5} onChange={handleFeaturedImageChange} />
                    </CardContent>
                  </Card>

                  <Card>
                    <CardHeader>
                      <CardTitle>{t('page.ecommerce.products.form.category_title')}</CardTitle>
                      <CardDescription>{t('page.ecommerce.products.form.category_description')}</CardDescription>
                    </CardHeader>
                    <CardContent>
                      <Select value={data.category_id?.toString() || ""} onValueChange={(value) => setData(prev => ({ ...prev, category_id: parseInt(value) }))}>
                        <SelectTrigger><SelectValue placeholder={t('page.ecommerce.products.form.category_placeholder')} /></SelectTrigger>
                        <SelectContent>
                          {categories.map((category) => (
                            <SelectItem key={category.id} value={category.id.toString()}>{category.name}</SelectItem>
                          ))}
                        </SelectContent>
                      </Select>
                    </CardContent>
                  </Card>

                  <Card>
                    <CardHeader>
                      <CardTitle>{t('page.ecommerce.products.form.tags_title')}</CardTitle>
                      <CardDescription>{t('page.ecommerce.products.form.tags_description')}</CardDescription>
                    </CardHeader>
                    <CardContent>
                      <div className="space-y-4">
                        <div className="grid grid-cols-2 gap-2">
                          {tags.map((tag) => (
                            <Button key={tag.id} variant={data.tag_ids.includes(tag.id) ? "default" : "outline"}
                              size="sm" onClick={() => handleTagToggle(tag.id)} className="justify-start">
                              {data.tag_ids.includes(tag.id) && <X className="mr-1 h-3 w-3" />}
                              {tag.name}
                            </Button>
                          ))}
                        </div>
                        {selectedTagObjects.length > 0 && (
                          <div>
                            <Label className="text-sm font-medium mb-2 block">{t('page.ecommerce.products.form.selected_tags', { count: selectedTagObjects.length })}</Label>
                            <div className="flex flex-wrap gap-1">
                              {selectedTagObjects.map((tag) => (
                                <Badge key={tag.id} variant="secondary" className="text-xs">
                                  {tag.name}
                                  <X className="ml-1 h-3 w-3 cursor-pointer" onClick={() => handleTagToggle(tag.id)} />
                                </Badge>
                              ))}
                            </div>
                          </div>
                        )}
                      </div>
                    </CardContent>
                  </Card>
                </div>
              </div>

              <div className="flex items-center justify-center gap-2 md:hidden">
                <Button variant="outline" size="sm" onClick={() => handleSubmit('draft')} disabled={processing}>{t('page.ecommerce.products.create.save_draft')}</Button>
                <Button size="sm" onClick={() => handleSubmit()} disabled={processing}>{processing ? t('page.ecommerce.products.create.creating') : t('page.ecommerce.products.create.submit')}</Button>
              </div>
            </div>
          </div>
        </Main>
      </AuthenticatedLayout>
    </>
  )
}
