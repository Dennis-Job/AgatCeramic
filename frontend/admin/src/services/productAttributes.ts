import { apiFetch, requestCsrfCookie } from './auth'
import type { Attribute } from './attributes'

export type ProductAttributeValue = { id: number; product_id: number; attribute_id: number; value: string | number | boolean | string[]; attribute: Attribute; created_at: string; updated_at: string }
export type ProductAttributePayload = { attributes: Array<{ attribute_id: number; value: string | number | boolean | string[] }> }

async function fail(response: Response): Promise<never> { const body = (await response.json().catch(() => ({}))) as { error?: { message?: string; details?: Record<string, string[]> } }; throw new Error(Object.values(body.error?.details ?? {}).flat()[0] ?? body.error?.message ?? 'Не удалось выполнить запрос.') }
export async function getCategoryAttributes(categoryId: number): Promise<Attribute[]> { const response = await apiFetch(`/admin/categories/${categoryId}/attributes`); if (!response.ok) return fail(response); return ((await response.json()) as { data: Attribute[] }).data }
export async function getProductAttributeValues(productId: number): Promise<ProductAttributeValue[]> { const response = await apiFetch(`/admin/products/${productId}/attributes`); if (!response.ok) return fail(response); return ((await response.json()) as { data: ProductAttributeValue[] }).data }
export async function saveProductAttributeValues(productId: number, payload: ProductAttributePayload): Promise<ProductAttributeValue[]> { await requestCsrfCookie(); const response = await apiFetch(`/admin/products/${productId}/attributes`, { method: 'PUT', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(payload) }); if (!response.ok) return fail(response); return ((await response.json()) as { data: ProductAttributeValue[] }).data }
