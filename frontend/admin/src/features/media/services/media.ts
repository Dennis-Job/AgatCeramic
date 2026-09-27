import { apiFetch, requestCsrfCookie } from '../../../services/auth'
import {
  loadAllPages,
  withPage,
  type PageRequest,
  type PaginatedResponse,
} from '../../../services/pagination'
import type { Media } from '../types/media.types'

async function fail(response: Response): Promise<never> {
  const body = (await response.json().catch(() => ({}))) as {
    error?: { message?: string; details?: Record<string, string[]> }
  }
  throw new Error(
    Object.values(body.error?.details ?? {}).flat()[0] ??
      body.error?.message ??
      'Не удалось выполнить запрос.',
  )
}

export async function getMedia(
  kind?: Media['kind'],
  request: PageRequest = {},
): Promise<PaginatedResponse<Media>> {
  const query = new URLSearchParams()
  if (kind) query.set('kind', kind)
  withPage(query, request)
  const response = await apiFetch(
    `/admin/media${query.size ? `?${query}` : ''}`,
  )
  if (!response.ok) return fail(response)
  return (await response.json()) as PaginatedResponse<Media>
}

export function getAllMedia(kind: Media['kind']): Promise<Media[]> {
  return loadAllPages((request) => getMedia(kind, request))
}

export async function uploadMedia(
  file: File,
  kind: Media['kind'],
  title: string,
  alt: string,
): Promise<Media> {
  await requestCsrfCookie()
  const body = new FormData()
  body.set('file', file)
  body.set('kind', kind)
  body.set('title', title)
  if (kind === 'image') body.set('alt', alt)
  const response = await apiFetch('/admin/media', { method: 'POST', body })
  if (!response.ok) return fail(response)
  return ((await response.json()) as { data: Media }).data
}

export async function updateMedia(
  id: number,
  payload: { title: string; alt: string | null },
): Promise<Media> {
  await requestCsrfCookie()
  const response = await apiFetch(`/admin/media/${id}`, {
    method: 'PATCH',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(payload),
  })
  if (!response.ok) return fail(response)
  return ((await response.json()) as { data: Media }).data
}

export async function deleteMedia(id: number): Promise<void> {
  await requestCsrfCookie()
  const response = await apiFetch(`/admin/media/${id}`, { method: 'DELETE' })
  if (!response.ok) return fail(response)
}
