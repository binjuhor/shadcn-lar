import { useForm } from 'react-hook-form'
import { useTranslation } from 'react-i18next'
import { zodResolver } from '@hookform/resolvers/zod'
import { Link, router } from '@inertiajs/react'
import { toast } from '@/hooks/use-toast'
import { Button } from '@/components/ui/button'
import { Checkbox } from '@/components/ui/checkbox'
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
import { Switch } from '@/components/ui/switch'
import { notificationsFormSchema, type NotificationsFormValues } from '../data/schema'

interface Props {
  settings?: Partial<NotificationsFormValues>
}

export function NotificationsForm({ settings }: Props) {
  const { t } = useTranslation()
  const form = useForm<NotificationsFormValues>({
    resolver: zodResolver(notificationsFormSchema),
    defaultValues: {
      type: settings?.type ?? 'all',
      mobile: settings?.mobile ?? false,
      communication_emails: settings?.communication_emails ?? false,
      social_emails: settings?.social_emails ?? true,
      marketing_emails: settings?.marketing_emails ?? false,
      security_emails: settings?.security_emails ?? true,
    },
  })

  function onSubmit(data: NotificationsFormValues) {
    router.patch('/dashboard/settings/notifications', data, {
      preserveScroll: true,
      onSuccess: () => {
        toast({ title: t('settings.notifications.update_success') })
      },
      onError: (errors) => {
        toast({
          title: t('settings.notifications.update_error'),
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
          name='type'
          render={({ field }) => (
            <FormItem className='space-y-3 relative'>
              <FormLabel>{t('settings.notifications.notify_about')}</FormLabel>
              <FormControl>
                <RadioGroup
                  onValueChange={field.onChange}
                  defaultValue={field.value}
                  className='flex flex-col space-y-1'
                >
                  <FormItem className='flex items-center space-x-3 space-y-0'>
                    <FormControl>
                      <RadioGroupItem value='all' />
                    </FormControl>
                    <FormLabel className='font-normal'>
                      {t('settings.notifications.all_messages')}
                    </FormLabel>
                  </FormItem>
                  <FormItem className='flex items-center space-x-3 space-y-0'>
                    <FormControl>
                      <RadioGroupItem value='mentions' />
                    </FormControl>
                    <FormLabel className='font-normal'>
                      {t('settings.notifications.direct_mentions')}
                    </FormLabel>
                  </FormItem>
                  <FormItem className='flex items-center space-x-3 space-y-0'>
                    <FormControl>
                      <RadioGroupItem value='none' />
                    </FormControl>
                    <FormLabel className='font-normal'>{t('settings.notifications.nothing')}</FormLabel>
                  </FormItem>
                </RadioGroup>
              </FormControl>
              <FormMessage />
            </FormItem>
          )}
        />
        <div className='relative'>
          <h3 className='mb-4 text-lg font-medium'>{t('settings.notifications.email_notifications')}</h3>
          <div className='space-y-4'>
            <FormField
              control={form.control}
              name='communication_emails'
              render={({ field }) => (
                <FormItem className='flex flex-row items-center justify-between rounded-lg border p-4'>
                  <div className='space-y-0.5'>
                    <FormLabel className='text-base'>
                      {t('settings.notifications.communication_emails')}
                    </FormLabel>
                    <FormDescription>
                      {t('settings.notifications.communication_emails_description')}
                    </FormDescription>
                  </div>
                  <FormControl>
                    <Switch
                      checked={field.value}
                      onCheckedChange={field.onChange}
                    />
                  </FormControl>
                </FormItem>
              )}
            />
            <FormField
              control={form.control}
              name='marketing_emails'
              render={({ field }) => (
                <FormItem className='flex flex-row items-center justify-between rounded-lg border p-4'>
                  <div className='space-y-0.5'>
                    <FormLabel className='text-base'>
                      {t('settings.notifications.marketing_emails')}
                    </FormLabel>
                    <FormDescription>
                      {t('settings.notifications.marketing_emails_description')}
                    </FormDescription>
                  </div>
                  <FormControl>
                    <Switch
                      checked={field.value}
                      onCheckedChange={field.onChange}
                    />
                  </FormControl>
                </FormItem>
              )}
            />
            <FormField
              control={form.control}
              name='social_emails'
              render={({ field }) => (
                <FormItem className='flex flex-row items-center justify-between rounded-lg border p-4'>
                  <div className='space-y-0.5'>
                    <FormLabel className='text-base'>{t('settings.notifications.social_emails')}</FormLabel>
                    <FormDescription>
                      {t('settings.notifications.social_emails_description')}
                    </FormDescription>
                  </div>
                  <FormControl>
                    <Switch
                      checked={field.value}
                      onCheckedChange={field.onChange}
                    />
                  </FormControl>
                </FormItem>
              )}
            />
            <FormField
              control={form.control}
              name='security_emails'
              render={({ field }) => (
                <FormItem className='flex flex-row items-center justify-between rounded-lg border p-4'>
                  <div className='space-y-0.5'>
                    <FormLabel className='text-base'>{t('settings.notifications.security_emails')}</FormLabel>
                    <FormDescription>
                      {t('settings.notifications.security_emails_description')}
                    </FormDescription>
                  </div>
                  <FormControl>
                    <Switch
                      checked={field.value}
                      onCheckedChange={field.onChange}
                      disabled
                      aria-readonly
                    />
                  </FormControl>
                </FormItem>
              )}
            />
          </div>
        </div>
        <FormField
          control={form.control}
          name='mobile'
          render={({ field }) => (
            <FormItem className='flex flex-row items-start space-x-3 space-y-0 relative'>
              <FormControl>
                <Checkbox
                  checked={field.value}
                  onCheckedChange={field.onChange}
                />
              </FormControl>
              <div className='space-y-1 leading-none'>
                <FormLabel>
                  {t('settings.notifications.mobile_settings')}
                </FormLabel>
                <FormDescription>
                  {t('settings.notifications.mobile_settings_description')}{' '}
                  <Link
                    href='/settings'
                    className='underline decoration-dashed underline-offset-4 hover:decoration-solid'
                  >
                    {t('settings.notifications.mobile_settings_link')}
                  </Link>
                  .
                </FormDescription>
              </div>
            </FormItem>
          )}
        />
        <Button type='submit'>{t('settings.notifications.update')}</Button>
      </form>
    </Form>
  )
}
