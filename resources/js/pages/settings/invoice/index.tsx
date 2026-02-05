import { useTranslation } from 'react-i18next'
import { SettingLayout } from '@/layouts'
import SettingsProvider from '../context/settings-context'
import ContentSection from '../components/content-section'
import { InvoiceForm } from './invoice-form'
import { type InvoiceSettingsFormValues } from '../data/schema'

interface Currency {
  code: string
  name: string
  symbol: string
}

interface Props {
  settings?: Partial<InvoiceSettingsFormValues>
  currencies: Currency[]
}

export default function SettingsInvoice({ settings, currencies }: Props) {
  const { t } = useTranslation()
  return (
    <SettingsProvider defaultTab='invoice'>
      <SettingLayout title={t('settings.invoice.title')}>
        <ContentSection
          title={t('settings.invoice.title')}
          desc={t('settings.invoice.description')}
        >
          <InvoiceForm settings={settings} currencies={currencies} />
        </ContentSection>
      </SettingLayout>
    </SettingsProvider>
  )
}
