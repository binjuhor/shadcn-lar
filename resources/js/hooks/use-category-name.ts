import { useTranslation } from 'react-i18next'

interface CategoryWithNameKey {
  name: string
  name_key?: string | null
}

export function useCategoryName() {
  const { t } = useTranslation()

  return (category: CategoryWithNameKey): string => {
    if (category.name_key) {
      return t(category.name_key, { defaultValue: category.name })
    }
    return category.name
  }
}
