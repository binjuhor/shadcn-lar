import {AuthenticatedLayout} from "@/layouts"
import {
  MoreHorizontal,
  PlusCircle,
  Edit,
  Trash2,
  Loader2,
} from "lucide-react"

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
        title: "Error",
        description: "Tag name is required",
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
        title: "Success",
        description: "Tag created successfully",
      })

      setFormData(initialFormData)
      setIsCreateOpen(false)
      router.reload()
    } catch (error) {
      toast({
        title: "Error",
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
        title: "Error",
        description: "Tag name is required",
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
        title: "Success",
        description: "Tag updated successfully",
      })

      setFormData(initialFormData)
      setEditingTag(null)
      setIsEditOpen(false)
      router.reload()
    } catch (error) {
      toast({
        title: "Error",
        description: getErrorMessage(error),
        variant: "destructive",
      })
    } finally {
      setIsLoading(false)
    }
  }

  const handleDeleteTag = async (tag: BlogTag) => {
    if (!confirm("Are you sure you want to delete this tag?")) {
      return
    }

    setIsLoading(true)
    try {
      await axios.delete(`/dashboard/tags/${tag.slug}`)

      toast({
        title: "Success",
        description: "Tag deleted successfully",
      })

      router.reload()
    } catch (error) {
      toast({
        title: "Error",
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
      <AuthenticatedLayout title="Blog Tags">
        <Main>
          <div className="grid flex-1 items-start gap-4 md:gap-8">
            <div className="flex items-center gap-4">
              <div className="relative flex-1 md:max-w-sm">
                <Input
                  placeholder="Search tags..."
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
                        Add Tag
                      </span>
                    </Button>
                  </DialogTrigger>
                  <DialogContent>
                    <DialogHeader>
                      <DialogTitle>Create Tag</DialogTitle>
                      <DialogDescription>
                        Add a new tag for organizing your blog posts.
                      </DialogDescription>
                    </DialogHeader>
                    <div className="grid gap-4 py-4">
                      <div className="grid gap-2">
                        <Label htmlFor="create-name">Name *</Label>
                        <Input
                          id="create-name"
                          value={formData.name}
                          onChange={(e) => setFormData(prev => ({ ...prev, name: e.target.value }))}
                          placeholder="Tag name"
                        />
                      </div>
                      <div className="grid gap-2">
                        <div className="flex items-center justify-between">
                          <Label htmlFor="create-slug">Slug (URL)</Label>
                          <Button
                            type="button"
                            variant="ghost"
                            size="sm"
                            onClick={() => setFormData(prev => ({ ...prev, slug: generateSlug(formData.name) }))}
                            className="h-7 text-xs"
                          >
                            Auto-generate
                          </Button>
                        </div>
                        <Input
                          id="create-slug"
                          type="text"
                          className="w-full font-mono text-sm"
                          placeholder="tag-url-slug"
                          value={formData.slug}
                          onChange={(e) => setFormData(prev => ({ ...prev, slug: e.target.value }))}
                        />
                        <p className="text-xs text-muted-foreground">
                          URL: /tags/{formData.slug || 'your-tag-slug'}
                        </p>
                      </div>
                      <div className="grid gap-2">
                        <Label htmlFor="create-description">Description</Label>
                        <Textarea
                          id="create-description"
                          value={formData.description}
                          onChange={(e) => setFormData(prev => ({ ...prev, description: e.target.value }))}
                          placeholder="Brief description of the tag"
                          rows={3}
                        />
                      </div>
                      <div className="grid gap-2">
                        <Label htmlFor="create-color">Color</Label>
                        <Input
                          id="create-color"
                          type="color"
                          value={formData.color}
                          onChange={(e) => setFormData(prev => ({ ...prev, color: e.target.value }))}
                        />
                      </div>
                      <div className="flex items-center justify-between">
                        <Label htmlFor="create-active">Active</Label>
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
                        Cancel
                      </Button>
                      <Button
                        type="submit"
                        onClick={handleCreateTag}
                        disabled={!formData.name.trim() || isLoading}
                      >
                        {isLoading && <Loader2 className="mr-2 h-4 w-4 animate-spin" />}
                        Create Tag
                      </Button>
                    </DialogFooter>
                  </DialogContent>
                </Dialog>
              </div>
            </div>

            <div className="grid gap-4">
              <Card>
                <CardHeader>
                  <CardTitle>All Tags</CardTitle>
                  <CardDescription>
                    Manage tags for organizing your blog posts. Tags help readers find related content.
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
                        +{filteredTags.length - 20} more
                      </Badge>
                    )}
                  </div>

                  <Table>
                    <TableHeader>
                      <TableRow>
                        <TableHead>Name</TableHead>
                        <TableHead>Slug</TableHead>
                        <TableHead className="hidden md:table-cell">Usage</TableHead>
                        <TableHead className="hidden md:table-cell">Status</TableHead>
                        <TableHead className="hidden md:table-cell">Created</TableHead>
                        <TableHead>
                          <span className="sr-only">Actions</span>
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
                            {tag.usage_count || 0} posts
                          </TableCell>
                          <TableCell className="hidden md:table-cell">
                            <Badge variant={tag.is_active ? "default" : "secondary"}>
                              {tag.is_active ? "Active" : "Inactive"}
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
                                  <span className="sr-only">Toggle menu</span>
                                </Button>
                              </DropdownMenuTrigger>
                              <DropdownMenuContent align="end">
                                <DropdownMenuLabel>Actions</DropdownMenuLabel>
                                <DropdownMenuSeparator />
                                <DropdownMenuItem onClick={() => handleEditTag(tag)}>
                                  <Edit className="mr-2 h-4 w-4" />
                                  Edit
                                </DropdownMenuItem>
                                <DropdownMenuSeparator />
                                <DropdownMenuItem
                                  className="text-red-600"
                                  onClick={() => handleDeleteTag(tag)}
                                >
                                  <Trash2 className="mr-2 h-4 w-4" />
                                  Delete
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
                    Showing <strong>{filteredTags.length}</strong> of <strong>{tags.length}</strong> tags
                  </div>
                </CardFooter>
              </Card>
            </div>

            <Dialog open={isEditOpen} onOpenChange={setIsEditOpen}>
              <DialogContent>
                <DialogHeader>
                  <DialogTitle>Edit Tag</DialogTitle>
                  <DialogDescription>
                    Update the tag information.
                  </DialogDescription>
                </DialogHeader>
                <div className="grid gap-4 py-4">
                  <div className="grid gap-2">
                    <Label htmlFor="edit-name">Name *</Label>
                    <Input
                      id="edit-name"
                      value={formData.name}
                      onChange={(e) => setFormData(prev => ({ ...prev, name: e.target.value }))}
                      placeholder="Tag name"
                    />
                  </div>
                  <div className="grid gap-2">
                    <div className="flex items-center justify-between">
                      <Label htmlFor="edit-slug">Slug (URL)</Label>
                      <Button
                        type="button"
                        variant="ghost"
                        size="sm"
                        onClick={() => setFormData(prev => ({ ...prev, slug: generateSlug(formData.name) }))}
                        className="h-7 text-xs"
                      >
                        Auto-generate
                      </Button>
                    </div>
                    <Input
                      id="edit-slug"
                      type="text"
                      className="w-full font-mono text-sm"
                      placeholder="tag-url-slug"
                      value={formData.slug}
                      onChange={(e) => setFormData(prev => ({ ...prev, slug: e.target.value }))}
                    />
                    <p className="text-xs text-muted-foreground">
                      URL: /tags/{formData.slug || 'your-tag-slug'}
                    </p>
                  </div>
                  <div className="grid gap-2">
                    <Label htmlFor="edit-description">Description</Label>
                    <Textarea
                      id="edit-description"
                      value={formData.description}
                      onChange={(e) => setFormData(prev => ({ ...prev, description: e.target.value }))}
                      placeholder="Brief description of the tag"
                      rows={3}
                    />
                  </div>
                  <div className="grid gap-2">
                    <Label htmlFor="edit-color">Color</Label>
                    <Input
                      id="edit-color"
                      type="color"
                      value={formData.color}
                      onChange={(e) => setFormData(prev => ({ ...prev, color: e.target.value }))}
                    />
                  </div>
                  <div className="flex items-center justify-between">
                    <Label htmlFor="edit-active">Active</Label>
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
                    Cancel
                  </Button>
                  <Button
                    type="submit"
                    onClick={handleUpdateTag}
                    disabled={!formData.name.trim() || isLoading}
                  >
                    {isLoading && <Loader2 className="mr-2 h-4 w-4 animate-spin" />}
                    Update Tag
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
