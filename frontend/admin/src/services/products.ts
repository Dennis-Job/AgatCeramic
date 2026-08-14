import { apiFetch, requestCsrfCookie } from './auth'
import type { Brand } from './brands'
import type { Category } from './categories'

export type Product = {
  id: number; category_id: number; brand_id: number | null; name: string; slug: string; description: string | null; is_active: boolean
  category: Category; brand: Brand | null; created_at: string; updated_at: string
}
export type ProductPayload = Omit<Product, 'id' | 'category' | 'brand' | 'created_at' | 'updated_at'>
export type ProductFilters = { search?: string; category_id?: number; brand_id?: number; is_active?: boolean; has_stock?: boolean; price_from?: string; price_to?: string }

async function fail(response: Response): Promise<never> {
  const body = (await response.json().catch(() => ({}))) as { error?: { message?: string; details?: Record<string, string[]> } }
  throw new Error(Object.values(body.error?.details ?? {}).flat()[0] ?? body.error?.message ?? 'Не удалось выполнить запрос.')
}
export async function getProducts(filters: ProductFilters = {}): Promise<Product[]> { const query = new URLSearchParams(); Object.entries(filters).forEach(([key, value]) => { if (value !== undefined && value !== '') query.set(key, typeof value === 'boolean' ? (value ? '1' : '0') : String(value)) }); const response = await apiFetch(`/admin/products${query.size ? `?${query}` : ''}`); if (!response.ok) return fail(response); return ((await response.json()) as { data: Product[] }).data }
export async function saveProduct(id: number | null, payload: ProductPayload): Promise<Product> { await requestCsrfCookie(); const response = await apiFetch(id === null ? '/admin/products' : `/admin/products/${id}`, { method: id === null ? 'POST' : 'PATCH', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(payload) }); if (!response.ok) return fail(response); return ((await response.json()) as { data: Product }).data }
export async function deleteProduct(id: number): Promise<void> { await requestCsrfCookie(); const response = await apiFetch(`/admin/products/${id}`, { method: 'DELETE' }); if (!response.ok) return fail(response) }
