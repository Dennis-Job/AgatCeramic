import { apiFetch } from '../../../services/api'
import type { ProductImageImport } from '../types/product.types'

export async function uploadProductImageImport(
  file: File,
): Promise<ProductImageImport> {
  const body = new FormData()
  body.append('file', file)
  const response = await apiFetch('/admin/product-image-imports', {
    method: 'POST',
    body,
  })
  return ((await response.json()) as { data: ProductImageImport }).data
}

export async function getProductImageImport(
  id: number,
): Promise<ProductImageImport> {
  const response = await apiFetch(`/admin/product-image-imports/${id}`)
  return ((await response.json()) as { data: ProductImageImport }).data
}

export async function getProductImageImportErrors(
  id: number,
): Promise<{ blob: Blob; filename: string }> {
  const response = await apiFetch(`/admin/product-image-imports/${id}/errors`)
  const disposition = response.headers.get('Content-Disposition') ?? ''
  const filename =
    disposition.match(/filename="?([^";]+)"?/i)?.[1] ??
    `product-image-import-${id}-errors.csv`
  return { blob: await response.blob(), filename }
}
