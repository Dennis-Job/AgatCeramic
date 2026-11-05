export type OrderItem = {
  product_id: number | null
  product_name: string
  product_sku: string | null
  unit_price: string
  quantity: number
  line_total: string
}
export type OrderAssignee = { id: number; name: string }
export type OrderStatusCounts = Record<'new' | 'working' | 'shipped', number>
export type Order = {
  id: number
  order_number: string
  customer: { name: string; phone: string | null; email: string | null }
  delivery_address: string
  customer_comment: string | null
  assignee: OrderAssignee | null
  assigned_at: string | null
  status: string
  payment_status: string
  payment_amount: string | null
  payment_method: string | null
  payment_reference: string | null
  total_amount: string
  items: OrderItem[]
  paid_at: string | null
  completed_at: string | null
  created_at: string
}
export type OrderStatus = {
  code: string
  name: string
  sort_order: number
  is_terminal: boolean
}
export type OrderHistory = {
  id: number
  from_status: string
  to_status: string
  actor: { id: number | null; name: string } | null
  occurred_at: string
}
export type OrderComment = {
  id: number
  body: string
  author: { id: number | null; name: string } | null
  created_at: string
}
export type OrderFilters = {
  search?: string
  status?: string
  statuses?: string[]
  payment_status?: string
  assignee_id?: string
  unassigned?: boolean
}
export type PaymentForm = {
  payment_status: string
  payment_amount: string
  payment_method: string
  payment_reference: string
}

export type OrderStatisticMetric = {
  count: number
  previous_count: number
  change_percent: number | null
}
export type OrderStatistics = {
  month: string
  previous_month: string
  timezone: string
  metrics: Record<
    'total' | 'new' | 'completed' | 'cancelled',
    OrderStatisticMetric
  >
}
