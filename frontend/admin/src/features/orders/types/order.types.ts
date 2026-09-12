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
export type PaymentForm = { payment_status: string; payment_amount: string; payment_method: string; payment_reference: string }
