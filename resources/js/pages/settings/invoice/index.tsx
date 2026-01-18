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
  return (
    <SettingsProvider defaultTab='invoice'>
      <SettingLayout title='Invoice settings'>
        <ContentSection
          title='Invoice Settings'
          desc='Configure your invoice defaults including currency, tax rate, payment terms, and company information.'
        >
          <InvoiceForm settings={settings} currencies={currencies} />
        </ContentSection>
      </SettingLayout>
    </SettingsProvider>
  )
}
