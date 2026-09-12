import { apiFetch, requestCsrfCookie } from '../../../services/auth'
import { withPage, type PageRequest, type PaginatedResponse } from '../../../services/pagination'
import type { Order, OrderComment, OrderFilters, OrderHistory, OrderStatus } from '../types/order.types'

async function responseOrThrow<T>(response: Response, fallback: string): Promise<T> {
  const body = await response.json().catch(() => ({})) as { data?: T; error?: { message?: string; details?: Record<string, string[]> } }
  if (!response.ok) throw new Error(Object.values(body.error?.details ?? {}).flat()[0] ?? body.error?.message ?? fallback)
  return body.data as T
}

export async function getOrders(filters: OrderFilters = {}, page: PageRequest = {}): Promise<PaginatedResponse<Order>> {
  const query = new URLSearchParams()
  Object.entries(filters).forEach(([key, value]) => { if (value) query.set(key, value) })
  withPage(query, { ...page, perPage: page.perPage ?? 25 })
  const response = await apiFetch(`/admin/orders?${query}`)
  if (!response.ok) await responseOrThrow(response, 'Не удалось загрузить заказы.')
  return await response.json() as PaginatedResponse<Order>
}

export const getOrder = async (id: number): Promise<Order> => responseOrThrow(await apiFetch(`/admin/orders/${id}`), 'Не удалось загрузить заказ.')
export const getOrderStatuses = async (): Promise<OrderStatus[]> => responseOrThrow(await apiFetch('/admin/order-statuses'), 'Не удалось загрузить статусы.')
export const getOrderHistory = async (id: number): Promise<OrderHistory[]> => responseOrThrow(await apiFetch(`/admin/orders/${id}/status-history?per_page=100`), 'Не удалось загрузить историю статусов.')
export const getOrderComments = async (id: number): Promise<OrderComment[]> => responseOrThrow(await apiFetch(`/admin/orders/${id}/comments?per_page=100`), 'Не удалось загрузить комментарии.')

export async function updateOrderStatus(id: number, status: string): Promise<Order> {
  await requestCsrfCookie()
  return responseOrThrow(await apiFetch(`/admin/orders/${id}/status`, { method: 'PATCH', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ status }) }), 'Не удалось изменить статус.')
}

export async function updateOrderPayment(id: number, payload: { payment_status: string; payment_amount?: string; payment_method?: string; payment_reference?: string; paid_at?: string }): Promise<void> {
  await requestCsrfCookie()
  await responseOrThrow(await apiFetch(`/admin/orders/${id}/payment`, { method: 'PATCH', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(payload) }), 'Не удалось сохранить оплату.')
}

export async function addOrderComment(id: number, body: string): Promise<OrderComment> {
  await requestCsrfCookie()
  return responseOrThrow(await apiFetch(`/admin/orders/${id}/comments`, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ body }) }), 'Не удалось добавить комментарий.')
}
