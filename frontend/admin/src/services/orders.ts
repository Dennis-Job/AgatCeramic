import { apiFetch, requestCsrfCookie } from './auth'
import { withPage, type PageRequest, type PaginatedResponse } from './pagination'

export type OrderItem = { product_id: number | null; product_name: string; product_sku: string | null; unit_price: string; quantity: number; line_total: string }
export type Order = {
  id: number; order_number: string; customer: { name: string; phone: string; email: string | null }; delivery_address: string; customer_comment: string | null
  status: string; payment_status: string; payment_amount: string | null; payment_method: string | null; payment_reference: string | null; total_amount: string
  items: OrderItem[]; paid_at: string | null; completed_at: string | null; created_at: string
}
export type OrderStatus = { code: string; name: string; sort_order: number; is_terminal: boolean }
export type OrderHistory = { id: number; from_status: string; to_status: string; actor: { id: number | null; name: string } | null; occurred_at: string }
export type OrderComment = { id: number; body: string; author: { id: number | null; name: string } | null; created_at: string }
export type OrderFilters = { search?: string; status?: string; payment_status?: string }

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

export async function getOrder(id: number): Promise<Order> { return responseOrThrow<Order>(await apiFetch(`/admin/orders/${id}`), 'Не удалось загрузить заказ.') }
export async function getOrderStatuses(): Promise<OrderStatus[]> { return responseOrThrow<OrderStatus[]>(await apiFetch('/admin/order-statuses'), 'Не удалось загрузить статусы.') }
export async function getOrderHistory(id: number): Promise<OrderHistory[]> { return responseOrThrow<OrderHistory[]>(await apiFetch(`/admin/orders/${id}/status-history?per_page=100`), 'Не удалось загрузить историю статусов.') }
export async function getOrderComments(id: number): Promise<OrderComment[]> { return responseOrThrow<OrderComment[]>(await apiFetch(`/admin/orders/${id}/comments?per_page=100`), 'Не удалось загрузить комментарии.') }

export async function updateOrderStatus(id: number, status: string): Promise<Order> {
  await requestCsrfCookie()
  return responseOrThrow<Order>(await apiFetch(`/admin/orders/${id}/status`, { method: 'PATCH', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ status }) }), 'Не удалось изменить статус.')
}
export async function updateOrderPayment(id: number, payload: { payment_status: string; payment_amount?: string; payment_method?: string; payment_reference?: string; paid_at?: string }): Promise<void> {
  await requestCsrfCookie()
  await responseOrThrow(await apiFetch(`/admin/orders/${id}/payment`, { method: 'PATCH', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(payload) }), 'Не удалось сохранить оплату.')
}
export async function addOrderComment(id: number, body: string): Promise<OrderComment> {
  await requestCsrfCookie()
  return responseOrThrow<OrderComment>(await apiFetch(`/admin/orders/${id}/comments`, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ body }) }), 'Не удалось добавить комментарий.')
}
