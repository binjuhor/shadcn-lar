import { useForm } from 'react-hook-form'
import { useTranslation } from 'react-i18next'
import { ChevronDownIcon } from '@radix-ui/react-icons'
import { zodResolver } from '@hookform/resolvers/zod'
import { router } from '@inertiajs/react'
import { cn } from '@/lib/utils'
import { toast } from '@/hooks/use-toast'
import { Button, buttonVariants } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Textarea } from '@/components/ui/textarea'
import {
  Form,
  FormControl,
  FormDescription,
  FormField,
  FormItem,
  FormLabel,
  FormMessage,
} from '@/components/ui/form'
import {
  invoiceSettingsFormSchema,
  type InvoiceSettingsFormValues,
  paymentTermsOptions,
} from '../data/schema'

interface Currency {
  code: string
  name: string
  symbol: string
}

interface Props {
  settings?: Partial<InvoiceSettingsFormValues>
  currencies: Currency[]
}

export function InvoiceForm({ settings, currencies }: Props) {
  const { t } = useTranslation()
  const form = useForm<InvoiceSettingsFormValues>({
    resolver: zodResolver(invoiceSettingsFormSchema),
    defaultValues: {
      default_currency: settings?.default_currency ?? 'VND',
      default_tax_rate: settings?.default_tax_rate ?? 10,
      default_payment_terms: settings?.default_payment_terms ?? 30,
      company_name: settings?.company_name ?? '',
      company_address: settings?.company_address ?? '',
      company_email: settings?.company_email ?? '',
      company_phone: settings?.company_phone ?? '',
    },
  })

  function onSubmit(data: InvoiceSettingsFormValues) {
    // Convert empty strings to null for optional fields
    const cleanedData = {
      ...data,
      company_name: data.company_name || null,
      company_address: data.company_address || null,
      company_email: data.company_email || null,
      company_phone: data.company_phone || null,
    }

    router.patch('/dashboard/settings/invoice', cleanedData, {
      preserveScroll: true,
      onSuccess: () => {
        toast({ title: t('settings.invoice.update_success') })
      },
      onError: (errors) => {
        toast({
          title: t('settings.invoice.update_error'),
          description: Object.values(errors).flat().join(', '),
          variant: 'destructive',
        })
      },
    })
  }

  return (
    <Form {...form}>
      <form onSubmit={form.handleSubmit(onSubmit)} className='space-y-8'>
        <FormField
          control={form.control}
          name='default_currency'
          render={({ field }) => (
            <FormItem>
              <FormLabel>{t('settings.invoice.default_currency')}</FormLabel>
              <div className='relative w-max'>
                <FormControl>
                  <select
                    className={cn(
                      buttonVariants({ variant: 'outline' }),
                      'w-[280px] appearance-none font-normal'
                    )}
                    {...field}
                  >
                    {currencies.map((currency) => (
                      <option key={currency.code} value={currency.code}>
                        {currency.code} - {currency.name}
                      </option>
                    ))}
                  </select>
                </FormControl>
                <ChevronDownIcon className='absolute right-3 top-2.5 h-4 w-4 opacity-50' />
              </div>
              <FormDescription>
                {t('settings.invoice.default_currency_description')}
              </FormDescription>
              <FormMessage />
            </FormItem>
          )}
        />

        <FormField
          control={form.control}
          name='default_tax_rate'
          render={({ field }) => (
            <FormItem>
              <FormLabel>{t('settings.invoice.default_tax_rate')}</FormLabel>
              <FormControl>
                <Input
                  type='number'
                  min={0}
                  max={100}
                  step={0.01}
                  className='w-[200px]'
                  {...field}
                  onChange={(e) => field.onChange(parseFloat(e.target.value) || 0)}
                />
              </FormControl>
              <FormDescription>
                {t('settings.invoice.default_tax_rate_description')}
              </FormDescription>
              <FormMessage />
            </FormItem>
          )}
        />

        <FormField
          control={form.control}
          name='default_payment_terms'
          render={({ field }) => (
            <FormItem>
              <FormLabel>{t('settings.invoice.default_payment_terms')}</FormLabel>
              <div className='relative w-max'>
                <FormControl>
                  <select
                    className={cn(
                      buttonVariants({ variant: 'outline' }),
                      'w-[200px] appearance-none font-normal'
                    )}
                    value={field.value}
                    onChange={(e) => field.onChange(parseInt(e.target.value))}
                  >
                    {paymentTermsOptions.map((option) => (
                      <option key={option.value} value={option.value}>
                        {option.label}
                      </option>
                    ))}
                  </select>
                </FormControl>
                <ChevronDownIcon className='absolute right-3 top-2.5 h-4 w-4 opacity-50' />
              </div>
              <FormDescription>
                {t('settings.invoice.default_payment_terms_description')}
              </FormDescription>
              <FormMessage />
            </FormItem>
          )}
        />

        <div className='space-y-4 border-t pt-6'>
          <h3 className='text-lg font-medium'>{t('settings.invoice.company_info')}</h3>
          <p className='text-sm text-muted-foreground'>
            {t('settings.invoice.company_info_description')}
          </p>

          <FormField
            control={form.control}
            name='company_name'
            render={({ field }) => (
              <FormItem>
                <FormLabel>{t('settings.invoice.company_name')}</FormLabel>
                <FormControl>
                  <Input
                    className='max-w-md'
                    placeholder={t('settings.invoice.company_name_placeholder')}
                    {...field}
                    value={field.value ?? ''}
                  />
                </FormControl>
                <FormMessage />
              </FormItem>
            )}
          />

          <FormField
            control={form.control}
            name='company_address'
            render={({ field }) => (
              <FormItem>
                <FormLabel>{t('settings.invoice.company_address')}</FormLabel>
                <FormControl>
                  <Textarea
                    className='max-w-md'
                    placeholder={t('settings.invoice.company_address_placeholder')}
                    rows={3}
                    {...field}
                    value={field.value ?? ''}
                  />
                </FormControl>
                <FormMessage />
              </FormItem>
            )}
          />

          <div className='grid gap-4 md:grid-cols-2 max-w-md'>
            <FormField
              control={form.control}
              name='company_email'
              render={({ field }) => (
                <FormItem>
                  <FormLabel>{t('settings.invoice.company_email')}</FormLabel>
                  <FormControl>
                    <Input
                      type='email'
                      placeholder={t('settings.invoice.company_email_placeholder')}
                      {...field}
                      value={field.value ?? ''}
                    />
                  </FormControl>
                  <FormMessage />
                </FormItem>
              )}
            />

            <FormField
              control={form.control}
              name='company_phone'
              render={({ field }) => (
                <FormItem>
                  <FormLabel>{t('settings.invoice.company_phone')}</FormLabel>
                  <FormControl>
                    <Input
                      placeholder={t('settings.invoice.company_phone_placeholder')}
                      {...field}
                      value={field.value ?? ''}
                    />
                  </FormControl>
                  <FormMessage />
                </FormItem>
              )}
            />
          </div>
        </div>

        <Button type='submit'>{t('settings.invoice.update')}</Button>
      </form>
    </Form>
  )
}
