import { SettingLayout } from '@/layouts'
import SettingsProvider from '../context/settings-context'
import ContentSection from '../components/content-section'
import { DisplayForm } from './display-form'

export default function SettingsDisplay() {
  return (
    <SettingsProvider defaultTab='display'>
      <SettingLayout title='Display Settings'>
        <ContentSection
          title='Display'
          desc="Turn items on or off to control what's displayed in the app."
        >
          <DisplayForm />
        </ContentSection>
      </SettingLayout>
    </SettingsProvider>
  )
}
