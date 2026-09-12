import { apiFetch, requestCsrfCookie } from '../../../services/auth'

export type ProductImageImportStatus = 'pending' | 'processing' | 'completed' | 'failed'

export type ProductImageImport = {
  id: number
  filename: string
  status: ProductImageImportStatus
  total_folders: number
  processed_folders: number
  created_images: number
  replaced_images: number
  failed_folders: number
  errors: { sku: string; entry: string | null; messages: string[] }[]
  has_error_file: boolean
  error_message: string | null
  created_at?: string
  started_at?: string | null
  completed_at?: string | null
}

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
