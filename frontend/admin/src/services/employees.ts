import { apiFetch, requestCsrfCookie } from './auth'

export type Role = { id: number; name: string; slug: string }
export type Employee = { id: number; name: string; email: string; status: 'active' | 'blocked'; last_login_at: string | null; roles: Role[] }
export type EmployeePayload = { name: string; email: string; password?: string; password_confirmation?: string; status: 'active' | 'blocked'; role_ids: number[] }
type PaginatedResponse<T> = { data: T[]; meta: { current_page: number; last_page: number; total: number } }
type ErrorResponse = { error?: { message?: string; details?: Record<string, string[]> } }

const systemRoleNames: Record<string, string> = {
  'super-admin': 'Супер Администратор',
  'administrator': 'Администратор',
  'catalog-manager': 'Менеджер каталога',
  'order-manager': 'Менеджер заказов',
  'content-manager': 'Контент-менеджер',
  'seo-manager': 'SEO-менеджер',
  'analyst': 'Аналитик',
}

export function roleDisplayName(role: Role): string {
  return systemRoleNames[role.slug] ?? role.name
}

async function parseError(response: Response): Promise<never> {
  const body = (await response.json().catch(() => ({}))) as ErrorResponse
  const firstDetail = body.error?.details ? Object.values(body.error.details).flat()[0] : undefined
  throw new Error(firstDetail ?? body.error?.message ?? 'Не удалось выполнить запрос.')
}

export async function getEmployees(search = '', status = ''): Promise<PaginatedResponse<Employee>> {
  const query = new URLSearchParams({ per_page: '50' })
  if (search) query.set('search', search)
  if (status) query.set('status', status)
  const response = await apiFetch(`/admin/users?${query.toString()}`)
  if (!response.ok) return parseError(response)
  return response.json() as Promise<PaginatedResponse<Employee>>
}

export async function getRoles(): Promise<Role[]> {
  const response = await apiFetch('/admin/users/roles')
  if (!response.ok) return parseError(response)
  return ((await response.json()) as { data: Role[] }).data
}

export async function saveEmployee(id: number | null, payload: EmployeePayload): Promise<Employee> {
  await requestCsrfCookie()
  const response = await apiFetch(id === null ? '/admin/users' : `/admin/users/${id}`, { method: id === null ? 'POST' : 'PATCH', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(payload) })
  if (!response.ok) return parseError(response)
  return ((await response.json()) as { data: Employee }).data
}

export async function deleteEmployee(id: number): Promise<void> {
  await requestCsrfCookie()
  const response = await apiFetch(`/admin/users/${id}`, { method: 'DELETE' })
  if (!response.ok) return parseError(response)
}
