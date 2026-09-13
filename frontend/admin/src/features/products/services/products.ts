import { apiFetch, requestCsrfCookie } from '../../../services/auth'
import { loadAllPages, withPage, type PaginatedResponse } from '../../../services/pagination'
import type { Product, ProductFilters, ProductImport, ProductPayload } from '../types/product.types'
export async function getProductPriceStatusTemplate(): Promise<{ blob: Blob; filename: string }> {
  const response = await apiFetch('/admin/products/price-status-template')
  if (!response.ok) return fail(response)
  return { blob: await response.blob(), filename: 'product-price-status-template.xlsx' }
}
export async function uploadProductPriceStatusImport(file: File): Promise<ProductImport> {
  await requestCsrfCookie()
  const body = new FormData(); body.append('file', file)
  const response = await apiFetch('/admin/products/price-status-import', { method: 'POST', body })
  if (!response.ok) return fail(response)
  return ((await response.json()) as { data: ProductImport }).data
}
export async function getProductPriceStatusImport(id: number): Promise<ProductImport> {
  const response = await apiFetch(`/admin/product-price-status-imports/${id}`)
  if (!response.ok) return fail(response)
  return ((await response.json()) as { data: ProductImport }).data
}
export async function getProductPriceStatusImportErrors(id: number): Promise<{ blob: Blob; filename: string }> {
  const response = await apiFetch(`/admin/product-price-status-imports/${id}/errors`)
  if (!response.ok) return fail(response)
  return { blob: await response.blob(), filename: `product-price-status-import-${id}-errors.xlsx` }
}
export async function getProductGroupImportTemplate(): Promise<{ blob: Blob; filename: string }> {
  const response = await apiFetch('/admin/products/group-import-template')
  if (!response.ok) return fail(response)
  return { blob: await response.blob(), filename: 'product-groups-template.xlsx' }
}
export async function uploadProductGroupImport(file: File): Promise<ProductImport> {
  await requestCsrfCookie()
  const body = new FormData(); body.append('file', file)
  const response = await apiFetch('/admin/products/group-import', { method: 'POST', body })
  if (!response.ok) return fail(response)
  return ((await response.json()) as { data: ProductImport }).data
}
export async function getProductGroupImport(id: number): Promise<ProductImport> {
  const response = await apiFetch(`/admin/product-group-imports/${id}`)
  if (!response.ok) return fail(response)
  return ((await response.json()) as { data: ProductImport }).data
}
export async function getProductGroupImportErrors(id: number): Promise<{ blob: Blob; filename: string }> {
  const response = await apiFetch(`/admin/product-group-imports/${id}/errors`)
  if (!response.ok) return fail(response)
  return { blob: await response.blob(), filename: `product-group-import-${id}-errors.xlsx` }
}
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
export async function getProductImportTemplate(categoryId: number, editing = false): Promise<{ blob: Blob; filename: string }> {
  const response = await apiFetch(`/admin/products/import-template?category_id=${categoryId}${editing ? '&editing=1' : ''}`)
  if (!response.ok) return fail(response)
  return { blob: await response.blob(), filename: `products-category-${categoryId}${editing ? '-edit' : ''}.xlsx` }
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
