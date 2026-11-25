import { apiData, apiFetch } from '../../../services/api'
import {
  withPage,
  type PageRequest,
  type PaginatedResponse,
} from '../../../services/pagination'
import type {
  Order,
  OrderComment,
  OrderFilters,
  OrderHistory,
  OrderStatus,
  OrderStatistics,
  OrderAssignee,
  OrderStatusCounts,
} from '../types/order.types'

export async function getOrders(
  filters: OrderFilters = {},
  page: PageRequest = {},
): Promise<PaginatedResponse<Order>> {
  const query = new URLSearchParams()
  Object.entries(filters).forEach(([key, value]) => {
    if (Array.isArray(value))
      value.forEach((code) => query.append(`${key}[]`, code))
    else if (value) query.set(key, value === true ? '1' : value)
  })
  withPage(query, { ...page, perPage: page.perPage ?? 25 })
  const response = await apiFetch(`/admin/orders?${query}`, {
    errorMessage: 'Не удалось загрузить заказы.',
  })
  return (await response.json()) as PaginatedResponse<Order>
}

export const getOrder = async (id: number): Promise<Order> =>
  apiData(`/admin/orders/${id}`, {
    errorMessage: 'Не удалось загрузить заказ.',
  })

export const getOrderAssignees = async (): Promise<OrderAssignee[]> =>
  apiData('/admin/order-assignees', {
    errorMessage: 'Не удалось загрузить список ответственных.',
  })

export const getOrderStatusCounts = async (): Promise<OrderStatusCounts> =>
  apiData('/admin/order-status-counts', {
    errorMessage: 'Не удалось загрузить счётчики заказов.',
  })

export async function assignOrder(
  id: number,
  assigneeId: number | null,
): Promise<void> {
  await apiFetch(`/admin/orders/${id}/assignee`, {
    method: 'PATCH',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ assignee_id: assigneeId }),
    errorMessage: 'Не удалось изменить ответственного.',
  })
}
export const getOrderStatuses = async (): Promise<OrderStatus[]> =>
  apiData('/admin/order-statuses', {
    errorMessage: 'Не удалось загрузить статусы.',
  })
export const getOrderHistory = async (id: number): Promise<OrderHistory[]> =>
  apiData(`/admin/orders/${id}/status-history?per_page=100`, {
    errorMessage: 'Не удалось загрузить историю статусов.',
  })
export const getOrderComments = async (id: number): Promise<OrderComment[]> =>
  apiData(`/admin/orders/${id}/comments?per_page=100`, {
    errorMessage: 'Не удалось загрузить комментарии.',
  })

export async function updateOrderStatus(
  id: number,
  status: string,
): Promise<Order> {
  return apiData(`/admin/orders/${id}/status`, {
    method: 'PATCH',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ status }),
    errorMessage: 'Не удалось изменить статус.',
  })
}

export async function updateOrderPayment(
  id: number,
  payload: {
    payment_status: string
    payment_amount?: string
    payment_method?: string
    payment_reference?: string
    paid_at?: string
  },
): Promise<void> {
  await apiFetch(`/admin/orders/${id}/payment`, {
    method: 'PATCH',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(payload),
    errorMessage: 'Не удалось сохранить оплату.',
  })
}

export async function addOrderComment(
  id: number,
  body: string,
): Promise<OrderComment> {
  return apiData(`/admin/orders/${id}/comments`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ body }),
    errorMessage: 'Не удалось добавить комментарий.',
  })
}

export const getOrderStatistics = async (
  month: string,
): Promise<OrderStatistics> =>
  apiData(`/admin/order-statistics?${new URLSearchParams({ month })}`, {
    errorMessage: 'Не удалось загрузить статистику заказов.',
  })
