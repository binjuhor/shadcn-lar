import { SettingLayout } from '@/layouts'
import SettingsProvider from '../context/settings-context'
import ContentSection from '../components/content-section'
import { ModulesForm } from './modules-form'
import { type Module } from '@/types'

interface Props {
  modules: Module[]
}

export default function SettingsModules({ modules }: Props) {
  return (
    <SettingsProvider defaultTab='modules'>
      <SettingLayout title='Modules Settings'>
        <ContentSection
          title='Modules'
          desc='Enable or disable system modules. Some modules are required and cannot be disabled.'
        >
          <ModulesForm modules={modules} />
        </ContentSection>
      </SettingLayout>
    </SettingsProvider>
  )
}
