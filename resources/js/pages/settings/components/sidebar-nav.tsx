import { useState } from 'react'
import { Link, router } from '@inertiajs/react'
import { useTranslation } from 'react-i18next'
import { cn } from '@/lib/utils'
import { buttonVariants } from '@/components/ui/button'
import { ScrollArea } from '@/components/ui/scroll-area'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { type SettingsNavItem } from '@/pages/settings/data/nav-items'

interface SidebarNavProps extends React.HTMLAttributes<HTMLElement> {
  items: SettingsNavItem[]
}

export default function SidebarNav({
  className,
  items,
  ...props
}: SidebarNavProps) {
  const { t } = useTranslation()
  const [currentUrl, setCurrentUrl] = useState<string>(() => {
    return window.location.pathname
  })

  const getTitle = (item: SettingsNavItem) => {
    return item.titleKey ? t(item.titleKey, { defaultValue: item.title }) : item.title
  }

  const handleSelect = (e: string) => {
    setCurrentUrl(e)
    router.visit(e, {
      preserveScroll: true,
      preserveState: true
    })
  }

  return (
    <>
      <div className='p-1 md:hidden'>
        <Select value={currentUrl} onValueChange={handleSelect}>
          <SelectTrigger className='h-12 sm:w-48'>
            <SelectValue placeholder='Theme' />
          </SelectTrigger>
          <SelectContent>
            {items.map((item) => (
              <SelectItem key={item.href} value={item.href}>
                <div className='flex gap-x-4 px-2 py-1'>
                  <span className='scale-125'>{item.icon}</span>
                  <span className='text-md'>{getTitle(item)}</span>
                </div>
              </SelectItem>
            ))}
          </SelectContent>
        </Select>
      </div>

      <ScrollArea
        type='always'
        className='hidden w-full bg-background px-1 py-2 md:block min-w-40'
      >
        <nav
          className={cn(
            'flex py-1 space-x-2 lg:flex-col lg:space-x-0 lg:space-y-1',
            className
          )}
          {...props}
        >
          {items.map((item) => (
            <Link
              key={item.href}
              href={item.href}
              className={cn(
                buttonVariants({ variant: 'ghost' }),
                  currentUrl === item.href
                  ? 'bg-muted hover:bg-muted'
                  : 'hover:bg-transparent hover:underline',
                'justify-start'
              )}
            >
              <span className='mr-2'>{item.icon}</span>
              {getTitle(item)}
            </Link>
          ))}
        </nav>
      </ScrollArea>
    </>
  )
}
