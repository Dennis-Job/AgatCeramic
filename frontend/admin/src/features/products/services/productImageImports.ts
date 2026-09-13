import { apiFetch, requestCsrfCookie } from '../../../services/auth'
import type { ProductImageImport } from '../types/product.types'

async function fail(response: Response): Promise<never> {
  const body = (await response.json().catch(() => ({}))) as { error?: { message?: string; details?: Record<string, string[]> } }
  throw new Error(Object.values(body.error?.details ?? {}).flat()[0] ?? body.error?.message ?? 'Не удалось выполнить запрос.')
}

export async function uploadProductImageImport(file: File): Promise<ProductImageImport> {
  await requestCsrfCookie()
  const body = new FormData()
  body.append('file', file)
  const response = await apiFetch('/admin/product-image-imports', { method: 'POST', body })
  if (!response.ok) return fail(response)
  return ((await response.json()) as { data: ProductImageImport }).data
}

export async function getProductImageImport(id: number): Promise<ProductImageImport> {
  const response = await apiFetch(`/admin/product-image-imports/${id}`)
  if (!response.ok) return fail(response)
  return ((await response.json()) as { data: ProductImageImport }).data
}

export async function getProductImageImportErrors(id: number): Promise<{ blob: Blob; filename: string }> {
  const response = await apiFetch(`/admin/product-image-imports/${id}/errors`)
  if (!response.ok) return fail(response)
  const disposition = response.headers.get('Content-Disposition') ?? ''
  const filename = disposition.match(/filename="?([^";]+)"?/i)?.[1] ?? `product-image-import-${id}-errors.csv`
  return { blob: await response.blob(), filename }
}
