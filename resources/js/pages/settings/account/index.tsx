import { SettingLayout } from '@/layouts'
import SettingsProvider from '../context/settings-context'
import ContentSection from '../components/content-section'
import { AccountForm } from './account-form'

interface Props {
  settings?: {
    name?: string
    dob?: string
    language?: string
  }
}

export default function SettingsAccount({ settings }: Props) {
  return (
    <SettingsProvider defaultTab='account'>
      <SettingLayout title='Account Settings'>
        <ContentSection
          title='Account'
          desc='Update your account settings. Set your preferred language and timezone.'
        >
          <AccountForm settings={settings} />
        </ContentSection>
      </SettingLayout>
    </SettingsProvider>
  )
}
