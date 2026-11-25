import { apiFetch } from '../../../services/api'
import {
  loadAllPages,
  withPage,
  type PageRequest,
  type PaginatedResponse,
} from '../../../services/pagination'
import type {
  ProductImage,
  ProductImageUpdatePayload,
} from '../types/product.types'
export async function getProductImages(
  productId: number,
  request: PageRequest = {},
): Promise<PaginatedResponse<ProductImage>> {
  const query = new URLSearchParams()
  withPage(query, request)
  const response = await apiFetch(
    `/admin/products/${productId}/images${query.size ? `?${query}` : ''}`,
  )
  return (await response.json()) as PaginatedResponse<ProductImage>
}
export async function getAllProductImages(
  productId: number,
): Promise<ProductImage[]> {
  return loadAllPages((request) => getProductImages(productId, request))
}
export async function uploadProductImage(
  productId: number,
  file: File,
  payload: Omit<ProductImageUpdatePayload, 'alt'>,
): Promise<ProductImage> {
  const form = new FormData()
  form.append('image', file)
  form.append('is_primary', payload.is_primary ? '1' : '0')
  form.append('sort_order', String(payload.sort_order ?? 0))
  const response = await apiFetch(`/admin/products/${productId}/images`, {
    method: 'POST',
    body: form,
  })
  return ((await response.json()) as { data: ProductImage }).data
}
export async function updateProductImage(
  productId: number,
  id: number,
  payload: ProductImageUpdatePayload,
): Promise<ProductImage> {
  const response = await apiFetch(`/admin/products/${productId}/images/${id}`, {
    method: 'PATCH',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(payload),
  })
  return ((await response.json()) as { data: ProductImage }).data
}
export async function deleteProductImage(
  productId: number,
  id: number,
): Promise<void> {
  await apiFetch(`/admin/products/${productId}/images/${id}`, {
    method: 'DELETE',
  })
}
