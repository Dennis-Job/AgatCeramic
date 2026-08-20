import { apiFetch, requestCsrfCookie } from './auth'
import { loadAllPages, withPage, type PageRequest, type PaginatedResponse } from './pagination'

export type AttributeGroup = { id: number; name: string; slug: string; description: string | null; sort_order: number; created_at: string; updated_at: string }
export type AttributeGroupPayload = Omit<AttributeGroup, 'id' | 'created_at' | 'updated_at'>

async function fail(response: Response): Promise<never> { const body = (await response.json().catch(() => ({}))) as { error?: { message?: string; details?: Record<string, string[]> } }; throw new Error(Object.values(body.error?.details ?? {}).flat()[0] ?? body.error?.message ?? 'Не удалось выполнить запрос.') }
export async function getAttributeGroups(request: PageRequest = {}): Promise<PaginatedResponse<AttributeGroup>> { const query = new URLSearchParams(); withPage(query, request); const response = await apiFetch(`/admin/attribute-groups${query.size ? `?${query}` : ''}`); if (!response.ok) return fail(response); return (await response.json()) as PaginatedResponse<AttributeGroup> }
export async function getAllAttributeGroups(): Promise<AttributeGroup[]> { return loadAllPages(getAttributeGroups) }
export async function saveAttributeGroup(id: number | null, payload: AttributeGroupPayload): Promise<AttributeGroup> { await requestCsrfCookie(); const response = await apiFetch(id === null ? '/admin/attribute-groups' : `/admin/attribute-groups/${id}`, { method: id === null ? 'POST' : 'PATCH', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(payload) }); if (!response.ok) return fail(response); return ((await response.json()) as { data: AttributeGroup }).data }
export async function deleteAttributeGroup(id: number): Promise<void> { await requestCsrfCookie(); const response = await apiFetch(`/admin/attribute-groups/${id}`, { method: 'DELETE' }); if (!response.ok) return fail(response) }
