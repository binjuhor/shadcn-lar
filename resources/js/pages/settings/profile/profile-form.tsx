import { useFieldArray, useForm } from 'react-hook-form'
import { useTranslation } from 'react-i18next'
import { zodResolver } from '@hookform/resolvers/zod'
import { router } from '@inertiajs/react'
import { cn } from '@/lib/utils'
import { toast } from '@/hooks/use-toast'
import { Button } from '@/components/ui/button'
import {
  Form,
  FormControl,
  FormDescription,
  FormField,
  FormItem,
  FormLabel,
  FormMessage,
} from '@/components/ui/form'
import { Input } from '@/components/ui/input'
import { Textarea } from '@/components/ui/textarea'
import { profileFormSchema, type ProfileFormValues } from '../data/schema'

interface Props {
  settings?: Partial<ProfileFormValues>
}

export default function ProfileForm({ settings }: Props) {
  const { t } = useTranslation()
  const form = useForm<ProfileFormValues>({
    resolver: zodResolver(profileFormSchema),
    defaultValues: {
      username: settings?.username ?? '',
      email: settings?.email ?? '',
      bio: settings?.bio ?? '',
      urls: settings?.urls ?? [{ value: '' }],
    },
    mode: 'onChange',
  })

  const { fields, append } = useFieldArray({
    name: 'urls',
    control: form.control,
  })

  function onSubmit(data: ProfileFormValues) {
    router.patch('/dashboard/settings/profile', data, {
      preserveScroll: true,
      onSuccess: () => {
        toast({ title: t('settings.profile.update_success') })
      },
      onError: (errors) => {
        toast({
          title: t('settings.profile.update_error'),
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
          name='username'
          render={({ field }) => (
            <FormItem>
              <FormLabel>{t('settings.profile.username')}</FormLabel>
              <FormControl>
                <Input placeholder={t('settings.profile.username_placeholder')} {...field} />
              </FormControl>
              <FormDescription>
                {t('settings.profile.username_description')}
              </FormDescription>
              <FormMessage />
            </FormItem>
          )}
        />
        <FormField
          control={form.control}
          name='email'
          render={({ field }) => (
            <FormItem>
              <FormLabel>{t('settings.profile.email')}</FormLabel>
              <FormControl>
                <Input type='email' placeholder={t('settings.profile.email_placeholder')} {...field} />
              </FormControl>
              <FormDescription>
                {t('settings.profile.email_description')}
              </FormDescription>
              <FormMessage />
            </FormItem>
          )}
        />
        <FormField
          control={form.control}
          name='bio'
          render={({ field }) => (
            <FormItem>
              <FormLabel>{t('settings.profile.bio')}</FormLabel>
              <FormControl>
                <Textarea
                  placeholder={t('settings.profile.bio_placeholder')}
                  className='resize-none'
                  {...field}
                />
              </FormControl>
              <FormDescription>
                {t('settings.profile.bio_description')}
              </FormDescription>
              <FormMessage />
            </FormItem>
          )}
        />
        <div>
          {fields.map((field, index) => (
            <FormField
              control={form.control}
              key={field.id}
              name={`urls.${index}.value`}
              render={({ field }) => (
                <FormItem>
                  <FormLabel className={cn(index !== 0 && 'sr-only')}>
                    {t('settings.profile.urls')}
                  </FormLabel>
                  <FormDescription className={cn(index !== 0 && 'sr-only')}>
                    {t('settings.profile.urls_description')}
                  </FormDescription>
                  <FormControl>
                    <Input {...field} />
                  </FormControl>
                  <FormMessage />
                </FormItem>
              )}
            />
          ))}
          <Button
            type='button'
            variant='outline'
            size='sm'
            className='mt-2'
            onClick={() => append({ value: '' })}
          >
            {t('settings.profile.add_url')}
          </Button>
        </div>
        <Button type='submit'>{t('settings.profile.update')}</Button>
      </form>
    </Form>
  )
}
