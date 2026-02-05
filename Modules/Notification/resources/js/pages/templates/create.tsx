import { AuthenticatedLayout } from '@/layouts'
import { Main } from '@/components/layout'
import { Button } from '@/components/ui/button'
import {
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from '@/components/ui/card'
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
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select'
import { Checkbox } from '@/components/ui/checkbox'
import { Switch } from '@/components/ui/switch'
import { useForm } from 'react-hook-form'
import { router } from '@inertiajs/react'
import { useToast } from '@/hooks/use-toast'
import { NotificationCategory, NotificationChannel } from '@/types/notification'
import { PageProps } from '@/types'
import { useTranslation } from 'react-i18next'

interface TemplateFormValues {
  name: string
  subject: string
  body: string
  category: string
  channels: string[]
  variables: string
  is_active: boolean
}

interface CreateTemplatePageProps extends PageProps {
  categories: NotificationCategory[]
  channels: NotificationChannel[]
}

export default function CreateTemplate({ categories, channels }: CreateTemplatePageProps) {
  const { t } = useTranslation()
  const { toast } = useToast()

  const form = useForm<TemplateFormValues>({
    defaultValues: {
      name: '',
      subject: '',
      body: '',
      category: '',
      channels: ['database'],
      variables: '',
      is_active: true,
    },
  })

  function onSubmit(data: TemplateFormValues) {
    const variables = data.variables
      ? data.variables.split(',').map((v) => v.trim()).filter(Boolean)
      : []

    router.post(route('dashboard.notifications.templates.store'), {
      ...data,
      variables,
    }, {
      onSuccess: () => {
        toast({ title: t('common.messages.create_success') })
      },
      onError: (errors) => {
        toast({
          variant: 'destructive',
          title: t('common.messages.error'),
          description: Object.values(errors).flat().join(', '),
        })
      },
    })
  }

  return (
    <AuthenticatedLayout title={t('page.notifications.templates.create.title')}>
      <Main>
        <div className='mx-auto max-w-2xl'>
          <Card>
            <CardHeader>
              <CardTitle>{t('page.notifications.templates.create.title')}</CardTitle>
              <CardDescription>
                {t('page.notifications.templates.create.description')}
              </CardDescription>
            </CardHeader>
            <CardContent>
              <Form {...form}>
                <form onSubmit={form.handleSubmit(onSubmit)} className='space-y-6'>
                  <FormField
                    control={form.control}
                    name='name'
                    render={({ field }) => (
                      <FormItem>
                        <FormLabel>{t('common.fields.name')}</FormLabel>
                        <FormControl>
                          <Input placeholder={t('page.notifications.templates.create.name_placeholder')} {...field} />
                        </FormControl>
                        <FormDescription>{t('page.notifications.templates.create.name_description')}</FormDescription>
                        <FormMessage />
                      </FormItem>
                    )}
                  />

                  <FormField
                    control={form.control}
                    name='subject'
                    render={({ field }) => (
                      <FormItem>
                        <FormLabel>{t('page.notifications.templates.subject')}</FormLabel>
                        <FormControl>
                          <Input placeholder='Welcome to {{ app_name }}!' {...field} />
                        </FormControl>
                        <FormDescription>
                          {t('page.notifications.templates.create.subject_description')}
                        </FormDescription>
                        <FormMessage />
                      </FormItem>
                    )}
                  />

                  <FormField
                    control={form.control}
                    name='body'
                    render={({ field }) => (
                      <FormItem>
                        <FormLabel>{t('page.notifications.templates.create.body')}</FormLabel>
                        <FormControl>
                          <Textarea
                            placeholder='Hello {{ user_name }}, welcome to our platform!'
                            rows={6}
                            {...field}
                          />
                        </FormControl>
                        <FormDescription>
                          {t('page.notifications.templates.create.body_description')}
                        </FormDescription>
                        <FormMessage />
                      </FormItem>
                    )}
                  />

                  <FormField
                    control={form.control}
                    name='category'
                    render={({ field }) => (
                      <FormItem>
                        <FormLabel>{t('common.fields.category')}</FormLabel>
                        <Select onValueChange={field.onChange} defaultValue={field.value}>
                          <FormControl>
                            <SelectTrigger>
                              <SelectValue placeholder={t('page.notifications.templates.create.select_category')} />
                            </SelectTrigger>
                          </FormControl>
                          <SelectContent>
                            {categories.map((cat) => (
                              <SelectItem key={cat.value} value={cat.value}>
                                {cat.label}
                              </SelectItem>
                            ))}
                          </SelectContent>
                        </Select>
                        <FormMessage />
                      </FormItem>
                    )}
                  />

                  <FormField
                    control={form.control}
                    name='channels'
                    render={() => (
                      <FormItem>
                        <FormLabel>{t('page.notifications.templates.channels')}</FormLabel>
                        <FormDescription>
                          {t('page.notifications.templates.create.channels_description')}
                        </FormDescription>
                        <div className='grid grid-cols-2 gap-4 mt-2'>
                          {channels.map((channel) => (
                            <FormField
                              key={channel.value}
                              control={form.control}
                              name='channels'
                              render={({ field }) => (
                                <FormItem className='flex items-center space-x-3 space-y-0'>
                                  <FormControl>
                                    <Checkbox
                                      checked={field.value?.includes(channel.value)}
                                      onCheckedChange={(checked) => {
                                        return checked
                                          ? field.onChange([...field.value, channel.value])
                                          : field.onChange(
                                              field.value?.filter((v) => v !== channel.value)
                                            )
                                      }}
                                    />
                                  </FormControl>
                                  <FormLabel className='font-normal'>
                                    {channel.label}
                                    {channel.description && (
                                      <span className='block text-xs text-muted-foreground'>
                                        {channel.description}
                                      </span>
                                    )}
                                  </FormLabel>
                                </FormItem>
                              )}
                            />
                          ))}
                        </div>
                        <FormMessage />
                      </FormItem>
                    )}
                  />

                  <FormField
                    control={form.control}
                    name='variables'
                    render={({ field }) => (
                      <FormItem>
                        <FormLabel>{t('page.notifications.templates.create.variables')}</FormLabel>
                        <FormControl>
                          <Input placeholder='user_name, app_name, action_url' {...field} />
                        </FormControl>
                        <FormDescription>
                          {t('page.notifications.templates.create.variables_description')}
                        </FormDescription>
                        <FormMessage />
                      </FormItem>
                    )}
                  />

                  <FormField
                    control={form.control}
                    name='is_active'
                    render={({ field }) => (
                      <FormItem className='flex items-center justify-between rounded-lg border p-4'>
                        <div className='space-y-0.5'>
                          <FormLabel className='text-base'>{t('common.fields.active')}</FormLabel>
                          <FormDescription>
                            {t('page.notifications.templates.create.active_description')}
                          </FormDescription>
                        </div>
                        <FormControl>
                          <Switch checked={field.value} onCheckedChange={field.onChange} />
                        </FormControl>
                      </FormItem>
                    )}
                  />

                  <div className='flex gap-4'>
                    <Button type='submit'>{t('page.notifications.templates.create.create_button')}</Button>
                    <Button
                      type='button'
                      variant='outline'
                      onClick={() => router.get(route('dashboard.notifications.templates.index'))}
                    >
                      {t('common.actions.cancel')}
                    </Button>
                  </div>
                </form>
              </Form>
            </CardContent>
          </Card>
        </div>
      </Main>
    </AuthenticatedLayout>
  )
}
