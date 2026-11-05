import { createPinia, setActivePinia } from 'pinia'
import { expect, test, vi } from 'vitest'
import { effectScope } from 'vue'
import { useOrdersWorkspace } from '../src/features/orders/composables/useOrdersWorkspace'
import type { Order } from '../src/features/orders/types/order.types'
import { useAuthStore } from '../src/stores/auth'

const api = vi.hoisted(() => ({
  getOrder: vi.fn(),
  getOrderHistory: vi.fn(),
  getOrderComments: vi.fn(),
  getOrders: vi.fn(),
  updateOrderStatus: vi.fn(),
  updateOrderPayment: vi.fn(),
}))
vi.mock('../src/features/orders/services/orders', () => api)

test('changing the linked order cannot restore details from a slower previous request', async () => {
  setActivePinia(createPinia())
  api.getOrderHistory.mockResolvedValue([])
  api.getOrderComments.mockResolvedValue([])
  let finish!: (order: Order) => void
  api.getOrder.mockReturnValueOnce(
    new Promise<Order>((resolve) => {
      finish = resolve
    }),
  )
  api.getOrder.mockResolvedValueOnce({ id: 2, order_number: 'AG-2' })
  const scope = effectScope()
  const state = scope.run(useOrdersWorkspace)!
  const stale = state.selectOrder({ id: 1 })
  await state.selectOrder({ id: 2 })
  finish({ id: 1, order_number: 'AG-1' } as Order)
  await stale
  expect(state.selected.value?.id).toBe(2)
  expect(api.getOrderHistory).not.toHaveBeenCalledWith(1)
  scope.stop()
})

test('closing an order invalidates pending detail requests and clears drafts', async () => {
  setActivePinia(createPinia())
  let finish!: (order: Order) => void
  api.getOrder.mockReturnValueOnce(
    new Promise<Order>((resolve) => (finish = resolve)),
  )
  const scope = effectScope()
  const state = scope.run(useOrdersWorkspace)!
  const pending = state.selectOrder({ id: 1 })
  state.commentBody.value = 'Черновик'
  state.clearSelection()
  finish({ id: 1, order_number: 'AG-1' } as Order)
  await pending
  expect(state.selected.value).toBeNull()
  expect(state.detailLoading.value).toBe(false)
  expect(state.commentBody.value).toBe('')
  scope.stop()
})

test('unsaved order status, payment and comment are tracked independently', async () => {
  setActivePinia(createPinia())
  api.getOrder.mockResolvedValueOnce({
    id: 1,
    status: 'new',
    payment_status: 'not_paid',
    payment_amount: null,
    payment_method: null,
    payment_reference: null,
  })
  api.getOrderHistory.mockResolvedValue([])
  api.getOrderComments.mockResolvedValue([])
  const scope = effectScope()
  const state = scope.run(useOrdersWorkspace)!
  await state.selectOrder({ id: 1 })
  expect(state.hasUnsavedChanges.value).toBe(false)
  state.paymentForm.value.payment_method = 'Наличные'
  expect(state.hasUnsavedChanges.value).toBe(true)
  state.paymentForm.value.payment_method = ''
  state.selected.value!.status = 'processing'
  expect(state.hasUnsavedChanges.value).toBe(true)
  state.selected.value!.status = 'new'
  state.commentBody.value = 'Комментарий'
  expect(state.hasUnsavedChanges.value).toBe(true)
  state.clearSelection()
  expect(state.hasUnsavedChanges.value).toBe(false)
  scope.stop()
})

test('saving status and payment preserves drafts in the other form', async () => {
  setActivePinia(createPinia())
  useAuthStore().user = {
    id: 1,
    name: 'Manager',
    email: 'manager@example.test',
    status: 'active',
    last_login_at: null,
    permissions: ['orders.manage', 'payments.manage'],
  }
  const record = {
    id: 1,
    status: 'new',
    payment_status: 'not_paid',
    payment_amount: null,
    payment_method: null,
    payment_reference: null,
  }
  api.getOrder.mockImplementation(async () => ({ ...record }))
  api.getOrderHistory.mockResolvedValue([])
  api.getOrderComments.mockResolvedValue([])
  api.getOrders.mockResolvedValue({
    data: [],
    meta: { current_page: 1, last_page: 1, per_page: 25, total: 0 },
  })
  api.updateOrderStatus.mockImplementation(async (_id, status) => {
    record.status = status
  })
  api.updateOrderPayment.mockImplementation(async (_id, payload) => {
    Object.assign(record, payload)
  })
  const scope = effectScope()
  const state = scope.run(useOrdersWorkspace)!
  state.statusReady.value = true
  await state.selectOrder({ id: 1 })
  state.paymentForm.value.payment_method = 'Наличные'
  state.selected.value!.status = 'processing'
  await state.changeStatus()
  expect(state.paymentForm.value.payment_method).toBe('Наличные')
  state.selected.value!.status = 'confirmed'
  state.paymentForm.value.payment_status = 'paid'
  state.paymentForm.value.payment_amount = '100.00'
  await state.savePayment()
  expect(state.selected.value!.status).toBe('confirmed')
  expect(state.hasUnsavedChanges.value).toBe(true)
  scope.stop()
})
