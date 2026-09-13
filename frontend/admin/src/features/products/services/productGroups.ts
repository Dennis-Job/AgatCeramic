import { apiFetch, requestCsrfCookie } from '../../../services/auth'
import { loadAllPages, withPage, type PageRequest, type PaginatedResponse } from '../../../services/pagination'
import type { ProductGroup, ProductGroupPayload } from '../types/product.types'

export class ProductGroupRequestError extends Error {
  readonly details: Record<string, string[]>
  constructor(message: string, details: Record<string, string[]>) { super(message); this.details = details }
}

async function fail(response: Response): Promise<never> {
  const body = (await response.json().catch(() => ({}))) as { error?: { message?: string; details?: Record<string, string[]> } }
  const details = body.error?.details ?? {}
  throw new ProductGroupRequestError(Object.values(details).flat()[0] ?? body.error?.message ?? 'Не удалось выполнить запрос.', details)
}

export async function getProductGroups(request: PageRequest = {}): Promise<PaginatedResponse<ProductGroup>> {
  const query = new URLSearchParams(); withPage(query, request)
  const response = await apiFetch(`/admin/product-groups${query.size ? `?${query}` : ''}`)
  if (!response.ok) return fail(response)
  return (await response.json()) as PaginatedResponse<ProductGroup>
}
export async function getAllProductGroups(): Promise<ProductGroup[]> { return loadAllPages(getProductGroups) }
export async function saveProductGroup(id: number | null, payload: ProductGroupPayload): Promise<ProductGroup> {
  await requestCsrfCookie()
  const response = await apiFetch(id === null ? '/admin/product-groups' : `/admin/product-groups/${id}`, {
    method: id === null ? 'POST' : 'PATCH', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(payload),
  })
  if (!response.ok) return fail(response)
  return ((await response.json()) as { data: ProductGroup }).data
}
export async function deleteProductGroup(id: number): Promise<void> {
  await requestCsrfCookie(); const response = await apiFetch(`/admin/product-groups/${id}`, { method: 'DELETE' }); if (!response.ok) return fail(response)
}
