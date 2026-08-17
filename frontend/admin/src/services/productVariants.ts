import { apiFetch, requestCsrfCookie } from './auth'
import type { Attribute } from './attributes'

export type ProductVariantAttributeValue = { id?: number; product_variant_id?: number; attribute_id: number; value: string | number | boolean | string[]; attribute?: Attribute }
export type ProductVariant = { id: number; product_id: number; name: string; sku: string; price: string; old_price: string | null; stock_quantity: number; is_active: boolean; sort_order: number; attribute_values: ProductVariantAttributeValue[]; created_at: string; updated_at: string }
export type ProductVariantPayload = { name: string; sku: string; price: string; old_price: string | null; stock_quantity: number; is_active: boolean; sort_order: number; attribute_values?: Array<{ attribute_id: number; value: string | number | boolean | string[] }> }

async function fail(response: Response): Promise<never> {
  const body = (await response.json().catch(() => ({}))) as { error?: { message?: string; details?: Record<string, string[]> } }
  throw new Error(Object.values(body.error?.details ?? {}).flat()[0] ?? body.error?.message ?? 'Не удалось выполнить запрос.')
}

export async function getProductVariants(productId: number): Promise<ProductVariant[]> { const response = await apiFetch(`/admin/products/${productId}/variants`); if (!response.ok) return fail(response); return ((await response.json()) as { data: ProductVariant[] }).data }
export async function saveProductVariant(productId: number, id: number | null, payload: ProductVariantPayload): Promise<ProductVariant> { await requestCsrfCookie(); const response = await apiFetch(id === null ? `/admin/products/${productId}/variants` : `/admin/products/${productId}/variants/${id}`, { method: id === null ? 'POST' : 'PATCH', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(payload) }); if (!response.ok) return fail(response); return ((await response.json()) as { data: ProductVariant }).data }
export async function deleteProductVariant(productId: number, id: number): Promise<void> { await requestCsrfCookie(); const response = await apiFetch(`/admin/products/${productId}/variants/${id}`, { method: 'DELETE' }); if (!response.ok) return fail(response) }
