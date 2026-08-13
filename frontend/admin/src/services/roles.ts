import { apiFetch, requestCsrfCookie } from './auth'

export type Permission = { id: number; name: string; code: string; description: string | null }
export type AccessRole = { id: number; name: string; slug: string; description: string | null; is_system: boolean; permissions: Permission[] }
export type RolePayload = { name: string; slug: string; description: string; permission_ids: number[] }

async function fail(response: Response): Promise<never> {
  const body = (await response.json().catch(() => ({}))) as { error?: { message?: string; details?: Record<string, string[]> } }
  throw new Error(Object.values(body.error?.details ?? {}).flat()[0] ?? body.error?.message ?? 'Не удалось выполнить запрос.')
}

export async function getRoles(): Promise<AccessRole[]> {
  const response = await apiFetch('/admin/roles')
  if (!response.ok) return fail(response)
  return ((await response.json()) as { data: AccessRole[] }).data
}

export async function getPermissions(): Promise<Permission[]> {
  const response = await apiFetch('/admin/roles/permissions')
  if (!response.ok) return fail(response)
  return ((await response.json()) as { data: Permission[] }).data
}

export async function saveRole(id: number | null, payload: RolePayload): Promise<AccessRole> {
  await requestCsrfCookie()
  const response = await apiFetch(id === null ? '/admin/roles' : `/admin/roles/${id}`, { method: id === null ? 'POST' : 'PATCH', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(payload) })
  if (!response.ok) return fail(response)
  return ((await response.json()) as { data: AccessRole }).data
}

export async function deleteRole(id: number): Promise<void> {
  await requestCsrfCookie()
  const response = await apiFetch(`/admin/roles/${id}`, { method: 'DELETE' })
  if (!response.ok) return fail(response)
}
