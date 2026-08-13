import { apiFetch } from './auth'

export type AssignedRole = { id: number; name: string; slug: string }
export type CataloguePermission = {
  id: number
  name: string
  code: string
  description: string | null
  roles: AssignedRole[]
}

export async function getPermissionCatalogue(module?: string): Promise<CataloguePermission[]> {
  const query = module ? `?${new URLSearchParams({ module })}` : ''
  const response = await apiFetch(`/admin/permissions${query}`)

  if (!response.ok) throw new Error('Не удалось загрузить каталог прав.')

  return ((await response.json()) as { data: CataloguePermission[] }).data
}
