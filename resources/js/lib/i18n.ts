import i18n from 'i18next'
import { initReactI18next } from 'react-i18next'

let initialized = false

export function initI18n(
  locale: string,
  translations: Record<string, string>
): typeof i18n {
  if (initialized) {
    if (i18n.language !== locale) {
      i18n.changeLanguage(locale)
      i18n.addResourceBundle(locale, 'translation', translations, true, true)
    }
    return i18n
  }

  i18n.use(initReactI18next).init({
    lng: locale,
    fallbackLng: 'en',
    resources: {
      [locale]: {
        translation: translations,
      },
    },
    interpolation: {
      escapeValue: false,
    },
    react: {
      useSuspense: false,
    },
  })

  initialized = true
  return i18n
}

export function isModuleEnabled(
  enabledModules: string[],
  moduleName: string
): boolean {
  return enabledModules.includes(moduleName)
}

export { i18n }
