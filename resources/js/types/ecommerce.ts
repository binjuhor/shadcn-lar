export interface ProductCategory {
  id: number
  name: string
  slug: string
  description?: string
  color?: string
  icon?: string
  parent_id?: number
  parent?: ProductCategory
  children?: ProductCategory[]
  sort_order?: number
  is_active: boolean
  meta_title?: string
  meta_description?: string
  products_count?: number
  created_at: string
  updated_at: string
}

export interface ProductTag {
  id: number
  name: string
  slug: string
  description?: string
  color?: string
  is_active?: boolean
  products_count?: number
  created_at?: string
  updated_at?: string
}

export interface Product {
  id: number
  name: string
  slug: string
  description?: string
  content?: string
  sku: string
  price: number
  sale_price?: number
  cost?: number
  stock_quantity: number
  low_stock_threshold?: number
  track_inventory: boolean
  status: 'draft' | 'active' | 'archived'
  featured_image?: string
  featured_image_url?: string
  images?: string[]
  category_id?: number
  category?: ProductCategory
  tags?: ProductTag[]
  user_id: number
  user: {
    id: number
    name: string
    email: string
  }
  views_count: number
  sales_count: number
  is_featured: boolean
  meta_title?: string
  meta_description?: string
  created_at: string
  updated_at: string
  deleted_at?: string
  // Computed attributes
  effective_price?: number
  is_on_sale?: boolean
  discount_percentage?: number
  is_low_stock?: boolean
  is_out_of_stock?: boolean
}

export interface ProductFormData {
  name: string
  slug?: string
  description?: string
  content?: string
  sku?: string
  price: number
  sale_price?: number
  cost?: number
  stock_quantity: number
  low_stock_threshold?: number
  track_inventory?: boolean
  status: 'draft' | 'active' | 'archived'
  featured_image?: string
  images?: string[]
  category_id?: number
  tag_ids: number[]
  is_featured: boolean
  meta_title?: string
  meta_description?: string
  [key: string]: any
}

export interface ProductFilters {
  search?: string
  category_id?: number
  status?: 'draft' | 'active' | 'archived'
  featured?: boolean
}

export interface Order {
  id: number
  order_number: string
  user_id: number
  user: {
    id: number
    name: string
    email: string
  }
  status: 'pending' | 'processing' | 'completed' | 'cancelled' | 'refunded'
  subtotal: number
  tax: number
  shipping: number
  discount: number
  total: number
  currency: string
  payment_method?: string
  payment_status: 'unpaid' | 'paid' | 'refunded'
  paid_at?: string
  customer_notes?: string
  admin_notes?: string
  billing_address?: {
    first_name: string
    last_name: string
    company?: string
    address_1: string
    address_2?: string
    city: string
    state: string
    postcode: string
    country: string
    email: string
    phone: string
  }
  shipping_address?: {
    first_name: string
    last_name: string
    company?: string
    address_1: string
    address_2?: string
    city: string
    state: string
    postcode: string
    country: string
  }
  items?: OrderItem[]
  created_at: string
  updated_at: string
  deleted_at?: string
  // Computed attributes
  is_paid?: boolean
  is_completed?: boolean
  is_cancelled?: boolean
  is_pending?: boolean
  is_processing?: boolean
}

export interface OrderItem {
  id: number
  order_id: number
  product_id: number
  product: Product
  quantity: number
  price: number
  subtotal: number
  tax: number
  total: number
  created_at: string
  updated_at: string
}

export interface OrderFilters {
  search?: string
  status?: 'pending' | 'processing' | 'completed' | 'cancelled' | 'refunded'
  payment_status?: 'unpaid' | 'paid' | 'refunded'
  date_from?: string
  date_to?: string
}

export interface ProductCategoryFormData {
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

export interface ProductTagFormData {
  name: string
  slug?: string
  description?: string
  color?: string
  is_active?: boolean
}
