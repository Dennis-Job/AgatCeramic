import { apiData, apiFetch } from '../../../services/api'
import {
  withPage,
  type PageRequest,
  type PaginatedResponse,
} from '../../../services/pagination'
import type {
  Contact,
  ContactAssignee,
  ContactComment,
  ContactFilters,
  ContactHistory,
  ContactStatus,
  ContactStatusCounts,
  ContactStatistics,
} from '../types/contact.types'

export async function getContacts(
  filters: ContactFilters,
  page: PageRequest = {},
): Promise<PaginatedResponse<Contact>> {
  const query = new URLSearchParams()
  Object.entries(filters).forEach(([key, value]) => {
    if (value) query.set(key, String(value))
  })
  withPage(query, { perPage: 25, ...page })
  const response = await apiFetch(`/admin/contact-requests?${query}`, {
    errorMessage: 'Не удалось загрузить обращения.',
  })
  return response.json() as Promise<PaginatedResponse<Contact>>
}

export const getContact = (id: number) =>
  apiData<Contact>(`/admin/contact-requests/${id}`, {
    errorMessage: 'Не удалось загрузить обращение.',
  })
export const getContactStatuses = () =>
  apiData<ContactStatus[]>('/admin/contact-workflow', {
    errorMessage: 'Не удалось загрузить статусы.',
  })
export const getContactStatusCounts = () =>
  apiData<ContactStatusCounts>('/admin/contact-request-counts', {
    errorMessage: 'Не удалось загрузить счётчики обращений.',
  })
export const getContactStatistics = (month: string) =>
  apiData<ContactStatistics>(
    `/admin/contact-statistics?${new URLSearchParams({ month })}`,
    { errorMessage: 'Не удалось загрузить статистику обращений.' },
  )
export const getContactAssignees = () =>
  apiData<ContactAssignee[]>('/admin/contact-assignees', {
    errorMessage: 'Не удалось загрузить ответственных.',
  })

export const getContactHistory = (id: number) =>
  apiData<ContactHistory[]>(
    `/admin/contact-requests/${id}/status-history?per_page=100`,
    { errorMessage: 'Не удалось загрузить историю.' },
  )
export const getContactComments = (id: number) =>
  apiData<ContactComment[]>(
    `/admin/contact-requests/${id}/comments?per_page=100`,
    { errorMessage: 'Не удалось загрузить комментарии.' },
  )

async function patch(path: string, payload: unknown): Promise<void> {
  await apiFetch(path, {
    method: 'PATCH',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(payload),
    errorMessage: 'Не удалось сохранить изменения.',
  })
}

export const assignContact = (id: number, assigneeId: number | null) =>
  patch(`/admin/contact-requests/${id}/assignee`, { assignee_id: assigneeId })
export const updateContactStatus = (id: number, status: string) =>
  patch(`/admin/contact-requests/${id}/status`, { status })

export async function addContactComment(
  id: number,
  body: string,
): Promise<void> {
  await apiFetch(`/admin/contact-requests/${id}/comments`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ body }),
    errorMessage: 'Не удалось добавить комментарий.',
  })
}
