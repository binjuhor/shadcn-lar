import { useTranslation } from 'react-i18next'
import { SettingLayout } from '@/layouts'
import SettingsProvider from '../context/settings-context'
import ContentSection from '../components/content-section'
import { DisplayForm } from './display-form'

export default function SettingsDisplay() {
  const { t } = useTranslation()
  return (
    <SettingsProvider defaultTab='display'>
      <SettingLayout title={t('settings.display.title')}>
        <ContentSection
          title={t('settings.display.title')}
          desc={t('settings.display.description')}
        >
          <DisplayForm />
        </ContentSection>
      </SettingLayout>
    </SettingsProvider>
  )
}
