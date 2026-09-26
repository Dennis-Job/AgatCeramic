import { apiFetch, requestCsrfCookie } from '../../../services/auth'
import {
  withPage,
  type PageRequest,
  type PaginatedResponse,
} from '../../../services/pagination'
import type { Banner } from '../../banners/types/banner.types'
import type { Slider, SliderPayload } from '../types/slider.types'

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

export async function getSliders(
  request: PageRequest = {},
): Promise<PaginatedResponse<Slider>> {
  const query = new URLSearchParams()
  withPage(query, request)
  const response = await apiFetch(
    `/admin/sliders${query.size ? `?${query}` : ''}`,
  )
  if (!response.ok) return fail(response)
  return (await response.json()) as PaginatedResponse<Slider>
}

export async function getBannerOptions(search: string): Promise<Banner[]> {
  const query = new URLSearchParams()
  if (search.trim()) query.set('q', search.trim())
  const response = await apiFetch(
    `/admin/sliders/banner-options${query.size ? `?${query}` : ''}`,
  )
  if (!response.ok) return fail(response)
  return ((await response.json()) as { data: Banner[] }).data
}

export async function saveSlider(
  id: number | null,
  payload: SliderPayload,
): Promise<Slider> {
  await requestCsrfCookie()
  const response = await apiFetch(
    id === null ? '/admin/sliders' : `/admin/sliders/${id}`,
    {
      method: id === null ? 'POST' : 'PATCH',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload),
    },
  )
  if (!response.ok) return fail(response)
  return ((await response.json()) as { data: Slider }).data
}

export async function deleteSlider(id: number): Promise<void> {
  await requestCsrfCookie()
  const response = await apiFetch(`/admin/sliders/${id}`, { method: 'DELETE' })
  if (!response.ok) return fail(response)
}
