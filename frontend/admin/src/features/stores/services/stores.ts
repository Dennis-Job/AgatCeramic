import { apiFetch } from '../../../services/api'
import {
  withPage,
  type PageRequest,
  type PaginatedResponse,
} from '../../../services/pagination'
import type { Store, StorePayload, WorkingHour } from '../types/store.types'

export async function getStores(
  request: PageRequest = {},
): Promise<PaginatedResponse<Store>> {
  const query = new URLSearchParams()
  withPage(query, request)
  const response = await apiFetch(
    `/admin/stores${query.size ? `?${query}` : ''}`,
  )
  return (await response.json()) as PaginatedResponse<Store>
}

export async function saveStore(
  id: number | null,
  payload: StorePayload,
): Promise<Store> {
  const response = await apiFetch(
    id === null ? '/admin/stores' : `/admin/stores/${id}`,
    {
      method: id === null ? 'POST' : 'PATCH',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload),
    },
  )
  return ((await response.json()) as { data: Store }).data
}

export async function saveWorkingHours(
  id: number,
  hours: WorkingHour[],
): Promise<Store> {
  const response = await apiFetch(`/admin/stores/${id}/working-hours`, {
    method: 'PUT',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ working_hours: hours }),
  })
  return ((await response.json()) as { data: Store }).data
}

export async function deleteStore(id: number): Promise<void> {
  await apiFetch(`/admin/stores/${id}`, { method: 'DELETE' })
}
