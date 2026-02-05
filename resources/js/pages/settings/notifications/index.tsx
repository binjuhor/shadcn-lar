import { useTranslation } from 'react-i18next'
import { SettingLayout } from '@/layouts'
import SettingsProvider from '../context/settings-context'
import ContentSection from '../components/content-section'
import { NotificationsForm } from './notifications-form'
import { type NotificationsFormValues } from '../data/schema'

interface Props {
  settings?: Partial<NotificationsFormValues>
}

export default function SettingsNotifications({ settings }: Props) {
  const { t } = useTranslation()
  return (
    <SettingsProvider defaultTab='notifications'>
      <SettingLayout title={t('settings.notifications.title')}>
        <ContentSection
          title={t('settings.notifications.title')}
          desc={t('settings.notifications.description')}
        >
          <NotificationsForm settings={settings} />
        </ContentSection>
      </SettingLayout>
    </SettingsProvider>
  )
}
