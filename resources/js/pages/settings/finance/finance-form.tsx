import { useForm } from 'react-hook-form'
import { useTranslation } from 'react-i18next'
import { ChevronDownIcon } from '@radix-ui/react-icons'
import { zodResolver } from '@hookform/resolvers/zod'
import { router } from '@inertiajs/react'
import { cn } from '@/lib/utils'
import { toast } from '@/hooks/use-toast'
import { Button, buttonVariants } from '@/components/ui/button'
import {
  Form,
  FormControl,
  FormDescription,
  FormField,
  FormItem,
  FormLabel,
  FormMessage,
} from '@/components/ui/form'
import { RadioGroup, RadioGroupItem } from '@/components/ui/radio-group'
import {
  financeSettingsFormSchema,
  type FinanceSettingsFormValues,
  exchangeRateSources,
  numberFormatOptions,
  months,
} from '../data/schema'

interface Currency {
  code: string
  name: string
  symbol: string
}

interface Account {
  id: number
  name: string
  account_type: string
  currency_code: string
}

interface Props {
  settings?: Partial<FinanceSettingsFormValues>
  currencies: Currency[]
  accounts: Account[]
}

export function FinanceForm({ settings, currencies, accounts }: Props) {
  const { t } = useTranslation()
  const form = useForm<FinanceSettingsFormValues>({
    resolver: zodResolver(financeSettingsFormSchema),
    defaultValues: {
      default_currency: settings?.default_currency ?? 'VND',
      default_exchange_rate_source: settings?.default_exchange_rate_source ?? '__default__',
      fiscal_year_start: settings?.fiscal_year_start ?? 1,
      number_format: settings?.number_format ?? 'thousand_comma',
      default_smart_input_account_id: settings?.default_smart_input_account_id ?? null,
    },
  })

  function onSubmit(data: FinanceSettingsFormValues) {
    router.patch('/dashboard/settings/finance', data, {
      preserveScroll: true,
      onSuccess: () => {
        toast({ title: t('settings.finance.update_success') })
      },
      onError: (errors) => {
        toast({
          title: t('settings.finance.update_error'),
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
              <FormLabel>{t('settings.finance.default_currency')}</FormLabel>
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
                {t('settings.finance.default_currency_description')}
              </FormDescription>
              <FormMessage />
            </FormItem>
          )}
        />

        <FormField
          control={form.control}
          name='default_exchange_rate_source'
          render={({ field }) => (
            <FormItem>
              <FormLabel>{t('settings.finance.exchange_rate_source')}</FormLabel>
              <div className='relative w-max'>
                <FormControl>
                  <select
                    className={cn(
                      buttonVariants({ variant: 'outline' }),
                      'w-[280px] appearance-none font-normal'
                    )}
                    value={field.value ?? '__default__'}
                    onChange={(e) => field.onChange(e.target.value === '__default__' ? null : e.target.value)}
                  >
                    {exchangeRateSources.map((source) => (
                      <option key={source.value} value={source.value}>
                        {source.label}
                      </option>
                    ))}
                  </select>
                </FormControl>
                <ChevronDownIcon className='absolute right-3 top-2.5 h-4 w-4 opacity-50' />
              </div>
              <FormDescription>
                {t('settings.finance.exchange_rate_source_description')}
              </FormDescription>
              <FormMessage />
            </FormItem>
          )}
        />

        <FormField
          control={form.control}
          name='fiscal_year_start'
          render={({ field }) => (
            <FormItem>
              <FormLabel>{t('settings.finance.fiscal_year_start')}</FormLabel>
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
                    {months.map((month) => (
                      <option key={month.value} value={month.value}>
                        {month.label}
                      </option>
                    ))}
                  </select>
                </FormControl>
                <ChevronDownIcon className='absolute right-3 top-2.5 h-4 w-4 opacity-50' />
              </div>
              <FormDescription>
                {t('settings.finance.fiscal_year_start_description')}
              </FormDescription>
              <FormMessage />
            </FormItem>
          )}
        />

        <FormField
          control={form.control}
          name='number_format'
          render={({ field }) => (
            <FormItem className='space-y-1'>
              <FormLabel>{t('settings.finance.number_format')}</FormLabel>
              <FormDescription>
                {t('settings.finance.number_format_description')}
              </FormDescription>
              <FormMessage />
              <RadioGroup
                onValueChange={field.onChange}
                defaultValue={field.value}
                className='grid max-w-xl md:grid-cols-2 gap-4 pt-2'
              >
                {numberFormatOptions.map((option) => (
                  <FormItem key={option.value}>
                    <FormLabel className='[&:has([data-state=checked])>div]:border-primary cursor-pointer'>
                      <FormControl>
                        <RadioGroupItem value={option.value} className='sr-only' />
                      </FormControl>
                      <div className='rounded-md border-2 border-muted p-4 hover:border-accent'>
                        <div className='text-sm font-medium'>{option.label}</div>
                        <div className='mt-1 text-2xl font-semibold text-muted-foreground'>
                          {option.preview}
                        </div>
                      </div>
                    </FormLabel>
                  </FormItem>
                ))}
              </RadioGroup>
            </FormItem>
          )}
        />

        {accounts.length > 0 && (
          <FormField
            control={form.control}
            name='default_smart_input_account_id'
            render={({ field }) => (
              <FormItem>
                <FormLabel>{t('settings.finance.default_smart_input_account')}</FormLabel>
                <div className='relative w-max'>
                  <FormControl>
                    <select
                      className={cn(
                        buttonVariants({ variant: 'outline' }),
                        'w-[280px] appearance-none font-normal'
                      )}
                      value={field.value ?? ''}
                      onChange={(e) => field.onChange(e.target.value ? parseInt(e.target.value) : null)}
                    >
                      <option value=''>{t('settings.finance.no_default_account')}</option>
                      {accounts.map((account) => (
                        <option key={account.id} value={account.id}>
                          {account.name} ({account.currency_code})
                        </option>
                      ))}
                    </select>
                  </FormControl>
                  <ChevronDownIcon className='absolute right-3 top-2.5 h-4 w-4 opacity-50' />
                </div>
                <FormDescription>
                  {t('settings.finance.default_smart_input_account_description')}
                </FormDescription>
                <FormMessage />
              </FormItem>
            )}
          />
        )}

        <Button type='submit'>{t('settings.finance.update')}</Button>
      </form>
    </Form>
  )
}
