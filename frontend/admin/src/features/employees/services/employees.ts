import { apiFetch } from '../../../services/api'
import type {
  Employee,
  EmployeePage,
  EmployeePayload,
  EmployeeRole,
} from '../types/employee.types'

export async function getEmployees(
  search = '',
  status = '',
): Promise<EmployeePage> {
  const query = new URLSearchParams({ per_page: '50' })
  if (search) query.set('search', search)
  if (status) query.set('status', status)
  const response = await apiFetch(`/admin/users?${query.toString()}`)
  return response.json() as Promise<EmployeePage>
}

export async function getEmployeeRoles(): Promise<EmployeeRole[]> {
  const response = await apiFetch('/admin/users/roles')
  return ((await response.json()) as { data: EmployeeRole[] }).data
}

export async function saveEmployee(
  id: number | null,
  payload: EmployeePayload,
): Promise<Employee> {
  const response = await apiFetch(
    id === null ? '/admin/users' : `/admin/users/${id}`,
    {
      method: id === null ? 'POST' : 'PATCH',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload),
    },
  )
  return ((await response.json()) as { data: Employee }).data
}

export async function deleteEmployee(id: number): Promise<void> {
  await apiFetch(`/admin/users/${id}`, { method: 'DELETE' })
}
