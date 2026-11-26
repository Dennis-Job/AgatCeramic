import { apiFetch } from '../../../services/api'
import type {
  ProductGroup,
  ProductGroupPayload,
  ProductGroupSummary,
} from '../types/product.types'

export async function lookupProductGroups(
  search: string,
): Promise<ProductGroupSummary[]> {
  const query = new URLSearchParams({ search })
  const response = await apiFetch(`/admin/product-groups/lookup?${query}`)
  return ((await response.json()) as { data: ProductGroupSummary[] }).data
}

export async function getProductGroup(id: number): Promise<ProductGroup> {
  const response = await apiFetch(`/admin/product-groups/${id}`)
  return ((await response.json()) as { data: ProductGroup }).data
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
