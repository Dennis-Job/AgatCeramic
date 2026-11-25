import { apiFetch } from '../../../services/api'
import {
  withPage,
  type PageRequest,
  type PaginatedResponse,
} from '../../../services/pagination'
import type { ContentPage, PagePayload } from '../types/page.types'

export async function getPages(
  request: PageRequest = {},
): Promise<PaginatedResponse<ContentPage>> {
  const query = new URLSearchParams()
  withPage(query, request)
  const response = await apiFetch(
    `/admin/pages${query.size ? `?${query}` : ''}`,
  )
  return (await response.json()) as PaginatedResponse<ContentPage>
}

export async function getPage(id: number): Promise<ContentPage> {
  const response = await apiFetch(`/admin/pages/${id}`)
  return ((await response.json()) as { data: ContentPage }).data
}

export async function savePage(
  id: number | null,
  payload: PagePayload,
): Promise<ContentPage> {
  const response = await apiFetch(
    id === null ? '/admin/pages' : `/admin/pages/${id}`,
    {
      method: id === null ? 'POST' : 'PATCH',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload),
    },
  )
  return ((await response.json()) as { data: ContentPage }).data
}

export async function deletePage(id: number): Promise<void> {
  await apiFetch(`/admin/pages/${id}`, { method: 'DELETE' })
}

export async function publishPage(id: number): Promise<ContentPage> {
  const response = await apiFetch(`/admin/pages/${id}/publish`, {
    method: 'POST',
  })
  return ((await response.json()) as { data: ContentPage }).data
}

export async function unpublishPage(id: number): Promise<ContentPage> {
  const response = await apiFetch(`/admin/pages/${id}`, {
    method: 'PATCH',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ is_published: false }),
  })
  return ((await response.json()) as { data: ContentPage }).data
}
