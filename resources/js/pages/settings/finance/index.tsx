import { useTranslation } from 'react-i18next'
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

interface Account {
  id: number
  name: string
  account_type: string
  currency_code: string
}

interface Props {
  settings?: Partial<FinanceSettingsFormValues>
  currencies: Currency[]
  accounts: Account[]
}

export default function SettingsFinance({ settings, currencies, accounts = [] }: Props) {
  const { t } = useTranslation()
  return (
    <SettingsProvider defaultTab='finance'>
      <SettingLayout title={t('settings.finance.title')}>
        <ContentSection
          title={t('settings.finance.title')}
          desc={t('settings.finance.description')}
        >
          <FinanceForm settings={settings} currencies={currencies} accounts={accounts} />
        </ContentSection>
      </SettingLayout>
    </SettingsProvider>
  )
}
