import { useTranslation } from 'react-i18next'
import { SettingLayout } from '@/layouts'
import ContentSection from '../components/content-section'
import SettingsProvider from '../context/settings-context'
import { AiKeysForm, type AiProviderStatuses } from './ai-keys-form'

interface Props {
  providers: AiProviderStatuses
  selectedProvider: keyof AiProviderStatuses
  ollama: {
    baseUrl: string
    model: string
  }
}

export default function SettingsAi({ providers, selectedProvider, ollama }: Props) {
  const { t } = useTranslation()

  return (
    <SettingsProvider defaultTab='ai'>
      <SettingLayout title={t('settings.ai.title')}>
        <ContentSection title={t('settings.ai.title')} desc={t('settings.ai.description')}>
          <AiKeysForm providers={providers} selectedProvider={selectedProvider} ollama={ollama} />
        </ContentSection>
      </SettingLayout>
    </SettingsProvider>
  )
}
