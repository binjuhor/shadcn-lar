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
        toast({ title: 'Template created successfully!' })
      },
      onError: (errors) => {
        toast({
          variant: 'destructive',
          title: 'Error creating template',
          description: Object.values(errors).flat().join(', '),
        })
      },
    })
  }

  return (
    <AuthenticatedLayout title='Create Template'>
      <Main>
        <div className='mx-auto max-w-2xl'>
          <Card>
            <CardHeader>
              <CardTitle>Create Notification Template</CardTitle>
              <CardDescription>
                Create a reusable notification template for sending to users.
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
                        <FormLabel>Name</FormLabel>
                        <FormControl>
                          <Input placeholder='Welcome Email' {...field} />
                        </FormControl>
                        <FormDescription>Internal name for this template.</FormDescription>
                        <FormMessage />
                      </FormItem>
                    )}
                  />

                  <FormField
                    control={form.control}
                    name='subject'
                    render={({ field }) => (
                      <FormItem>
                        <FormLabel>Subject</FormLabel>
                        <FormControl>
                          <Input placeholder='Welcome to {{ app_name }}!' {...field} />
                        </FormControl>
                        <FormDescription>
                          Use {"{{ variable }}"} syntax for dynamic content.
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
                        <FormLabel>Body</FormLabel>
                        <FormControl>
                          <Textarea
                            placeholder='Hello {{ user_name }}, welcome to our platform!'
                            rows={6}
                            {...field}
                          />
                        </FormControl>
                        <FormDescription>
                          The notification content. Use {"{{ variable }}"} for placeholders.
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
                        <FormLabel>Category</FormLabel>
                        <Select onValueChange={field.onChange} defaultValue={field.value}>
                          <FormControl>
                            <SelectTrigger>
                              <SelectValue placeholder='Select a category' />
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
                        <FormLabel>Channels</FormLabel>
                        <FormDescription>
                          Select which channels this notification will be sent through.
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
                        <FormLabel>Variables</FormLabel>
                        <FormControl>
                          <Input placeholder='user_name, app_name, action_url' {...field} />
                        </FormControl>
                        <FormDescription>
                          Comma-separated list of variable names used in this template.
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
                          <FormLabel className='text-base'>Active</FormLabel>
                          <FormDescription>
                            Template will be available for sending notifications.
                          </FormDescription>
                        </div>
                        <FormControl>
                          <Switch checked={field.value} onCheckedChange={field.onChange} />
                        </FormControl>
                      </FormItem>
                    )}
                  />

                  <div className='flex gap-4'>
                    <Button type='submit'>Create Template</Button>
                    <Button
                      type='button'
                      variant='outline'
                      onClick={() => router.get(route('dashboard.notifications.templates.index'))}
                    >
                      Cancel
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
