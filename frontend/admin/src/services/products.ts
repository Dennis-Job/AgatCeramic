import { apiFetch, requestCsrfCookie } from './auth'
import type { Brand } from './brands'
import type { Category } from './categories'
import type { ProductAttributeValue } from './productAttributes'
import { loadAllPages, withPage, type PageRequest, type PaginatedResponse } from './pagination'

export type Product = {
  id: number; category_id: number; brand_id: number | null; name: string; slug: string; description: string | null
  sku: string; article_number: string | null; barcode: string | null; unit: ProductUnit; price: string; old_price: string | null; stock_quantity: number; is_active: boolean; is_on_sale: boolean
  attribute_values?: ProductAttributeValue[]
  primary_image?: { id: number; url: string; alt: string | null } | null
  category: Category; brand: Brand | null; created_at: string; updated_at: string
}
export type ProductUnit = 'piece' | 'square_meter' | 'linear_meter' | 'package' | 'kilogram' | 'liter' | 'set'
export type ProductSort = 'sku' | 'name' | 'created_at' | 'updated_at'
export type SortDirection = 'asc' | 'desc'
export type ProductImport = {
  id: number
  filename: string
  status: 'pending' | 'processing' | 'completed' | 'failed'
  created_rows: number
  updated_rows: number
  processed_rows: number
  category_id: number | null
  total_rows: number
  failed_rows: number
  row_errors: { row: number; name: string; messages: string[] }[]
  has_error_file: boolean
  error_message: string | null
  created_at: string
  started_at: string | null
  completed_at: string | null
}
export type ProductPayload = Omit<Product, 'id' | 'sku' | 'category' | 'brand' | 'attribute_values' | 'primary_image' | 'created_at' | 'updated_at'>
export type ProductFilters = { search?: string; category_id?: number; brand_id?: number; is_active?: boolean; is_on_sale?: boolean; has_stock?: boolean; price_from?: string; price_to?: string; sort?: ProductSort; direction?: SortDirection } & PageRequest

function productQuery(filters: ProductFilters, includePage: boolean): URLSearchParams {
  const query = new URLSearchParams()
  Object.entries(filters).forEach(([key, value]) => {
    if (key !== 'page' && key !== 'perPage' && value !== undefined && value !== '') query.set(key, typeof value === 'boolean' ? (value ? '1' : '0') : String(value))
  })
  if (includePage) withPage(query, filters)
  return query
}

async function fail(response: Response): Promise<never> {
  const body = (await response.json().catch(() => ({}))) as { error?: { message?: string; details?: Record<string, string[]> } }
  throw new Error(Object.values(body.error?.details ?? {}).flat()[0] ?? body.error?.message ?? 'Не удалось выполнить запрос.')
}
export async function getProducts(filters: ProductFilters = {}): Promise<PaginatedResponse<Product>> { const query = productQuery(filters, true); const response = await apiFetch(`/admin/products${query.size ? `?${query}` : ''}`); if (!response.ok) return fail(response); return (await response.json()) as PaginatedResponse<Product> }
export async function getAllProducts(filters: Omit<ProductFilters, 'page' | 'perPage'> = {}): Promise<Product[]> { return loadAllPages((request) => getProducts({ ...filters, ...request })) }
export async function getProductExport(filters: Omit<ProductFilters, 'page' | 'perPage'> = {}): Promise<{ blob: Blob; filename: string }> {
  const query = productQuery(filters, false)
  const response = await apiFetch(`/admin/products/export${query.size ? `?${query}` : ''}`)
  if (!response.ok) return fail(response)
  const disposition = response.headers.get('Content-Disposition') ?? ''
  const filename = disposition.match(/filename="?([^";]+)"?/i)?.[1] ?? 'products.xlsx'
  return { blob: await response.blob(), filename }
}
export async function getProductImportTemplate(categoryId: number): Promise<{ blob: Blob; filename: string }> {
  const response = await apiFetch(`/admin/products/import-template?category_id=${categoryId}`)
  if (!response.ok) return fail(response)
  return { blob: await response.blob(), filename: `products-category-${categoryId}.xlsx` }
}
export async function getProductImportErrors(id: number): Promise<{ blob: Blob; filename: string }> {
  const response = await apiFetch(`/admin/product-imports/${id}/errors`)
  if (!response.ok) return fail(response)
  return { blob: await response.blob(), filename: `product-import-${id}-errors.xlsx` }
}
export async function uploadProductImport(file: File, categoryId?: number): Promise<ProductImport> {
  await requestCsrfCookie()
  const body = new FormData()
  body.append('file', file)
  if (categoryId !== undefined) body.append('category_id', String(categoryId))
  const response = await apiFetch('/admin/products/import', { method: 'POST', body })
  if (!response.ok) return fail(response)
  return ((await response.json()) as { data: ProductImport }).data
}
export async function getProductImport(id: number): Promise<ProductImport> {
  const response = await apiFetch(`/admin/product-imports/${id}`)
  if (!response.ok) return fail(response)
  return ((await response.json()) as { data: ProductImport }).data
}
export async function saveProduct(id: number | null, payload: ProductPayload): Promise<Product> { await requestCsrfCookie(); const response = await apiFetch(id === null ? '/admin/products' : `/admin/products/${id}`, { method: id === null ? 'POST' : 'PATCH', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(payload) }); if (!response.ok) return fail(response); return ((await response.json()) as { data: Product }).data }
export async function deleteProduct(id: number): Promise<void> { await requestCsrfCookie(); const response = await apiFetch(`/admin/products/${id}`, { method: 'DELETE' }); if (!response.ok) return fail(response) }
