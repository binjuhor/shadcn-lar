import { SettingLayout } from '@/layouts'
import SettingsProvider from '../context/settings-context'
import ContentSection from '../components/content-section'
import ProfileForm from './profile-form'
import { type ProfileFormValues } from '../data/schema'

interface Props {
  settings?: Partial<ProfileFormValues>
}

export default function SettingsProfile({ settings }: Props) {
  return (
    <SettingsProvider defaultTab='profile'>
      <SettingLayout title='User profile'>
        <ContentSection
          title='Profile'
          desc='This is how others will see you on the site.'
        >
          <ProfileForm settings={settings} />
        </ContentSection>
      </SettingLayout>
    </SettingsProvider>
  )
}
