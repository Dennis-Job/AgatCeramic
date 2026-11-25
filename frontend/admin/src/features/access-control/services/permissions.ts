import { apiData } from '../../../services/api'
import type { CataloguePermission } from '../types/access.types'

export async function getPermissionCatalogue(
  module?: string,
): Promise<CataloguePermission[]> {
  const query = module ? `?${new URLSearchParams({ module })}` : ''
  return apiData(`/admin/permissions${query}`, {
    errorMessage: 'Не удалось загрузить каталог прав.',
    errorMessageSource: 'fallback',
  })
}
