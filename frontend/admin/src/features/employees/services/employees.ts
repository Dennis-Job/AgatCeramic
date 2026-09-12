import { apiFetch, requestCsrfCookie } from '../../../services/auth'
import type { Employee, EmployeePage, EmployeePayload, EmployeeRole } from '../types/employee.types'

type ErrorResponse = { error?: { message?: string; details?: Record<string, string[]> } }

const systemRoleNames: Record<string, string> = {
  'super-admin': 'Супер Администратор', administrator: 'Администратор', 'catalog-manager': 'Менеджер каталога',
  'order-manager': 'Менеджер заказов', 'content-manager': 'Контент-менеджер', 'seo-manager': 'SEO-менеджер', analyst: 'Аналитик',
}

export function roleDisplayName(role: EmployeeRole): string { return systemRoleNames[role.slug] ?? role.name }

async function parseError(response: Response): Promise<never> {
  const body = (await response.json().catch(() => ({}))) as ErrorResponse
  const firstDetail = body.error?.details ? Object.values(body.error.details).flat()[0] : undefined
  throw new Error(firstDetail ?? body.error?.message ?? 'Не удалось выполнить запрос.')
}

export async function getEmployees(search = '', status = ''): Promise<EmployeePage> {
  const query = new URLSearchParams({ per_page: '50' })
  if (search) query.set('search', search)
  if (status) query.set('status', status)
  const response = await apiFetch(`/admin/users?${query.toString()}`)
  if (!response.ok) return parseError(response)
  return response.json() as Promise<EmployeePage>
}

export async function getEmployeeRoles(): Promise<EmployeeRole[]> {
  const response = await apiFetch('/admin/users/roles')
  if (!response.ok) return parseError(response)
  return ((await response.json()) as { data: EmployeeRole[] }).data
}

// Compatibility name for existing service consumers during the incremental migration.
export const getRoles = getEmployeeRoles

export async function saveEmployee(id: number | null, payload: EmployeePayload): Promise<Employee> {
  await requestCsrfCookie()
  const response = await apiFetch(id === null ? '/admin/users' : `/admin/users/${id}`, {
    method: id === null ? 'POST' : 'PATCH', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(payload),
  })
  if (!response.ok) return parseError(response)
  return ((await response.json()) as { data: Employee }).data
}

export async function deleteEmployee(id: number): Promise<void> {
  await requestCsrfCookie()
  const response = await apiFetch(`/admin/users/${id}`, { method: 'DELETE' })
  if (!response.ok) return parseError(response)
}
