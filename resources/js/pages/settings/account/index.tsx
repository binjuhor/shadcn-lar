import { useTranslation } from 'react-i18next'
import { SettingLayout } from '@/layouts'
import SettingsProvider from '../context/settings-context'
import { AccountForm } from './account-form'
import { PasswordForm } from './password-form'
import { Separator } from '@/components/ui/separator'
import { ScrollArea } from '@/components/ui/scroll-area'

interface Props {
  settings?: {
    name?: string
    dob?: string
    language?: string
  }
}

export default function SettingsAccount({ settings }: Props) {
  const { t } = useTranslation()
  return (
    <SettingsProvider defaultTab='account'>
      <SettingLayout title={t('settings.account.title')}>
        <ScrollArea className='faded-bottom -mx-4 flex-1 scroll-smooth px-4 md:pb-16'>
          <div className='lg:max-w-xl space-y-8'>
            {/* Account Settings Section */}
            <section>
              <h3 className='text-lg font-medium'>{t('settings.account.title')}</h3>
              <p className='text-sm text-muted-foreground'>{t('settings.account.description')}</p>
              <Separator className='my-4' />
              <AccountForm settings={settings} />
            </section>

            {/* Password Section */}
            <section>
              <h3 className='text-lg font-medium'>{t('settings.password.title')}</h3>
              <p className='text-sm text-muted-foreground'>{t('settings.password.description')}</p>
              <Separator className='my-4' />
              <PasswordForm />
            </section>
          </div>
        </ScrollArea>
      </SettingLayout>
    </SettingsProvider>
  )
}
