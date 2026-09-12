import { apiFetch, requestCsrfCookie } from '../../../services/auth'
import { withPage, type PageRequest, type PaginatedResponse } from '../../../services/pagination'
import type { Contact, ContactAssignee, ContactComment, ContactFilters, ContactHistory, ContactStatus } from '../types/contact.types'

type Envelope<T> = { data: T; error?: { message?: string; details?: Record<string, string[]> } }

async function unwrap<T>(response: Response | Promise<Response>, fallback: string): Promise<T> {
  const resolved = await response
  const body = await resolved.json().catch(() => ({})) as Envelope<T>
  if (!resolved.ok) throw new Error(Object.values(body.error?.details ?? {}).flat()[0] ?? body.error?.message ?? fallback)
  return body.data
}

export async function getContacts(filters: ContactFilters, page: PageRequest = {}): Promise<PaginatedResponse<Contact>> {
  const query = new URLSearchParams()
  Object.entries(filters).forEach(([key, value]) => { if (value) query.set(key, String(value)) })
  withPage(query, { ...page, perPage: 25 })
  const response = await apiFetch(`/admin/contact-requests?${query}`)
  if (!response.ok) await unwrap(response, 'Не удалось загрузить обращения.')
  return response.json() as Promise<PaginatedResponse<Contact>>
}

export const getContact = (id: number) => unwrap<Contact>(apiFetch(`/admin/contact-requests/${id}`), 'Не удалось загрузить обращение.')
export const getContactStatuses = () => unwrap<ContactStatus[]>(apiFetch('/admin/contact-statuses'), 'Не удалось загрузить статусы.')
export const getContactAssignees = () => unwrap<ContactAssignee[]>(apiFetch('/admin/contact-assignees'), 'Не удалось загрузить ответственных.')

async function getActivityPage<T>(path: string, fallback: string): Promise<T[]> {
  const response = await apiFetch(path)
  if (!response.ok) await unwrap(response, fallback)
  return ((await response.json()) as PaginatedResponse<T>).data
}

export const getContactHistory = (id: number) => getActivityPage<ContactHistory>(`/admin/contact-requests/${id}/status-history?per_page=100`, 'Не удалось загрузить историю.')
export const getContactComments = (id: number) => getActivityPage<ContactComment>(`/admin/contact-requests/${id}/comments?per_page=100`, 'Не удалось загрузить комментарии.')

async function patch(path: string, payload: unknown): Promise<void> {
  await requestCsrfCookie()
  await unwrap(apiFetch(path, { method: 'PATCH', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(payload) }), 'Не удалось сохранить изменения.')
}

export const assignContact = (id: number, assigneeId: number | null) => patch(`/admin/contact-requests/${id}/assignee`, { assignee_id: assigneeId })
export const updateContactStatus = (id: number, status: string) => patch(`/admin/contact-requests/${id}/status`, { status })

export async function addContactComment(id: number, body: string): Promise<void> {
  await requestCsrfCookie()
  await unwrap(apiFetch(`/admin/contact-requests/${id}/comments`, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ body }) }), 'Не удалось добавить комментарий.')
}
