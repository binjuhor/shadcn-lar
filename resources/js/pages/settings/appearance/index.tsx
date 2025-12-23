import { SettingLayout } from '@/layouts'
import SettingsProvider from '../context/settings-context'
import ContentSection from '../components/content-section'
import { AppearanceForm } from './appearance-form'
import { type AppearanceFormValues } from '../data/schema'

interface Props {
  settings?: Partial<AppearanceFormValues>
}

export default function SettingsAppearance({ settings }: Props) {
  return (
    <SettingsProvider defaultTab='appearance'>
      <SettingLayout title='Appearance settings'>
        <ContentSection
          title='Appearance'
          desc='Customize the appearance of the app. Automatically switch between day and night themes.'
        >
          <AppearanceForm settings={settings} />
        </ContentSection>
      </SettingLayout>
    </SettingsProvider>
  )
}
