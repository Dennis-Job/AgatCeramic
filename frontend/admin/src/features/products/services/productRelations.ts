import { apiFetch } from '../../../services/api'
import type {
  Product,
  ProductRelation,
  ProductRelationPayload,
} from '../types/product.types'
export async function getProductRelations(
  productId: number,
): Promise<ProductRelation[]> {
  const response = await apiFetch(`/admin/products/${productId}/relations`)
  return ((await response.json()) as { data: ProductRelation[] }).data
}
export async function getRelationCandidates(
  productId: number,
  search = '',
  limit = 20,
): Promise<Product[]> {
  const query = new URLSearchParams({ limit: String(limit) })
  if (search) query.set('search', search)
  const response = await apiFetch(
    `/admin/products/${productId}/relation-candidates?${query}`,
  )
  return ((await response.json()) as { data: Product[] }).data
}
export async function saveProductRelations(
  productId: number,
  payload: ProductRelationPayload,
): Promise<ProductRelation[]> {
  const response = await apiFetch(`/admin/products/${productId}/relations`, {
    method: 'PUT',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(payload),
  })
  return ((await response.json()) as { data: ProductRelation[] }).data
}
