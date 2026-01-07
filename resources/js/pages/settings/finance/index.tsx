import { SettingLayout } from '@/layouts'
import SettingsProvider from '../context/settings-context'
import ContentSection from '../components/content-section'
import { FinanceForm } from './finance-form'
import { type FinanceSettingsFormValues } from '../data/schema'

interface Currency {
  code: string
  name: string
  symbol: string
}

interface Props {
  settings?: Partial<FinanceSettingsFormValues>
  currencies: Currency[]
}

export default function SettingsFinance({ settings, currencies }: Props) {
  return (
    <SettingsProvider defaultTab='finance'>
      <SettingLayout title='Finance settings'>
        <ContentSection
          title='Finance Settings'
          desc='Configure your finance module preferences including currency, exchange rates, and number formatting.'
        >
          <FinanceForm settings={settings} currencies={currencies} />
        </ContentSection>
      </SettingLayout>
    </SettingsProvider>
  )
}
