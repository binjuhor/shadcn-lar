import {AuthenticatedLayout} from "@/layouts"
import {
  ChevronLeft,
  CalendarIcon,
  X,
} from "lucide-react"
import { useTranslation } from "react-i18next"
import { Input } from "@/components/ui/input"
import { Label } from "@/components/ui/label"
import { Textarea } from "@/components/ui/textarea"
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select"
import {Badge} from "@/components/ui/badge"
import {Button} from "@/components/ui/button"
import {
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from "@/components/ui/card"
import { Switch } from "@/components/ui/switch"
import {
  Popover,
  PopoverContent,
  PopoverTrigger,
} from "@/components/ui/popover"
import { Calendar } from "@/components/ui/calendar"
import { Main } from "@/components/layout"
import { Content } from "@tiptap/react"
import { MinimalTiptapEditor } from "@/components/ui/minimal-tiptap"
import { useState } from "react"
import { BlogCategory, BlogTag, BlogPostFormData } from "@/types/blog"
import { router } from "@inertiajs/react"
import { PageProps } from "@/types"
import { useToast } from "@/hooks/use-toast"
import { MediaUploader } from "@/components/MediaLibrary"
import { format } from "date-fns"
import { cn } from "@/lib/utils"

interface CreateBlogPostPageProps extends PageProps {
  categories: BlogCategory[]
  tags: BlogTag[]
}

export default function CreateBlogPost({ categories, tags }: CreateBlogPostPageProps) {
  const { t } = useTranslation()
  const [data, setData] = useState<BlogPostFormData>({
    title: "",
    content: "",
    excerpt: "",
    featured_image: "",
    status: "draft",
    is_featured: false,
    category_id: undefined,
    tag_ids: [],
    published_at: undefined,
    meta_title: "",
    meta_description: "",
  })

  const [content, setContent] = useState<Content>("")
  const [featuredImageFiles, setFeaturedImageFiles] = useState<File[]>([])
  const [publishedDate, setPublishedDate] = useState<Date | undefined>(undefined)
  const [publishedDateOpen, setPublishedDateOpen] = useState(false)
  const [publishedTime, setPublishedTime] = useState<string>("12:00")
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

  const handleSubmit = (status?: 'draft' | 'published') => {
    setProcessing(true)

    const formData = new FormData()

    // Add all form fields
    formData.append('title', data.title)
    formData.append('content', data.content)
    if (data.excerpt) {
      formData.append('excerpt', data.excerpt)
    }
    formData.append('status', status || data.status)
    formData.append('is_featured', data.is_featured ? '1' : '0')

    if (data.category_id) {
      formData.append('category_id', data.category_id.toString())
    }

    // Add tags
    data.tag_ids.forEach((tagId, index) => {
      formData.append(`tag_ids[${index}]`, tagId.toString())
    })

    // Add published_at
    if (publishedDate && publishedTime) {
      const [hours, minutes] = publishedTime.split(':')
      const dateTime = new Date(publishedDate)
      dateTime.setHours(parseInt(hours), parseInt(minutes), 0, 0)
      formData.append('published_at', dateTime.toISOString())
    } else if (status === 'published') {
      formData.append('published_at', new Date().toISOString())
    }

    // Add meta fields
    if (data.meta_title) {
      formData.append('meta_title', data.meta_title)
    }
    if (data.meta_description) {
      formData.append('meta_description', data.meta_description)
    }

    // Add featured image if selected
    if (featuredImageFiles.length > 0) {
      formData.append('featured_image', featuredImageFiles[0])
    }

    router.post(route('dashboard.posts.store'), formData, {
      forceFormData: true,
      onSuccess: () => {
        setProcessing(false)
        toast({
          title: status === 'published' ? t('page.blog.posts.toast.published_title') : t('page.blog.posts.toast.draft_saved_title'),
          description: status === 'published'
            ? t('page.blog.posts.toast.published_description')
            : t('page.blog.posts.toast.draft_saved_description'),
        })
        // Redirect to posts list after a short delay
        setTimeout(() => {
          router.get(route('dashboard.posts.index'))
        }, 1000)
      },
      onError: (errors) => {
        setProcessing(false)
        console.error('Validation errors:', errors)
        toast({
          variant: "destructive",
          title: t('page.blog.posts.toast.error_creating'),
          description: t('page.blog.posts.toast.check_form'),
        })
      }
    })
  }

  const selectedTagObjects = tags.filter(tag => data.tag_ids.includes(tag.id))

  return (
    <>
      <AuthenticatedLayout title={t('page.blog.posts.create.title')}>
        <Main>
          <div className="grid flex-1 items-start gap-4 md:gap-8">
            <div className="grid flex-1 auto-rows-max gap-4">
              <div className="flex items-center gap-4">
                <Button
                  variant="outline"
                  size="icon"
                  className="h-7 w-7"
                  onClick={() => window.history.back()}
                >
                  <ChevronLeft className="h-4 w-4" />
                  <span className="sr-only">{t('common.actions.back')}</span>
                </Button>
                <h1 className="flex-1 shrink-0 whitespace-nowrap text-xl font-semibold tracking-tight sm:grow-0">
                  {t('page.blog.posts.create.title')}
                </h1>
                <div className="hidden items-center gap-2 md:ml-auto md:flex">
                  <Button
                    variant="outline"
                    size="sm"
                    onClick={() => handleSubmit('draft')}
                    disabled={processing}
                  >
                    {t('page.blog.posts.actions.save_draft')}
                  </Button>
                  <Button
                    size="sm"
                    onClick={() => handleSubmit()}
                    disabled={processing}
                  >
                    {processing ? t('page.blog.posts.actions.creating') : t('page.blog.posts.actions.create_post')}
                  </Button>
                </div>
              </div>

              <div className="grid gap-4 md:grid-cols-[1fr_350px] lg:gap-8">
                <div className="grid auto-rows-max items-start gap-4 lg:gap-8">
                  <Card>
                    <CardHeader>
                      <CardTitle>{t('page.blog.posts.form.details_title')}</CardTitle>
                      <CardDescription>
                        {t('page.blog.posts.form.details_description')}
                      </CardDescription>
                    </CardHeader>
                    <CardContent>
                      <div className="grid gap-6">
                        <div className="grid gap-3">
                          <Label htmlFor="title">{t('page.blog.posts.form.title_label')}</Label>
                          <Input
                            id="title"
                            type="text"
                            className="w-full"
                            placeholder={t('page.blog.posts.form.title_placeholder')}
                            value={data.title}
                            onChange={(e) => setData(prev => ({ ...prev, title: e.target.value }))}
                          />
                        </div>

                        <div className="grid gap-3">
                          <Label htmlFor="excerpt">{t('page.blog.posts.form.excerpt')}</Label>
                          <Textarea
                            id="excerpt"
                            placeholder={t('page.blog.posts.form.excerpt_placeholder')}
                            className="min-h-20"
                            value={data.excerpt}
                            onChange={(e) => setData(prev => ({ ...prev, excerpt: e.target.value }))}
                          />
                        </div>

                        <div className="grid gap-3">
                          <Label htmlFor="content">{t('page.blog.posts.form.content')}</Label>
                          <MinimalTiptapEditor
                            value={content}
                            onChange={handleContentChange}
                            className="w-full"
                            editorContentClassName="p-5"
                            output="html"
                            placeholder={t('page.blog.posts.form.content_placeholder')}
                            autofocus={false}
                            editable={true}
                            editorClassName="focus:outline-none min-h-[400px]"
                          />
                        </div>
                      </div>
                    </CardContent>
                  </Card>

                </div>

                <div className="grid auto-rows-max items-start gap-4 lg:gap-8">
                  <Card>
                    <CardHeader>
                      <CardTitle>{t('page.blog.posts.form.publication')}</CardTitle>
                    </CardHeader>
                    <CardContent className="space-y-4">
                      <div className="grid gap-3">
                        <Label htmlFor="status">{t('common.fields.status')}</Label>
                        <Select
                          value={data.status}
                          onValueChange={(value: 'draft' | 'published' | 'archived') =>
                            setData(prev => ({ ...prev, status: value }))
                          }
                        >
                          <SelectTrigger id="status">
                            <SelectValue />
                          </SelectTrigger>
                          <SelectContent>
                            <SelectItem value="draft">{t('common.statuses.draft')}</SelectItem>
                            <SelectItem value="published">{t('common.statuses.published')}</SelectItem>
                            <SelectItem value="archived">{t('common.statuses.archived')}</SelectItem>
                          </SelectContent>
                        </Select>
                      </div>

                      <div className="flex items-center space-x-2">
                        <Switch
                          id="featured"
                          checked={data.is_featured}
                          onCheckedChange={(checked) => setData(prev => ({ ...prev, is_featured: checked }))}
                        />
                        <Label htmlFor="featured" className="text-sm font-medium">
                          {t('page.blog.posts.form.featured_post')}
                        </Label>
                      </div>

                      {data.status === 'published' && (
                        <>
                          <div className="grid gap-3">
                            <Label>{t('page.blog.posts.form.publication_date')}</Label>
                            <Popover open={publishedDateOpen} onOpenChange={setPublishedDateOpen}>
                              <PopoverTrigger asChild>
                                <Button
                                  variant="outline"
                                  className={cn(
                                    "w-full justify-start text-left font-normal",
                                    !publishedDate && "text-muted-foreground"
                                  )}
                                >
                                  <CalendarIcon className="mr-2 h-4 w-4" />
                                  {publishedDate ? format(publishedDate, "PPP") : <span>{t('page.blog.posts.form.pick_date')}</span>}
                                </Button>
                              </PopoverTrigger>
                              <PopoverContent className="w-auto p-0" align="start">
                                <Calendar
                                  mode="single"
                                  selected={publishedDate}
                                  defaultMonth={publishedDate}
                                  onSelect={(date) => {
                                    setPublishedDate(date)
                                    setPublishedDateOpen(false)
                                  }}
                                  initialFocus
                                  captionLayout="dropdown"
                                  fromYear={2000}
                                  toYear={new Date().getFullYear() + 10}
                                />
                              </PopoverContent>
                            </Popover>
                          </div>
                          <div className="grid gap-3">
                            <Label htmlFor="published_time">{t('page.blog.posts.form.publication_time')}</Label>
                            <Input
                              id="published_time"
                              type="time"
                              value={publishedTime}
                              onChange={(e) => setPublishedTime(e.target.value)}
                            />
                          </div>
                        </>
                      )}
                    </CardContent>
                  </Card>

                  <Card>
                    <CardHeader>
                      <CardTitle>{t('page.blog.posts.form.featured_image')}</CardTitle>
                      <CardDescription>
                        {t('page.blog.posts.form.featured_image_description')}
                      </CardDescription>
                    </CardHeader>
                    <CardContent>
                      <MediaUploader
                        name="featured_image"
                        multiple={false}
                        maxFiles={1}
                        acceptedFileTypes={['image/jpeg', 'image/png', 'image/webp', 'image/jpg']}
                        maxFileSize={5}
                        onChange={handleFeaturedImageChange}
                      />
                    </CardContent>
                  </Card>
                  <Card>
                    <CardHeader>
                      <CardTitle>{t('common.fields.category')}</CardTitle>
                      <CardDescription>
                        {t('page.blog.posts.form.category_description')}
                      </CardDescription>
                    </CardHeader>
                    <CardContent>
                      <Select
                        value={data.category_id?.toString() || ""}
                        onValueChange={(value) => setData(prev => ({ ...prev, category_id: parseInt(value) }))}
                      >
                        <SelectTrigger>
                          <SelectValue placeholder={t('page.blog.posts.form.select_category')} />
                        </SelectTrigger>
                        <SelectContent>
                          {categories.map((category) => (
                            <SelectItem key={category.id} value={category.id.toString()}>
                              {category.name}
                            </SelectItem>
                          ))}
                        </SelectContent>
                      </Select>
                    </CardContent>
                  </Card>

                  <Card>
                    <CardHeader>
                      <CardTitle>{t('page.blog.posts.tags')}</CardTitle>
                      <CardDescription>
                        {t('page.blog.posts.form.tags_description')}
                      </CardDescription>
                    </CardHeader>
                    <CardContent>
                      <div className="space-y-4">
                        <div className="grid grid-cols-2 gap-2">
                          {tags.map((tag) => (
                            <Button
                              key={tag.id}
                              variant={data.tag_ids.includes(tag.id) ? "default" : "outline"}
                              size="sm"
                              onClick={() => handleTagToggle(tag.id)}
                              className="justify-start"
                            >
                              {data.tag_ids.includes(tag.id) && (
                                <X className="mr-1 h-3 w-3" />
                              )}
                              {tag.name}
                            </Button>
                          ))}
                        </div>

                        {selectedTagObjects.length > 0 && (
                          <div>
                            <Label className="text-sm font-medium mb-2 block">
                              {t('page.blog.posts.form.selected_tags', { count: selectedTagObjects.length })}
                            </Label>
                            <div className="flex flex-wrap gap-1">
                              {selectedTagObjects.map((tag) => (
                                <Badge key={tag.id} variant="secondary" className="text-xs">
                                  {tag.name}
                                  <X
                                    className="ml-1 h-3 w-3 cursor-pointer"
                                    onClick={() => handleTagToggle(tag.id)}
                                  />
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
                <Button
                  variant="outline"
                  size="sm"
                  onClick={() => handleSubmit('draft')}
                  disabled={processing}
                >
                  {t('page.blog.posts.actions.save_draft')}
                </Button>
                <Button
                  size="sm"
                  onClick={() => handleSubmit()}
                  disabled={processing}
                >
                  {processing ? t('page.blog.posts.actions.creating') : t('page.blog.posts.actions.create_post')}
                </Button>
              </div>
            </div>
          </div>
        </Main>
      </AuthenticatedLayout>
    </>
  )
}
