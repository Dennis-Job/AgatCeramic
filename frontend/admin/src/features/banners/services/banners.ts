import { apiFetch } from '../../../services/api'
import {
  withPage,
  type PageRequest,
  type PaginatedResponse,
} from '../../../services/pagination'
import type { Banner, BannerPayload } from '../types/banner.types'

export async function getBanners(
  request: PageRequest = {},
): Promise<PaginatedResponse<Banner>> {
  const query = new URLSearchParams()
  withPage(query, request)
  const response = await apiFetch(
    `/admin/banners${query.size ? `?${query}` : ''}`,
  )
  return (await response.json()) as PaginatedResponse<Banner>
}

export async function saveBanner(
  id: number | null,
  payload: BannerPayload,
): Promise<Banner> {
  const response = await apiFetch(
    id === null ? '/admin/banners' : `/admin/banners/${id}`,
    {
      method: id === null ? 'POST' : 'PATCH',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload),
    },
  )
  return ((await response.json()) as { data: Banner }).data
}

export async function deleteBanner(id: number): Promise<void> {
  await apiFetch(`/admin/banners/${id}`, { method: 'DELETE' })
}
