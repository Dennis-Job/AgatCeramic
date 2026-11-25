import { apiFetch } from '../../../services/api'
import {
  withPage,
  type PageRequest,
  type PaginatedResponse,
} from '../../../services/pagination'
import type {
  ProductCollection,
  CollectionPayload,
} from '../types/collection.types'

export async function getCollections(
  request: PageRequest & {
    search?: string
    brand_id?: number
    is_active?: boolean
  } = {},
): Promise<PaginatedResponse<ProductCollection>> {
  const query = new URLSearchParams()
  withPage(query, request)
  if (request.search?.trim()) query.set('search', request.search.trim())
  if (request.brand_id) query.set('brand_id', String(request.brand_id))
  if (request.is_active !== undefined)
    query.set('is_active', request.is_active ? '1' : '0')
  const response = await apiFetch(
    `/admin/collections${query.size ? `?${query}` : ''}`,
  )
  return (await response.json()) as PaginatedResponse<ProductCollection>
}
export async function getCollection(id: number): Promise<ProductCollection> {
  const response = await apiFetch(`/admin/collections/${id}`)
  return ((await response.json()) as { data: ProductCollection }).data
}
export async function saveCollection(
  id: number | null,
  payload: CollectionPayload,
): Promise<ProductCollection> {
  const response = await apiFetch(
    id === null ? '/admin/collections' : `/admin/collections/${id}`,
    {
      method: id === null ? 'POST' : 'PATCH',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload),
    },
  )
  return ((await response.json()) as { data: ProductCollection }).data
}
export async function deleteCollection(id: number): Promise<void> {
  await apiFetch(`/admin/collections/${id}`, {
    method: 'DELETE',
  })
}
