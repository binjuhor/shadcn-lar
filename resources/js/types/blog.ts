export interface BlogCategory {
  id: number
  name: string
  slug: string
  description?: string
  color?: string
  icon?: string
  parent_id?: number
  parent?: BlogCategory
  children?: BlogCategory[]
  sort_order?: number
  is_active: boolean
  meta_title?: string
  meta_description?: string
  posts_count?: number
  published_posts_count?: number
  created_at: string
  updated_at: string
}

export interface BlogTag {
  id: number
  name: string
  slug: string
  description?: string
  color?: string
  is_active: boolean
  usage_count?: number
  posts_count?: number
  published_posts_count?: number
  created_at: string
  updated_at: string
}

export interface BlogPost {
  id: number
  title: string
  slug: string
  content: string
  excerpt?: string
  featured_image?: string
  featured_image_url?: string
  status: 'draft' | 'published' | 'archived' | 'scheduled'
  is_featured: boolean
  published_at?: string
  created_at: string
  updated_at: string
  meta_title?: string
  meta_description?: string
  views_count?: number
  reading_time?: number
  category?: BlogCategory
  tags?: BlogTag[]
  user: {
    id: number
    name: string
    email: string
  }
}

export interface BlogPostFormData {
  title: string
  slug?: string
  content: string
  excerpt?: string
  featured_image?: string
  status: 'draft' | 'published' | 'archived' | 'scheduled'
  is_featured: boolean
  category_id?: number
  tag_ids: number[]
  published_at?: string
  meta_title?: string
  meta_description?: string
  [key: string]: any
}

export interface BlogFilters {
  search?: string
  category?: string
  tag?: string
  status?: 'draft' | 'published' | 'archived'
  featured?: boolean
}

export interface BlogCategoryFormData {
  name: string
  slug?: string
  description?: string
  color?: string
  icon?: string
  parent_id?: number
  is_active?: boolean
  meta_title?: string
  meta_description?: string
}

export interface BlogTagFormData {
  name: string
  slug?: string
  description?: string
  color?: string
  is_active?: boolean
}