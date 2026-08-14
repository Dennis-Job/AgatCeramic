import { apiFetch, requestCsrfCookie } from './auth'

export type AttributeGroup = { id: number; name: string; slug: string; description: string | null; sort_order: number; created_at: string; updated_at: string }
export type AttributeGroupPayload = Omit<AttributeGroup, 'id' | 'created_at' | 'updated_at'>

async function fail(response: Response): Promise<never> { const body = (await response.json().catch(() => ({}))) as { error?: { message?: string; details?: Record<string, string[]> } }; throw new Error(Object.values(body.error?.details ?? {}).flat()[0] ?? body.error?.message ?? 'Не удалось выполнить запрос.') }
export async function getAttributeGroups(): Promise<AttributeGroup[]> { const response = await apiFetch('/admin/attribute-groups'); if (!response.ok) return fail(response); return ((await response.json()) as { data: AttributeGroup[] }).data }
export async function saveAttributeGroup(id: number | null, payload: AttributeGroupPayload): Promise<AttributeGroup> { await requestCsrfCookie(); const response = await apiFetch(id === null ? '/admin/attribute-groups' : `/admin/attribute-groups/${id}`, { method: id === null ? 'POST' : 'PATCH', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(payload) }); if (!response.ok) return fail(response); return ((await response.json()) as { data: AttributeGroup }).data }
export async function deleteAttributeGroup(id: number): Promise<void> { await requestCsrfCookie(); const response = await apiFetch(`/admin/attribute-groups/${id}`, { method: 'DELETE' }); if (!response.ok) return fail(response) }
