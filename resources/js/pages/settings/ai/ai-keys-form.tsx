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
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select'
import {
  aiProviderNames,
  aiSettingsFormSchema,
  type AiProvider,
  type AiSettingsFormValues,
} from '../data/schema'

interface ProviderStatus {
  stored: boolean
  fallback: boolean
  configured: boolean
}

export type AiProviderStatuses = Record<AiProvider, ProviderStatus>

interface Props {
  providers: AiProviderStatuses
  selectedProvider: AiProvider
  ollama: {
    baseUrl: string
    model: string
  }
}

const emptyKeys = () =>
  Object.fromEntries(
    Object.keys(aiProviderNames).map((provider) => [provider, ''])
  ) as Record<AiProvider, string>

export function AiKeysForm({ providers, selectedProvider, ollama }: Props) {
  const { t } = useTranslation()
  const form = useForm<AiSettingsFormValues>({
    resolver: zodResolver(aiSettingsFormSchema),
    defaultValues: {
      provider: selectedProvider,
      ollama_base_url: ollama.baseUrl,
      ollama_model: ollama.model,
      keys: emptyKeys(),
      clear: [],
    },
  })

  const provider = form.watch('provider')
  const clear = form.watch('clear')
  const status = providers[provider]
  const willClear = clear.includes(provider)

  function toggleClear(target: AiProvider) {
    const current = form.getValues('clear')
    form.setValue(
      'clear',
      current.includes(target)
        ? current.filter((item) => item !== target)
        : [...current, target]
    )
  }

  function onSubmit(data: AiSettingsFormValues) {
    router.patch('/dashboard/settings/ai', {
      provider: data.provider,
      keys: data.keys,
      clear: data.clear,
      ...(data.provider === 'ollama'
        ? {
            ollama_base_url: data.ollama_base_url,
            ollama_model: data.ollama_model,
          }
        : {}),
    }, {
      preserveScroll: true,
      onSuccess: () => {
        form.reset({ ...form.getValues(), keys: emptyKeys(), clear: [] })
        toast({ title: t('settings.ai.update_success') })
      },
      onError: (errors) => {
        toast({
          title: t('settings.ai.update_error'),
          description: Object.values(errors).flat().join(', '),
          variant: 'destructive',
        })
      },
    })
  }

  function statusLabel(): string {
    if (willClear) return t('settings.ai.status_will_clear')
    if (status.stored) return t('settings.ai.status_database')
    if (status.fallback) return t('settings.ai.status_environment')
    if (provider === 'ollama') return t('settings.ai.status_optional')
    return t('settings.ai.status_missing')
  }

  return (
    <Form {...form}>
      <form onSubmit={form.handleSubmit(onSubmit)} className='space-y-8'>
        <FormField
          control={form.control}
          name='provider'
          render={({ field }) => (
            <FormItem>
              <FormLabel>{t('settings.ai.provider')}</FormLabel>
              <Select onValueChange={field.onChange} value={field.value}>
                <FormControl>
                  <SelectTrigger className='w-full max-w-md'>
                    <SelectValue />
                  </SelectTrigger>
                </FormControl>
                <SelectContent>
                  {(Object.keys(aiProviderNames) as AiProvider[]).map((option) => (
                    <SelectItem key={option} value={option}>
                      {aiProviderNames[option]}
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
              <FormDescription>
                {t('settings.ai.provider_description')}
              </FormDescription>
              <FormMessage />
            </FormItem>
          )}
        />

        {provider === 'ollama' && (
          <div className='grid gap-8 md:grid-cols-2'>
            <FormField
              control={form.control}
              name='ollama_base_url'
              render={({ field }) => (
                <FormItem>
                  <FormLabel>{t('settings.ai.ollama_base_url')}</FormLabel>
                  <FormControl>
                    <Input
                      type='url'
                      placeholder='http://127.0.0.1:11434'
                      {...field}
                    />
                  </FormControl>
                  <FormDescription>
                    {t('settings.ai.ollama_base_url_description')}
                  </FormDescription>
                  <FormMessage />
                </FormItem>
              )}
            />
            <FormField
              control={form.control}
              name='ollama_model'
              render={({ field }) => (
                <FormItem>
                  <FormLabel>{t('settings.ai.ollama_model')}</FormLabel>
                  <FormControl>
                    <Input placeholder='llama3.2' {...field} />
                  </FormControl>
                  <FormDescription>
                    {t('settings.ai.ollama_model_description')}
                  </FormDescription>
                  <FormMessage />
                </FormItem>
              )}
            />
          </div>
        )}

        <FormField
          control={form.control}
          name={`keys.${provider}`}
          render={({ field }) => (
            <FormItem>
              <div className='flex flex-wrap items-start justify-between gap-2'>
                <div className='space-y-1.5'>
                  <FormLabel>
                    {t('settings.ai.api_key_label', {
                      provider: aiProviderNames[provider],
                    })}
                  </FormLabel>
                  <FormDescription>{statusLabel()}</FormDescription>
                </div>
                {status.stored && (
                  <Button
                    type='button'
                    variant={willClear ? 'default' : 'outline'}
                    size='sm'
                    onClick={() => toggleClear(provider)}
                  >
                    {willClear
                      ? t('settings.ai.keep_key')
                      : t('settings.ai.clear_key')}
                  </Button>
                )}
              </div>
              <FormControl>
                <Input
                  type='password'
                  autoComplete='off'
                  disabled={willClear}
                  maxLength={4096}
                  placeholder={
                    status.stored
                      ? t('settings.ai.replace_placeholder')
                      : t('settings.ai.key_placeholder')
                  }
                  {...field}
                />
              </FormControl>
              <FormMessage />
            </FormItem>
          )}
        />

        <p className='text-sm text-muted-foreground'>
          {t('settings.ai.security_note')}
        </p>
        <Button type='submit'>{t('settings.ai.update')}</Button>
      </form>
    </Form>
  )
}
