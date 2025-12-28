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
import { RadioGroup, RadioGroupItem } from '@/components/ui/radio-group'
import { useForm } from 'react-hook-form'
import { useToast } from '@/hooks/use-toast'
import { NotificationCategory, NotificationChannel, NotificationTemplate } from '@/types/notification'
import { PageProps } from '@/types'
import { useState } from 'react'

interface SendNotificationFormValues {
  recipient_type: 'users' | 'roles' | 'all'
  user_ids: number[]
  role_ids: number[]
  use_template: boolean
  template_id?: number
  title?: string
  message?: string
  category?: string
  channels: string[]
  action_url?: string
  action_label?: string
}

interface SendNotificationPageProps extends PageProps {
  templates: NotificationTemplate[]
  categories: NotificationCategory[]
  channels: NotificationChannel[]
  roles: { value: number; label: string }[]
}

export default function SendNotification({
  templates,
  categories,
  channels,
  roles,
}: SendNotificationPageProps) {
  const { toast } = useToast()
  const [isSubmitting, setIsSubmitting] = useState(false)
  const [userSearch, setUserSearch] = useState('')
  const [searchResults, setSearchResults] = useState<{ value: number; label: string; description: string }[]>([])
  const [selectedUsers, setSelectedUsers] = useState<{ value: number; label: string }[]>([])

  const form = useForm<SendNotificationFormValues>({
    defaultValues: {
      recipient_type: 'users',
      user_ids: [],
      role_ids: [],
      use_template: false,
      channels: ['database'],
    },
  })

  const useTemplate = form.watch('use_template')
  const recipientType = form.watch('recipient_type')

  const searchUsers = async (query: string) => {
    if (query.length < 2) {
      setSearchResults([])
      return
    }

    try {
      const response = await fetch(`/api/v1/notification/admin/users/search?q=${encodeURIComponent(query)}`)
      const data = await response.json()
      setSearchResults(data.users || [])
    } catch {
      setSearchResults([])
    }
  }

  const addUser = (user: { value: number; label: string }) => {
    if (!selectedUsers.find((u) => u.value === user.value)) {
      const newUsers = [...selectedUsers, user]
      setSelectedUsers(newUsers)
      form.setValue('user_ids', newUsers.map((u) => u.value))
    }
    setUserSearch('')
    setSearchResults([])
  }

  const removeUser = (userId: number) => {
    const newUsers = selectedUsers.filter((u) => u.value !== userId)
    setSelectedUsers(newUsers)
    form.setValue('user_ids', newUsers.map((u) => u.value))
  }

  async function onSubmit(data: SendNotificationFormValues) {
    setIsSubmitting(true)

    try {
      const response = await fetch('/api/v1/notification/admin/send', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json',
          'X-XSRF-TOKEN': decodeURIComponent(document.cookie.split('; ').find(row => row.startsWith('XSRF-TOKEN='))?.split('=')[1] || ''),
        },
        credentials: 'same-origin',
        body: JSON.stringify({
          ...data,
          action_url: data.action_url || null,
        }),
      })

      const result = await response.json()

      if (response.ok) {
        toast({ title: result.message || 'Notification sent successfully!' })
        form.reset()
        setSelectedUsers([])
      } else {
        toast({
          variant: 'destructive',
          title: 'Error sending notification',
          description: result.message || 'Something went wrong.',
        })
      }
    } catch (error) {
      toast({
        variant: 'destructive',
        title: 'Error sending notification',
        description: 'Network error. Please try again.',
      })
    } finally {
      setIsSubmitting(false)
    }
  }

  return (
    <AuthenticatedLayout title='Send Notification'>
      <Main>
        <div className='mx-auto max-w-2xl'>
          <Card>
            <CardHeader>
              <CardTitle>Send Notification</CardTitle>
              <CardDescription>
                Send notifications to users, roles, or broadcast to everyone.
              </CardDescription>
            </CardHeader>
            <CardContent>
              <Form {...form}>
                <form onSubmit={form.handleSubmit(onSubmit)} className='space-y-6'>
                  <FormField
                    control={form.control}
                    name='recipient_type'
                    render={({ field }) => (
                      <FormItem>
                        <FormLabel>Recipients</FormLabel>
                        <FormControl>
                          <RadioGroup
                            onValueChange={field.onChange}
                            defaultValue={field.value}
                            className='flex flex-col space-y-1'
                          >
                            <FormItem className='flex items-center space-x-3 space-y-0'>
                              <FormControl>
                                <RadioGroupItem value='users' />
                              </FormControl>
                              <FormLabel className='font-normal'>Specific Users</FormLabel>
                            </FormItem>
                            <FormItem className='flex items-center space-x-3 space-y-0'>
                              <FormControl>
                                <RadioGroupItem value='roles' />
                              </FormControl>
                              <FormLabel className='font-normal'>By Role</FormLabel>
                            </FormItem>
                            <FormItem className='flex items-center space-x-3 space-y-0'>
                              <FormControl>
                                <RadioGroupItem value='all' />
                              </FormControl>
                              <FormLabel className='font-normal'>All Users (Broadcast)</FormLabel>
                            </FormItem>
                          </RadioGroup>
                        </FormControl>
                        <FormMessage />
                      </FormItem>
                    )}
                  />

                  {recipientType === 'users' && (
                    <FormItem>
                      <FormLabel>Select Users</FormLabel>
                      <div className='space-y-2'>
                        <Input
                          placeholder='Search users by name or email...'
                          value={userSearch}
                          onChange={(e) => {
                            setUserSearch(e.target.value)
                            searchUsers(e.target.value)
                          }}
                        />
                        {searchResults.length > 0 && (
                          <div className='border rounded-md divide-y'>
                            {searchResults.map((user) => (
                              <div
                                key={user.value}
                                className='p-2 hover:bg-muted cursor-pointer'
                                onClick={() => addUser(user)}
                              >
                                <div className='font-medium'>{user.label}</div>
                                <div className='text-sm text-muted-foreground'>{user.description}</div>
                              </div>
                            ))}
                          </div>
                        )}
                        {selectedUsers.length > 0 && (
                          <div className='flex flex-wrap gap-2'>
                            {selectedUsers.map((user) => (
                              <div
                                key={user.value}
                                className='flex items-center gap-1 bg-secondary px-2 py-1 rounded-md text-sm'
                              >
                                {user.label}
                                <button
                                  type='button'
                                  onClick={() => removeUser(user.value)}
                                  className='hover:text-destructive'
                                >
                                  ×
                                </button>
                              </div>
                            ))}
                          </div>
                        )}
                      </div>
                    </FormItem>
                  )}

                  {recipientType === 'roles' && (
                    <FormField
                      control={form.control}
                      name='role_ids'
                      render={() => (
                        <FormItem>
                          <FormLabel>Select Roles</FormLabel>
                          <div className='grid grid-cols-2 gap-4'>
                            {roles.map((role) => (
                              <FormField
                                key={role.value}
                                control={form.control}
                                name='role_ids'
                                render={({ field }) => (
                                  <FormItem className='flex items-center space-x-3 space-y-0'>
                                    <FormControl>
                                      <Checkbox
                                        checked={field.value?.includes(role.value)}
                                        onCheckedChange={(checked) => {
                                          return checked
                                            ? field.onChange([...(field.value || []), role.value])
                                            : field.onChange(field.value?.filter((v) => v !== role.value))
                                        }}
                                      />
                                    </FormControl>
                                    <FormLabel className='font-normal'>{role.label}</FormLabel>
                                  </FormItem>
                                )}
                              />
                            ))}
                          </div>
                          <FormMessage />
                        </FormItem>
                      )}
                    />
                  )}

                  <FormField
                    control={form.control}
                    name='use_template'
                    render={({ field }) => (
                      <FormItem className='flex items-center justify-between rounded-lg border p-4'>
                        <div className='space-y-0.5'>
                          <FormLabel className='text-base'>Use Template</FormLabel>
                          <FormDescription>
                            Use a pre-defined template instead of custom content.
                          </FormDescription>
                        </div>
                        <FormControl>
                          <Switch checked={field.value} onCheckedChange={field.onChange} />
                        </FormControl>
                      </FormItem>
                    )}
                  />

                  {useTemplate ? (
                    <FormField
                      control={form.control}
                      name='template_id'
                      render={({ field }) => (
                        <FormItem>
                          <FormLabel>Template</FormLabel>
                          <Select
                            onValueChange={(value) => field.onChange(parseInt(value))}
                            defaultValue={field.value?.toString()}
                          >
                            <FormControl>
                              <SelectTrigger>
                                <SelectValue placeholder='Select a template' />
                              </SelectTrigger>
                            </FormControl>
                            <SelectContent>
                              {templates.map((template) => (
                                <SelectItem key={template.id} value={template.id.toString()}>
                                  {template.name}
                                </SelectItem>
                              ))}
                            </SelectContent>
                          </Select>
                          <FormMessage />
                        </FormItem>
                      )}
                    />
                  ) : (
                    <>
                      <FormField
                        control={form.control}
                        name='title'
                        render={({ field }) => (
                          <FormItem>
                            <FormLabel>Title</FormLabel>
                            <FormControl>
                              <Input placeholder='Notification title' {...field} />
                            </FormControl>
                            <FormMessage />
                          </FormItem>
                        )}
                      />

                      <FormField
                        control={form.control}
                        name='message'
                        render={({ field }) => (
                          <FormItem>
                            <FormLabel>Message</FormLabel>
                            <FormControl>
                              <Textarea
                                placeholder='Notification message...'
                                rows={4}
                                {...field}
                              />
                            </FormControl>
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
                            <div className='grid grid-cols-2 gap-4'>
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
                                              ? field.onChange([...(field.value || []), channel.value])
                                              : field.onChange(
                                                  field.value?.filter((v) => v !== channel.value)
                                                )
                                          }}
                                        />
                                      </FormControl>
                                      <FormLabel className='font-normal'>{channel.label}</FormLabel>
                                    </FormItem>
                                  )}
                                />
                              ))}
                            </div>
                            <FormMessage />
                          </FormItem>
                        )}
                      />
                    </>
                  )}

                  <FormField
                    control={form.control}
                    name='action_url'
                    render={({ field }) => (
                      <FormItem>
                        <FormLabel>Action URL (Optional)</FormLabel>
                        <FormControl>
                          <Input placeholder='https://example.com/path' {...field} />
                        </FormControl>
                        <FormDescription>Link for the notification action button.</FormDescription>
                        <FormMessage />
                      </FormItem>
                    )}
                  />

                  <FormField
                    control={form.control}
                    name='action_label'
                    render={({ field }) => (
                      <FormItem>
                        <FormLabel>Action Label (Optional)</FormLabel>
                        <FormControl>
                          <Input placeholder='View Details' {...field} />
                        </FormControl>
                        <FormDescription>Text for the action button.</FormDescription>
                        <FormMessage />
                      </FormItem>
                    )}
                  />

                  <Button type='submit' disabled={isSubmitting}>
                    {isSubmitting ? 'Sending...' : 'Send Notification'}
                  </Button>
                </form>
              </Form>
            </CardContent>
          </Card>
        </div>
      </Main>
    </AuthenticatedLayout>
  )
}
