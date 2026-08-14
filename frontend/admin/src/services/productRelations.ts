import { apiFetch, requestCsrfCookie } from './auth'
import type { Product } from './products'

export type ProductRelationType = 'related' | 'recommended'
export type ProductRelation = { id: number; product_id: number; related_product_id: number; type: ProductRelationType; sort_order: number; related_product: Product; created_at: string; updated_at: string }
export type ProductRelationPayload = { relations: Array<{ related_product_id: number; type: ProductRelationType; sort_order: number }> }
async function fail(response: Response): Promise<never> { const body = (await response.json().catch(() => ({}))) as { error?: { message?: string; details?: Record<string, string[]> } }; throw new Error(Object.values(body.error?.details ?? {}).flat()[0] ?? body.error?.message ?? 'Не удалось выполнить запрос.') }
export async function getProductRelations(productId: number): Promise<ProductRelation[]> { const response = await apiFetch(`/admin/products/${productId}/relations`); if (!response.ok) return fail(response); return ((await response.json()) as { data: ProductRelation[] }).data }
export async function saveProductRelations(productId: number, payload: ProductRelationPayload): Promise<ProductRelation[]> { await requestCsrfCookie(); const response = await apiFetch(`/admin/products/${productId}/relations`, { method: 'PUT', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(payload) }); if (!response.ok) return fail(response); return ((await response.json()) as { data: ProductRelation[] }).data }
