import { apiFetch, requestCsrfCookie } from './auth'
import { loadAllPages, withPage, type PageRequest, type PaginatedResponse } from './pagination'

export type Brand = {
  id: number
  name: string
  slug: string
  description: string | null
  country_code: string | null
  is_active: boolean
  created_at: string
  updated_at: string
}

export type BrandPayload = Omit<Brand, 'id' | 'created_at' | 'updated_at'>

async function fail(response: Response): Promise<never> {
  const body = (await response.json().catch(() => ({}))) as { error?: { message?: string; details?: Record<string, string[]> } }

  throw new Error(Object.values(body.error?.details ?? {}).flat()[0] ?? body.error?.message ?? 'Не удалось выполнить запрос.')
}

export async function getBrands(request: PageRequest = {}): Promise<PaginatedResponse<Brand>> {
  const query = new URLSearchParams()
  withPage(query, request)
  const response = await apiFetch(`/admin/brands${query.size ? `?${query}` : ''}`)
  if (!response.ok) return fail(response)

  return (await response.json()) as PaginatedResponse<Brand>
}

export async function getAllBrands(): Promise<Brand[]> { return loadAllPages(getBrands) }

export async function saveBrand(id: number | null, payload: BrandPayload): Promise<Brand> {
  await requestCsrfCookie()
  const response = await apiFetch(id === null ? '/admin/brands' : `/admin/brands/${id}`, {
    method: id === null ? 'POST' : 'PATCH',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(payload),
  })
  if (!response.ok) return fail(response)

  return ((await response.json()) as { data: Brand }).data
}

export async function deleteBrand(id: number): Promise<void> {
  await requestCsrfCookie()
  const response = await apiFetch(`/admin/brands/${id}`, { method: 'DELETE' })
  if (!response.ok) return fail(response)
}
