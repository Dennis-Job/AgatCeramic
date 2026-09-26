import { apiFetch, requestCsrfCookie } from '../../../services/auth'
import {
  withPage,
  type PageRequest,
  type PaginatedResponse,
} from '../../../services/pagination'
import type { Banner, BannerPayload } from '../types/banner.types'

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

export async function getBanners(
  request: PageRequest = {},
): Promise<PaginatedResponse<Banner>> {
  const query = new URLSearchParams()
  withPage(query, request)
  const response = await apiFetch(
    `/admin/banners${query.size ? `?${query}` : ''}`,
  )
  if (!response.ok) return fail(response)
  return (await response.json()) as PaginatedResponse<Banner>
}

export async function saveBanner(
  id: number | null,
  payload: BannerPayload,
): Promise<Banner> {
  await requestCsrfCookie()
  const response = await apiFetch(
    id === null ? '/admin/banners' : `/admin/banners/${id}`,
    {
      method: id === null ? 'POST' : 'PATCH',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload),
    },
  )
  if (!response.ok) return fail(response)
  return ((await response.json()) as { data: Banner }).data
}

export async function deleteBanner(id: number): Promise<void> {
  await requestCsrfCookie()
  const response = await apiFetch(`/admin/banners/${id}`, { method: 'DELETE' })
  if (!response.ok) return fail(response)
}
