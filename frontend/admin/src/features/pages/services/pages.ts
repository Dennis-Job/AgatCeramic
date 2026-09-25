import { apiFetch, requestCsrfCookie } from '../../../services/auth'
import {
  withPage,
  type PageRequest,
  type PaginatedResponse,
} from '../../../services/pagination'
import type { ContentPage, PagePayload } from '../types/page.types'

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

export async function getPages(
  request: PageRequest = {},
): Promise<PaginatedResponse<ContentPage>> {
  const query = new URLSearchParams()
  withPage(query, request)
  const response = await apiFetch(
    `/admin/pages${query.size ? `?${query}` : ''}`,
  )
  if (!response.ok) return fail(response)
  return (await response.json()) as PaginatedResponse<ContentPage>
}

export async function savePage(
  id: number | null,
  payload: PagePayload,
): Promise<ContentPage> {
  await requestCsrfCookie()
  const response = await apiFetch(
    id === null ? '/admin/pages' : `/admin/pages/${id}`,
    {
      method: id === null ? 'POST' : 'PATCH',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload),
    },
  )
  if (!response.ok) return fail(response)
  return ((await response.json()) as { data: ContentPage }).data
}

export async function deletePage(id: number): Promise<void> {
  await requestCsrfCookie()
  const response = await apiFetch(`/admin/pages/${id}`, { method: 'DELETE' })
  if (!response.ok) return fail(response)
}
