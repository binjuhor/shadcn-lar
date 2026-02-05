import {AuthenticatedLayout} from "@/layouts"
import {
  MoreHorizontal,
  PlusCircle,
  Edit,
  Trash2,
  Loader2,
} from "lucide-react"
import { useTranslation } from 'react-i18next'

import {Badge} from "@/components/ui/badge"
import {Button} from "@/components/ui/button"
import {
  Card,
  CardContent,
  CardDescription,
  CardFooter,
  CardHeader,
  CardTitle,
} from "@/components/ui/card"
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
  DialogTrigger,
} from "@/components/ui/dialog"
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuLabel,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu"
import { Input } from "@/components/ui/input"
import { Label } from "@/components/ui/label"
import { Textarea } from "@/components/ui/textarea"
import { Switch } from "@/components/ui/switch"
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from "@/components/ui/table"
import { Main } from "@/components/layout"
import { useState } from "react"
import { BlogTag, BlogTagFormData } from "@/types/blog"
import { PageProps } from "@/types"
import { router } from "@inertiajs/react"
import { useToast } from "@/hooks/use-toast"
import { axios } from "@/lib/axios"
import { getErrorMessage } from "@/lib/errors"

interface BlogTagsPageProps extends PageProps {
  tags: BlogTag[]
}

const initialFormData: BlogTagFormData = {
  name: "",
  slug: "",
  description: "",
  color: "",
  is_active: true,
}

export default function BlogTags({ tags }: BlogTagsPageProps) {
  const { t } = useTranslation()
  const [isCreateOpen, setIsCreateOpen] = useState(false)
  const [isEditOpen, setIsEditOpen] = useState(false)
  const [editingTag, setEditingTag] = useState<BlogTag | null>(null)
  const [formData, setFormData] = useState<BlogTagFormData>(initialFormData)
  const [isLoading, setIsLoading] = useState(false)
  const [searchTerm, setSearchTerm] = useState("")
  const { toast } = useToast()

  // Helper function to generate slug from name
  const generateSlug = (text: string) => {
    return text
      .toLowerCase()
      .trim()
      .replace(/[^\w\s-]/g, '') // Remove special characters
      .replace(/\s+/g, '-') // Replace spaces with hyphens
      .replace(/-+/g, '-') // Replace multiple hyphens with single hyphen
  }

  const filteredTags = tags.filter(tag =>
    tag.name.toLowerCase().includes(searchTerm.toLowerCase())
  )

  const handleCreateTag = async () => {
    if (!formData.name.trim()) {
      toast({
        title: t('common.messages.error'),
        description: t('page.blog.tags.toast.name_required'),
        variant: "destructive",
      })
      return
    }

    setIsLoading(true)
    try {
      // Clean empty strings to undefined for optional fields
      const cleanedData = {
        name: formData.name.trim(),
        slug: formData.slug?.trim() || undefined,
        description: formData.description?.trim() || undefined,
        color: formData.color?.trim() || undefined,
        is_active: formData.is_active,
      }

      await axios.post('/dashboard/tags', cleanedData)

      toast({
        title: t('common.messages.create_success'),
        description: t('page.blog.tags.toast.created'),
      })

      setFormData(initialFormData)
      setIsCreateOpen(false)
      router.reload()
    } catch (error) {
      toast({
        title: t('common.messages.error'),
        description: getErrorMessage(error),
        variant: "destructive",
      })
    } finally {
      setIsLoading(false)
    }
  }

  const handleEditTag = (tag: BlogTag) => {
    setEditingTag(tag)
    setFormData({
      name: tag.name,
      slug: tag.slug,
      description: tag.description || "",
      color: tag.color || "",
      is_active: tag.is_active,
    })
    setIsEditOpen(true)
  }

  const handleUpdateTag = async () => {
    if (!editingTag || !formData.name.trim()) {
      toast({
        title: t('common.messages.error'),
        description: t('page.blog.tags.toast.name_required'),
        variant: "destructive",
      })
      return
    }

    setIsLoading(true)
    try {
      // Clean empty strings to null for optional fields
      const cleanedData = {
        name: formData.name.trim(),
        slug: formData.slug?.trim() || undefined,
        description: formData.description?.trim() || undefined,
        color: formData.color?.trim() || undefined,
        is_active: formData.is_active,
      }

      await axios.put(`/dashboard/tags/${editingTag.slug}`, cleanedData)

      toast({
        title: t('common.messages.update_success'),
        description: t('page.blog.tags.toast.updated'),
      })

      setFormData(initialFormData)
      setEditingTag(null)
      setIsEditOpen(false)
      router.reload()
    } catch (error) {
      toast({
        title: t('common.messages.error'),
        description: getErrorMessage(error),
        variant: "destructive",
      })
    } finally {
      setIsLoading(false)
    }
  }

  const handleDeleteTag = async (tag: BlogTag) => {
    if (!confirm(t('page.blog.tags.toast.delete_confirm'))) {
      return
    }

    setIsLoading(true)
    try {
      await axios.delete(`/dashboard/tags/${tag.slug}`)

      toast({
        title: t('common.messages.delete_success'),
        description: t('page.blog.tags.toast.deleted'),
      })

      router.reload()
    } catch (error) {
      toast({
        title: t('common.messages.error'),
        description: getErrorMessage(error),
        variant: "destructive",
      })
    } finally {
      setIsLoading(false)
    }
  }

  const formatDate = (dateString: string) => {
    return new Date(dateString).toLocaleDateString("en-US", {
      year: "numeric",
      month: "short",
      day: "numeric",
    })
  }

  return (
    <>
      <AuthenticatedLayout title={t('page.blog.tags.title')}>
        <Main>
          <div className="grid flex-1 items-start gap-4 md:gap-8">
            <div className="flex items-center gap-4">
              <div className="relative flex-1 md:max-w-sm">
                <Input
                  placeholder={t('page.blog.tags.search_placeholder')}
                  value={searchTerm}
                  onChange={(e) => setSearchTerm(e.target.value)}
                />
              </div>
              <div className="ml-auto flex items-center gap-2">
                <Dialog open={isCreateOpen} onOpenChange={setIsCreateOpen}>
                  <DialogTrigger asChild>
                    <Button size="sm" className="h-7 gap-1">
                      <PlusCircle className="h-3.5 w-3.5" />
                      <span className="sr-only sm:not-sr-only sm:whitespace-nowrap">
                        {t('page.blog.tags.add_tag')}
                      </span>
                    </Button>
                  </DialogTrigger>
                  <DialogContent>
                    <DialogHeader>
                      <DialogTitle>{t('page.blog.tags.create.title')}</DialogTitle>
                      <DialogDescription>
                        {t('page.blog.tags.create.description')}
                      </DialogDescription>
                    </DialogHeader>
                    <div className="grid gap-4 py-4">
                      <div className="grid gap-2">
                        <Label htmlFor="create-name">{t('page.blog.tags.form.name')} *</Label>
                        <Input
                          id="create-name"
                          value={formData.name}
                          onChange={(e) => setFormData(prev => ({ ...prev, name: e.target.value }))}
                          placeholder={t('page.blog.tags.form.name_placeholder')}
                        />
                      </div>
                      <div className="grid gap-2">
                        <div className="flex items-center justify-between">
                          <Label htmlFor="create-slug">{t('page.blog.tags.form.slug')}</Label>
                          <Button
                            type="button"
                            variant="ghost"
                            size="sm"
                            onClick={() => setFormData(prev => ({ ...prev, slug: generateSlug(formData.name) }))}
                            className="h-7 text-xs"
                          >
                            {t('page.blog.tags.form.auto_generate')}
                          </Button>
                        </div>
                        <Input
                          id="create-slug"
                          type="text"
                          className="w-full font-mono text-sm"
                          placeholder={t('page.blog.tags.form.slug_placeholder')}
                          value={formData.slug}
                          onChange={(e) => setFormData(prev => ({ ...prev, slug: e.target.value }))}
                        />
                        <p className="text-xs text-muted-foreground">
                          {t('page.blog.tags.form.url_preview', { slug: formData.slug || 'your-tag-slug' })}
                        </p>
                      </div>
                      <div className="grid gap-2">
                        <Label htmlFor="create-description">{t('common.fields.description')}</Label>
                        <Textarea
                          id="create-description"
                          value={formData.description}
                          onChange={(e) => setFormData(prev => ({ ...prev, description: e.target.value }))}
                          placeholder={t('page.blog.tags.form.description_placeholder')}
                          rows={3}
                        />
                      </div>
                      <div className="grid gap-2">
                        <Label htmlFor="create-color">{t('common.fields.color')}</Label>
                        <Input
                          id="create-color"
                          type="color"
                          value={formData.color}
                          onChange={(e) => setFormData(prev => ({ ...prev, color: e.target.value }))}
                        />
                      </div>
                      <div className="flex items-center justify-between">
                        <Label htmlFor="create-active">{t('common.fields.active')}</Label>
                        <Switch
                          id="create-active"
                          checked={formData.is_active}
                          onCheckedChange={(checked) => setFormData(prev => ({ ...prev, is_active: checked }))}
                        />
                      </div>
                    </div>
                    <DialogFooter>
                      <Button
                        type="button"
                        variant="outline"
                        onClick={() => {
                          setIsCreateOpen(false)
                          setFormData(initialFormData)
                        }}
                        disabled={isLoading}
                      >
                        {t('common.actions.cancel')}
                      </Button>
                      <Button
                        type="submit"
                        onClick={handleCreateTag}
                        disabled={!formData.name.trim() || isLoading}
                      >
                        {isLoading && <Loader2 className="mr-2 h-4 w-4 animate-spin" />}
                        {t('page.blog.tags.create.submit')}
                      </Button>
                    </DialogFooter>
                  </DialogContent>
                </Dialog>
              </div>
            </div>

            <div className="grid gap-4">
              <Card>
                <CardHeader>
                  <CardTitle>{t('page.blog.tags.title')}</CardTitle>
                  <CardDescription>
                    {t('page.blog.tags.description')}
                  </CardDescription>
                </CardHeader>
                <CardContent>
                  <div className="flex flex-wrap gap-2 mb-6">
                    {filteredTags.slice(0, 20).map((tag) => (
                      <Badge
                        key={tag.id}
                        variant="secondary"
                        className="cursor-pointer hover:bg-secondary/80"
                        style={tag.color ? { backgroundColor: tag.color + '20', borderColor: tag.color } : {}}
                      >
                        <span className="mr-1">{tag.name}</span>
                        (<span className="text-xs opacity-70">
                        {tag.usage_count && tag.usage_count > 0 && (
                          <>{tag.usage_count}</>
                        )}</span>)
                      </Badge>
                    ))}
                    {filteredTags.length > 20 && (
                      <Badge variant="outline">
                        +{filteredTags.length - 20} {t('page.blog.tags.more')}
                      </Badge>
                    )}
                  </div>

                  <Table>
                    <TableHeader>
                      <TableRow>
                        <TableHead>{t('common.fields.name')}</TableHead>
                        <TableHead>{t('common.fields.slug')}</TableHead>
                        <TableHead className="hidden md:table-cell">{t('page.blog.tags.table.usage')}</TableHead>
                        <TableHead className="hidden md:table-cell">{t('common.fields.status')}</TableHead>
                        <TableHead className="hidden md:table-cell">{t('page.blog.tags.table.created')}</TableHead>
                        <TableHead>
                          <span className="sr-only">{t('common.actions.actions')}</span>
                        </TableHead>
                      </TableRow>
                    </TableHeader>
                    <TableBody>
                      {filteredTags.map((tag) => (
                        <TableRow key={tag.id}>
                          <TableCell className="font-medium">
                            <Badge
                              variant="secondary"
                              style={tag.color ? { backgroundColor: tag.color + '20', borderColor: tag.color } : {}}
                            >
                              {tag.name}
                            </Badge>
                          </TableCell>
                          <TableCell className="text-muted-foreground">
                            {tag.slug}
                          </TableCell>
                          <TableCell className="hidden md:table-cell">
                            {t('page.blog.tags.table.posts_count', { count: tag.usage_count || 0 })}
                          </TableCell>
                          <TableCell className="hidden md:table-cell">
                            <Badge variant={tag.is_active ? "default" : "secondary"}>
                              {tag.is_active ? t('common.statuses.active') : t('common.statuses.inactive')}
                            </Badge>
                          </TableCell>
                          <TableCell className="hidden md:table-cell">
                            {formatDate(tag.created_at)}
                          </TableCell>
                          <TableCell>
                            <DropdownMenu>
                              <DropdownMenuTrigger asChild>
                                <Button
                                  aria-haspopup="true"
                                  size="icon"
                                  variant="ghost"
                                >
                                  <MoreHorizontal className="h-4 w-4" />
                                  <span className="sr-only">{t('page.blog.tags.table.toggle_menu')}</span>
                                </Button>
                              </DropdownMenuTrigger>
                              <DropdownMenuContent align="end">
                                <DropdownMenuLabel>{t('common.actions.actions')}</DropdownMenuLabel>
                                <DropdownMenuSeparator />
                                <DropdownMenuItem onClick={() => handleEditTag(tag)}>
                                  <Edit className="mr-2 h-4 w-4" />
                                  {t('common.actions.edit')}
                                </DropdownMenuItem>
                                <DropdownMenuSeparator />
                                <DropdownMenuItem
                                  className="text-red-600"
                                  onClick={() => handleDeleteTag(tag)}
                                >
                                  <Trash2 className="mr-2 h-4 w-4" />
                                  {t('common.actions.delete')}
                                </DropdownMenuItem>
                              </DropdownMenuContent>
                            </DropdownMenu>
                          </TableCell>
                        </TableRow>
                      ))}
                    </TableBody>
                  </Table>
                </CardContent>
                <CardFooter>
                  <div className="text-xs text-muted-foreground">
                    {t('page.blog.tags.table.showing')} <strong>{filteredTags.length}</strong> {t('page.blog.tags.table.of')} <strong>{tags.length}</strong> {t('page.blog.tags.table.tags')}
                  </div>
                </CardFooter>
              </Card>
            </div>

            <Dialog open={isEditOpen} onOpenChange={setIsEditOpen}>
              <DialogContent>
                <DialogHeader>
                  <DialogTitle>{t('page.blog.tags.edit.title')}</DialogTitle>
                  <DialogDescription>
                    {t('page.blog.tags.edit.description')}
                  </DialogDescription>
                </DialogHeader>
                <div className="grid gap-4 py-4">
                  <div className="grid gap-2">
                    <Label htmlFor="edit-name">{t('page.blog.tags.form.name')} *</Label>
                    <Input
                      id="edit-name"
                      value={formData.name}
                      onChange={(e) => setFormData(prev => ({ ...prev, name: e.target.value }))}
                      placeholder={t('page.blog.tags.form.name_placeholder')}
                    />
                  </div>
                  <div className="grid gap-2">
                    <div className="flex items-center justify-between">
                      <Label htmlFor="edit-slug">{t('page.blog.tags.form.slug')}</Label>
                      <Button
                        type="button"
                        variant="ghost"
                        size="sm"
                        onClick={() => setFormData(prev => ({ ...prev, slug: generateSlug(formData.name) }))}
                        className="h-7 text-xs"
                      >
                        {t('page.blog.tags.form.auto_generate')}
                      </Button>
                    </div>
                    <Input
                      id="edit-slug"
                      type="text"
                      className="w-full font-mono text-sm"
                      placeholder={t('page.blog.tags.form.slug_placeholder')}
                      value={formData.slug}
                      onChange={(e) => setFormData(prev => ({ ...prev, slug: e.target.value }))}
                    />
                    <p className="text-xs text-muted-foreground">
                      {t('page.blog.tags.form.url_preview', { slug: formData.slug || 'your-tag-slug' })}
                    </p>
                  </div>
                  <div className="grid gap-2">
                    <Label htmlFor="edit-description">{t('common.fields.description')}</Label>
                    <Textarea
                      id="edit-description"
                      value={formData.description}
                      onChange={(e) => setFormData(prev => ({ ...prev, description: e.target.value }))}
                      placeholder={t('page.blog.tags.form.description_placeholder')}
                      rows={3}
                    />
                  </div>
                  <div className="grid gap-2">
                    <Label htmlFor="edit-color">{t('common.fields.color')}</Label>
                    <Input
                      id="edit-color"
                      type="color"
                      value={formData.color}
                      onChange={(e) => setFormData(prev => ({ ...prev, color: e.target.value }))}
                    />
                  </div>
                  <div className="flex items-center justify-between">
                    <Label htmlFor="edit-active">{t('common.fields.active')}</Label>
                    <Switch
                      id="edit-active"
                      checked={formData.is_active}
                      onCheckedChange={(checked) => setFormData(prev => ({ ...prev, is_active: checked }))}
                    />
                  </div>
                </div>
                <DialogFooter>
                  <Button
                    type="button"
                    variant="outline"
                    onClick={() => {
                      setIsEditOpen(false)
                      setEditingTag(null)
                      setFormData(initialFormData)
                    }}
                    disabled={isLoading}
                  >
                    {t('common.actions.cancel')}
                  </Button>
                  <Button
                    type="submit"
                    onClick={handleUpdateTag}
                    disabled={!formData.name.trim() || isLoading}
                  >
                    {isLoading && <Loader2 className="mr-2 h-4 w-4 animate-spin" />}
                    {t('page.blog.tags.edit.submit')}
                  </Button>
                </DialogFooter>
              </DialogContent>
            </Dialog>
          </div>
        </Main>
      </AuthenticatedLayout>
    </>
  )
}
