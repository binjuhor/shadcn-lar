import { z } from 'zod'

// Schema matching backend User model
const roleSchema = z.object({
  id: z.number(),
  name: z.string(),
  guard_name: z.string().optional(),
})

export const userSchema = z.object({
  id: z.number(),
  name: z.string(),
  email: z.string(),
  email_verified_at: z.string().nullable().optional(),
  roles: z.array(roleSchema).optional(),
  role_names: z.array(z.string()).optional(),
  permissions: z.array(z.string()).optional(),
  created_at: z.string().optional(),
  updated_at: z.string().optional(),
})

export type User = z.infer<typeof userSchema>
export type Role = z.infer<typeof roleSchema>

export const userListSchema = z.array(userSchema)

// Paginated response from backend
export interface PaginatedUsers {
  data: User[]
  current_page: number
  last_page: number
  per_page: number
  total: number
}
