import { z } from 'zod'

// Profile Schema
export const profileFormSchema = z.object({
  username: z
    .string()
    .min(2, { message: 'Username must be at least 2 characters.' })
    .max(30, { message: 'Username must not be longer than 30 characters.' }),
  email: z
    .string({ required_error: 'Please select an email to display.' })
    .email(),
  bio: z.string().max(160).min(4),
  urls: z
    .array(
      z.object({
        value: z.string().url({ message: 'Please enter a valid URL.' }),
      })
    )
    .optional(),
})
export type ProfileFormValues = z.infer<typeof profileFormSchema>

// Account Schema
export const accountFormSchema = z.object({
  name: z
    .string()
    .min(2, { message: 'Name must be at least 2 characters.' })
    .max(30, { message: 'Name must not be longer than 30 characters.' }),
  dob: z.date().optional(),
  language: z.string({ required_error: 'Please select a language.' }),
})
export type AccountFormValues = z.infer<typeof accountFormSchema>

// Appearance Schema
export const appearanceFormSchema = z.object({
  theme: z.enum(['light', 'dark'], {
    required_error: 'Please select a theme.',
  }),
  font: z.enum(['inter', 'manrope', 'system'], {
    invalid_type_error: 'Select a font',
    required_error: 'Please select a font.',
  }),
})
export type AppearanceFormValues = z.infer<typeof appearanceFormSchema>

// Notifications Schema
export const notificationsFormSchema = z.object({
  type: z.enum(['all', 'mentions', 'none'], {
    required_error: 'You need to select a notification type.',
  }),
  mobile: z.boolean().default(false).optional(),
  communication_emails: z.boolean().default(false).optional(),
  social_emails: z.boolean().default(false).optional(),
  marketing_emails: z.boolean().default(false).optional(),
  security_emails: z.boolean(),
})
export type NotificationsFormValues = z.infer<typeof notificationsFormSchema>

// Display Schema
export const displayFormSchema = z.object({
  items: z.array(z.string()).refine((value) => value.some((item) => item), {
    message: 'You have to select at least one item.',
  }),
})
export type DisplayFormValues = z.infer<typeof displayFormSchema>

// Display sidebar items constant
export const displayItems = [
  { id: 'recents', label: 'Recents' },
  { id: 'home', label: 'Home' },
  { id: 'applications', label: 'Applications' },
  { id: 'desktop', label: 'Desktop' },
  { id: 'downloads', label: 'Downloads' },
  { id: 'documents', label: 'Documents' },
] as const

// Languages constant (supported languages for i18n)
export const languages = [
  { label: 'English', value: 'en' },
  { label: 'Tiếng Việt', value: 'vi' },
] as const

// Password Schema
export const passwordFormSchema = z
  .object({
    current_password: z.string().min(1, 'Current password is required.'),
    password: z.string().min(8, 'Password must be at least 8 characters.'),
    password_confirmation: z.string().min(1, 'Please confirm your password.'),
  })
  .refine((data) => data.password === data.password_confirmation, {
    message: 'Passwords do not match.',
    path: ['password_confirmation'],
  })
export type PasswordFormValues = z.infer<typeof passwordFormSchema>

// Finance Settings Schema
export const financeSettingsFormSchema = z.object({
  default_currency: z.string().length(3, 'Currency code must be 3 characters'),
  default_exchange_rate_source: z.string().nullable(),
  fiscal_year_start: z.number().min(1).max(12),
  number_format: z.enum(['thousand_comma', 'thousand_dot', 'space_dot', 'space_comma']),
  default_smart_input_account_id: z.number().nullable(),
})
export type FinanceSettingsFormValues = z.infer<typeof financeSettingsFormSchema>

// Exchange rate sources constant
export const exchangeRateSources = [
  { label: 'Default (Best Available)', value: '__default__' },
  { label: 'Vietcombank', value: 'vietcombank' },
  { label: 'Payoneer', value: 'payoneer' },
  { label: 'ExchangeRate API', value: 'exchangerate_api' },
  { label: 'Open Exchange Rates', value: 'open_exchange_rates' },
] as const

// Number format options
export const numberFormatOptions = [
  { label: '1,234.56 (US/UK)', value: 'thousand_comma', preview: '1,234,567.89' },
  { label: '1.234,56 (EU)', value: 'thousand_dot', preview: '1.234.567,89' },
  { label: '1 234.56 (International)', value: 'space_dot', preview: '1 234 567.89' },
  { label: '1 234,56 (French)', value: 'space_comma', preview: '1 234 567,89' },
] as const

// Months constant
export const months = [
  { label: 'January', value: 1 },
  { label: 'February', value: 2 },
  { label: 'March', value: 3 },
  { label: 'April', value: 4 },
  { label: 'May', value: 5 },
  { label: 'June', value: 6 },
  { label: 'July', value: 7 },
  { label: 'August', value: 8 },
  { label: 'September', value: 9 },
  { label: 'October', value: 10 },
  { label: 'November', value: 11 },
  { label: 'December', value: 12 },
] as const

// Invoice Settings Schema
export const invoiceSettingsFormSchema = z.object({
  default_currency: z.string().length(3, 'Currency code must be 3 characters'),
  default_tax_rate: z.number().min(0).max(100),
  default_payment_terms: z.number().min(0).max(365),
  company_name: z.string().max(255).nullable(),
  company_address: z.string().max(1000).nullable(),
  company_email: z.string().email().max(255).nullable().or(z.literal('')),
  company_phone: z.string().max(50).nullable(),
})
export type InvoiceSettingsFormValues = z.infer<typeof invoiceSettingsFormSchema>

// Payment terms options
export const paymentTermsOptions = [
  { label: 'Due on Receipt', value: 0 },
  { label: 'Net 7', value: 7 },
  { label: 'Net 15', value: 15 },
  { label: 'Net 30', value: 30 },
  { label: 'Net 45', value: 45 },
  { label: 'Net 60', value: 60 },
  { label: 'Net 90', value: 90 },
] as const
