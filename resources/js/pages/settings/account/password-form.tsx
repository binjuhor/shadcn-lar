import { useForm } from 'react-hook-form'
import { useTranslation } from 'react-i18next'
import { zodResolver } from '@hookform/resolvers/zod'
import { router } from '@inertiajs/react'
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
import { passwordFormSchema, type PasswordFormValues } from '../data/schema'

export function PasswordForm() {
  const { t } = useTranslation()
  const form = useForm<PasswordFormValues>({
    resolver: zodResolver(passwordFormSchema),
    defaultValues: {
      current_password: '',
      password: '',
      password_confirmation: '',
    },
  })

  function onSubmit(data: PasswordFormValues) {
    router.put('/password', data, {
      preserveScroll: true,
      onSuccess: () => {
        form.reset()
        toast({ title: t('settings.password.success') })
      },
      onError: (errors) => {
        if (errors.current_password) {
          form.setError('current_password', { message: errors.current_password })
        }
        if (errors.password) {
          form.setError('password', { message: errors.password })
        }
        toast({
          title: t('settings.password.error'),
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
          name='current_password'
          render={({ field }) => (
            <FormItem>
              <FormLabel>{t('settings.password.current')}</FormLabel>
              <FormControl>
                <Input type='password' placeholder='••••••••' {...field} />
              </FormControl>
              <FormDescription>
                {t('settings.password.current_description')}
              </FormDescription>
              <FormMessage />
            </FormItem>
          )}
        />
        <FormField
          control={form.control}
          name='password'
          render={({ field }) => (
            <FormItem>
              <FormLabel>{t('settings.password.new')}</FormLabel>
              <FormControl>
                <Input type='password' placeholder='••••••••' {...field} />
              </FormControl>
              <FormDescription>
                {t('settings.password.new_description')}
              </FormDescription>
              <FormMessage />
            </FormItem>
          )}
        />
        <FormField
          control={form.control}
          name='password_confirmation'
          render={({ field }) => (
            <FormItem>
              <FormLabel>{t('settings.password.confirm')}</FormLabel>
              <FormControl>
                <Input type='password' placeholder='••••••••' {...field} />
              </FormControl>
              <FormMessage />
            </FormItem>
          )}
        />
        <Button type='submit'>{t('settings.password.update')}</Button>
      </form>
    </Form>
  )
}
