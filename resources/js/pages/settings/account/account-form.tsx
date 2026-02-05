import { useState } from 'react'
import { format } from 'date-fns'
import { useForm } from 'react-hook-form'
import { useTranslation } from 'react-i18next'
import { CalendarIcon, CaretSortIcon, CheckIcon } from '@radix-ui/react-icons'
import { zodResolver } from '@hookform/resolvers/zod'
import { router } from '@inertiajs/react'
import { cn } from '@/lib/utils'
import { toast } from '@/hooks/use-toast'
import { Button } from '@/components/ui/button'
import { Calendar } from '@/components/ui/calendar'
import {
  Command,
  CommandEmpty,
  CommandGroup,
  CommandInput,
  CommandItem,
  CommandList,
} from '@/components/ui/command'
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
import {
  Popover,
  PopoverContent,
  PopoverTrigger,
} from '@/components/ui/popover'
import { accountFormSchema, languages, type AccountFormValues } from '../data/schema'

interface Props {
  settings?: {
    name?: string
    dob?: string
    language?: string
  }
}

export function AccountForm({ settings }: Props) {
  const { t } = useTranslation()
  const [dobOpen, setDobOpen] = useState(false)
  const form = useForm<AccountFormValues>({
    resolver: zodResolver(accountFormSchema),
    defaultValues: {
      name: settings?.name ?? '',
      dob: settings?.dob ? new Date(settings.dob) : undefined,
      language: settings?.language ?? 'en',
    },
  })

  function onSubmit(data: AccountFormValues) {
    // Format date as YYYY-MM-DD string for backend
    const formattedData = {
      ...data,
      dob: data.dob ? format(data.dob, 'yyyy-MM-dd') : null,
    }

    router.patch('/dashboard/settings/account', formattedData, {
      preserveScroll: true,
      onSuccess: () => {
        toast({ title: t('settings.account.success') })
      },
      onError: (errors) => {
        toast({
          title: t('settings.account.error'),
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
          name='name'
          render={({ field }) => (
            <FormItem>
              <FormLabel>{t('settings.account.name')}</FormLabel>
              <FormControl>
                <Input placeholder={t('settings.account.name_placeholder')} {...field} />
              </FormControl>
              <FormDescription>
                {t('settings.account.name_description')}
              </FormDescription>
              <FormMessage />
            </FormItem>
          )}
        />
        <FormField
          control={form.control}
          name='dob'
          render={({ field }) => (
            <FormItem className='flex flex-col'>
              <FormLabel>{t('settings.account.dob')}</FormLabel>
              <Popover open={dobOpen} onOpenChange={setDobOpen}>
                <PopoverTrigger asChild>
                  <FormControl>
                    <Button
                      variant={'outline'}
                      className={cn(
                        'w-[240px] pl-3 text-left font-normal',
                        !field.value && 'text-muted-foreground'
                      )}
                    >
                      {field.value ? (
                        format(field.value, 'MMM d, yyyy')
                      ) : (
                        <span>{t('settings.account.pick_date')}</span>
                      )}
                      <CalendarIcon className='ml-auto h-4 w-4 opacity-50' />
                    </Button>
                  </FormControl>
                </PopoverTrigger>
                <PopoverContent className='w-auto p-0' align='start'>
                  <Calendar
                    mode='single'
                    selected={field.value}
                    defaultMonth={field.value}
                    onSelect={(date) => {
                      field.onChange(date)
                      setDobOpen(false)
                    }}
                    disabled={(date: Date) =>
                      date > new Date() || date < new Date('1900-01-01')
                    }
                    captionLayout="dropdown"
                    fromYear={1900}
                    toYear={new Date().getFullYear()}
                  />
                </PopoverContent>
              </Popover>
              <FormDescription>
                {t('settings.account.dob_description')}
              </FormDescription>
              <FormMessage />
            </FormItem>
          )}
        />
        <FormField
          control={form.control}
          name='language'
          render={({ field }) => (
            <FormItem className='flex flex-col'>
              <FormLabel>{t('settings.account.language')}</FormLabel>
              <Popover>
                <PopoverTrigger asChild>
                  <FormControl>
                    <Button
                      variant='outline'
                      role='combobox'
                      className={cn(
                        'w-[200px] justify-between',
                        !field.value && 'text-muted-foreground'
                      )}
                    >
                      {field.value
                        ? languages.find(
                            (language) => language.value === field.value
                          )?.label
                        : t('settings.account.select_language')}
                      <CaretSortIcon className='ml-2 h-4 w-4 shrink-0 opacity-50' />
                    </Button>
                  </FormControl>
                </PopoverTrigger>
                <PopoverContent className='w-[200px] p-0'>
                  <Command>
                    <CommandInput placeholder={t('settings.account.search_language')} />
                    <CommandEmpty>{t('settings.account.no_language')}</CommandEmpty>
                    <CommandGroup>
                      <CommandList>
                        {languages.map((language) => (
                          <CommandItem
                            value={language.label}
                            key={language.value}
                            onSelect={() => {
                              form.setValue('language', language.value)
                            }}
                          >
                            <CheckIcon
                              className={cn(
                                'mr-2 h-4 w-4',
                                language.value === field.value
                                  ? 'opacity-100'
                                  : 'opacity-0'
                              )}
                            />
                            {language.label}
                          </CommandItem>
                        ))}
                      </CommandList>
                    </CommandGroup>
                  </Command>
                </PopoverContent>
              </Popover>
              <FormDescription>
                {t('settings.account.language_description')}
              </FormDescription>
              <FormMessage />
            </FormItem>
          )}
        />
        <Button type='submit'>{t('settings.account.update')}</Button>
      </form>
    </Form>
  )
}
