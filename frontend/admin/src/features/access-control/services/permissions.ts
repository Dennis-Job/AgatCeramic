import { apiFetch } from '../../../services/auth'
import type { CataloguePermission } from '../types/access.types'

export async function getPermissionCatalogue(module?: string): Promise<CataloguePermission[]> {
  const query = module ? `?${new URLSearchParams({ module })}` : ''
  const response = await apiFetch(`/admin/permissions${query}`)
  if (!response.ok) throw new Error('Не удалось загрузить каталог прав.')
  return ((await response.json()) as { data: CataloguePermission[] }).data
}
