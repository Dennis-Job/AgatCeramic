import { apiFetch } from '../../../services/api'
import type {
  AccessRole,
  Permission,
  RolePayload,
  SystemRolePayload,
} from '../types/access.types'

export async function getRoles(): Promise<AccessRole[]> {
  const response = await apiFetch('/admin/roles')
  return ((await response.json()) as { data: AccessRole[] }).data
}

export async function getPermissions(): Promise<Permission[]> {
  const response = await apiFetch('/admin/roles/permissions')
  return ((await response.json()) as { data: Permission[] }).data
}

export async function saveRole(
  id: number | null,
  payload: RolePayload | SystemRolePayload,
): Promise<AccessRole> {
  const response = await apiFetch(
    id === null ? '/admin/roles' : `/admin/roles/${id}`,
    {
      method: id === null ? 'POST' : 'PATCH',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload),
    },
  )
  return ((await response.json()) as { data: AccessRole }).data
}

export async function deleteRole(id: number): Promise<void> {
  await apiFetch(`/admin/roles/${id}`, { method: 'DELETE' })
}
