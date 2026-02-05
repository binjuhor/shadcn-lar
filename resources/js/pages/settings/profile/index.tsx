import { useTranslation } from 'react-i18next'
import { SettingLayout } from '@/layouts'
import SettingsProvider from '../context/settings-context'
import ContentSection from '../components/content-section'
import ProfileForm from './profile-form'
import { type ProfileFormValues } from '../data/schema'

interface Props {
  settings?: Partial<ProfileFormValues>
}

export default function SettingsProfile({ settings }: Props) {
  const { t } = useTranslation()
  return (
    <SettingsProvider defaultTab='profile'>
      <SettingLayout title={t('settings.profile.title')}>
        <ContentSection
          title={t('settings.profile.title')}
          desc={t('settings.profile.description')}
        >
          <ProfileForm settings={settings} />
        </ContentSection>
      </SettingLayout>
    </SettingsProvider>
  )
}
