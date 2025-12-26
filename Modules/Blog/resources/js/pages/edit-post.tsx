import {AuthenticatedLayout} from "@/layouts"
import {
  ChevronLeft,
  CalendarIcon,
  X,
} from "lucide-react"
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
import { useState, useEffect } from "react"
import { BlogCategory, BlogTag, BlogPostFormData, BlogPost } from "@/types/blog"
import { router } from "@inertiajs/react"
import { PageProps } from "@/types"
import { useToast } from "@/hooks/use-toast"
import { MediaUploader } from "@/components/MediaLibrary"
import { format } from "date-fns"
import { cn } from "@/lib/utils"

interface EditBlogPostPageProps extends PageProps {
  post: BlogPost
  categories: BlogCategory[]
  tags: BlogTag[]
}

export default function EditBlogPost({ post, categories, tags }: EditBlogPostPageProps) {
  const [data, setData] = useState<BlogPostFormData>({
    title: post.title,
    slug: post.slug,
    content: post.content,
    excerpt: post.excerpt || "",
    featured_image: post.featured_image || "",
    status: post.status,
    is_featured: post.is_featured,
    category_id: post.category?.id,
    tag_ids: post.tags?.map(tag => tag.id) || [],
    published_at: post.published_at ? new Date(post.published_at).toISOString().slice(0, 16) : "",
    meta_title: post.meta_title || "",
    meta_description: post.meta_description || "",
  })

  // Helper function to generate slug from title
  const generateSlug = (text: string) => {
    return text
      .toLowerCase()
      .trim()
      .replace(/[^\w\s-]/g, '') // Remove special characters
      .replace(/\s+/g, '-') // Replace spaces with hyphens
      .replace(/-+/g, '-') // Replace multiple hyphens with single hyphen
  }

  const [content, setContent] = useState<Content>(post.content)
  const [featuredImageFiles, setFeaturedImageFiles] = useState<File[]>([])
  const [removeFeaturedImage, setRemoveFeaturedImage] = useState(false)
  const [publishedDate, setPublishedDate] = useState<Date | undefined>(
    post.published_at ? new Date(post.published_at) : undefined
  )
  const [publishedTime, setPublishedTime] = useState<string>(() => {
    if (post.published_at) {
      const date = new Date(post.published_at)
      return `${date.getHours().toString().padStart(2, '0')}:${date.getMinutes().toString().padStart(2, '0')}`
    }
    return "12:00"
  })
  const [processing, setProcessing] = useState(false)
  const { toast } = useToast()

  // Prepare initial media for MediaUploader
  const initialMedia = post.featured_image_url ? [{
    uuid: `post-${post.id}-featured`,
    name: 'Featured Image',
    file_name: 'featured-image.jpg',
    mime_type: 'image/jpeg',
    size: 0,
    preview_url: post.featured_image_url,
  }] : []

  useEffect(() => {
    setContent(post.content)
  }, [post.content])

  const handleFeaturedImageChange = (files: File[]) => {
    setFeaturedImageFiles(files)
    if (files.length > 0) {
      setRemoveFeaturedImage(false)
    }
  }

  const handleRemoveMedia = (uuid: string) => {
    setRemoveFeaturedImage(true)
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
    if (data.slug) {
      formData.append('slug', data.slug)
    }
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
    } else if (status === 'published' || data.status === 'published') {
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
    } else if (removeFeaturedImage) {
      // If user removed the existing featured image
      formData.append('remove_featured_image', '1')
    }

    // Add _method for PUT request
    formData.append('_method', 'PUT')

    router.post(route('dashboard.posts.update', post.slug), formData, {
      forceFormData: true,
      onSuccess: () => {
        setProcessing(false)
        toast({
          title: "Post updated!",
          description: "Your blog post has been updated successfully.",
        })
      },
      onError: (errors) => {
        setProcessing(false)
        console.error('Validation errors:', errors)
        toast({
          variant: "destructive",
          title: "Error updating post",
          description: "Please check your form and try again.",
        })
      }
    })
  }

  const selectedTagObjects = tags.filter(tag => data.tag_ids.includes(tag.id))

  return (
    <>
      <AuthenticatedLayout title={`Edit: ${post.title}`}>
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
                  <span className="sr-only">Back</span>
                </Button>
                <h1 className="flex-1 shrink-0 whitespace-nowrap text-xl font-semibold tracking-tight sm:grow-0">
                  Edit Blog Post
                </h1>
                <div className="hidden items-center gap-2 md:ml-auto md:flex">
                  <Button
                    variant="outline"
                    size="sm"
                    onClick={() => handleSubmit('draft')}
                    disabled={processing}
                  >
                    Save Draft
                  </Button>
                  <Button
                    size="sm"
                    onClick={() => handleSubmit()}
                    disabled={processing}
                  >
                    {processing ? 'Updating...' : 'Update Post'}
                  </Button>
                </div>
              </div>

              <div className="grid gap-4 md:grid-cols-[1fr_350px] lg:gap-8">
                <div className="grid auto-rows-max items-start gap-4 lg:gap-8">
                  <Card>
                    <CardHeader>
                      <CardTitle>Post Details</CardTitle>
                      <CardDescription>
                        Update the basic information for your blog post
                      </CardDescription>
                    </CardHeader>
                    <CardContent>
                      <div className="grid gap-6">
                        <div className="grid gap-3">
                          <Label htmlFor="title">Title</Label>
                          <Input
                            id="title"
                            type="text"
                            className="w-full"
                            placeholder="Enter post title..."
                            value={data.title}
                            onChange={(e) => setData(prev => ({ ...prev, title: e.target.value }))}
                          />
                        </div>

                        <div className="grid gap-3">
                          <div className="flex items-center justify-between">
                            <Label htmlFor="slug">Slug (URL)</Label>
                            <Button
                              type="button"
                              variant="ghost"
                              size="sm"
                              onClick={() => setData(prev => ({ ...prev, slug: generateSlug(data.title) }))}
                              className="h-7 text-xs"
                            >
                              Auto-generate
                            </Button>
                          </div>
                          <Input
                            id="slug"
                            type="text"
                            className="w-full font-mono text-sm"
                            placeholder="post-url-slug"
                            value={data.slug}
                            onChange={(e) => setData(prev => ({ ...prev, slug: e.target.value }))}
                          />
                          <p className="text-xs text-muted-foreground">
                            URL: /blog/{data.slug || 'your-post-slug'}
                          </p>
                        </div>

                        <div className="grid gap-3">
                          <Label htmlFor="excerpt">Excerpt</Label>
                          <Textarea
                            id="excerpt"
                            placeholder="Enter a brief summary of your post..."
                            className="min-h-20"
                            value={data.excerpt}
                            onChange={(e) => setData(prev => ({ ...prev, excerpt: e.target.value }))}
                          />
                        </div>

                        <div className="grid gap-3">
                          <Label htmlFor="content">Content</Label>
                          <MinimalTiptapEditor
                            value={content}
                            onChange={handleContentChange}
                            className="w-full"
                            editorContentClassName="p-5"
                            output="html"
                            placeholder="Write your blog post content..."
                            autofocus={false}
                            editable={true}
                            editorClassName="focus:outline-none min-h-[400px]"
                          />
                        </div>

                        <div className="grid gap-3">
                          <Label htmlFor="meta_title">SEO Title</Label>
                          <Input
                            id="meta_title"
                            type="text"
                            placeholder="SEO optimized title..."
                            value={data.meta_title}
                            onChange={(e) => setData(prev => ({ ...prev, meta_title: e.target.value }))}
                          />
                        </div>

                        <div className="grid gap-3">
                          <Label htmlFor="meta_description">SEO Description</Label>
                          <Textarea
                            id="meta_description"
                            placeholder="SEO meta description..."
                            className="min-h-20"
                            value={data.meta_description}
                            onChange={(e) => setData(prev => ({ ...prev, meta_description: e.target.value }))}
                          />
                        </div>
                      </div>
                    </CardContent>
                  </Card>
                </div>

                <div className="grid auto-rows-max items-start gap-4 lg:gap-8">
                  <Card>
                    <CardHeader>
                      <CardTitle>Publication</CardTitle>
                    </CardHeader>
                    <CardContent className="space-y-4">
                      <div className="grid gap-3">
                        <Label htmlFor="status">Status</Label>
                        <Select
                          value={data.status}
                          onValueChange={(value: 'draft' | 'published' | 'archived' | 'scheduled') =>
                            setData(prev => ({ ...prev, status: value }))
                          }
                        >
                          <SelectTrigger id="status">
                            <SelectValue />
                          </SelectTrigger>
                          <SelectContent>
                            <SelectItem value="draft">Draft</SelectItem>
                            <SelectItem value="published">Published</SelectItem>
                            <SelectItem value="scheduled">Scheduled</SelectItem>
                            <SelectItem value="archived">Archived</SelectItem>
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
                          Featured Post
                        </Label>
                      </div>

                      {(data.status === 'published' || data.status === 'scheduled') && (
                        <>
                          <div className="grid gap-3">
                            <Label>Publication Date</Label>
                            <Popover>
                              <PopoverTrigger asChild>
                                <Button
                                  variant="outline"
                                  className={cn(
                                    "w-full justify-start text-left font-normal",
                                    !publishedDate && "text-muted-foreground"
                                  )}
                                >
                                  <CalendarIcon className="mr-2 h-4 w-4" />
                                  {publishedDate ? format(publishedDate, "PPP") : <span>Pick a date</span>}
                                </Button>
                              </PopoverTrigger>
                              <PopoverContent className="w-auto p-0" align="start">
                                <Calendar
                                  mode="single"
                                  selected={publishedDate}
                                  onSelect={setPublishedDate}
                                  initialFocus
                                />
                              </PopoverContent>
                            </Popover>
                          </div>
                          <div className="grid gap-3">
                            <Label htmlFor="published_time">Publication Time</Label>
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
                      <CardTitle>Featured Image</CardTitle>
                      <CardDescription>
                        Upload a thumbnail image for your blog post
                      </CardDescription>
                    </CardHeader>
                    <CardContent>
                      <MediaUploader
                        name="featured_image"
                        initialMedia={initialMedia}
                        multiple={false}
                        maxFiles={1}
                        acceptedFileTypes={['image/jpeg', 'image/png', 'image/webp', 'image/jpg']}
                        maxFileSize={5}
                        onChange={handleFeaturedImageChange}
                        onRemove={handleRemoveMedia}
                      />
                    </CardContent>
                  </Card>

                  <Card>
                    <CardHeader>
                      <CardTitle>Category</CardTitle>
                      <CardDescription>
                        Select a category for your post
                      </CardDescription>
                    </CardHeader>
                    <CardContent>
                      <Select
                        value={data.category_id?.toString() || ""}
                        onValueChange={(value) => setData(prev => ({ ...prev, category_id: parseInt(value) }))}
                      >
                        <SelectTrigger>
                          <SelectValue placeholder="Select category" />
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
                      <CardTitle>Tags</CardTitle>
                      <CardDescription>
                        Add relevant tags to your post
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
                              Selected Tags:
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
                  Save Draft
                </Button>
                <Button
                  size="sm"
                  onClick={() => handleSubmit()}
                  disabled={processing}
                >
                  {processing ? 'Updating...' : 'Update Post'}
                </Button>
              </div>
            </div>
          </div>
        </Main>
      </AuthenticatedLayout>
    </>
  )
}
