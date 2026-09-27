import { apiFetch, requestCsrfCookie } from '../../../services/auth'
import {
  withPage,
  type PageRequest,
  type PaginatedResponse,
} from '../../../services/pagination'
import type { Store, StorePayload, WorkingHour } from '../types/store.types'

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

export async function getStores(
  request: PageRequest = {},
): Promise<PaginatedResponse<Store>> {
  const query = new URLSearchParams()
  withPage(query, request)
  const response = await apiFetch(
    `/admin/stores${query.size ? `?${query}` : ''}`,
  )
  if (!response.ok) return fail(response)
  return (await response.json()) as PaginatedResponse<Store>
}

export async function saveStore(
  id: number | null,
  payload: StorePayload,
): Promise<Store> {
  await requestCsrfCookie()
  const response = await apiFetch(
    id === null ? '/admin/stores' : `/admin/stores/${id}`,
    {
      method: id === null ? 'POST' : 'PATCH',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload),
    },
  )
  if (!response.ok) return fail(response)
  return ((await response.json()) as { data: Store }).data
}

export async function saveWorkingHours(
  id: number,
  hours: WorkingHour[],
): Promise<Store> {
  await requestCsrfCookie()
  const response = await apiFetch(`/admin/stores/${id}/working-hours`, {
    method: 'PUT',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ working_hours: hours }),
  })
  if (!response.ok) return fail(response)
  return ((await response.json()) as { data: Store }).data
}

export async function deleteStore(id: number): Promise<void> {
  await requestCsrfCookie()
  const response = await apiFetch(`/admin/stores/${id}`, { method: 'DELETE' })
  if (!response.ok) return fail(response)
}
