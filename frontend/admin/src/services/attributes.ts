import { apiFetch, requestCsrfCookie } from './auth'
import { loadAllPages, withPage, type PageRequest, type PaginatedResponse } from './pagination'

async function fail(response: Response): Promise<never> {
  const body = (await response.json().catch(() => ({}))) as { error?: { message?: string; details?: Record<string, string[]> } }
  throw new Error(Object.values(body.error?.details ?? {}).flat()[0] ?? body.error?.message ?? 'Не удалось выполнить запрос.')
}

export type AttributeOption = { id?: number; value: string; label: string; sort_order: number }
export type AttributeType = 'text' | 'number' | 'boolean' | 'select' | 'multiselect'
export type Attribute = { id: number; attribute_group_id: number | null; name: string; slug: string; type: AttributeType; unit: string | null; is_filterable: boolean; is_required: boolean; sort_order: number; options: AttributeOption[]; created_at: string; updated_at: string }
export type AttributePayload = Omit<Attribute, 'id' | 'created_at' | 'updated_at'>

export async function getAttributes(request: PageRequest = {}): Promise<PaginatedResponse<Attribute>> {
  const query = new URLSearchParams()
  withPage(query, request)
  const response = await apiFetch(`/admin/attributes${query.size ? `?${query}` : ''}`)
  if (!response.ok) return fail(response)
  return (await response.json()) as PaginatedResponse<Attribute>
}

export async function getAllAttributes(): Promise<Attribute[]> { return loadAllPages(getAttributes) }

export async function saveAttribute(id: number | null, payload: AttributePayload): Promise<Attribute> {
  await requestCsrfCookie()
  const response = await apiFetch(id === null ? '/admin/attributes' : `/admin/attributes/${id}`, {
    method: id === null ? 'POST' : 'PATCH',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(payload),
  })
  if (!response.ok) return fail(response)
  return ((await response.json()) as { data: Attribute }).data
}

export async function deleteAttribute(id: number): Promise<void> {
  await requestCsrfCookie()
  const response = await apiFetch(`/admin/attributes/${id}`, { method: 'DELETE' })
  if (!response.ok) return fail(response)
}
