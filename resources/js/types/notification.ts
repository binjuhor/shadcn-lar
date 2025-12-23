export interface Notification {
  id: string
  type: string
  category: string | null
  title: string | null
  message: string | null
  icon: string | null
  action_url: string | null
  action_label: string | null
  read_at: string | null
  is_read: boolean
  created_at: string
  time_ago: string
}

export interface NotificationTemplate {
  id: number
  name: string
  slug: string
  subject: string
  body?: string
  category: string
  category_label: string
  channels: string[]
  variables: string[] | null
  is_active: boolean
  created_at: string
  updated_at: string
}

export interface NotificationCategory {
  value: string
  label: string
  description?: string
  icon?: string
}

export interface NotificationChannel {
  value: string
  label: string
  description?: string
  icon?: string
}

export interface NotificationFilters {
  status?: 'read' | 'unread'
  category?: string
}

export interface NotificationTemplateFilters {
  search?: string
  category?: string
  status?: 'active' | 'inactive'
}
