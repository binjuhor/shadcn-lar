import { useTranslation } from 'react-i18next'
import { SettingLayout } from '@/layouts'
import SettingsProvider from '../context/settings-context'
import ContentSection from '../components/content-section'
import { ModulesForm } from './modules-form'
import { type Module } from '@/types'

interface Props {
  modules: Module[]
}

export default function SettingsModules({ modules }: Props) {
  const { t } = useTranslation()
  return (
    <SettingsProvider defaultTab='modules'>
      <SettingLayout title={t('settings.modules.title')}>
        <ContentSection
          title={t('settings.modules.title')}
          desc={t('settings.modules.description')}
        >
          <ModulesForm modules={modules} />
        </ContentSection>
      </SettingLayout>
    </SettingsProvider>
  )
}
