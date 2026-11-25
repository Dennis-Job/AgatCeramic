import { apiFetch } from '../../../services/api'
import {
  loadAllPages,
  withPage,
  type PageRequest,
  type PaginatedResponse,
} from '../../../services/pagination'
import type { ProductGroup, ProductGroupPayload } from '../types/product.types'

export async function getProductGroups(
  request: PageRequest = {},
): Promise<PaginatedResponse<ProductGroup>> {
  const query = new URLSearchParams()
  withPage(query, request)
  const response = await apiFetch(
    `/admin/product-groups${query.size ? `?${query}` : ''}`,
  )
  return (await response.json()) as PaginatedResponse<ProductGroup>
}
export async function getAllProductGroups(): Promise<ProductGroup[]> {
  return loadAllPages(getProductGroups)
}
export async function saveProductGroup(
  id: number | null,
  payload: ProductGroupPayload,
): Promise<ProductGroup> {
  const response = await apiFetch(
    id === null ? '/admin/product-groups' : `/admin/product-groups/${id}`,
    {
      method: id === null ? 'POST' : 'PATCH',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload),
    },
  )
  return ((await response.json()) as { data: ProductGroup }).data
}
export async function deleteProductGroup(id: number): Promise<void> {
  await apiFetch(`/admin/product-groups/${id}`, {
    method: 'DELETE',
  })
}
