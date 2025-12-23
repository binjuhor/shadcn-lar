import { SettingLayout } from '@/layouts'
import SettingsProvider from '../context/settings-context'
import ContentSection from '../components/content-section'
import { NotificationsForm } from './notifications-form'
import { type NotificationsFormValues } from '../data/schema'

interface Props {
  settings?: Partial<NotificationsFormValues>
}

export default function SettingsNotifications({ settings }: Props) {
  return (
    <SettingsProvider defaultTab='notifications'>
      <SettingLayout title='Notifications Settings'>
        <ContentSection
          title='Notifications'
          desc='Configure how you receive notifications.'
        >
          <NotificationsForm settings={settings} />
        </ContentSection>
      </SettingLayout>
    </SettingsProvider>
  )
}
