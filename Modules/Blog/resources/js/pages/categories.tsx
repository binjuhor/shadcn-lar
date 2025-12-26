import {AuthenticatedLayout} from "@/layouts"
import {
  MoreHorizontal,
  PlusCircle,
  Edit,
  Trash2,
  Loader2,
} from "lucide-react"

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
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select"
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
import { Badge } from "@/components/ui/badge"
import { Main } from "@/components/layout"
import { useState } from "react"
import { BlogCategory, BlogCategoryFormData } from "@/types/blog"
import { PageProps } from "@/types"
import { router } from "@inertiajs/react"
import { useToast } from "@/hooks/use-toast"
import { axios } from "@/lib/axios"
import { getErrorMessage } from "@/lib/errors"

interface BlogCategoriesPageProps extends PageProps {
  categories: BlogCategory[]
}

const initialFormData: BlogCategoryFormData = {
  name: "",
  slug: "",
  description: "",
  color: "",
  icon: "",
  parent_id: undefined,
  is_active: true,
  meta_title: "",
  meta_description: "",
}

export default function BlogCategories({ categories }: BlogCategoriesPageProps) {
  const [isCreateOpen, setIsCreateOpen] = useState(false)
  const [isEditOpen, setIsEditOpen] = useState(false)
  const [editingCategory, setEditingCategory] = useState<BlogCategory | null>(null)
  const [formData, setFormData] = useState<BlogCategoryFormData>(initialFormData)
  const [isLoading, setIsLoading] = useState(false)
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

  const handleCreateCategory = async () => {
    if (!formData.name.trim()) {
      toast({
        title: "Error",
        description: "Category name is required",
        variant: "destructive",
      })
      return
    }

    setIsLoading(true)
    try {
      await axios.post('/dashboard/categories', formData)

      toast({
        title: "Success",
        description: "Category created successfully",
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

  const handleEditCategory = (category: BlogCategory) => {
    setEditingCategory(category)
    setFormData({
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
    setIsEditOpen(true)
  }

  const handleUpdateCategory = async () => {
    if (!editingCategory || !formData.name.trim()) {
      toast({
        title: "Error",
        description: "Category name is required",
        variant: "destructive",
      })
      return
    }

    setIsLoading(true)
    try {
      await axios.put(`/dashboard/categories/${editingCategory.slug}`, formData)

      toast({
        title: "Success",
        description: "Category updated successfully",
      })

      setFormData(initialFormData)
      setEditingCategory(null)
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

  const handleDeleteCategory = async (category: BlogCategory) => {
    if (!confirm("Are you sure you want to delete this category?")) {
      return
    }

    setIsLoading(true)
    try {
      await axios.delete(`/dashboard/categories/${category.slug}`)

      toast({
        title: "Success",
        description: "Category deleted successfully",
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

  const getAvailableParentCategories = () => {
    if (!editingCategory) {
      return categories.filter(cat => cat.is_active)
    }
    return categories.filter(cat =>
      cat.id !== editingCategory.id &&
      cat.parent_id !== editingCategory.id &&
      cat.is_active
    )
  }

  return (
    <>
      <AuthenticatedLayout title="Blog Categories">
        <Main>
          <div className="grid flex-1 items-start gap-4 md:gap-8">
            <div className="flex items-center">
              <div className="ml-auto flex items-center gap-2">
                <Dialog open={isCreateOpen} onOpenChange={setIsCreateOpen}>
                  <DialogTrigger asChild>
                    <Button size="sm" className="h-7 gap-1">
                      <PlusCircle className="h-3.5 w-3.5" />
                      <span className="sr-only sm:not-sr-only sm:whitespace-nowrap">
                        Add Category
                      </span>
                    </Button>
                  </DialogTrigger>
                  <DialogContent className="max-h-[90vh] overflow-y-auto">
                    <DialogHeader>
                      <DialogTitle>Create Category</DialogTitle>
                      <DialogDescription>
                        Add a new category for organizing your blog posts.
                      </DialogDescription>
                    </DialogHeader>
                    <div className="grid gap-4 py-4">
                      <div className="grid gap-2">
                        <Label htmlFor="create-name">Name *</Label>
                        <Input
                          id="create-name"
                          value={formData.name}
                          onChange={(e) => setFormData(prev => ({ ...prev, name: e.target.value }))}
                          placeholder="Category name"
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
                          placeholder="category-url-slug"
                          value={formData.slug}
                          onChange={(e) => setFormData(prev => ({ ...prev, slug: e.target.value }))}
                        />
                        <p className="text-xs text-muted-foreground">
                          URL: /categories/{formData.slug || 'your-category-slug'}
                        </p>
                      </div>
                      <div className="grid gap-2">
                        <Label htmlFor="create-description">Description</Label>
                        <Textarea
                          id="create-description"
                          value={formData.description}
                          onChange={(e) => setFormData(prev => ({ ...prev, description: e.target.value }))}
                          placeholder="Brief description of the category"
                          rows={3}
                        />
                      </div>
                      <div className="grid grid-cols-2 gap-4">
                        <div className="grid gap-2">
                          <Label htmlFor="create-color">Color</Label>
                          <Input
                            id="create-color"
                            type="color"
                            value={formData.color}
                            onChange={(e) => setFormData(prev => ({ ...prev, color: e.target.value }))}
                          />
                        </div>
                        <div className="grid gap-2">
                          <Label htmlFor="create-icon">Icon</Label>
                          <Input
                            id="create-icon"
                            value={formData.icon}
                            onChange={(e) => setFormData(prev => ({ ...prev, icon: e.target.value }))}
                            placeholder="Icon name"
                          />
                        </div>
                      </div>
                      <div className="grid gap-2">
                        <Label htmlFor="create-parent">Parent Category</Label>
                        <Select
                          value={formData.parent_id?.toString() || "none"}
                          onValueChange={(value) => setFormData(prev => ({
                            ...prev,
                            parent_id: value === "none" ? undefined : parseInt(value)
                          }))}
                        >
                          <SelectTrigger id="create-parent">
                            <SelectValue placeholder="Select parent category" />
                          </SelectTrigger>
                          <SelectContent>
                            <SelectItem value="none">None (Top Level)</SelectItem>
                            {getAvailableParentCategories().map((cat) => (
                              <SelectItem key={cat.id} value={cat.id.toString()}>
                                {cat.name}
                              </SelectItem>
                            ))}
                          </SelectContent>
                        </Select>
                      </div>
                      <div className="flex items-center justify-between">
                        <Label htmlFor="create-active">Active</Label>
                        <Switch
                          id="create-active"
                          checked={formData.is_active}
                          onCheckedChange={(checked) => setFormData(prev => ({ ...prev, is_active: checked }))}
                        />
                      </div>
                      <div className="grid gap-2">
                        <Label htmlFor="create-meta-title">Meta Title</Label>
                        <Input
                          id="create-meta-title"
                          value={formData.meta_title}
                          onChange={(e) => setFormData(prev => ({ ...prev, meta_title: e.target.value }))}
                          placeholder="SEO meta title"
                        />
                      </div>
                      <div className="grid gap-2">
                        <Label htmlFor="create-meta-description">Meta Description</Label>
                        <Textarea
                          id="create-meta-description"
                          value={formData.meta_description}
                          onChange={(e) => setFormData(prev => ({ ...prev, meta_description: e.target.value }))}
                          placeholder="SEO meta description"
                          rows={2}
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
                        onClick={handleCreateCategory}
                        disabled={!formData.name.trim() || isLoading}
                      >
                        {isLoading && <Loader2 className="mr-2 h-4 w-4 animate-spin" />}
                        Create Category
                      </Button>
                    </DialogFooter>
                  </DialogContent>
                </Dialog>
              </div>
            </div>

            <Card>
              <CardHeader>
                <CardTitle>Blog Categories</CardTitle>
                <CardDescription>
                  Manage categories for organizing your blog posts.
                </CardDescription>
              </CardHeader>
              <CardContent>
                <Table>
                  <TableHeader>
                    <TableRow>
                      <TableHead>Name</TableHead>
                      <TableHead>Slug</TableHead>
                      <TableHead>Description</TableHead>
                      <TableHead>Status</TableHead>
                      <TableHead className="hidden md:table-cell">Created</TableHead>
                      <TableHead>
                        <span className="sr-only">Actions</span>
                      </TableHead>
                    </TableRow>
                  </TableHeader>
                  <TableBody>
                    {categories.map((category) => (
                      <TableRow key={category.id}>
                        <TableCell className="font-medium">
                          {category.name}
                        </TableCell>
                        <TableCell className="text-muted-foreground">
                          {category.slug}
                        </TableCell>
                        <TableCell>
                          {category.description || "No description"}
                        </TableCell>
                        <TableCell>
                          {category.is_active ? (
                            <Badge variant="default">
                              Active
                            </Badge>
                          ) : (
                            <Badge variant="secondary">
                              Inactive
                            </Badge>
                          )}
                        </TableCell>
                        <TableCell className="hidden md:table-cell">
                          {formatDate(category.created_at)}
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
                              <DropdownMenuItem onClick={() => handleEditCategory(category)}>
                                <Edit className="mr-2 h-4 w-4" />
                                Edit
                              </DropdownMenuItem>
                              <DropdownMenuSeparator />
                              <DropdownMenuItem
                                className="text-red-600"
                                onClick={() => handleDeleteCategory(category)}
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
                  Showing <strong>{categories.length}</strong> categories
                </div>
              </CardFooter>
            </Card>

            <Dialog open={isEditOpen} onOpenChange={setIsEditOpen}>
              <DialogContent className="max-h-[90vh] overflow-y-auto">
                <DialogHeader>
                  <DialogTitle>Edit Category</DialogTitle>
                  <DialogDescription>
                    Update the category information.
                  </DialogDescription>
                </DialogHeader>
                <div className="grid gap-4 py-4">
                  <div className="grid gap-2">
                    <Label htmlFor="edit-name">Name *</Label>
                    <Input
                      id="edit-name"
                      value={formData.name}
                      onChange={(e) => setFormData(prev => ({ ...prev, name: e.target.value }))}
                      placeholder="Category name"
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
                      placeholder="category-url-slug"
                      value={formData.slug}
                      onChange={(e) => setFormData(prev => ({ ...prev, slug: e.target.value }))}
                    />
                    <p className="text-xs text-muted-foreground">
                      URL: /categories/{formData.slug || 'your-category-slug'}
                    </p>
                  </div>
                  <div className="grid gap-2">
                    <Label htmlFor="edit-description">Description</Label>
                    <Textarea
                      id="edit-description"
                      value={formData.description}
                      onChange={(e) => setFormData(prev => ({ ...prev, description: e.target.value }))}
                      placeholder="Brief description of the category"
                      rows={3}
                    />
                  </div>
                  <div className="grid grid-cols-2 gap-4">
                    <div className="grid gap-2">
                      <Label htmlFor="edit-color">Color</Label>
                      <Input
                        id="edit-color"
                        type="color"
                        value={formData.color}
                        onChange={(e) => setFormData(prev => ({ ...prev, color: e.target.value }))}
                      />
                    </div>
                    <div className="grid gap-2">
                      <Label htmlFor="edit-icon">Icon</Label>
                      <Input
                        id="edit-icon"
                        value={formData.icon}
                        onChange={(e) => setFormData(prev => ({ ...prev, icon: e.target.value }))}
                        placeholder="Icon name"
                      />
                    </div>
                  </div>
                  <div className="grid gap-2">
                    <Label htmlFor="edit-parent">Parent Category</Label>
                    <Select
                      value={formData.parent_id?.toString() || "none"}
                      onValueChange={(value) => setFormData(prev => ({
                        ...prev,
                        parent_id: value === "none" ? undefined : parseInt(value)
                      }))}
                    >
                      <SelectTrigger id="edit-parent">
                        <SelectValue placeholder="Select parent category" />
                      </SelectTrigger>
                      <SelectContent>
                        <SelectItem value="none">None (Top Level)</SelectItem>
                        {getAvailableParentCategories().map((cat) => (
                          <SelectItem key={cat.id} value={cat.id.toString()}>
                            {cat.name}
                          </SelectItem>
                        ))}
                      </SelectContent>
                    </Select>
                  </div>
                  <div className="flex items-center justify-between">
                    <Label htmlFor="edit-active">Active</Label>
                    <Switch
                      id="edit-active"
                      checked={formData.is_active}
                      onCheckedChange={(checked) => setFormData(prev => ({ ...prev, is_active: checked }))}
                    />
                  </div>
                  <div className="grid gap-2">
                    <Label htmlFor="edit-meta-title">Meta Title</Label>
                    <Input
                      id="edit-meta-title"
                      value={formData.meta_title}
                      onChange={(e) => setFormData(prev => ({ ...prev, meta_title: e.target.value }))}
                      placeholder="SEO meta title"
                    />
                  </div>
                  <div className="grid gap-2">
                    <Label htmlFor="edit-meta-description">Meta Description</Label>
                    <Textarea
                      id="edit-meta-description"
                      value={formData.meta_description}
                      onChange={(e) => setFormData(prev => ({ ...prev, meta_description: e.target.value }))}
                      placeholder="SEO meta description"
                      rows={2}
                    />
                  </div>
                </div>
                <DialogFooter>
                  <Button
                    type="button"
                    variant="outline"
                    onClick={() => {
                      setIsEditOpen(false)
                      setEditingCategory(null)
                      setFormData(initialFormData)
                    }}
                    disabled={isLoading}
                  >
                    Cancel
                  </Button>
                  <Button
                    type="submit"
                    onClick={handleUpdateCategory}
                    disabled={!formData.name.trim() || isLoading}
                  >
                    {isLoading && <Loader2 className="mr-2 h-4 w-4 animate-spin" />}
                    Update Category
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
