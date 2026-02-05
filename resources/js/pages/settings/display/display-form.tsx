import { useForm } from 'react-hook-form'
import { useTranslation } from 'react-i18next'
import { zodResolver } from '@hookform/resolvers/zod'
import { router } from '@inertiajs/react'
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
import { displayFormSchema, displayItems, type DisplayFormValues } from '../data/schema'

interface Props {
  settings?: Partial<DisplayFormValues>
}

export function DisplayForm({ settings }: Props) {
  const { t } = useTranslation()
  const form = useForm<DisplayFormValues>({
    resolver: zodResolver(displayFormSchema),
    defaultValues: {
      items: settings?.items ?? ['recents', 'home'],
    },
  })

  function onSubmit(data: DisplayFormValues) {
    router.patch('/dashboard/settings/display', data, {
      preserveScroll: true,
      onSuccess: () => {
        toast({ title: t('settings.display.update_success') })
      },
      onError: (errors) => {
        toast({
          title: t('settings.display.update_error'),
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
          name='items'
          render={() => (
            <FormItem>
              <div className='mb-4'>
                <FormLabel className='text-base'>{t('settings.display.sidebar')}</FormLabel>
                <FormDescription>
                  {t('settings.display.sidebar_description')}
                </FormDescription>
              </div>
              {displayItems.map((item) => (
                <FormField
                  key={item.id}
                  control={form.control}
                  name='items'
                  render={({ field }) => {
                    return (
                      <FormItem
                        key={item.id}
                        className='flex flex-row items-start space-x-3 space-y-0'
                      >
                        <FormControl>
                          <Checkbox
                            checked={field.value?.includes(item.id)}
                            onCheckedChange={(checked) => {
                              return checked
                                ? field.onChange([...field.value, item.id])
                                : field.onChange(
                                    field.value?.filter(
                                      (value) => value !== item.id
                                    )
                                  )
                            }}
                          />
                        </FormControl>
                        <FormLabel className='font-normal'>
                          {item.label}
                        </FormLabel>
                      </FormItem>
                    )
                  }}
                />
              ))}
              <FormMessage />
            </FormItem>
          )}
        />
        <Button type='submit'>{t('settings.display.update')}</Button>
      </form>
    </Form>
  )
}
