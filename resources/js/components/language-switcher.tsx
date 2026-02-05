import { useState } from 'react'
import { router, usePage } from '@inertiajs/react'
import { IconCheck, IconLanguage } from '@tabler/icons-react'
import { useTranslation } from 'react-i18next'
import { cn } from '@/lib/utils'
import { Button } from '@/components/ui/button'
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu'

const languages = [
  { code: 'en', label: 'English', flag: 'EN' },
  { code: 'vi', label: 'Tiếng Việt', flag: 'VI' },
]

export function LanguageSwitcher() {
  const { t } = useTranslation()
  const { props } = usePage()
  const currentLocale = (props.locale as string) || 'en'
  const [isLoading, setIsLoading] = useState(false)

  const handleLanguageChange = (languageCode: string) => {
    if (languageCode === currentLocale || isLoading) {
      return
    }

    setIsLoading(true)

    router.patch(
      '/language',
      { language: languageCode },
      {
        preserveScroll: true,
        onSuccess: () => {
          window.location.reload()
        },
        onError: () => {
          setIsLoading(false)
        },
      }
    )
  }

  const currentLanguage = languages.find((l) => l.code === currentLocale) || languages[0]

  return (
    <DropdownMenu modal={false}>
      <DropdownMenuTrigger asChild>
        <Button
          variant="ghost"
          size="icon"
          className="scale-95 rounded-full"
          disabled={isLoading}
        >
          <IconLanguage className="size-[1.2rem]" />
          <span className="sr-only">{t('language.switch')}</span>
        </Button>
      </DropdownMenuTrigger>
      <DropdownMenuContent align="end">
        {languages.map((language) => (
          <DropdownMenuItem
            key={language.code}
            onClick={() => handleLanguageChange(language.code)}
            disabled={isLoading}
          >
            <span className="mr-2 font-medium">{language.flag}</span>
            {language.label}
            <IconCheck
              size={14}
              className={cn(
                'ml-auto',
                currentLocale !== language.code && 'hidden'
              )}
            />
          </DropdownMenuItem>
        ))}
      </DropdownMenuContent>
    </DropdownMenu>
  )
}
