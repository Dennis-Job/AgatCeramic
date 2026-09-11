import { apiFetch, requestCsrfCookie } from './auth'
import { withPage, type PageRequest, type PaginatedResponse } from './pagination'

export type Contact = { id: number; type: 'callback' | 'email' | 'partner'; contact: { name: string | null; phone: string | null; email: string | null }; message: string | null; source: string; status: string; assignee?: { id: number; name: string } | null; assigned_at: string | null; completed_at: string | null; created_at: string }
export type ContactStatus = { code: string; name: string; is_terminal: boolean }
export type ContactAssignee = { id: number; name: string }
export type ContactHistory = { id: number; from_status: string; to_status: string; actor: { id: number | null; name: string } | null; occurred_at: string }
export type ContactComment = { id: number; body: string; author: { id: number | null; name: string } | null; created_at: string }
export type ContactFilters = { search?: string; type?: string; status?: string; unassigned?: boolean }
type Envelope<T> = { data: T; error?: { message?: string; details?: Record<string, string[]> } }
async function unwrap<T>(response: Response | Promise<Response>, fallback: string): Promise<T> { const resolved = await response; const body = await resolved.json().catch(() => ({})) as Envelope<T>; if (!resolved.ok) throw new Error(Object.values(body.error?.details ?? {}).flat()[0] ?? body.error?.message ?? fallback); return body.data }
export async function getContacts(filters: ContactFilters, page: PageRequest = {}): Promise<PaginatedResponse<Contact>> { const query = new URLSearchParams(); Object.entries(filters).forEach(([key, value]) => { if (value) query.set(key, String(value)) }); withPage(query, { ...page, perPage: 25 }); const response = await apiFetch(`/admin/contact-requests?${query}`); if (!response.ok) await unwrap(response, 'Не удалось загрузить обращения.'); return response.json() as Promise<PaginatedResponse<Contact>> }
export const getContact = (id: number) => unwrap<Contact>(apiFetch(`/admin/contact-requests/${id}`).then(response => response), 'Не удалось загрузить обращение.')
export const getContactStatuses = () => unwrap<ContactStatus[]>(apiFetch('/admin/contact-statuses').then(response => response), 'Не удалось загрузить статусы.')
export const getContactAssignees = () => unwrap<ContactAssignee[]>(apiFetch('/admin/contact-assignees').then(response => response), 'Не удалось загрузить ответственных.')
export const getContactHistory = (id: number) => unwrap<PaginatedResponse<ContactHistory>>(apiFetch(`/admin/contact-requests/${id}/status-history?per_page=100`).then(response => response), 'Не удалось загрузить историю.').then(page => page.data)
export const getContactComments = (id: number) => unwrap<PaginatedResponse<ContactComment>>(apiFetch(`/admin/contact-requests/${id}/comments?per_page=100`).then(response => response), 'Не удалось загрузить комментарии.').then(page => page.data)
async function patch(path: string, payload: unknown): Promise<void> { await requestCsrfCookie(); await unwrap(apiFetch(path, { method: 'PATCH', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(payload) }).then(response => response), 'Не удалось сохранить изменения.') }
export const assignContact = (id: number, assigneeId: number | null) => patch(`/admin/contact-requests/${id}/assignee`, { assignee_id: assigneeId })
export const updateContactStatus = (id: number, status: string) => patch(`/admin/contact-requests/${id}/status`, { status })
export async function addContactComment(id: number, body: string): Promise<void> { await requestCsrfCookie(); await unwrap(apiFetch(`/admin/contact-requests/${id}/comments`, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ body }) }).then(response => response), 'Не удалось добавить комментарий.') }
